<?php

namespace App\Http\Controllers;

use App\Models\ClientContract;
use App\Models\ClientInvoice;
use App\Models\DailySiteLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\MeasurementBookEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RetentionReleaseClaim;
use App\Models\Site;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorWorkOrder;
use App\Models\Supplier;
use App\Services\DocumentService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    /**
     * Map of safe slug aliases to Eloquent model class names.
     */
    public const MODEL_MAP = [
        'project' => Project::class,
        'site' => Site::class,
        'daily_site_log' => DailySiteLog::class,
        'purchase_request' => PurchaseRequest::class,
        'purchase_order' => PurchaseOrder::class,
        'goods_receipt' => GoodsReceipt::class,
        'employee' => Employee::class,
        'payroll_period' => PayrollPeriod::class,
        'client_invoice' => ClientInvoice::class,
        'client_contract' => ClientContract::class,
        'retention_claim' => RetentionReleaseClaim::class,
        'subcontractor' => Supplier::class,
        'work_order' => SubcontractorWorkOrder::class,
        'measurement_book' => MeasurementBookEntry::class,
        'subcontractor_bill' => SubcontractorBill::class,
        'fixed_asset' => FixedAsset::class,
        'loan' => Loan::class,
        'journal_entry' => JournalEntry::class,
    ];

    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display the central Document Centre.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Document::query()
            ->with(['documentable', 'uploadedBy'])
            ->active()
            ->visibleTo($user);

        // Filter: Search Keyword
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        // Filter: Category
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Filter: Module / Parent Type
        if ($module = $request->input('module')) {
            if (isset(self::MODEL_MAP[$module])) {
                $query->where('documentable_type', self::MODEL_MAP[$module]);
            }
        }

        // Filter: Confidentiality
        if ($request->filled('confidential')) {
            $query->where('is_confidential', $request->boolean('confidential'));
        }

        // Calculate KPI Stats for active company
        $statsBase = Document::query()->active()->visibleTo($user);
        $totalDocs = (clone $statsBase)->count();
        $totalBytes = (clone $statsBase)->sum('file_size');
        $confidentialDocs = (clone $statsBase)->where('is_confidential', true)->count();
        $recentDocs = (clone $statsBase)->where('created_at', '>=', now()->subDays(7))->count();

        $stats = [
            'total_count' => $totalDocs,
            'total_size_formatted' => $this->formatBytes($totalBytes),
            'confidential_count' => $confidentialDocs,
            'recent_count' => $recentDocs,
        ];

        $documents = $query->latest()->paginate(15)->withQueryString();

        return view('documents.index', compact('documents', 'stats'));
    }

    /**
     * Handle document upload to any authorized business record.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'documentable_type' => ['required', 'string'],
            'documentable_id' => ['required', 'integer'],
            'file' => ['required', 'file'],
            'category' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:255'],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $typeKey = strtolower($validated['documentable_type']);
        if (! isset(self::MODEL_MAP[$typeKey])) {
            throw ValidationException::withMessages([
                'documentable_type' => 'Unsupported or invalid parent record type.',
            ]);
        }

        $modelClass = self::MODEL_MAP[$typeKey];
        $parent = $modelClass::findOrFail($validated['documentable_id']);

        // Authorize upload
        Gate::authorize('upload', [Document::class, $parent]);

        $file = $request->file('file');
        $document = $this->documentService->storeDocument($parent, $file, [
            'category' => $validated['category'] ?? 'general',
            'title' => $validated['title'] ?? null,
            'is_confidential' => $request->boolean('is_confidential'),
        ], $request->user());

        // Notify relevant roles for significant uploads
        $this->broadcastDocumentEvent($document, $parent);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document uploaded successfully.',
                'document' => $document->load('uploadedBy'),
            ], 201);
        }

        return back()->with('success', "Document [{$document->original_filename}] attached successfully.");
    }

    /**
     * Inline secure preview for supported images and PDFs.
     */
    public function preview(Document $document): BinaryFileResponse
    {
        Gate::authorize('view', $document);
        return $this->documentService->streamPreview($document);
    }

    /**
     * Secure file download.
     */
    public function download(Document $document): BinaryFileResponse
    {
        Gate::authorize('download', $document);
        return $this->documentService->download($document);
    }

    /**
     * Replace an existing document with a new version.
     */
    public function replace(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        Gate::authorize('replace', $document);

        $request->validate([
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $newDoc = $this->documentService->replaceDocument(
            $document,
            $request->file('file'),
            ['title' => $request->input('title')],
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document version replaced successfully.',
                'document' => $newDoc->load('uploadedBy'),
            ]);
        }

        return back()->with('success', "Document replaced with version {$newDoc->version}.");
    }

    /**
     * Delete or archive a document.
     */
    public function destroy(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $document);

        $reason = $request->input('reason', 'Removed by authorized user');
        $this->documentService->deleteDocument($document, $request->user(), $reason);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document removed successfully.',
            ]);
        }

        return back()->with('success', 'Document removed successfully.');
    }

    /**
     * Optional notification dispatching for key document events.
     */
    protected function broadcastDocumentEvent(Document $document, $parent): void
    {
        try {
            $companyId = $document->company_id;
            $categoryName = $document->category_label;

            // Notify PMs on Drawing / Contract uploads
            if ($parent instanceof Project && in_array($document->category, ['drawing', 'contract', 'permit'])) {
                NotificationService::sendToRole(
                    $companyId,
                    'project_manager',
                    "New {$categoryName} Uploaded",
                    "A new {$categoryName} [{$document->original_filename}] was attached to project {$parent->name}.",
                    'info',
                    route('projects.show', $parent)
                );
            }

            // Notify Accounts on GRN delivery challan uploads
            if ($parent instanceof GoodsReceipt && $document->category === 'delivery_challan') {
                NotificationService::sendToRole(
                    $companyId,
                    'accountant',
                    "Delivery Challan Uploaded: {$parent->grn_number}",
                    "Delivery Challan scanned copy attached for GRN {$parent->grn_number}.",
                    'success',
                    route('procurement.invoices')
                );
            }
        } catch (\Throwable) {
            // Fail gracefully if notifications cannot be sent
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
