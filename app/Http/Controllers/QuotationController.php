<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuotationController extends Controller
{
    /**
     * Resolve the active company ID or fail closed.
     */
    protected function getCompanyId(): int
    {
        $companyId = Company::configuredCompanyId() ?? session('current_company_id');
        if (! $companyId) {
            abort(403, 'No active company workspace resolved.');
        }

        return $companyId;
    }

    /**
     * Authorize viewing quotations.
     */
    protected function authorizeQuotationView(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('quotations.*') ||
            $user->hasPermission('quotations.view') ||
            in_array($roleSlug, ['owner', 'admin', 'accountant'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized access to quotation and estimates module.');
        }
    }

    /**
     * Authorize creating a quotation.
     */
    protected function authorizeQuotationCreate(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('quotations.*') ||
            $user->hasPermission('quotations.create') ||
            in_array($roleSlug, ['owner', 'admin'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized. Creating commercial quotations requires explicit quotations.create permission or Company Owner role.');
        }
    }

    /**
     * Authorize modifying quotation pricing, rates, or items.
     */
    protected function authorizeQuotationPricing(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('quotations.*') ||
            $user->hasPermission('quotations.pricing') ||
            $user->hasPermission('quotations.edit') ||
            in_array($roleSlug, ['owner', 'admin'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized. Modifying quotation line pricing, discounts or taxes requires explicit quotations.pricing permission.');
        }
    }

    /**
     * Authorize updating quotation lifecycle status.
     */
    protected function authorizeQuotationStatus(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('quotations.*') ||
            $user->hasPermission('quotations.status') ||
            in_array($roleSlug, ['owner', 'admin'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized. Updating quotation status requires explicit quotations.status permission or Company Owner role.');
        }
    }

    /**
     * Authorize converting accepted quotation to project.
     */
    protected function authorizeQuotationConvert(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('quotations.*') ||
            $user->hasPermission('quotations.convert') ||
            in_array($roleSlug, ['owner', 'admin'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized. Converting an accepted quotation into an active project requires explicit quotations.convert permission.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeQuotationView();
        $companyId = $this->getCompanyId();

        $query = Quotation::with(['client', 'creator', 'convertedProject'])
            ->where('company_id', $companyId)
            ->latest('date');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('project_title', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('date', '<=', $toDate);
        }

        $quotations = $query->paginate(15)->withQueryString();

        $allQuotes = Quotation::where('company_id', $companyId)->get();
        $stats = [
            'total_count' => $allQuotes->count(),
            'total_value' => $allQuotes->sum('total_amount'),
            'accepted_value' => $allQuotes->where('status', 'accepted')->sum('total_amount'),
            'converted_count' => $allQuotes->whereNotNull('converted_project_id')->count(),
            'pending_count' => $allQuotes->where('status', 'sent')->count(),
        ];

        return view('quotations.index', compact('quotations', 'stats'));
    }

    public function create(): View
    {
        $this->authorizeQuotationCreate();
        $companyId = $this->getCompanyId();

        $clients = Client::where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get();
        if ($clients->isEmpty()) {
            $clients = Client::where('company_id', $companyId)->orderBy('name')->get();
        }

        $year = date('Y');
        $count = Quotation::where('company_id', $companyId)->whereYear('date', $year)->count() + 1;
        $defaultNumber = 'QT-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        return view('quotations.create', compact('clients', 'defaultNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeQuotationCreate();
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quotation_number' => 'required|string|max:50',
            'date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:date',
            'project_title' => 'required|string|max:255',
            'project_description' => 'nullable|string',
            'tax_type' => 'required|string|in:gst_18,igst_18,gst_12,gst_5,exempt,custom',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_rate' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        // Prevent duplicate quotation numbers for the company
        if (Quotation::where('company_id', $companyId)->where('quotation_number', $validated['quotation_number'])->exists()) {
            throw ValidationException::withMessages([
                'quotation_number' => "Quotation number '{$validated['quotation_number']}' already exists in this company.",
            ]);
        }

        // Verify client belongs to company
        $client = Client::where('id', $validated['client_id'])->where('company_id', $companyId)->first();
        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'Selected client does not belong to the active company.',
            ]);
        }

        $quotation = DB::transaction(function () use ($validated, $companyId) {
            $quotation = Quotation::create([
                'company_id' => $companyId,
                'quotation_number' => $validated['quotation_number'],
                'client_id' => $validated['client_id'],
                'date' => $validated['date'],
                'valid_until' => $validated['valid_until'],
                'project_title' => $validated['project_title'],
                'project_description' => $validated['project_description'] ?? null,
                'status' => 'draft',
                'tax_type' => $validated['tax_type'],
                'tax_rate' => $validated['tax_type'] === 'exempt' ? 0.00 : $validated['tax_rate'],
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_rate' => $validated['discount_rate'] ?? 0.00,
                'discount_amount' => $validated['discount_amount'] ?? 0.00,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $index => $itemData) {
                $qty = (float) $itemData['quantity'];
                $rate = (float) $itemData['unit_rate'];
                $amount = round($qty * $rate, 2);

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_order' => $index + 1,
                    'description' => $itemData['description'],
                    'quantity' => $qty,
                    'unit' => $itemData['unit'],
                    'unit_rate' => $rate,
                    'amount' => $amount,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $quotation->load('items');
            $quotation->recalculateTotals();

            AuditLog::create([
                'user_id' => Auth::id(),
                'company_id' => $quotation->company_id,
                'action' => 'create_quotation',
                'auditable_type' => Quotation::class,
                'auditable_id' => $quotation->id,
                'new_values' => [
                    'quotation_number' => $quotation->quotation_number,
                    'total_amount' => $quotation->total_amount,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->quotation_number} created successfully.");
    }

    public function show(Quotation $quotation): View
    {
        $this->authorizeQuotationView();
        $companyId = $this->getCompanyId();

        if ((int) $quotation->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to quotation belonging to another company.');
        }

        $quotation->load(['client', 'items', 'creator', 'convertedProject']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation): View
    {
        $this->authorizeQuotationPricing();
        $companyId = $this->getCompanyId();

        if ((int) $quotation->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to quotation belonging to another company.');
        }

        if ($quotation->converted_project_id) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'Converted quotations cannot be modified.');
        }

        $clients = Client::where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get();
        $quotation->load(['client', 'items']);

        return view('quotations.edit', compact('quotation', 'clients'));
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotationPricing();
        $companyId = $this->getCompanyId();

        if ((int) $quotation->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to quotation belonging to another company.');
        }

        if ($quotation->converted_project_id) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'Converted quotations cannot be modified.');
        }

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quotation_number' => 'required|string|max:50',
            'date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:date',
            'project_title' => 'required|string|max:255',
            'project_description' => 'nullable|string',
            'tax_type' => 'required|string|in:gst_18,igst_18,gst_12,gst_5,exempt,custom',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_rate' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if (Quotation::where('company_id', $companyId)->where('quotation_number', $validated['quotation_number'])->where('id', '!=', $quotation->id)->exists()) {
            throw ValidationException::withMessages([
                'quotation_number' => "Quotation number '{$validated['quotation_number']}' already exists.",
            ]);
        }

        $client = Client::where('id', $validated['client_id'])->where('company_id', $companyId)->first();
        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'Selected client does not belong to the active company.',
            ]);
        }

        DB::transaction(function () use ($quotation, $validated) {
            $quotation->update([
                'client_id' => $validated['client_id'],
                'quotation_number' => $validated['quotation_number'],
                'date' => $validated['date'],
                'valid_until' => $validated['valid_until'],
                'project_title' => $validated['project_title'],
                'project_description' => $validated['project_description'] ?? null,
                'tax_type' => $validated['tax_type'],
                'tax_rate' => $validated['tax_type'] === 'exempt' ? 0.00 : $validated['tax_rate'],
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_rate' => $validated['discount_rate'] ?? 0.00,
                'discount_amount' => $validated['discount_amount'] ?? 0.00,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Replace items cleanly
            $quotation->items()->delete();

            foreach ($validated['items'] as $index => $itemData) {
                $qty = (float) $itemData['quantity'];
                $rate = (float) $itemData['unit_rate'];
                $amount = round($qty * $rate, 2);

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_order' => $index + 1,
                    'description' => $itemData['description'],
                    'quantity' => $qty,
                    'unit' => $itemData['unit'],
                    'unit_rate' => $rate,
                    'amount' => $amount,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $quotation->load('items');
            $quotation->recalculateTotals();

            AuditLog::create([
                'user_id' => Auth::id(),
                'company_id' => $quotation->company_id,
                'action' => 'update_quotation',
                'auditable_type' => Quotation::class,
                'auditable_id' => $quotation->id,
                'new_values' => ['total_amount' => $quotation->total_amount],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->quotation_number} updated successfully.");
    }

    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotationStatus();
        $companyId = $this->getCompanyId();

        if ((int) $quotation->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to quotation belonging to another company.');
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $targetStatus = $validated['status'];

        if (! $quotation->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'status' => "Invalid status transition from '{$quotation->status}' to '{$targetStatus}'.",
            ]);
        }

        $oldStatus = $quotation->status;

        $quotation->update([
            'status' => $targetStatus,
            'rejection_reason' => $targetStatus === 'rejected' ? ($validated['rejection_reason'] ?? null) : $quotation->rejection_reason,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'company_id' => $quotation->company_id,
            'action' => 'update_quotation_status',
            'auditable_type' => Quotation::class,
            'auditable_id' => $quotation->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $targetStatus],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation status updated to '" . ucfirst($targetStatus) . "'.");
    }

    public function convertToProject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotationConvert();
        $companyId = $this->getCompanyId();

        if ((int) $quotation->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to quotation belonging to another company.');
        }

        $validated = $request->validate([
            'project_name' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:start_date',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        try {
            $project = $quotation->convertToProject(Auth::user(), [
                'name' => $validated['project_name'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'target_completion_date' => $validated['target_completion_date'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
            ]);

            return redirect()->route('projects.show', $project)
                ->with('success', "Quotation {$quotation->quotation_number} successfully converted to Project {$project->code} ({$project->name}).");
        } catch (DomainException $e) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', $e->getMessage());
        }
    }

    public function printView(Quotation $quotation): View
    {
        $this->authorizeQuotationView();
        $companyId = $this->getCompanyId();

        if ((int) $quotation->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to quotation belonging to another company.');
        }

        $quotation->load(['client', 'items', 'creator']);
        $company = Company::configuredCompany() ?? $quotation->company;

        return view('quotations.print', compact('quotation', 'company'));
    }
}
