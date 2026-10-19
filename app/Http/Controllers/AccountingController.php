<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountingController extends Controller
{
    protected function authorizeAccounting(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('accounting.*') && ! $user->hasPermission('finance.*') && ! in_array($roleSlug, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized access to double-entry ledger and financial reports.');
        }
    }

    public function index(): View
    {
        $this->authorizeAccounting();

        $accounts = Account::orderBy('code')->get();

        $stats = [
            'total_accounts' => Account::count(),
            'assets_count' => Account::where('account_type', 'asset')->count(),
            'liabilities_count' => Account::where('account_type', 'liability')->count(),
            'revenue_count' => Account::where('account_type', 'revenue')->count(),
        ];

        return view('accounting.index', compact('accounts', 'stats'));
    }

    public function journalEntries(): View
    {
        $this->authorizeAccounting();

        $entries = JournalEntry::with(['lines.account', 'creator', 'reverser'])
            ->latest('entry_date')
            ->paginate(15);

        return view('accounting.journals', compact('entries'));
    }

    public function createJournal(): View
    {
        $this->authorizeAccounting();

        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        $projects = Project::orderBy('name')->get();

        return view('accounting.create-journal', compact('accounts', 'projects'));
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        $this->authorizeAccounting();

        $validated = $request->validate([
            'entry_date' => 'required|date',
            'narration' => 'required|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.project_id' => 'nullable|exists:projects,id',
            'lines.*.description' => 'nullable|string',
        ]);

        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($validated['lines'] as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        // Strict double-entry balance validation
        if (bccomp((string) $totalDebit, (string) $totalCredit, 2) !== 0) {
            return back()->withInput()->withErrors([
                'lines' => "Journal entry is unbalanced. Total Debits: ₹" . number_format($totalDebit, 2) . ", Total Credits: ₹" . number_format($totalCredit, 2),
            ]);
        }

        if ($totalDebit <= 0) {
            return back()->withInput()->withErrors([
                'lines' => "Journal entry amount must be greater than zero.",
            ]);
        }

        DB::transaction(function () use ($validated, $totalDebit) {
            $entry = JournalEntry::create([
                'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => $validated['entry_date'],
                'narration' => $validated['narration'],
                'total_amount' => $totalDebit,
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['lines'] as $lineData) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineData['account_id'],
                    'project_id' => $lineData['project_id'] ?? null,
                    'debit' => $lineData['debit'] ?? 0.00,
                    'credit' => $lineData['credit'] ?? 0.00,
                    'description' => $lineData['description'] ?? null,
                ]);
            }

            JournalEntry::lockAfterCreation($entry->id);

            AuditLog::create([
                'company_id' => $entry->company_id,
                'user_id' => Auth::id(),
                'action' => 'create_journal_entry',
                'auditable_type' => JournalEntry::class,
                'auditable_id' => $entry->id,
                'new_values' => [
                    'entry_number' => $entry->entry_number,
                    'total_amount' => $entry->total_amount,
                    'status' => 'posted',
                ],
            ]);
        });

        return redirect()->route('accounting.journals')->with('success', 'Balanced journal entry posted successfully.');
    }

    public function reverseJournal(Request $request, JournalEntry $journal): RedirectResponse
    {
        $this->authorizeAccounting();

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:5|max:1000',
        ]);

        if ($journal->status === 'reversed') {
            return back()->withErrors(['reversal_reason' => "Journal entry {$journal->entry_number} is already reversed and cannot be reversed again."]);
        }

        if ($journal->status !== 'posted') {
            return back()->withErrors(['reversal_reason' => "Only posted journal entries can be reversed."]);
        }

        // Subsystem operational integrity checks
        if ($journal->reference_type === 'subcontractor_bills' && $journal->reference_id) {
            return back()->withErrors(['reversal_reason' => "This journal entry originates from Subcontractor Bill #{$journal->reference_id}. Please use the Subcontractor Bill reversal workflow to properly synchronize work order quantities and measurement book entries."]);
        }

        if ($journal->reference_type === 'loan_emi_schedules' && $journal->reference_id) {
            return back()->withErrors(['reversal_reason' => "This journal entry originates from Equipment Loan EMI Schedule #{$journal->reference_id}. Please use the Equipment Loan reversal workflow to synchronize loan amortizations."]);
        }

        if ($journal->reference_type === 'asset_depreciation_entries' && $journal->reference_id) {
            return back()->withErrors(['reversal_reason' => "This journal entry originates from Fixed Asset Depreciation Entry #{$journal->reference_id}. Please use the Asset Depreciation reversal workflow."]);
        }

        $user = Auth::user();

        $counterEntry = DB::transaction(function () use ($journal, $validated, $user) {
            $lockedJournal = JournalEntry::where('id', $journal->id)->lockForUpdate()->firstOrFail();

            if ($lockedJournal->status === 'reversed') {
                throw new \DomainException("Journal entry {$lockedJournal->entry_number} is already reversed.");
            }

            // 1. Mark original journal entry as reversed
            $lockedJournal->update([
                'status' => 'reversed',
                'reversed_by' => $user->id,
                'reversed_at' => Carbon::now(),
                'reversal_reason' => $validated['reversal_reason'],
            ]);

            // 2. Create counter-journal entry
            $counterEntry = JournalEntry::create([
                'company_id' => $lockedJournal->company_id,
                'entry_number' => 'JV-REV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => Carbon::today(),
                'reference_type' => 'journal_entries_reversal',
                'reference_id' => $lockedJournal->id,
                'narration' => "REVERSAL of {$lockedJournal->entry_number} - Reason: " . $validated['reversal_reason'],
                'total_amount' => $lockedJournal->total_amount,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // 3. Insert inverted debit/credit lines
            foreach ($lockedJournal->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $counterEntry->id,
                    'account_id' => $line->account_id,
                    'project_id' => $line->project_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => "Reversal of Line #{$line->id}: " . ($line->description ?? ''),
                ]);
            }

            JournalEntry::lockAfterCreation($counterEntry->id);

            // 4. Traceable Audit Log
            AuditLog::create([
                'company_id' => $lockedJournal->company_id,
                'user_id' => $user->id,
                'action' => 'reverse_journal_entry',
                'auditable_type' => JournalEntry::class,
                'auditable_id' => $lockedJournal->id,
                'old_values' => [
                    'entry_number' => $lockedJournal->entry_number,
                    'status' => 'posted',
                ],
                'new_values' => [
                    'entry_number' => $lockedJournal->entry_number,
                    'status' => 'reversed',
                    'reversal_entry_number' => $counterEntry->entry_number,
                    'reversal_entry_id' => $counterEntry->id,
                    'reversal_reason' => $validated['reversal_reason'],
                ],
            ]);

            return $counterEntry;
        });

        return redirect()->route('accounting.journals')->with('success', "Journal entry {$journal->entry_number} reversed successfully with counter-entry {$counterEntry->entry_number}.");
    }

    public function trialBalance(): View
    {
        $this->authorizeAccounting();

        $accounts = Account::with(['journalLines'])->orderBy('code')->get();

        $rows = [];
        $totalDebits = 0.00;
        $totalCredits = 0.00;

        foreach ($accounts as $acc) {
            $sumDebit = (float) $acc->journalLines->sum('debit');
            $sumCredit = (float) $acc->journalLines->sum('credit');

            if ($sumDebit > 0 || $sumCredit > 0) {
                $rows[] = [
                    'account' => $acc,
                    'debit' => $sumDebit,
                    'credit' => $sumCredit,
                ];
                $totalDebits += $sumDebit;
                $totalCredits += $sumCredit;
            }
        }

        return view('accounting.trial-balance', compact('rows', 'totalDebits', 'totalCredits'));
    }

    public function profitAndLoss(Request $request): View
    {
        $this->authorizeAccounting();

        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfYear()->toDateString());

        // Fetch revenues (credit balance)
        $revenueAccounts = Account::where('account_type', 'revenue')
            ->with(['journalLines' => function ($q) use ($startDate, $endDate) {
                $q->whereHas('journalEntry', function ($je) use ($startDate, $endDate) {
                    $je->where('status', 'posted')->whereBetween('entry_date', [$startDate, $endDate]);
                });
            }])
            ->orderBy('code')
            ->get();

        $revenueRows = [];
        $totalRevenue = 0.00;
        foreach ($revenueAccounts as $acc) {
            $net = (float) $acc->journalLines->sum('credit') - (float) $acc->journalLines->sum('debit');
            if ($net != 0) {
                $revenueRows[] = ['account' => $acc, 'amount' => $net];
                $totalRevenue += $net;
            }
        }

        // Fetch direct and indirect expenses (debit balance)
        $expenseAccounts = Account::where('account_type', 'expense')
            ->with(['journalLines' => function ($q) use ($startDate, $endDate) {
                $q->whereHas('journalEntry', function ($je) use ($startDate, $endDate) {
                    $je->where('status', 'posted')->whereBetween('entry_date', [$startDate, $endDate]);
                });
            }])
            ->orderBy('code')
            ->get();

        $expenseRows = [];
        $totalExpenses = 0.00;
        foreach ($expenseAccounts as $acc) {
            $net = (float) $acc->journalLines->sum('debit') - (float) $acc->journalLines->sum('credit');
            if ($net != 0) {
                $expenseRows[] = ['account' => $acc, 'amount' => $net];
                $totalExpenses += $net;
            }
        }

        $netProfit = $totalRevenue - $totalExpenses;

        return view('accounting.profit-loss', compact(
            'revenueRows',
            'expenseRows',
            'totalRevenue',
            'totalExpenses',
            'netProfit',
            'startDate',
            'endDate'
        ));
    }

    public function balanceSheet(Request $request): View
    {
        $this->authorizeAccounting();

        $asOfDate = $request->input('as_of_date', Carbon::today()->toDateString());

        // Assets (debit balance)
        $assetAccounts = Account::where('account_type', 'asset')
            ->with(['journalLines' => function ($q) use ($asOfDate) {
                $q->whereHas('journalEntry', function ($je) use ($asOfDate) {
                    $je->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate);
                });
            }])
            ->orderBy('code')
            ->get();

        $assetRows = [];
        $totalAssets = 0.00;
        foreach ($assetAccounts as $acc) {
            $net = (float) $acc->journalLines->sum('debit') - (float) $acc->journalLines->sum('credit');
            if ($net != 0) {
                $assetRows[] = ['account' => $acc, 'amount' => $net];
                $totalAssets += $net;
            }
        }

        // Liabilities (credit balance)
        $liabilityAccounts = Account::where('account_type', 'liability')
            ->with(['journalLines' => function ($q) use ($asOfDate) {
                $q->whereHas('journalEntry', function ($je) use ($asOfDate) {
                    $je->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate);
                });
            }])
            ->orderBy('code')
            ->get();

        $liabilityRows = [];
        $totalLiabilities = 0.00;
        foreach ($liabilityAccounts as $acc) {
            $net = (float) $acc->journalLines->sum('credit') - (float) $acc->journalLines->sum('debit');
            if ($net != 0) {
                $liabilityRows[] = ['account' => $acc, 'amount' => $net];
                $totalLiabilities += $net;
            }
        }

        // Equity accounts
        $equityAccounts = Account::where('account_type', 'equity')
            ->with(['journalLines' => function ($q) use ($asOfDate) {
                $q->whereHas('journalEntry', function ($je) use ($asOfDate) {
                    $je->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate);
                });
            }])
            ->orderBy('code')
            ->get();

        $equityRows = [];
        $totalEquity = 0.00;
        foreach ($equityAccounts as $acc) {
            $net = (float) $acc->journalLines->sum('credit') - (float) $acc->journalLines->sum('debit');
            if ($net != 0) {
                $equityRows[] = ['account' => $acc, 'amount' => $net];
                $totalEquity += $net;
            }
        }

        // Calculate Retained Earnings from cumulative P&L
        $allRev = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('account_type', 'revenue'))
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate))
            ->sum(DB::raw('credit - debit'));

        $allExp = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('account_type', 'expense'))
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate))
            ->sum(DB::raw('debit - credit'));

        $cumulativeRetainedEarnings = $allRev - $allExp;
        $totalEquityWithRetained = $totalEquity + $cumulativeRetainedEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithRetained;

        return view('accounting.balance-sheet', compact(
            'assetRows',
            'liabilityRows',
            'equityRows',
            'totalAssets',
            'totalLiabilities',
            'totalEquity',
            'cumulativeRetainedEarnings',
            'totalEquityWithRetained',
            'totalLiabilitiesAndEquity',
            'asOfDate'
        ));
    }
}
