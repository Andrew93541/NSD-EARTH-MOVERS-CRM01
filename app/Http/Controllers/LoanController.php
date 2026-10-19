<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanEmiSchedule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoanController extends Controller
{
    protected function getCompanyId(): int
    {
        return (int) (session('current_company_id')
            ?? Auth::user()?->currentCompany()?->id
            ?? \App\Models\Company::configuredCompanyId()
            ?? \App\Models\Company::first()?->id
            ?? 1);
    }

    protected function authorizeLoans(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('loans.*') && ! $user->hasPermission('finance.*') && ! in_array($roleSlug, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized access to equipment loans and debt financing.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeLoans();
        $companyId = $this->getCompanyId();

        $loans = Loan::where('company_id', $companyId)
            ->withCount(['emiSchedules as pending_emis_count' => function ($q) {
                $q->where('status', 'pending');
            }])->latest()->paginate(10);

        $totalOutstanding = Loan::where('company_id', $companyId)->where('status', 'active')->sum('outstanding_principal');
        $monthlyCommitment = Loan::where('company_id', $companyId)->where('status', 'active')->sum('monthly_emi');

        return view('loans.index', compact('loans', 'totalOutstanding', 'monthlyCommitment'));
    }

    public function create(): View
    {
        $this->authorizeLoans();

        return view('loans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeLoans();

        $validated = $request->validate([
            'loan_account_number' => 'required|string|max:50',
            'lender_name' => 'required|string|max:150',
            'loan_type' => 'required|in:equipment_finance,vehicle_loan,term_loan,working_capital',
            'asset_description' => 'required|string|max:255',
            'principal_amount' => 'required|numeric|min:1000',
            'annual_interest_rate' => 'required|numeric|between:1,40',
            'tenure_months' => 'required|integer|between:1,120',
            'start_date' => 'required|date',
        ]);

        $principal = (float) $validated['principal_amount'];
        $rate = (float) $validated['annual_interest_rate'] / 1200; // Monthly interest rate
        $n = (int) $validated['tenure_months'];

        // Standard Amortization formula: EMI = P * r * (1+r)^n / ((1+r)^n - 1)
        if ($rate > 0) {
            $emi = $principal * $rate * pow(1 + $rate, $n) / (pow(1 + $rate, $n) - 1);
        } else {
            $emi = $principal / $n;
        }

        $companyId = $this->getCompanyId();
        $validated['company_id'] = $companyId;
        $validated['monthly_emi'] = round($emi, 2);
        $validated['outstanding_principal'] = $principal;
        $validated['status'] = 'active';

        $loan = Loan::create($validated);

        // Generate full EMI schedule
        $balance = $principal;
        $startDate = Carbon::parse($validated['start_date']);

        for ($i = 1; $i <= $n; $i++) {
            $interestComponent = round($balance * $rate, 2);
            $principalComponent = round($emi - $interestComponent, 2);

            if ($i === $n || $principalComponent > $balance) {
                $principalComponent = $balance;
                $emi = $principalComponent + $interestComponent;
                $balance = 0;
            } else {
                $balance -= $principalComponent;
            }

            LoanEmiSchedule::create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => (clone $startDate)->addMonths($i),
                'principal_component' => $principalComponent,
                'interest_component' => $interestComponent,
                'total_emi' => round($emi, 2),
                'remaining_balance' => max(0, round($balance, 2)),
                'status' => 'pending',
            ]);
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Equipment finance facility setup and amortized EMI schedule generated.');
    }

    public function show(Loan $loan): View
    {
        $this->authorizeLoans();
        $companyId = $this->getCompanyId();

        if ($loan->company_id !== $companyId) {
            abort(404, 'Equipment loan not found in current company workspace.');
        }

        $loan->load(['emiSchedules.journalEntry', 'fixedAsset', 'liabilityAccount', 'interestAccount']);
        $bankAccounts = \App\Models\BankAccount::where('company_id', $companyId)->where('is_active', true)->get();

        return view('loans.show', compact('loan', 'bankAccounts'));
    }

    public function recordEmiPayment(Request $request, LoanEmiSchedule $schedule): RedirectResponse
    {
        $this->authorizeLoans();

        $validated = $request->validate([
            'payment_ref' => 'nullable|string|max:100',
            'paid_date' => 'nullable|date',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        try {
            $user = Auth::user();
            $journalEntry = \App\Services\EquipmentAccountingService::postEmiPayment($schedule, $validated, $user);

            return back()->with('success', "Installment #{$schedule->installment_number} marked as paid and Journal Entry {$journalEntry->entry_number} posted successfully.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function reverseEmiPayment(Request $request, LoanEmiSchedule $schedule): RedirectResponse
    {
        $this->authorizeLoans();

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:5|max:255',
        ]);

        try {
            $user = Auth::user();
            $reversalJournal = \App\Services\EquipmentAccountingService::reverseEmiPayment($schedule, $validated['reversal_reason'], $user);

            return back()->with('success', "Installment #{$schedule->installment_number} payment reversed. Counter-journal {$reversalJournal->entry_number} posted.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withErrors(['reversal' => $e->getMessage()]);
        }
    }
}
