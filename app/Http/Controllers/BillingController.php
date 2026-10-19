<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\InvoicePayment;
use App\Models\InvoicePaymentAllocation;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillingController extends Controller
{
    protected function getCompanyId(): int
    {
        return (int) (session('current_company_id')
            ?? Auth::user()?->currentCompany()?->id
            ?? Company::configuredCompanyId()
            ?? Company::first()?->id
            ?? 1);
    }

    protected function authorizeBilling(): void
    {
        $user = Auth::user();
        $roleSlug = $user?->currentRole()?->slug;
        if (! $user || (! $user->hasPermission('*') && ! $user->hasPermission('billing.*') && ! $user->hasPermission('finance.*') && ! in_array($roleSlug, ['owner', 'admin', 'accountant']))) {
            abort(403, 'Unauthorized access to client billing, contracts, and revenue receipts.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeBilling();
        $companyId = $this->getCompanyId();

        $invoices = ClientInvoice::where('company_id', $companyId)
            ->with(['client', 'project', 'payments'])
            ->latest('issue_date')
            ->paginate(10);

        $totalReceivables = ClientInvoice::where('company_id', $companyId)
            ->whereIn('status', ['submitted', 'approved', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as total')
            ->value('total') ?? 0;

        $stats = [
            'total_invoices' => ClientInvoice::where('company_id', $companyId)->count(),
            'receivables' => $totalReceivables,
            'collected' => ClientInvoice::where('company_id', $companyId)->sum('paid_amount'),
        ];

        return view('billing.index', compact('invoices', 'stats'));
    }

    public function createInvoice(): View
    {
        $this->authorizeBilling();
        $companyId = $this->getCompanyId();

        $clients = Client::where('company_id', $companyId)->orderBy('name')->get();
        $projects = Project::where('company_id', $companyId)->where('status', '!=', 'cancelled')->orderBy('name')->get();

        return view('billing.create', compact('clients', 'projects'));
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $this->authorizeBilling();
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'required|exists:projects,id',
            'bill_type' => 'required|in:progress_ra,milestone,final_bill',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'subtotal' => 'required|numeric|min:1',
            'tax_rate' => 'nullable|numeric|between:0,100',
            'retention_rate_percent' => 'nullable|numeric|between:0,20',
            'notes' => 'nullable|string',
        ]);

        $subtotal = round((float) $validated['subtotal'], 2);
        $taxRate = $request->filled('tax_rate') ? (float) $request->input('tax_rate') : 18.00;
        $halfTaxRate = $taxRate / 2;
        $cgst = round($subtotal * ($halfTaxRate / 100), 2);
        $sgst = round($subtotal * ($halfTaxRate / 100), 2);

        $retentionRate = (float) ($validated['retention_rate_percent'] ?? 5.00);
        $retention = round($subtotal * ($retentionRate / 100), 2);
        $total = ($subtotal + $cgst + $sgst) - $retention;

        $invoice = ClientInvoice::create([
            'company_id' => $companyId,
            'client_id' => $validated['client_id'],
            'project_id' => $validated['project_id'],
            'invoice_number' => 'INV-RA-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'bill_type' => $validated['bill_type'],
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'subtotal' => $subtotal,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'retention_rate_percent' => $retentionRate,
            'retention_amount' => $retention,
            'total_amount' => $total,
            'paid_amount' => 0.00,
            'status' => 'submitted',
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
            'approved_by' => null,
            'approved_at' => null,
        ]);

        if ($invoice->project?->manager_id) {
            AppNotification::create([
                'company_id' => $invoice->project->company_id,
                'user_id' => $invoice->project->manager_id,
                'title' => 'Client RA Invoice Generated',
                'message' => "RA Bill {$invoice->invoice_number} generated for project {$invoice->project->name}.",
                'type' => 'info',
                'action_url' => route('billing.show', $invoice),
                'is_read' => false,
            ]);
        }

        return redirect()->route('billing.show', $invoice)->with('success', "RA Bill {$invoice->invoice_number} generated and submitted for approval.");
    }

    public function approveInvoice(Request $request, ClientInvoice $invoice): JsonResponse|RedirectResponse
    {
        $this->authorizeBilling();

        if ($invoice->status !== 'submitted') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only submitted invoices can be approved.'], 422);
            }
            return back()->withErrors(['status' => 'Only submitted invoices can be approved.']);
        }

        DB::transaction(function () use ($invoice) {
            $lockedInvoice = ClientInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            $lockedInvoice->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => Carbon::now(),
            ]);

            // Post GL Accrual Journal Entry:
            // Dr 1020 Accounts Receivable (net total_amount)
            // Dr 1025 Retention Money Withheld (retention_amount)
            // Cr 4010 Civil Contracting Revenue (total_amount + retention_amount)
            $companyId = $lockedInvoice->company_id ?? $this->getCompanyId();
            $arAccount = Account::where('company_id', $companyId)->where('code', '1020')->first();
            $retentionAccount = Account::where('company_id', $companyId)->where('code', '1025')->first();
            $revenueAccount = Account::where('company_id', $companyId)->where('code', '4010')->first();

            $grossRevenue = round((float) $lockedInvoice->total_amount + (float) $lockedInvoice->retention_amount, 2);

            if ($arAccount && $revenueAccount && $grossRevenue > 0) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => $lockedInvoice->issue_date->toDateString(),
                    'narration' => "Revenue recognized for Client RA Bill {$lockedInvoice->invoice_number}",
                    'total_amount' => $grossRevenue,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit Accounts Receivable
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $arAccount->id,
                    'debit' => $lockedInvoice->total_amount,
                    'credit' => 0.00,
                    'description' => "Receivable from client for RA bill {$lockedInvoice->invoice_number}",
                ]);

                // Debit Retention Money if retention withheld
                if ($retentionAccount && (float) $lockedInvoice->retention_amount > 0) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $retentionAccount->id,
                        'debit' => $lockedInvoice->retention_amount,
                        'credit' => 0.00,
                        'description' => "Retention withheld for RA bill {$lockedInvoice->invoice_number}",
                    ]);
                }

                // Credit Contracting Revenue
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $revenueAccount->id,
                    'debit' => 0.00,
                    'credit' => $grossRevenue,
                    'description' => "Contracting revenue recognized on bill {$lockedInvoice->invoice_number}",
                ]);

                JournalEntry::lockAfterCreation($journalEntry->id);

                AuditLog::create([
                    'company_id' => $companyId,
                    'user_id' => Auth::id(),
                    'action' => 'client_invoice_revenue_posted',
                    'auditable_type' => ClientInvoice::class,
                    'auditable_id' => $lockedInvoice->id,
                    'new_values' => [
                        'invoice_number' => $lockedInvoice->invoice_number,
                        'journal_entry_id' => $journalEntry->id,
                        'total_receivable' => $lockedInvoice->total_amount,
                        'retention' => $lockedInvoice->retention_amount,
                    ],
                ]);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Client RA Bill {$invoice->invoice_number} approved successfully.",
                'status' => 'approved',
            ]);
        }

        return back()->with('success', "Client RA Bill {$invoice->invoice_number} approved successfully.");
    }

    public function show(ClientInvoice $invoice): View
    {
        $this->authorizeBilling();

        $invoice->load(['client', 'project', 'creator', 'payments.recorder']);

        return view('billing.show', compact('invoice'));
    }

    public function recordPayment(Request $request, ClientInvoice $invoice): RedirectResponse
    {
        $this->authorizeBilling();

        $maxDue = $invoice->balanceDue();

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => "required|numeric|min:0.01|max:{$maxDue}",
            'payment_mode' => 'required|in:bank_transfer,cheque,rtgs_neft,cash',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $invoice) {
            $lockedInvoice = ClientInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $freshBalanceDue = $lockedInvoice->balanceDue();

            if ($validated['amount'] > $freshBalanceDue + 0.001) {
                return back()->withInput()->withErrors([
                    'amount' => "Payment amount exceeds current balance due of ₹" . number_format($freshBalanceDue, 2),
                ]);
            }

            $companyId = $lockedInvoice->company_id ?? $this->getCompanyId();

            $payment = InvoicePayment::create([
                'company_id' => $companyId,
                'client_invoice_id' => $lockedInvoice->id,
                'receipt_number' => 'RCP-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_mode' => $validated['payment_mode'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            InvoicePaymentAllocation::create([
                'company_id' => $companyId,
                'invoice_payment_id' => $payment->id,
                'client_invoice_id' => $lockedInvoice->id,
                'amount' => $validated['amount'],
                'notes' => 'Direct payment allocation for RA bill ' . $lockedInvoice->invoice_number,
            ]);

            $newPaid = round((float) $lockedInvoice->paid_amount + (float) $validated['amount'], 2);
            $newStatus = ($newPaid >= (float) $lockedInvoice->total_amount - 0.001) ? 'paid' : 'partially_paid';

            $lockedInvoice->update([
                'paid_amount' => $newPaid,
                'status' => $newStatus,
            ]);

            // Post GL Payment Entry: Dr 1010 Bank Account, Cr 1020 Accounts Receivable
            $bankAccount = Account::where('company_id', $companyId)->where('code', '1010')->first();
            $arAccount = Account::where('company_id', $companyId)->where('code', '1020')->first();

            if ($bankAccount && $arAccount && (float) $validated['amount'] > 0) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => $validated['payment_date'],
                    'narration' => "Receipt collected for RA bill {$lockedInvoice->invoice_number} via {$validated['payment_mode']}",
                    'total_amount' => $validated['amount'],
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit Bank Account (asset increase)
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $bankAccount->id,
                    'debit' => $validated['amount'],
                    'credit' => 0.00,
                    'description' => "Client remittance for {$lockedInvoice->invoice_number}",
                ]);

                // Credit Accounts Receivable (asset decrease)
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $arAccount->id,
                    'debit' => 0.00,
                    'credit' => $validated['amount'],
                    'description' => "Reduction of client receivable for {$lockedInvoice->invoice_number}",
                ]);

                JournalEntry::lockAfterCreation($journalEntry->id);
            }

            if ($lockedInvoice->created_by) {
                AppNotification::create([
                    'company_id' => $lockedInvoice->project?->company_id ?? Auth::user()->currentCompany()?->id,
                    'user_id' => $lockedInvoice->created_by,
                    'title' => 'Client Payment Recorded',
                    'message' => "Payment recorded for RA Bill {$lockedInvoice->invoice_number}.",
                    'type' => 'success',
                    'action_url' => route('billing.show', $lockedInvoice),
                    'is_read' => false,
                ]);
            }

            return redirect()->route('billing.show', $lockedInvoice)->with('success', 'Client payment receipt recorded and balance updated.');
        });
    }

    public function allocateReceipt(Request $request, InvoicePayment $payment): RedirectResponse|JsonResponse
    {
        $this->authorizeBilling();

        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => 'required|exists:client_invoices,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($validated, $payment, $request) {
            $lockedPayment = InvoicePayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $unallocated = $lockedPayment->unallocatedBalance();

            $totalRequestedAllocation = array_sum(array_column($validated['allocations'], 'amount'));
            if ($totalRequestedAllocation > $unallocated + 0.001) {
                $msg = 'Total allocation amount (₹' . number_format($totalRequestedAllocation, 2) . ') exceeds available unallocated receipt balance of ₹' . number_format($unallocated, 2);
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return back()->withInput()->withErrors(['allocations' => $msg]);
            }

            foreach ($validated['allocations'] as $alloc) {
                $lockedInvoice = ClientInvoice::where('id', $alloc['invoice_id'])->lockForUpdate()->firstOrFail();
                $due = $lockedInvoice->balanceDue();

                if ($alloc['amount'] > $due + 0.001) {
                    $msg = "Allocation of ₹{$alloc['amount']} exceeds balance due of ₹{$due} for invoice {$lockedInvoice->invoice_number}";
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }
                    return back()->withInput()->withErrors(['allocations' => $msg]);
                }

                InvoicePaymentAllocation::create([
                    'company_id' => $lockedPayment->company_id,
                    'invoice_payment_id' => $lockedPayment->id,
                    'client_invoice_id' => $lockedInvoice->id,
                    'amount' => $alloc['amount'],
                    'notes' => 'Allocated from receipt ' . $lockedPayment->receipt_number,
                ]);

                $newPaid = round((float) $lockedInvoice->paid_amount + (float) $alloc['amount'], 2);
                $newStatus = ($newPaid >= (float) $lockedInvoice->total_amount - 0.001) ? 'paid' : 'partially_paid';

                $lockedInvoice->update([
                    'paid_amount' => $newPaid,
                    'status' => $newStatus,
                ]);
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Receipt allocated successfully across selected client invoices.']);
            }

            return back()->with('success', 'Receipt allocated successfully across selected client invoices.');
        });
    }
}
