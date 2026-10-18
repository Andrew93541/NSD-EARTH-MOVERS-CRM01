<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AssetDepreciationEntry;
use App\Models\FixedAsset;
use App\Models\Loan;
use App\Models\Project;
use App\Services\EquipmentAccountingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    protected function authorizeAssets(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('assets.*') && ! $user->hasPermission('finance.*') && ! in_array($roleSlug, ['owner', 'admin', 'accountant', 'project_manager']))) {
            abort(403, 'Unauthorized access to Fixed Assets, Fleet Register, and Depreciation.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);

        $query = FixedAsset::where('company_id', $companyId)
            ->with(['project', 'loan', 'depreciationEntries' => function ($q) {
                $q->latest('period_end_date')->limit(1);
            }]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('asset_code', 'like', "%{$s}%")
                    ->orWhere('serial_number', 'like', "%{$s}%");
            });
        }

        $assets = $query->latest()->paginate(12)->withQueryString();

        // High-level aggregate KPIs
        $baseQuery = FixedAsset::where('company_id', $companyId);
        $totalCapitalizedCost = (float) (clone $baseQuery)->sum('total_capitalized_cost');
        $totalAccumDepreciation = (float) (clone $baseQuery)->sum('accumulated_depreciation');
        $totalNetBookValue = (float) (clone $baseQuery)->sum('current_book_value');
        $activeFleetCount = (clone $baseQuery)->where('status', 'active')->count();
        $financedEquipmentCount = (clone $baseQuery)->whereNotNull('loan_id')->count();

        $projects = Project::where('company_id', $companyId)->orderBy('name')->get();

        return view('assets.index', compact(
            'assets',
            'totalCapitalizedCost',
            'totalAccumDepreciation',
            'totalNetBookValue',
            'activeFleetCount',
            'financedEquipmentCount',
            'projects'
        ));
    }

    public function create(): View
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);
        $projects = Project::where('company_id', $companyId)->orderBy('name')->get();
        $loans = Loan::where('company_id', $companyId)->where('status', 'active')->orderBy('lender_name')->get();

        return view('assets.create', compact('projects', 'loans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);

        $validated = $request->validate([
            'asset_code' => 'required|string|max:50|unique:fixed_assets,asset_code,NULL,id,company_id,' . $companyId,
            'name' => 'required|string|max:200',
            'category' => 'required|string|in:excavator,backhoe_loader,dumper_tipper,crane,compactor,concrete_machinery,generator,other',
            'serial_number' => 'nullable|string|max:100',
            'purchase_date' => 'required|date',
            'capitalization_date' => 'required|date',
            'depreciation_start_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:1',
            'eligible_capitalized_costs' => 'nullable|numeric|min:0',
            'residual_value' => 'nullable|numeric|min:0',
            'useful_life_years' => 'required|numeric|between:1,30',
            'depreciation_method' => 'required|string|in:straight_line,written_down_value',
            'depreciation_rate_percent' => 'nullable|numeric|between:0.01,99.99',
            'project_id' => 'nullable|exists:projects,id',
            'loan_id' => 'nullable|exists:loans,id',
            'location' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);

        $acquisition = (float) $validated['acquisition_cost'];
        $additions = (float) ($validated['eligible_capitalized_costs'] ?? 0.0);
        $totalCapitalized = round($acquisition + $additions, 2);
        $residual = (float) ($validated['residual_value'] ?? 0.0);

        if ($residual >= $totalCapitalized) {
            return back()->withInput()->withErrors(['residual_value' => 'Residual salvage value cannot exceed or equal total capitalized cost.']);
        }

        $usefulYears = (float) $validated['useful_life_years'];
        $usefulMonths = (int) round($usefulYears * 12);

        // Ensure system accounts exist
        $assetAccount = EquipmentAccountingService::getOrCreateAccount($companyId, '1510', 'Plant, Machinery & Heavy Earthmoving Equipment', 'asset', 'fixed_asset');
        $accumAccount = EquipmentAccountingService::getOrCreateAccount($companyId, '1520', 'Accumulated Depreciation - Plant & Machinery', 'asset', 'fixed_asset');
        $expenseAccount = EquipmentAccountingService::getOrCreateAccount($companyId, '5030', 'Depreciation Expense - Heavy Equipment & Fleet', 'expense', 'depreciation');

        $asset = DB::transaction(function () use ($validated, $companyId, $totalCapitalized, $usefulYears, $usefulMonths, $assetAccount, $accumAccount, $expenseAccount) {
            $created = FixedAsset::create([
                'company_id' => $companyId,
                'asset_code' => strtoupper($validated['asset_code']),
                'name' => $validated['name'],
                'category' => $validated['category'],
                'serial_number' => $validated['serial_number'] ?? null,
                'purchase_date' => $validated['purchase_date'],
                'capitalization_date' => $validated['capitalization_date'],
                'depreciation_start_date' => $validated['depreciation_start_date'],
                'acquisition_cost' => $validated['acquisition_cost'],
                'eligible_capitalized_costs' => $validated['eligible_capitalized_costs'] ?? 0.00,
                'total_capitalized_cost' => $totalCapitalized,
                'residual_value' => $validated['residual_value'] ?? 0.00,
                'useful_life_years' => (int) ceil($usefulYears),
                'useful_life_months' => $usefulMonths,
                'depreciation_method' => $validated['depreciation_method'],
                'depreciation_rate_percent' => $validated['depreciation_rate_percent'] ?? null,
                'depreciation_frequency' => 'monthly',
                'accumulated_depreciation' => 0.00,
                'current_book_value' => $totalCapitalized,
                'status' => 'active',
                'asset_account_id' => $assetAccount->id,
                'accumulated_depr_account_id' => $accumAccount->id,
                'depr_expense_account_id' => $expenseAccount->id,
                'project_id' => $validated['project_id'] ?? null,
                'loan_id' => $validated['loan_id'] ?? null,
                'location' => $validated['location'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // If loan is selected, link loan back to this asset
            if (! empty($validated['loan_id'])) {
                Loan::where('id', $validated['loan_id'])->update(['fixed_asset_id' => $created->id]);
            }

            return $created;
        });

        return redirect()->route('assets.show', $asset)->with('success', "Capital asset {$asset->asset_code} registered successfully.");
    }

    public function show(FixedAsset $asset): View
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);
        if ($asset->company_id !== $companyId) {
            abort(404, 'Asset not found in current company workspace.');
        }

        $asset->load([
            'project',
            'loan.emiSchedules',
            'assetAccount',
            'accumulatedDeprAccount',
            'deprExpenseAccount',
            'depreciationEntries.journalEntry',
            'depreciationEntries.approver',
        ]);

        return view('assets.show', compact('asset'));
    }

    public function calculateDepreciation(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);
        if ($asset->company_id !== $companyId) {
            abort(404, 'Asset not found in current company workspace.');
        }

        $validated = $request->validate([
            'period_start_date' => 'required|date',
            'period_end_date' => 'required|date|after_or_equal:period_start_date',
        ]);

        try {
            $start = Carbon::parse($validated['period_start_date'])->startOfDay();
            $end = Carbon::parse($validated['period_end_date'])->endOfDay();

            $entry = EquipmentAccountingService::calculateDepreciation($asset, $start, $end, Auth::user());

            return back()->with('success', "Depreciation of ₹" . number_format($entry->depreciation_amount, 2) . " calculated for {$start->format('M Y')}. Review and post to the general ledger.");
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['depreciation' => $e->getMessage()]);
        }
    }

    public function postDepreciation(Request $request, AssetDepreciationEntry $entry): RedirectResponse
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);
        if ($entry->company_id !== $companyId) {
            abort(404, 'Depreciation record not found in current company workspace.');
        }

        try {
            $journalEntry = EquipmentAccountingService::postDepreciationEntry($entry, Auth::user());

            return back()->with('success', "Depreciation posted to general ledger under Journal Entry {$journalEntry->entry_number}.");
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }
    }

    public function reverseDepreciation(Request $request, AssetDepreciationEntry $entry): RedirectResponse
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);
        if ($entry->company_id !== $companyId) {
            abort(404, 'Depreciation record not found in current company workspace.');
        }

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:5|max:255',
        ]);

        try {
            $reversalJournal = EquipmentAccountingService::reverseDepreciationEntry($entry, $validated['reversal_reason'], Auth::user());

            return back()->with('success', "Depreciation entry reversed. Counter-journal {$reversalJournal->entry_number} posted.");
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reversal' => $e->getMessage()]);
        }
    }

    public function periodReview(Request $request): View
    {
        $this->authorizeAssets();

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);

        if ($request->filled('period_month') && preg_match('/^(\d{4})-(\d{1,2})$/', $request->period_month, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
        } elseif ($request->filled('month') && str_contains($request->month, '-')) {
            [$year, $month] = array_map('intval', explode('-', $request->month));
        } else {
            $month = (int) ($request->month ?? date('n'));
            $year = (int) ($request->year ?? date('Y'));
        }

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = (clone $periodStart)->endOfMonth();

        $summary = EquipmentAccountingService::getPeriodicFleetSummary($companyId, $periodStart, $periodEnd);

        return view('assets.period-review', compact('summary', 'month', 'year'));
    }

    public function postPeriodBatch(Request $request): RedirectResponse
    {
        $this->authorizeAssets();

        if ($request->filled('period_month') && preg_match('/^(\d{4})-(\d{1,2})$/', $request->period_month, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
        } else {
            $validated = $request->validate([
                'month' => 'required|integer|between:1,12',
                'year' => 'required|integer|between:2020,2040',
            ]);
            $month = (int) $validated['month'];
            $year = (int) $validated['year'];
        }

        $companyId = session('current_company_id', Auth::user()->companies()->first()?->id);
        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = (clone $periodStart)->endOfMonth();

        $summary = EquipmentAccountingService::getPeriodicFleetSummary($companyId, $periodStart, $periodEnd);

        $postedCount = 0;
        $totalPostedAmount = 0.0;
        $user = Auth::user();

        DB::transaction(function () use ($summary, $periodStart, $periodEnd, $user, &$postedCount, &$totalPostedAmount) {
            foreach ($summary['items'] as $item) {
                if (! $item['is_eligible']) {
                    continue;
                }

                $entry = $item['entry'];
                if (! $entry) {
                    $entry = EquipmentAccountingService::calculateDepreciation($item['asset'], $periodStart, $periodEnd, $user);
                }

                if ($entry->status !== 'posted') {
                    $journal = EquipmentAccountingService::postDepreciationEntry($entry, $user);
                    $postedCount++;
                    $totalPostedAmount += (float) $entry->depreciation_amount;
                }
            }
        });

        return back()->with('success', "Periodic depreciation batch completed: {$postedCount} asset journals posted totaling ₹" . number_format($totalPostedAmount, 2) . ".");
    }
}
