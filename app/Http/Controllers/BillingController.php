<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = ClientInvoice::with(['client', 'project', 'payments'])
            ->latest('issue_date')
            ->paginate(10);

        $totalReceivables = ClientInvoice::whereIn('status', ['submitted', 'approved', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as total')
            ->value('total') ?? 0;

        $stats = [
            'total_invoices' => ClientInvoice::count(),
            'receivables' => $totalReceivables,
            'collected' => ClientInvoice::sum('paid_amount'),
        ];

        return view('billing.index', compact('invoices', 'stats'));
    }

    public function createInvoice(): View
    {
        $clients = Client::orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('billing.create', compact('clients', 'projects'));
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'required|exists:projects,id',
            'bill_type' => 'required|in:progress_ra,milestone,final_bill',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'subtotal' => 'required|numeric|min:1',
            'retention_rate_percent' => 'nullable|numeric|between:0,20',
            'notes' => 'nullable|string',
        ]);

        $subtotal = (float) $validated['subtotal'];
        $cgst = $subtotal * 0.09;
        $sgst = $subtotal * 0.09;
        $retentionRate = (float) ($validated['retention_rate_percent'] ?? 5.00);
        $retention = $subtotal * ($retentionRate / 100);
        $total = ($subtotal + $cgst + $sgst) - $retention;

        $invoice = ClientInvoice::create([
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
            'status' => 'approved',
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        return redirect()->route('billing.show', $invoice)->with('success', "RA Bill {$invoice->invoice_number} generated successfully.");
    }

    public function show(ClientInvoice $invoice): View
    {
        $invoice->load(['client', 'project', 'creator', 'payments.recorder']);

        return view('billing.show', compact('invoice'));
    }

    public function recordPayment(Request $request, ClientInvoice $invoice): RedirectResponse
    {
        $maxDue = $invoice->balanceDue();

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => "required|numeric|min:1|max:{$maxDue}",
            'payment_mode' => 'required|in:bank_transfer,cheque,rtgs_neft,cash',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        InvoicePayment::create([
            'client_invoice_id' => $invoice->id,
            'receipt_number' => 'RCP-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_mode' => $validated['payment_mode'],
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $newPaid = $invoice->paid_amount + $validated['amount'];
        $newStatus = ($newPaid >= $invoice->total_amount) ? 'paid' : 'partially_paid';

        $invoice->update([
            'paid_amount' => $newPaid,
            'status' => $newStatus,
        ]);

        return redirect()->route('billing.show', $invoice)->with('success', 'Client payment receipt recorded and balance updated.');
    }
}
