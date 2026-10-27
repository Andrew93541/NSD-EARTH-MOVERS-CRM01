<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ConstructionWorkCategory;
use App\Models\Document;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentService;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkOrderController extends Controller
{
    protected WorkOrderService $workOrderService;
    protected DocumentService $documentService;

    public function __construct(
        WorkOrderService $workOrderService,
        DocumentService $documentService
    ) {
        $this->workOrderService = $workOrderService;
        $this->documentService = $documentService;
    }

    /**
     * Searchable and filterable Work Order Register.
     */
    public function index(Request $request)
    {
        $this->authorizeView();
        $companyId = $this->getActiveCompanyId();

        $query = WorkOrder::where('company_id', $companyId)
            ->with([
                'client',
                'project',
                'site',
                'assignedSupervisor',
                'creator',
                'duplicateMatchedOrder',
            ]);

        // Filter: Order Type (client_issued, internal)
        if ($request->filled('order_type') && in_array($request->order_type, ['client_issued', 'internal'], true)) {
            $query->where('order_type', $request->order_type);
        }

        // Filter: Management Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter: Document Extraction / Verification Status
        if ($request->filled('extraction_status')) {
            $query->where('extraction_status', $request->extraction_status);
        }

        // Filter: Client
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filter: Project
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Search: order_number, external_reference_number, title, or client name
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhere('external_reference_number', 'LIKE', "%{$search}%")
                    ->orWhere('title', 'LIKE', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('company_name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('project', function ($pq) use ($search) {
                        $pq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Metrics for Register Summary (defensively scoped to active company)
        $baseMetrics = WorkOrder::where('company_id', $companyId);

        $metrics = [
            'total_count' => (clone $baseMetrics)->count(),
            'client_count' => (clone $baseMetrics)->where('order_type', 'client_issued')->count(),
            'internal_count' => (clone $baseMetrics)->where('order_type', 'internal')->count(),
            'client_value' => (clone $baseMetrics)->where('order_type', 'client_issued')->sum('total_amount'),
            'internal_value' => (clone $baseMetrics)->where('order_type', 'internal')->sum('total_amount'),
            'needs_verification' => (clone $baseMetrics)->whereIn('extraction_status', ['extracted', 'scanned_manual_entry_required'])->count(),
            'duplicate_flagged' => (clone $baseMetrics)->where('is_duplicate_flagged', true)->count(),
        ];

        $workOrders = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $clients = Client::where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'company_name']);
        $projects = Project::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']);

        return view('work-orders-register.index', compact(
            'workOrders',
            'metrics',
            'clients',
            'projects'
        ));
    }

    /**
     * Creation hub for Client Upload or Internal Work Order.
     */
    public function create()
    {
        $this->authorizeCreate();
        $companyId = $this->getActiveCompanyId();

        $clients = Client::where('company_id', $companyId)->orderBy('name')->get();
        $projects = Project::where('company_id', $companyId)->orderBy('name')->get();
        $sites = Site::where('company_id', $companyId)->orderBy('name')->get();
        $supervisors = User::whereHas('companies', function ($q) use ($companyId) {
            $q->where('companies.id', $companyId)->wherePivot('status', 'active');
        })->orderBy('name')->get();
        $categories = ConstructionWorkCategory::where('is_active', true)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderBy('name')
            ->get();

        return view('work-orders-register.create', compact(
            'clients',
            'projects',
            'sites',
            'supervisors',
            'categories'
        ));
    }

    /**
     * Handle incoming Client Work Order PDF upload and automated extraction.
     */
    public function uploadClient(Request $request)
    {
        $this->authorizeCreate();
        $companyId = $this->getActiveCompanyId();

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:15360', // 15MB limit
            ],
        ], [
            'file.required' => 'Please select a Client Work Order PDF file to upload.',
            'file.file' => 'The uploaded file is invalid.',
            'file.mimes' => 'Only PDF documents are supported for work order document intake.',
            'file.mimetypes' => 'Only PDF documents are supported for work order document intake.',
            'file.max' => 'The uploaded file exceeds the 15 MB limit.',
        ]);

        $workOrder = $this->workOrderService->createClientWorkOrderFromUpload(
            $request->file('file'),
            $companyId,
            auth()->user()
        );

        $flashMessage = $workOrder->is_duplicate_flagged
            ? 'Client PDF uploaded. WARNING: A potential duplicate was detected. Please review fields and original document.'
            : ($workOrder->extraction_status === 'scanned_manual_entry_required'
                ? 'Client PDF uploaded. Note: Scanned document detected. Please review the PDF and complete verification fields manually.'
                : 'Client PDF uploaded and text parsed. Please verify extracted data against the document preview.');

        return redirect()->route('work-orders-register.verify', $workOrder)
            ->with($workOrder->is_duplicate_flagged ? 'warning' : 'success', $flashMessage);
    }

    /**
     * Verification interface (side-by-side document preview and data review).
     */
    public function verify(WorkOrder $workOrder)
    {
        $this->authorizeVerify();
        $companyId = $this->authorizeCompany($workOrder);

        $workOrder->load([
            'client',
            'project',
            'site',
            'assignedSupervisor',
            'documents',
            'duplicateMatchedOrder',
            'items.workCategory',
        ]);

        $clients = Client::where('company_id', $companyId)->orderBy('name')->get();
        $projects = Project::where('company_id', $companyId)->orderBy('name')->get();
        $sites = Site::where('company_id', $companyId)
            ->where(function ($q) use ($workOrder) {
                $q->where('project_id', $workOrder->project_id)->orWhereNull('project_id');
            })
            ->orderBy('name')
            ->get();
        $supervisors = User::whereHas('companies', function ($q) use ($companyId) {
            $q->where('companies.id', $companyId)->wherePivot('status', 'active');
        })->orderBy('name')->get();
        $categories = ConstructionWorkCategory::where('is_active', true)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderBy('name')
            ->get();

        $primaryDoc = $workOrder->primaryDocument();

        return view('work-orders-register.verify', compact(
            'workOrder',
            'clients',
            'projects',
            'sites',
            'supervisors',
            'categories',
            'primaryDoc'
        ));
    }

    /**
     * Submit verified fields and confirm document sign-off.
     */
    public function confirmVerification(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeVerify();
        $companyId = $this->authorizeCompany($workOrder);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'external_reference_number' => ['nullable', 'string', 'max:100'],
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where('company_id', $companyId),
            ],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where('company_id', $companyId),
            ],
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where('company_id', $companyId),
            ],
            'assigned_supervisor_id' => ['nullable', 'exists:users,id'],
            'issue_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.work_category_id' => ['nullable', 'exists:construction_work_categories,id'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->workOrderService->verifyClientWorkOrder($workOrder, $validated, auth()->user());

        return redirect()->route('work-orders-register.show', $workOrder)
            ->with('success', 'Work order document verification confirmed successfully.');
    }

    /**
     * Store internally created work order.
     */
    public function storeInternal(Request $request)
    {
        $this->authorizeCreate();
        $companyId = $this->getActiveCompanyId();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'external_reference_number' => ['nullable', 'string', 'max:100'],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where('company_id', $companyId),
            ],
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where('company_id', $companyId),
            ],
            'assigned_supervisor_id' => ['nullable', 'exists:users,id'],
            'issue_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,docx,xlsx', 'max:15360'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.work_category_id' => ['nullable', 'exists:construction_work_categories,id'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $workOrder = $this->workOrderService->createInternalWorkOrder(
            $validated,
            $companyId,
            auth()->user(),
            $request->file('attachment')
        );

        return redirect()->route('work-orders-register.show', $workOrder)
            ->with('success', "Internal Work Order [{$workOrder->order_number}] created successfully.");
    }

    /**
     * Work order detailed record view.
     */
    public function show(WorkOrder $workOrder)
    {
        $this->authorizeView();
        $this->authorizeCompany($workOrder);

        $workOrder->load([
            'client',
            'project',
            'site',
            'assignedSupervisor',
            'creator',
            'verifier',
            'approver',
            'duplicateMatchedOrder',
            'items.workCategory',
            'documents',
        ]);

        return view('work-orders-register.show', compact('workOrder'));
    }

    /**
     * Management approval action.
     */
    public function approve(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeApprove();
        $this->authorizeCompany($workOrder);

        $notes = $request->input('notes');

        $this->workOrderService->approveWorkOrder($workOrder, auth()->user(), $notes);

        return redirect()->route('work-orders-register.show', $workOrder)
            ->with('success', "Work Order [{$workOrder->order_number}] approved for operational execution.");
    }

    /**
     * Dismiss duplicate warning after review.
     */
    public function dismissDuplicate(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeDuplicateResolution();
        $this->authorizeCompany($workOrder);

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->workOrderService->dismissDuplicate($workOrder, auth()->user(), $request->input('reason'));

        return redirect()->route('work-orders-register.show', $workOrder)
            ->with('success', 'Duplicate flag dismissed and noted in audit trail.');
    }

    /**
     * Stream authorized inline document preview for attached PDF.
     */
    public function previewDocument(WorkOrder $workOrder, Document $document): BinaryFileResponse
    {
        $this->authorizeView();
        $activeCompanyId = $this->authorizeCompany($workOrder);

        // Defensively verify document belongs to this work order
        if ((int) $document->documentable_id !== (int) $workOrder->id || $document->documentable_type !== WorkOrder::class) {
            abort(404, 'Document not found.');
        }

        // Defensively verify document company matches active company
        if (empty($document->company_id) || (int) $document->company_id !== $activeCompanyId) {
            abort(404, 'Document not found.');
        }

        return $this->documentService->streamPreview($document);
    }

    /**
     * Download authorized original attached document.
     */
    public function downloadDocument(WorkOrder $workOrder, Document $document): BinaryFileResponse
    {
        $this->authorizeView();
        $activeCompanyId = $this->authorizeCompany($workOrder);

        // Defensively verify document belongs to this work order
        if ((int) $document->documentable_id !== (int) $workOrder->id || $document->documentable_type !== WorkOrder::class) {
            abort(404, 'Document not found.');
        }

        // Defensively verify document company matches active company
        if (empty($document->company_id) || (int) $document->company_id !== $activeCompanyId) {
            abort(404, 'Document not found.');
        }

        return $this->documentService->download($document);
    }

    /**
     * Resolve and validate active company context defensively.
     * Denies access (403) if no valid active company context exists for the authenticated user.
     * Never allows a missing or forged session value to bypass tenant isolation.
     */
    protected function getActiveCompanyId(): int
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, 'Unauthenticated user cannot access work orders.');
        }

        $sessionCompanyId = session('current_company_id');
        $validCompany = null;

        if ($sessionCompanyId) {
            // Verify the user actually has active membership in the requested session company
            $validCompany = $user->companies()
                ->where('companies.id', $sessionCompanyId)
                ->wherePivot('status', 'active')
                ->first();
        }

        // If no valid session company, fallback to user's current company
        if (! $validCompany) {
            $validCompany = $user->currentCompany();
        }

        // If still no valid active company, deny access immediately
        if (! $validCompany) {
            abort(403, 'Access denied: No valid active company context found.');
        }

        // Synchronize session to guarantee consistency across downstream Eloquent scopes
        if (session('current_company_id') !== $validCompany->id) {
            session(['current_company_id' => $validCompany->id]);
        }

        return (int) $validCompany->id;
    }

    /**
     * Enforce defensive company ownership on specific record instances.
     * Returns the verified active company ID or aborts with 404.
     */
    protected function authorizeCompany(WorkOrder $workOrder): int
    {
        $activeCompanyId = $this->getActiveCompanyId();

        if (empty($workOrder->company_id) || (int) $workOrder->company_id !== $activeCompanyId) {
            abort(404, 'Work order not found.');
        }

        return $activeCompanyId;
    }

    protected function authorizeView(): void
    {
        $user = auth()->user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.*')
            && ! $user->hasPermission('work_orders.view')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'site_supervisor', 'accountant', 'procurement_officer'], true))) {
            abort(403, 'Unauthorized to view Work Orders.');
        }
    }

    protected function authorizeCreate(): void
    {
        $user = auth()->user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.*')
            && ! $user->hasPermission('work_orders.create')
            && ! in_array($role, ['owner', 'admin', 'project_manager', 'procurement_officer'], true))) {
            abort(403, 'Unauthorized to create Work Orders.');
        }
    }

    protected function authorizeVerify(): void
    {
        $user = auth()->user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.*')
            && ! $user->hasPermission('work_orders.verify')
            && ! $user->hasPermission('work_orders.create')
            && ! in_array($role, ['owner', 'admin', 'project_manager'], true))) {
            abort(403, 'Unauthorized to verify Work Order documents.');
        }
    }

    protected function authorizeApprove(): void
    {
        $user = auth()->user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.*')
            && ! $user->hasPermission('work_orders.approve')
            && ! in_array($role, ['owner', 'admin', 'project_manager'], true))) {
            abort(403, 'Unauthorized: Only Project Managers or Admins may approve Work Orders.');
        }
    }

    protected function authorizeDuplicateResolution(): void
    {
        $user = auth()->user();
        $role = $user?->currentRole()?->slug;

        if (! $user || (! $user->hasPermission('*')
            && ! $user->hasPermission('work_orders.*')
            && ! $user->hasPermission('work_orders.approve')
            && ! $user->hasPermission('work_orders.duplicate_resolution')
            && ! in_array($role, ['owner', 'admin', 'project_manager'], true))) {
            abort(403, 'Unauthorized: Only Project Managers or Admins may resolve duplicate work order warnings.');
        }
    }
}

