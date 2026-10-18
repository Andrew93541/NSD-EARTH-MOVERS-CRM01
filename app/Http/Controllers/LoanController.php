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

        $loans = Loan::withCount(['emiSchedules as pending_emis_count' => function ($q) {
            $q->where('status', 'pending');
        }])->latest()->paginate(10);

        $totalOutstanding = Loan::where('status', 'active')->sum('outstanding_principal');
        $monthlyCommitment = Loan::where('status', 'active')->sum('monthly_emi');

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

        $loan->load('emiSchedules');

        return view('loans.show', compact('loan'));
    }

    public function recordEmiPayment(Request $request, LoanEmiSchedule $schedule): RedirectResponse
    {
        $this->authorizeLoans();

        $validated = $request->validate([
            'payment_ref' => 'required|string|max:100',
            'paid_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($validated, $schedule) {
            $lockedSchedule = LoanEmiSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

            if ($lockedSchedule->status === 'paid') {
                return back()->withErrors(['status' => "Installment #{$lockedSchedule->installment_number} has already been paid and cannot be double-processed."]);
            }

            $lockedSchedule->update([
                'status' => 'paid',
                'paid_date' => $validated['paid_date'],
                'payment_ref' => $validated['payment_ref'],
            ]);

            $loan = Loan::where('id', $lockedSchedule->loan_id)->lockForUpdate()->firstOrFail();
            $newOutstanding = max(0, round((float) $loan->outstanding_principal - (float) $lockedSchedule->principal_component, 2));
            $loan->update(['outstanding_principal' => $newOutstanding]);

            return back()->with('success', "Installment #{$lockedSchedule->installment_number} marked as paid.");
        });
    }
}
