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
use App\Services\StockAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialIssueController extends Controller
{
    protected function authorizeIssueAccess(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('inventory.*') ||
            $user->hasPermission('materials.*') ||
            $user->hasPermission('projects.*') ||
            $user->hasPermission('sites.*') ||
            in_array($roleSlug, ['owner', 'admin', 'storekeeper', 'project_manager', 'site_supervisor', 'procurement_officer', 'accountant'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized access to site material issue records.');
        }
    }

    protected function authorizeIssueExecution(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $authorized = $user && (
            $user->hasPermission('*') ||
            $user->hasPermission('inventory.*') ||
            $user->hasPermission('inventory.issue') ||
            in_array($roleSlug, ['owner', 'admin', 'storekeeper', 'project_manager', 'procurement_officer'], true)
        );

        if (! $authorized) {
            abort(403, 'Unauthorized. Issuing stock to construction sites is restricted to Storekeepers, Project Managers, and Administrators.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeIssueAccess();

        $query = MaterialIssue::with(['warehouse', 'project', 'site', 'issuer', 'items.material'])
            ->latest('issue_date');

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

        $allIssues = MaterialIssue::all();
        $stats = [
            'total_issues' => $allIssues->count(),
            'total_cost_issued' => $allIssues->sum('total_cost'),
            'active_projects_receiving' => $allIssues->pluck('project_id')->unique()->count(),
            'active_sites_receiving' => $allIssues->pluck('site_id')->unique()->count(),
        ];

        $warehouses = Warehouse::orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        return view('inventory.material-issues.index', compact('issues', 'stats', 'warehouses', 'projects'));
    }

    public function create(Request $request): View
    {
        $this->authorizeIssueExecution();

        $warehouses = Warehouse::orderBy('name')->get();
        $projects = Project::where('status', 'active')->with('sites')->orderBy('name')->get();
        $materials = Material::with('category')->orderBy('name')->get();
        $users = User::orderBy('name')->get();

        $year = date('Y');
        $count = MaterialIssue::whereYear('issue_date', $year)->count() + 1;
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
        $this->authorizeIssueExecution();

        $validated = $request->validate([
            'issue_number' => 'required|string|max:50|unique:material_issues,issue_number',
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

        $site = Site::findOrFail($validated['site_id']);
        if ((int) $site->project_id !== (int) $validated['project_id']) {
            throw ValidationException::withMessages([
                'site_id' => "Selected site '{$site->name}' does not belong to the selected project.",
            ]);
        }

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $issue = DB::transaction(function () use ($validated, $warehouse, $site) {
            // Check stock availability and lock materials rows to prevent concurrent overspending
            $itemsToProcess = [];

            foreach ($validated['items'] as $itemData) {
                $materialId = (int) $itemData['material_id'];
                $issuedQty = round((float) $itemData['issued_quantity'], 3);

                // Lock material row
                $material = Material::where('id', $materialId)->lockForUpdate()->firstOrFail();

                // Compute real-time available stock in source warehouse with lock
                $inStock = StockLedgerEntry::where('material_id', $material->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('direction', 'in')
                    ->lockForUpdate()
                    ->sum('quantity');

                $outStock = StockLedgerEntry::where('material_id', $material->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('direction', 'out')
                    ->lockForUpdate()
                    ->sum('quantity');

                $availableStock = round($inStock - $outStock, 3);

                if ($issuedQty > $availableStock) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$material->name} in {$warehouse->name}. Available: {$availableStock} {$material->unit_of_measure}, Requested to Issue: {$issuedQty} {$material->unit_of_measure}.",
                    ]);
                }

                $itemsToProcess[] = [
                    'material' => $material,
                    'requested_quantity' => (float) $itemData['requested_quantity'],
                    'issued_quantity' => $issuedQty,
                    'unit_rate' => (float) ($material->standard_cost > 0 ? $material->standard_cost : 0.00),
                    'remarks' => $itemData['remarks'] ?? null,
                    'available_stock_before' => $availableStock,
                ];
            }

            // Create Issue Record
            $materialIssue = MaterialIssue::create([
                'issue_number' => $validated['issue_number'],
                'warehouse_id' => $warehouse->id,
                'project_id' => $validated['project_id'],
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

            foreach ($itemsToProcess as $processed) {
                $material = $processed['material'];
                $issuedQty = $processed['issued_quantity'];
                $unitRate = $processed['unit_rate'];
                $amount = round($issuedQty * $unitRate, 2);
                $totalCost += $amount;

                MaterialIssueItem::create([
                    'material_issue_id' => $materialIssue->id,
                    'material_id' => $material->id,
                    'requested_quantity' => $processed['requested_quantity'],
                    'issued_quantity' => $issuedQty,
                    'unit_of_measure' => $material->unit_of_measure,
                    'unit_rate' => $unitRate,
                    'total_amount' => $amount,
                    'remarks' => $processed['remarks'],
                ]);

                // Create stock ledger entry in existing inventory ledger
                StockLedgerEntry::create([
                    'company_id' => $materialIssue->company_id,
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
                    'balance_quantity' => round($processed['available_stock_before'] - $issuedQty, 3),
                    'created_by' => Auth::id(),
                ]);
            }

            $materialIssue->updateQuietly(['total_cost' => $totalCost]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'company_id' => $materialIssue->company_id,
                'action' => 'issue_material_to_site',
                'auditable_type' => MaterialIssue::class,
                'auditable_id' => $materialIssue->id,
                'new_values' => [
                    'issue_number' => $materialIssue->issue_number,
                    'warehouse' => $warehouse->name,
                    'project_id' => $materialIssue->project_id,
                    'site_id' => $materialIssue->site_id,
                    'total_cost' => $totalCost,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $materialIssue;
        });

        return redirect()->route('material-issues.show', $issue)
            ->with('success', "Material Issue Note {$issue->issue_number} recorded successfully. Stock deducted from {$warehouse->name}.");
    }

    public function show(MaterialIssue $materialIssue): View
    {
        $this->authorizeIssueAccess();

        $materialIssue->load(['warehouse', 'project', 'site', 'items.material', 'requester', 'issuer']);

        return view('inventory.material-issues.show', compact('materialIssue'));
    }

    public function printSlip(MaterialIssue $materialIssue): View
    {
        $this->authorizeIssueAccess();

        $materialIssue->load(['warehouse', 'project', 'site', 'items.material', 'requester', 'issuer']);
        $company = Company::configuredCompany();

        return view('inventory.material-issues.print', compact('materialIssue', 'company'));
    }

    public function returnStock(Request $request, MaterialIssue $materialIssue): RedirectResponse
    {
        $this->authorizeIssueExecution();

        $validated = $request->validate([
            'return_date' => 'required|date',
            'returns' => 'required|array|min:1',
            'returns.*.item_id' => 'required|exists:material_issue_items,id',
            'returns.*.quantity' => 'required|numeric|min:0.001',
            'returns.*.reason' => 'nullable|string',
        ]);

        DB::transaction(function () use ($materialIssue, $validated) {
            foreach ($validated['returns'] as $returnData) {
                $item = MaterialIssueItem::findOrFail($returnData['item_id']);
                $qty = round((float) $returnData['quantity'], 3);

                if ($qty > (float) $item->issued_quantity) {
                    throw ValidationException::withMessages([
                        'returns' => "Returned quantity ({$qty}) cannot exceed originally issued quantity ({$item->issued_quantity}).",
                    ]);
                }

                $material = $item->material;
                $warehouse = $materialIssue->warehouse;

                $currentIn = StockLedgerEntry::where('material_id', $material->id)->where('warehouse_id', $warehouse->id)->where('direction', 'in')->sum('quantity');
                $currentOut = StockLedgerEntry::where('material_id', $material->id)->where('warehouse_id', $warehouse->id)->where('direction', 'out')->sum('quantity');
                $currentBalance = round($currentIn - $currentOut, 3);

                $unitRate = (float) $item->unit_rate;
                $amount = round($qty * $unitRate, 2);

                StockLedgerEntry::create([
                    'company_id' => $materialIssue->company_id,
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

            AuditLog::create([
                'user_id' => Auth::id(),
                'company_id' => $materialIssue->company_id,
                'action' => 'return_material_from_site',
                'auditable_type' => MaterialIssue::class,
                'auditable_id' => $materialIssue->id,
                'new_values' => ['returns' => $validated['returns']],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return redirect()->route('material-issues.show', $materialIssue)
            ->with('success', "Materials returned to {$materialIssue->warehouse->name} and stock ledger updated.");
    }
}
