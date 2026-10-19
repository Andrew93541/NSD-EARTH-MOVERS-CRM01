<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    protected function getCompanyId(): int
    {
        return (int) (session('current_company_id')
            ?? Auth::user()?->currentCompany()?->id
            ?? Company::configuredCompanyId()
            ?? Company::first()?->id
            ?? 1);
    }

    public function index(Request $request): View
    {
        $companyId = $this->getCompanyId();

        $expenses = Expense::where('company_id', $companyId)
            ->with(['category', 'project', 'site', 'submitter'])
            ->latest('expense_date')
            ->paginate(15);

        $totalExpenses = Expense::where('company_id', $companyId)->where('status', 'approved')->sum('amount');
        $categories = ExpenseCategory::where('company_id', $companyId)->get();

        return view('expenses.index', compact('expenses', 'totalExpenses', 'categories'));
    }

    public function create(): View
    {
        $companyId = $this->getCompanyId();

        $categories = ExpenseCategory::where('company_id', $companyId)->orderBy('name')->get();
        $projects = Project::where('company_id', $companyId)->whereIn('status', ['on_track', 'at_risk', 'delayed'])->orderBy('name')->get();
        $sites = Site::where('company_id', $companyId)->orderBy('name')->get();

        return view('expenses.create', compact('categories', 'projects', 'sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'site_id' => 'nullable|exists:sites,id',
            'title' => 'required|string|max:255',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:cash,bank_transfer,company_card,upi',
            'receipt_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $isAdminOrFinance = $user && ($user->hasPermission('*') || $user->hasPermission('finance.*') || in_array($roleSlug, ['owner', 'admin', 'accountant']));

        $validated['company_id'] = $companyId;
        $validated['expense_number'] = 'EXP-' . date('Y') . '-' . strtoupper(Str::random(5));
        $validated['status'] = $isAdminOrFinance ? 'approved' : 'pending';
        $validated['submitted_by'] = Auth::id();
        $validated['approved_by'] = $isAdminOrFinance ? Auth::id() : null;
        $validated['approved_at'] = $isAdminOrFinance ? Carbon::now() : null;

        $expense = DB::transaction(function () use ($validated, $isAdminOrFinance, $companyId) {
            $exp = Expense::create($validated);

            if ($isAdminOrFinance && (float) $exp->amount > 0) {
                $expenseAccount = Account::where('company_id', $companyId)->where('code', '5050')->first();
                $bankAccount = Account::where('company_id', $companyId)->where('code', '1010')->first();

                if ($expenseAccount && $bankAccount) {
                    $journalEntry = JournalEntry::create([
                        'company_id' => $companyId,
                        'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                        'entry_date' => $exp->expense_date->toDateString(),
                        'narration' => "Operational expense: {$exp->title} ({$exp->expense_number})",
                        'total_amount' => $exp->amount,
                        'status' => 'posted',
                        'created_by' => Auth::id(),
                    ]);

                    // Debit Expense Account
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $expenseAccount->id,
                        'project_id' => $exp->project_id,
                        'debit' => $exp->amount,
                        'credit' => 0.00,
                        'description' => "Expense: {$exp->title}",
                    ]);

                    // Credit Bank Account
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $bankAccount->id,
                        'project_id' => $exp->project_id,
                        'debit' => 0.00,
                        'credit' => $exp->amount,
                        'description' => "Disbursement for {$exp->expense_number}",
                    ]);

                    JournalEntry::lockAfterCreation($journalEntry->id);
                }
            }

            return $exp;
        });

        $msg = $isAdminOrFinance
            ? "Operational expense {$expense->expense_number} approved and recorded."
            : "Operational expense {$expense->expense_number} submitted for authorized review.";

        return redirect()->route('expenses.index')->with('success', $msg);
    }

    public function approve(Request $request, Expense $expense): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        $isAdminOrFinance = $user && ($user->hasPermission('*') || $user->hasPermission('finance.*') || in_array($roleSlug, ['owner', 'admin', 'accountant']));

        if (! $isAdminOrFinance) {
            abort(403, 'Unauthorized to approve operational expenses.');
        }

        if ($expense->status !== 'pending') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only pending expenses can be approved.'], 422);
            }
            return back()->withErrors(['status' => 'Only pending expenses can be approved.']);
        }

        DB::transaction(function () use ($expense) {
            $lockedExpense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            $lockedExpense->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => Carbon::now(),
            ]);

            $companyId = $lockedExpense->company_id ?? $this->getCompanyId();
            $expenseAccount = Account::where('company_id', $companyId)->where('code', '5050')->first();
            $bankAccount = Account::where('company_id', $companyId)->where('code', '1010')->first();

            if ($expenseAccount && $bankAccount && (float) $lockedExpense->amount > 0) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => $lockedExpense->expense_date->toDateString(),
                    'narration' => "Approved operational expense: {$lockedExpense->title} ({$lockedExpense->expense_number})",
                    'total_amount' => $lockedExpense->amount,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit Expense Account
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $expenseAccount->id,
                    'project_id' => $lockedExpense->project_id,
                    'debit' => $lockedExpense->amount,
                    'credit' => 0.00,
                    'description' => "Approved expense: {$lockedExpense->title}",
                ]);

                // Credit Bank Account
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $bankAccount->id,
                    'project_id' => $lockedExpense->project_id,
                    'debit' => 0.00,
                    'credit' => $lockedExpense->amount,
                    'description' => "Disbursement for {$lockedExpense->expense_number}",
                ]);

                JournalEntry::lockAfterCreation($journalEntry->id);
            }

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'action' => 'expense_approved',
                'auditable_type' => Expense::class,
                'auditable_id' => $lockedExpense->id,
                'new_values' => [
                    'expense_number' => $lockedExpense->expense_number,
                    'amount' => $lockedExpense->amount,
                    'status' => 'approved',
                ],
            ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Expense {$expense->expense_number} approved successfully.",
                'status' => 'approved',
            ]);
        }

        return back()->with('success', "Expense {$expense->expense_number} approved successfully.");
    }
}
