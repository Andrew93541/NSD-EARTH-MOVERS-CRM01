@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $invoice->invoice_number }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $invoice->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }} uppercase">
                    {{ $invoice->status }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Client: {{ $invoice->client->name ?? '—' }} • Project: {{ $invoice->project->name ?? '—' }}</p>
        </div>
        <a href="{{ route('billing.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Billing</a>
    </div>

    <!-- Invoice Sheet -->
    <div class="soft-card p-6 space-y-6">
        <div class="grid grid-cols-2 gap-4 pb-4 border-b border-gray-100 text-xs">
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Client Organization</span>
                <strong class="text-gray-900 text-sm block mt-1">{{ $invoice->client->name }}</strong>
                <span class="text-gray-500 block">GSTIN: {{ $invoice->client->gstin ?? 'N/A' }}</span>
                <span class="text-gray-500 block">{{ $invoice->client->billing_address ?? 'Bengaluru' }}</span>
            </div>
            <div class="text-right">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Bill Parameters</span>
                <span class="text-gray-700 block mt-1">Issue Date: <strong>{{ $invoice->issue_date->format('d M, Y') }}</strong></span>
                <span class="text-gray-700 block">Due Date: <strong>{{ $invoice->due_date->format('d M, Y') }}</strong></span>
                <span class="text-gray-700 block">Type: <strong>{{ strtoupper(str_replace('_', ' ', $invoice->bill_type)) }}</strong></span>
            </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="space-y-2 text-xs">
            <div class="flex justify-between py-2 border-b border-gray-50 text-gray-600">
                <span>Certified Gross Work Done:</span>
                <span class="font-mono font-bold text-gray-900">₹{{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-50 text-gray-600">
                <span>Central GST (CGST 9%):</span>
                <span class="font-mono font-bold text-gray-900">₹{{ number_format($invoice->cgst_amount, 2) }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-50 text-gray-600">
                <span>State GST (SGST 9%):</span>
                <span class="font-mono font-bold text-gray-900">₹{{ number_format($invoice->sgst_amount, 2) }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-50 text-red-600">
                <span>Retention Deducted ({{ $invoice->retention_rate_percent }}%):</span>
                <span class="font-mono font-bold">- ₹{{ number_format($invoice->retention_amount, 2) }}</span>
            </div>
            <div class="flex justify-between py-3 border-t-2 border-gray-200 text-base font-extrabold text-gray-900">
                <span>Net Payable Amount:</span>
                <span class="font-mono text-blue-600">₹{{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            <div class="flex justify-between py-1 text-xs text-gray-500">
                <span>Amount Realized:</span>
                <span class="font-mono text-emerald-600 font-bold">₹{{ number_format($invoice->paid_amount, 2) }}</span>
            </div>
            <div class="flex justify-between py-1 text-sm font-bold text-red-600">
                <span>Remaining Balance Due:</span>
                <span class="font-mono">₹{{ number_format($invoice->balanceDue(), 2) }}</span>
            </div>
        </div>

        <!-- Payment Collection Action -->
        @if ($invoice->balanceDue() > 0)
            <div class="mt-6 pt-5 border-t border-gray-100">
                <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Record Client Remittance</h4>
                <form method="POST" action="{{ route('billing.payments.store', $invoice) }}" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                           class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>

                    <input type="number" step="0.01" min="1" max="{{ $invoice->balanceDue() }}" name="amount" value="{{ $invoice->balanceDue() }}" required
                           class="w-36 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono font-bold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>

                    <select name="payment_mode" required class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="bank_transfer">RTGS / NEFT</option>
                        <option value="cheque">Account Payee Cheque</option>
                        <option value="rtgs_neft">Direct Bank Remittance</option>
                    </select>

                    <input type="text" name="reference_number" placeholder="Bank UTR No. / Cheque No."
                           class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none flex-1 min-w-[160px]"/>

                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        Confirm Receipt
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Client RA Bill Supporting Documents & Measurement Sheets -->
    <x-document-manager 
        :record="$invoice" 
        type="client_invoice" 
        title="Joint Measurement Sheets & Certified RA Bill Evidence"
        :categories="[
            'measurement_sheet' => 'Joint Measurement Sheet (JMR)',
            'completion_certificate' => 'Site Engineer Milestone Certification',
            'supplier_invoice' => 'Signed & Stamped Client Invoice Copy',
            'voucher_support' => 'Client Bank Remittance / UTR Advice',
            'general' => 'General Billing Attachment',
        ]"
    />
</div>
@endsection
