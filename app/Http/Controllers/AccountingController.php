<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
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

        $entries = JournalEntry::with(['lines.account', 'creator'])
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
        });

        return redirect()->route('accounting.journals')->with('success', 'Balanced journal entry posted successfully.');
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
}
