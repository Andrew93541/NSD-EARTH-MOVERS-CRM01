<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialIssueController extends Controller
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
     * Determine if a user is assigned to a specific project.
     */
    protected function isUserAssignedToProject(User $user, Project $project): bool
    {
        if ($user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin'], true)) {
            return true;
        }

        if ((int) $project->manager_id === (int) $user->id) {
            return true;
        }

        if (is_array($project->assigned_team) && in_array($user->id, $project->assigned_team)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user is assigned to a specific site.
     */
    protected function isUserAssignedToSite(User $user, Site $site): bool
    {
        if ($user->hasPermission('*') || in_array($user->currentRole()?->slug, ['owner', 'admin'], true)) {
            return true;
        }

        if ((int) $site->supervisor_id === (int) $user->id) {
            return true;
        }

        if ($site->project && $this->isUserAssignedToProject($user, $site->project)) {
            return true;
        }

        return false;
    }

    /**
     * Authorize read access to material issue module.
     */
    protected function authorizeIssueAccess(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('inventory.*') ||
            $user->hasPermission('materials.*') ||
            $user->hasPermission('projects.*') ||
            $user->hasPermission('projects.view') ||
            $user->hasPermission('sites.*') ||
            $user->hasPermission('sites.view') ||
            in_array($roleSlug, ['owner', 'admin', 'storekeeper', 'project_manager', 'site_supervisor', 'procurement_officer', 'accountant'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized access to site material issue records.');
        }
    }

    /**
     * Authorize execution of an issue or return dispatch.
     */
    protected function authorizeIssueExecution(?Project $project = null, ?Site $site = null): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        // Site supervisors must not issue or return stock unless they hold explicit permission
        if ($roleSlug === 'site_supervisor' && ! $user->hasPermission('inventory.issue') && ! $user->hasPermission('*')) {
            abort(403, 'Unauthorized. Site supervisors are not permitted to issue or return warehouse stock without explicit inventory.issue permission.');
        }

        $canExecute = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('inventory.*') ||
            $user->hasPermission('inventory.issue') ||
            in_array($roleSlug, ['owner', 'admin', 'storekeeper'], true) ||
            ($roleSlug === 'project_manager' && ($user->hasPermission('inventory.*') || $user->hasPermission('inventory.issue') || $user->hasPermission('projects.*')))
        );

        if (! $canExecute) {
            abort(403, 'Unauthorized. Issuing stock to construction sites is restricted to authorized Storekeepers and Administrators.');
        }

        if ($project && $roleSlug === 'project_manager' && ! $this->isUserAssignedToProject($user, $project)) {
            abort(403, "Unauthorized. You are not assigned to manage project '{$project->name}'.");
        }

        if ($site && $roleSlug === 'site_supervisor' && ! $this->isUserAssignedToSite($user, $site)) {
            abort(403, "Unauthorized. You are not assigned to site '{$site->name}'.");
        }
    }

    /**
     * Authorize access to a specific material issue record.
     */
    protected function authorizeIssueRecordView(MaterialIssue $materialIssue): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;

        if ($user->hasPermission('*') || in_array($roleSlug, ['owner', 'admin', 'storekeeper', 'accountant', 'procurement_officer'], true)) {
            return;
        }

        if ($roleSlug === 'project_manager') {
            if ($materialIssue->project && $this->isUserAssignedToProject($user, $materialIssue->project)) {
                return;
            }
            abort(403, 'Unauthorized. You are not assigned to manage the project associated with this issue note.');
        }

        if ($roleSlug === 'site_supervisor') {
            if ($materialIssue->site && $this->isUserAssignedToSite($user, $materialIssue->site)) {
                return;
            }
            abort(403, 'Unauthorized. You are not assigned to the site associated with this issue note.');
        }

        abort(403, 'Unauthorized access to this material issue record.');
    }

    public function index(Request $request): View
    {
        $this->authorizeIssueAccess();
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $companyId = $this->getCompanyId();

        $query = MaterialIssue::with(['warehouse', 'project', 'site', 'issuer', 'items.material'])
            ->where('company_id', $companyId)
            ->latest('issue_date');

        // Scope queries based on user role and project/site assignment
        if ($roleSlug === 'project_manager' && ! $user->hasPermission('*') && ! in_array($roleSlug, ['owner', 'admin'], true)) {
            $assignedProjectIds = Project::where('company_id', $companyId)
                ->where(function ($q) use ($user) {
                    $q->where('manager_id', $user->id)
                        ->orWhereJsonContains('assigned_team', $user->id);
                })->pluck('id');

            $query->whereIn('project_id', $assignedProjectIds);
        } elseif ($roleSlug === 'site_supervisor' && ! $user->hasPermission('*') && ! in_array($roleSlug, ['owner', 'admin'], true)) {
            $assignedSiteIds = Site::where('company_id', $companyId)
                ->where('supervisor_id', $user->id)
                ->pluck('id');

            $query->whereIn('site_id', $assignedSiteIds);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($siteId = $request->input('site_id')) {
            $query->where('site_id', $siteId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('issue_number', 'like', "%{$search}%")
                    ->orWhere('received_by_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_number', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('site', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('issue_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('issue_date', '<=', $toDate);
        }

        $issues = $query->paginate(15)->withQueryString();

        $statsQuery = clone $query;
        $scopedIssues = $statsQuery->get();
        $stats = [
            'total_issues' => $scopedIssues->count(),
            'total_cost_issued' => $scopedIssues->sum('total_cost'),
            'active_projects_receiving' => $scopedIssues->pluck('project_id')->unique()->count(),
            'active_sites_receiving' => $scopedIssues->pluck('site_id')->unique()->count(),
        ];

        $warehouses = Warehouse::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        $projectsQuery = Project::where('company_id', $companyId)->where('status', 'active');
        if ($roleSlug === 'project_manager' && ! $user->hasPermission('*') && ! in_array($roleSlug, ['owner', 'admin'], true)) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->where('manager_id', $user->id)
                    ->orWhereJsonContains('assigned_team', $user->id);
            });
        }
        $projects = $projectsQuery->orderBy('name')->get();

        return view('inventory.material-issues.index', compact('issues', 'stats', 'warehouses', 'projects'));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $this->authorizeIssueExecution();
        $companyId = $this->getCompanyId();

        $warehouses = Warehouse::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        $projectsQuery = Project::where('company_id', $companyId)->where('status', 'active')->with('sites');
        if ($roleSlug === 'project_manager' && ! $user->hasPermission('*') && ! in_array($roleSlug, ['owner', 'admin'], true)) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->where('manager_id', $user->id)
                    ->orWhereJsonContains('assigned_team', $user->id);
            });
        }
        $projects = $projectsQuery->orderBy('name')->get();

        $materials = Material::where('company_id', $companyId)->where('is_active', true)->with('category')->orderBy('name')->get();
        $users = User::whereHas('companies', fn($q) => $q->where('companies.id', $companyId)->wherePivot('status', 'active'))->orderBy('name')->get();

        $year = date('Y');
        $count = MaterialIssue::where('company_id', $companyId)->whereYear('issue_date', $year)->count() + 1;
        $defaultNumber = 'MIS-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $selectedProjectId = $request->input('project_id');
        $selectedSiteId = $request->input('site_id');
        $selectedWarehouseId = $request->input('warehouse_id');

        return view('inventory.material-issues.create', compact(
            'warehouses',
            'projects',
            'materials',
            'users',
            'defaultNumber',
            'selectedProjectId',
            'selectedSiteId',
            'selectedWarehouseId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'issue_number' => 'required|string|max:50',
            'warehouse_id' => 'required|exists:warehouses,id',
            'project_id' => 'required|exists:projects,id',
            'site_id' => 'required|exists:sites,id',
            'issue_date' => 'required|date',
            'purpose' => 'nullable|string|max:255',
            'requested_by' => 'nullable|exists:users,id',
            'received_by_name' => 'nullable|string|max:255',
            'vehicle_number' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.requested_quantity' => 'required|numeric|min:0.001',
            'items.*.issued_quantity' => 'required|numeric|min:0.001',
            'items.*.remarks' => 'nullable|string',
        ]);

        // Prevent duplicate issue number at the company level
        if (MaterialIssue::where('company_id', $companyId)->where('issue_number', $validated['issue_number'])->exists()) {
            throw ValidationException::withMessages([
                'issue_number' => "Material issue reference '{$validated['issue_number']}' already exists.",
            ]);
        }

        // Validate warehouse belongs to active NSD company
        $warehouse = Warehouse::where('id', $validated['warehouse_id'])->where('company_id', $companyId)->first();
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Selected warehouse does not belong to the active company.']);
        }

        // Validate project belongs to active NSD company
        $project = Project::where('id', $validated['project_id'])->where('company_id', $companyId)->first();
        if (! $project) {
            throw ValidationException::withMessages(['project_id' => 'Selected project does not belong to the active company.']);
        }

        // Validate site belongs to active NSD company AND the selected project
        $site = Site::where('id', $validated['site_id'])->where('company_id', $companyId)->first();
        if (! $site || (int) $site->project_id !== (int) $project->id) {
            throw ValidationException::withMessages([
                'site_id' => "Selected site does not belong to the selected project in this company.",
            ]);
        }

        // Enforce user assignment to project/site and dispatch execution rights
        $this->authorizeIssueExecution($project, $site);

        $issue = DB::transaction(function () use ($validated, $warehouse, $project, $site, $companyId) {
            // Aggregate requested and issued quantities per material to prevent multi-line bypass
            $combinedIssuedByMaterial = [];
            $combinedRequestedByMaterial = [];

            foreach ($validated['items'] as $itemData) {
                $matId = (int) $itemData['material_id'];
                $qty = round((float) $itemData['issued_quantity'], 3);
                $reqQty = round((float) $itemData['requested_quantity'], 3);

                $combinedIssuedByMaterial[$matId] = round(($combinedIssuedByMaterial[$matId] ?? 0.0) + $qty, 3);
                $combinedRequestedByMaterial[$matId] = round(($combinedRequestedByMaterial[$matId] ?? 0.0) + $reqQty, 3);
            }

            // Lock all materials in deterministic sorted order
            $materialIds = array_keys($combinedIssuedByMaterial);
            sort($materialIds);

            $materials = Material::whereIn('id', $materialIds)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($materials->count() !== count($materialIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more materials are invalid or do not belong to the active company.',
                ]);
            }

            // Real-time stock availability check for each material's combined issue volume
            $availableStockBefore = [];
            foreach ($materialIds as $matId) {
                $material = $materials->get($matId);

                $inStock = StockLedgerEntry::where('material_id', $matId)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('company_id', $companyId)
                    ->where('direction', 'in')
                    ->lockForUpdate()
                    ->sum('quantity');

                $outStock = StockLedgerEntry::where('material_id', $matId)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('company_id', $companyId)
                    ->where('direction', 'out')
                    ->lockForUpdate()
                    ->sum('quantity');

                $available = round($inStock - $outStock, 3);
                $availableStockBefore[$matId] = $available;

                $totalToIssue = $combinedIssuedByMaterial[$matId];

                if ($totalToIssue > $available) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$material->name} in {$warehouse->name}. Available: {$available} {$material->unit_of_measure}, Combined Requested to Issue: {$totalToIssue} {$material->unit_of_measure}.",
                    ]);
                }
            }

            // Create Issue Record
            $materialIssue = MaterialIssue::create([
                'company_id' => $companyId,
                'issue_number' => $validated['issue_number'],
                'warehouse_id' => $warehouse->id,
                'project_id' => $project->id,
                'site_id' => $site->id,
                'issue_date' => $validated['issue_date'],
                'purpose' => $validated['purpose'] ?? 'site_consumption',
                'status' => 'completed',
                'requested_by' => $validated['requested_by'] ?? null,
                'issued_by' => Auth::id(),
                'received_by_name' => $validated['received_by_name'] ?? null,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'total_cost' => 0.00,
            ]);

            $totalCost = 0.00;
            $runningBalances = $availableStockBefore;

            foreach ($validated['items'] as $itemData) {
                $matId = (int) $itemData['material_id'];
                $material = $materials->get($matId);
                $issuedQty = round((float) $itemData['issued_quantity'], 3);
                $unitRate = (float) ($material->standard_cost > 0 ? $material->standard_cost : 0.00);
                $amount = round($issuedQty * $unitRate, 2);
                $totalCost += $amount;

                MaterialIssueItem::create([
                    'material_issue_id' => $materialIssue->id,
                    'material_id' => $material->id,
                    'requested_quantity' => (float) $itemData['requested_quantity'],
                    'issued_quantity' => $issuedQty,
                    'returned_quantity' => 0.000,
                    'unit_of_measure' => $material->unit_of_measure,
                    'unit_rate' => $unitRate,
                    'total_amount' => $amount,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);

                $newBalance = round($runningBalances[$matId] - $issuedQty, 3);
                $runningBalances[$matId] = $newBalance;

                StockLedgerEntry::create([
                    'company_id' => $companyId,
                    'material_id' => $material->id,
                    'warehouse_id' => $warehouse->id,
                    'entry_date' => $validated['issue_date'],
                    'transaction_type' => 'site_issue',
                    'reference_type' => 'material_issues',
                    'reference_id' => $materialIssue->id,
                    'quantity' => $issuedQty,
                    'direction' => 'out',
                    'unit_rate' => $unitRate,
                    'total_amount' => $amount,
                    'balance_quantity' => $newBalance,
                    'created_by' => Auth::id(),
                ]);
            }

            $materialIssue->updateQuietly(['total_cost' => $totalCost]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'action' => 'issue_material_to_site',
                'auditable_type' => MaterialIssue::class,
                'auditable_id' => $materialIssue->id,
                'new_values' => [
                    'issue_number' => $materialIssue->issue_number,
                    'project_id' => $project->id,
                    'site_id' => $site->id,
                    'total_cost' => $totalCost,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $materialIssue;
        });

        return redirect()->route('material-issues.show', $issue)
            ->with('success', "Material Issue {$issue->issue_number} recorded successfully.");
    }

    public function show(MaterialIssue $materialIssue): View
    {
        $this->authorizeIssueRecordView($materialIssue);
        $materialIssue->load(['warehouse', 'project', 'site', 'items.material', 'requester', 'issuer']);

        return view('inventory.material-issues.show', compact('materialIssue'));
    }

    public function printSlip(MaterialIssue $materialIssue): View
    {
        $this->authorizeIssueRecordView($materialIssue);
        $materialIssue->load(['warehouse', 'project', 'site', 'items.material', 'requester', 'issuer']);
        $company = Company::configuredCompany() ?? $materialIssue->company;

        return view('inventory.material-issues.print', compact('materialIssue', 'company'));
    }

    public function returnStock(Request $request, MaterialIssue $materialIssue): RedirectResponse
    {
        $this->authorizeIssueRecordView($materialIssue);
        $this->authorizeIssueExecution($materialIssue->project, $materialIssue->site);
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'return_date' => 'required|date',
            'returns' => 'required|array|min:1',
            'returns.*.item_id' => 'required|integer',
            'returns.*.quantity' => 'required|numeric|min:0.001',
            'returns.*.reason' => 'nullable|string',
        ]);

        DB::transaction(function () use ($materialIssue, $validated, $companyId) {
            // Aggregate returns by item_id to handle duplicate return lines in a single request
            $groupedReturns = [];
            foreach ($validated['returns'] as $returnData) {
                $itemId = (int) $returnData['item_id'];
                $qty = round((float) $returnData['quantity'], 3);
                $groupedReturns[$itemId] = round(($groupedReturns[$itemId] ?? 0.0) + $qty, 3);
            }

            $itemIds = array_keys($groupedReturns);
            sort($itemIds);

            // Lock items in deterministic sorted order
            $items = MaterialIssueItem::whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

            // 1. Cross-issue check: every item must belong to THIS material issue
            foreach ($itemIds as $itemId) {
                $item = $items->get($itemId);
                if (! $item || (int) $item->material_issue_id !== (int) $materialIssue->id) {
                    throw ValidationException::withMessages([
                        'returns' => "Item #{$itemId} does not belong to Material Issue {$materialIssue->issue_number}.",
                    ]);
                }

                // 2. Remaining returnable calculation: issued_quantity - previously returned
                $alreadyReturned = (float) ($item->returned_quantity ?? 0.0);
                $remainingReturnable = round((float) $item->issued_quantity - $alreadyReturned, 3);
                $requestedToReturn = $groupedReturns[$itemId];

                if ($requestedToReturn > $remainingReturnable) {
                    $matName = $item->material?->name ?? "Item #{$itemId}";
                    throw ValidationException::withMessages([
                        'returns' => "Returned quantity ({$requestedToReturn}) exceeds remaining returnable quantity ({$remainingReturnable}) for {$matName} on Issue {$materialIssue->issue_number}.",
                    ]);
                }
            }

            $warehouse = $materialIssue->warehouse;

            foreach ($validated['returns'] as $returnData) {
                $item = $items->get((int) $returnData['item_id']);
                $qty = round((float) $returnData['quantity'], 3);
                $material = $item->material;

                // Update item's returned_quantity
                $item->returned_quantity = round((float) ($item->returned_quantity ?? 0.0) + $qty, 3);
                $item->save();

                // Compute real-time balance under lock
                $currentIn = StockLedgerEntry::where('material_id', $material->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('company_id', $companyId)
                    ->where('direction', 'in')
                    ->lockForUpdate()
                    ->sum('quantity');

                $currentOut = StockLedgerEntry::where('material_id', $material->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('company_id', $companyId)
                    ->where('direction', 'out')
                    ->lockForUpdate()
                    ->sum('quantity');

                $currentBalance = round($currentIn - $currentOut, 3);
                $unitRate = (float) $item->unit_rate;
                $amount = round($qty * $unitRate, 2);

                StockLedgerEntry::create([
                    'company_id' => $companyId,
                    'material_id' => $material->id,
                    'warehouse_id' => $warehouse->id,
                    'entry_date' => $validated['return_date'],
                    'transaction_type' => 'site_return',
                    'reference_type' => 'material_issues',
                    'reference_id' => $materialIssue->id,
                    'quantity' => $qty,
                    'direction' => 'in',
                    'unit_rate' => $unitRate,
                    'total_amount' => $amount,
                    'balance_quantity' => round($currentBalance + $qty, 3),
                    'created_by' => Auth::id(),
                ]);
            }

            // Check if all items on the issue are fully returned
            $allItems = MaterialIssueItem::where('material_issue_id', $materialIssue->id)->get();
            $allFullyReturned = $allItems->every(fn($i) => (float) $i->returned_quantity >= (float) $i->issued_quantity);
            $hasAnyReturned = $allItems->contains(fn($i) => (float) $i->returned_quantity > 0);

            if ($allFullyReturned) {
                $materialIssue->updateQuietly(['status' => 'returned']);
            } elseif ($hasAnyReturned) {
                $materialIssue->updateQuietly(['status' => 'partially_returned']);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'action' => 'return_material_from_site',
                'auditable_type' => MaterialIssue::class,
                'auditable_id' => $materialIssue->id,
                'new_values' => [
                    'issue_number' => $materialIssue->issue_number,
                    'returns' => $validated['returns'],
                    'status' => $materialIssue->status,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return redirect()->route('material-issues.show', $materialIssue)
            ->with('success', "Materials returned to {$materialIssue->warehouse->name} and stock ledger updated.");
    }
}
