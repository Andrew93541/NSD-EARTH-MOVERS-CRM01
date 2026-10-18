@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('subcontractor-bills.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold">← Subcontractor RA Bills</a>
                <span class="text-gray-400">/</span>
                <span class="font-mono text-gray-500">{{ $subcontractorBill->bill_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">Subcontractor RA Bill #{{ $subcontractorBill->bill_number }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $subcontractorBill->status_badge }}">
                    {{ ucfirst(str_replace('_', ' ', $subcontractorBill->status)) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Subcontractor: <a href="{{ route('subcontractors.show', $subcontractorBill->subcontractor_id) }}" class="text-blue-600 font-semibold hover:underline">{{ $subcontractorBill->subcontractor->name }}</a> |
                Work Order: <a href="{{ route('work-orders.show', $subcontractorBill->work_order_id) }}" class="text-blue-600 font-semibold hover:underline">{{ $subcontractorBill->workOrder->wo_number }}</a> |
                Project: <span class="font-medium text-gray-800">{{ $subcontractorBill->project->name }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(in_array($subcontractorBill->status, ['draft', 'submitted']))
                <form method="POST" action="{{ route('subcontractor-bills.certify', $subcontractorBill) }}" onsubmit="return confirm('Certify Subcontractor RA Bill #{{ $subcontractorBill->bill_number }}?\n\nThis will post balanced double-entry General Ledger journals (Dr 5040, Dr 1030 / Cr 2030, Cr 2040, Cr 2020) and update work order commitments.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Certify Bill & Post Ledger</span>
                    </button>
                </form>
            @endif

            @if(in_array($subcontractorBill->status, ['certified', 'approved_for_payment', 'partially_paid']))
                <button type="button" onclick="document.getElementById('paymentModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Record Payment Remittance</span>
                </button>

                @if($subcontractorBill->paid_amount == 0)
                    <button type="button" onclick="document.getElementById('reverseModal').classList.remove('hidden')"
                        class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-semibold transition">
                        Reverse Bill
                    </button>
                @endif
            @endif
        </div>
    </div>

    <!-- Financial Breakdown KPIs -->
    @php
        $cols = 5;
        if ($subcontractorBill->advance_recovery_amount > 0) $cols++;
        if ($subcontractorBill->other_deductions_amount > 0) $cols++;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ $cols }} gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase">Gross Work Certified</span>
            <div class="text-xl font-black text-gray-900 mt-1 font-mono">₹{{ number_format($subcontractorBill->gross_work_value, 2) }}</div>
            <span class="text-[11px] text-gray-400">Dr Cost Account 5040</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-emerald-600 uppercase">GST Input Tax Credit</span>
            <div class="text-xl font-black text-emerald-600 mt-1 font-mono">+₹{{ number_format($subcontractorBill->gst_amount, 2) }}</div>
            <span class="text-[11px] text-gray-400">Dr Asset 1030 ({{ $subcontractorBill->gst_rate_percent }}%)</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-amber-600 uppercase">Retention Withheld</span>
            <div class="text-xl font-black text-amber-600 mt-1 font-mono">-₹{{ number_format($subcontractorBill->retention_amount, 2) }}</div>
            <span class="text-[11px] text-gray-400">Cr Liability 2030 ({{ $subcontractorBill->retention_rate_percent }}%)</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-rose-600 uppercase">TDS Deducted u/s 194C</span>
            <div class="text-xl font-black text-rose-600 mt-1 font-mono">-₹{{ number_format($subcontractorBill->tds_amount, 2) }}</div>
            <span class="text-[11px] text-gray-400">Cr Liability 2040 ({{ $subcontractorBill->tds_rate_percent }}%)</span>
        </div>
        @if($subcontractorBill->advance_recovery_amount > 0)
            <div class="soft-card p-4 border-l-2 border-l-purple-500">
                <span class="text-[10px] font-bold text-purple-600 uppercase">Advance Recovery</span>
                <div class="text-xl font-black text-purple-700 mt-1 font-mono">-₹{{ number_format($subcontractorBill->advance_recovery_amount, 2) }}</div>
                <span class="text-[11px] text-gray-400">Cr Asset 1040 Recoupment</span>
            </div>
        @endif
        @if($subcontractorBill->other_deductions_amount > 0)
            <div class="soft-card p-4 border-l-2 border-l-orange-500">
                <span class="text-[10px] font-bold text-orange-600 uppercase">Other Deductions</span>
                <div class="text-xl font-black text-orange-700 mt-1 font-mono">-₹{{ number_format($subcontractorBill->other_deductions_amount, 2) }}</div>
                <span class="text-[11px] text-gray-400">Cr Asset 1050 Recovery</span>
            </div>
        @endif
        <div class="soft-card p-4 border-l-2 border-l-blue-600">
            <span class="text-[10px] font-bold text-blue-600 uppercase">Net Amount Payable</span>
            <div class="text-xl font-black text-blue-700 mt-1 font-mono">₹{{ number_format($subcontractorBill->net_payable_amount, 2) }}</div>
            <span class="text-[11px] text-gray-400">Cr Subcon Payable 2020</span>
        </div>
    </div>

    <!-- Comprehensive Financial Deductions & Net Payable Reconciliation Card -->
    <div class="soft-card p-6 space-y-4 border-l-4 border-l-indigo-600">
        <div class="border-b border-gray-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Certified Bill Financial Breakdown & Deductions Audit</h3>
                <p class="text-[11px] text-gray-500">Authoritative arithmetic reconciliation deriving Net Amount Payable and General Ledger allocations.</p>
            </div>
            <span class="text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-3 py-1 rounded-full font-mono">
                Gross + GST − Retention − TDS − Advance − Other = Net Payable
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <div class="space-y-2 text-xs">
                <div class="flex justify-between items-center py-1.5 border-b border-gray-50">
                    <span class="text-gray-700 font-medium">Gross Certified Work Value (Dr 5040):</span>
                    <span class="font-mono font-bold text-gray-900 text-sm">₹{{ number_format($subcontractorBill->gross_work_value, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5 border-b border-gray-50 text-emerald-700">
                    <span class="font-medium">(+) GST Input Tax Credit (Dr 1030, {{ $subcontractorBill->gst_rate_percent }}%):</span>
                    <span class="font-mono font-bold">+₹{{ number_format($subcontractorBill->gst_amount, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5 bg-gray-50 px-2 rounded-lg font-semibold text-gray-800">
                    <span>Subtotal Value with Taxes:</span>
                    <span class="font-mono font-bold">₹{{ number_format($subcontractorBill->gross_work_value + $subcontractorBill->gst_amount, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5 border-b border-gray-50 text-amber-700">
                    <span class="font-medium">(-) Contractual Retention Withheld (Cr 2030, {{ $subcontractorBill->retention_rate_percent }}%):</span>
                    <span class="font-mono font-bold">-₹{{ number_format($subcontractorBill->retention_amount, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5 border-b border-gray-50 text-rose-700">
                    <span class="font-medium">(-) Statutory TDS Deducted u/s 194C (Cr 2040, {{ $subcontractorBill->tds_rate_percent }}%):</span>
                    <span class="font-mono font-bold">-₹{{ number_format($subcontractorBill->tds_amount, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5 border-b border-gray-50 text-purple-700">
                    <span class="font-medium">(-) Mobilization Advance Recovery (Cr 1040):</span>
                    <span class="font-mono font-bold">-₹{{ number_format($subcontractorBill->advance_recovery_amount, 2) }}</span>
                </div>
                @if($subcontractorBill->other_deductions_amount > 0)
                    <div class="flex justify-between items-center py-1.5 border-b border-gray-50 text-orange-700">
                        <span class="font-medium">(-) Other Authorized Contract Deductions (Cr 1050):</span>
                        <span class="font-mono font-bold">-₹{{ number_format($subcontractorBill->other_deductions_amount, 2) }}</span>
                    </div>
                @endif
                <div class="border-t-2 border-gray-200 pt-2 flex justify-between items-center text-sm font-black bg-blue-50/50 p-2.5 rounded-xl">
                    <span class="text-blue-900 uppercase tracking-wider">Certified Net Amount Payable (Cr 2020):</span>
                    <span class="font-mono text-base text-blue-700 font-black">₹{{ number_format($subcontractorBill->net_payable_amount, 2) }}</span>
                </div>
            </div>

            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200/80 space-y-3 text-xs">
                <h4 class="font-bold text-gray-800 uppercase tracking-wider text-[11px] border-b border-gray-200 pb-1.5">Payment Remittance Status</h4>
                <div class="flex justify-between py-1">
                    <span class="text-gray-600">Net Payable Amount:</span>
                    <span class="font-mono font-bold text-gray-900">₹{{ number_format($subcontractorBill->net_payable_amount, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 text-emerald-700">
                    <span>(-) Total Payments Disbursed:</span>
                    <span class="font-mono font-bold">-₹{{ number_format($subcontractorBill->paid_amount, 2) }}</span>
                </div>
                <div class="border-t border-gray-200 pt-2 flex justify-between items-center">
                    <span class="font-bold text-gray-900">Outstanding Balance Due:</span>
                    <span class="font-mono text-base font-black text-rose-600">₹{{ number_format($subcontractorBill->outstandingBalance(), 2) }}</span>
                </div>
                <div class="text-[11px] text-gray-500 pt-1 flex items-center justify-between">
                    <span>Lifecycle Status:</span>
                    <span class="px-2 py-0.5 rounded-full font-bold uppercase text-[10px] {{ $subcontractorBill->status_badge }}">{{ str_replace('_', ' ', $subcontractorBill->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3-Way Reconciliation Table (WO BOQ vs Approved MB vs Billed) -->
    <div class="soft-card p-6 space-y-4">
        <div class="border-b border-gray-100 pb-3">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">3-Way Match & Reconciliation (Work Order BOQ vs Measurement Book vs RA Bill)</h3>
            <p class="text-[11px] text-gray-500">Rigorous audit matching verifies that billed quantities exactly correspond to approved site measurements without exceeding contract limits.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-2.5 px-3">BOQ Item</th>
                        <th class="py-2.5 px-3">Description</th>
                        <th class="py-2.5 px-3">Unit</th>
                        <th class="py-2.5 px-3 text-right">Approved BOQ Limit</th>
                        <th class="py-2.5 px-3 text-right">Previously Billed</th>
                        <th class="py-2.5 px-3 text-right">Current Period Measured</th>
                        <th class="py-2.5 px-3 text-right">Cumulative Billed</th>
                        <th class="py-2.5 px-3 text-right">Remaining Contract Qty</th>
                        <th class="py-2.5 px-3 text-right">Unit Rate</th>
                        <th class="py-2.5 px-3 text-right">Current Gross Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($subcontractorBill->items as $bItem)
                        @php
                            $woItem = $bItem->workOrderItem;
                            $approvedQty = (float) $woItem->approved_quantity;
                            $cumBilled = (float) $bItem->cumulative_billed_quantity;
                            $rem = max(0.0, $approvedQty - $cumBilled);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">{{ $woItem->item_code }}</td>
                            <td class="py-3 px-3 text-gray-800 font-medium">{{ $woItem->description }}</td>
                            <td class="py-3 px-3 text-gray-500 font-mono">{{ $woItem->unit_of_measure }}</td>
                            <td class="py-3 px-3 font-mono font-semibold text-right">{{ number_format($approvedQty, 3) }}</td>
                            <td class="py-3 px-3 font-mono text-gray-500 text-right">{{ number_format($bItem->previously_billed_quantity, 3) }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-blue-700 text-right">{{ number_format($bItem->current_billed_quantity, 3) }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-indigo-700 text-right">{{ number_format($cumBilled, 3) }}</td>
                            <td class="py-3 px-3 font-mono text-emerald-700 font-semibold text-right">{{ number_format($rem, 3) }}</td>
                            <td class="py-3 px-3 font-mono text-gray-700 text-right">₹{{ number_format($bItem->unit_rate, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-gray-900 text-right">₹{{ number_format($bItem->gross_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 font-bold bg-gray-50/50">
                        <td colspan="9" class="py-3 px-3 text-right uppercase text-[10px] text-gray-500 tracking-wider">Total Gross Work Value Certified:</td>
                        <td class="py-3 px-3 font-mono text-base font-black text-blue-700 text-right">₹{{ number_format($subcontractorBill->gross_work_value, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Underlying Measurement Book Sheets Allocated -->
    <div class="soft-card p-5 space-y-3">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Allocated Measurement Book (MB) Verification Sheets</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-2 px-2">MB Number</th>
                        <th class="py-2 px-2">Measurement Date</th>
                        <th class="py-2 px-2">BOQ Item</th>
                        <th class="py-2 px-2">Chainage / Location</th>
                        <th class="py-2 px-2">Calculation Dimensions</th>
                        <th class="py-2 px-2 text-right">Allocated Quantity</th>
                        <th class="py-2 px-2 text-right">Inspect</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($subcontractorBill->measurementsPivot as $alloc)
                        @php $m = $alloc->measurementBookEntry; @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-2.5 px-2 font-mono font-bold text-gray-900">
                                <a href="{{ route('measurement-books.show', $m) }}" class="hover:text-blue-600">{{ $m->mb_number }}</a>
                            </td>
                            <td class="py-2.5 px-2 text-gray-500">{{ $m->measurement_date->format('d M Y') }}</td>
                            <td class="py-2.5 px-2 font-mono text-blue-700">{{ $m->workOrderItem->item_code }}</td>
                            <td class="py-2.5 px-2 text-gray-700">{{ $m->location_chainage ?? 'General Site' }}</td>
                            <td class="py-2.5 px-2 font-mono text-gray-500 text-[11px]">
                                @if($m->manual_quantity_override)
                                    Override: {{ number_format($m->manual_quantity_override, 3) }}
                                @else
                                    {{ $m->length ?? 0 }}m × {{ $m->breadth ?? 0 }}m × {{ $m->depth_height ?? 0 }}m
                                @endif
                            </td>
                            <td class="py-2.5 px-2 font-mono font-bold text-gray-900 text-right">
                                {{ number_format($alloc->allocated_quantity, 3) }} {{ $m->workOrderItem->unit_of_measure }}
                            </td>
                            <td class="py-2.5 px-2 text-right">
                                <a href="{{ route('measurement-books.show', $m) }}" class="text-blue-600 font-semibold hover:text-blue-800">MB Sheet →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Double-Entry General Ledger Posting Card -->
    @if($subcontractorBill->journalEntry)
        <div class="soft-card p-6 space-y-4 border-l-4 border-l-blue-600">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block">Double-Entry Accounting Integration</span>
                    <h3 class="text-sm font-bold text-gray-900 mt-0.5">
                        Posted Journal Entry Voucher: <span class="font-mono text-blue-700">{{ $subcontractorBill->journalEntry->entry_number }}</span>
                    </h3>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    ✓ Balanced & Posted
                </span>
            </div>

            <p class="text-xs text-gray-600">Narration: {{ $subcontractorBill->journalEntry->narration }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            <th class="py-2 px-3">Account Code & Name</th>
                            <th class="py-2 px-3">Type</th>
                            <th class="py-2 px-3">Project</th>
                            <th class="py-2 px-3">Description</th>
                            <th class="py-2 px-3 text-right">Debit (₹)</th>
                            <th class="py-2 px-3 text-right">Credit (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 font-mono">
                        @foreach($subcontractorBill->journalEntry->lines as $line)
                            <tr>
                                <td class="py-2.5 px-3 font-semibold text-gray-900">
                                    {{ $line->account->code }} - {{ $line->account->name }}
                                </td>
                                <td class="py-2.5 px-3 text-[11px] text-gray-500 capitalize font-sans">
                                    {{ $line->account->account_type }}
                                </td>
                                <td class="py-2.5 px-3 text-[11px] text-gray-600 font-sans">
                                    {{ $line->project_id ? $subcontractorBill->project->code : '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-[11px] text-gray-500 font-sans">
                                    {{ $line->description }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900">
                                    {{ $line->debit > 0 ? '₹' . number_format($line->debit, 2) : '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900">
                                    {{ $line->credit > 0 ? '₹' . number_format($line->credit, 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 font-bold font-mono bg-gray-50/50">
                            <td colspan="4" class="py-2.5 px-3 text-right uppercase text-[10px] text-gray-500 font-sans">Balanced Ledger Total:</td>
                            <td class="py-2.5 px-3 text-right text-blue-700">₹{{ number_format($subcontractorBill->journalEntry->lines->sum('debit'), 2) }}</td>
                            <td class="py-2.5 px-3 text-right text-blue-700">₹{{ number_format($subcontractorBill->journalEntry->lines->sum('credit'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    <!-- Payment Remittances History -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Payment Remittance Transactions</h3>
                <p class="text-[11px] text-gray-500">Actual bank cash settlements recorded against this certified bill.</p>
            </div>
            <div class="text-right">
                <span class="text-xs font-bold text-gray-500">Outstanding Due:</span>
                <span class="font-mono text-base font-black text-rose-600 ml-1">₹{{ number_format($subcontractorBill->outstandingBalance(), 2) }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-2 px-3">Date</th>
                        <th class="py-2 px-3">Reference / UTR</th>
                        <th class="py-2 px-3">Payment Mode</th>
                        <th class="py-2 px-3">Bank Source Account</th>
                        <th class="py-2 px-3">Recorded By</th>
                        <th class="py-2 px-3 text-right">Amount Disbursed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($subcontractorBill->payments as $payment)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-2.5 px-3 text-gray-700 font-medium">{{ $payment->payment_date->format('d M Y') }}</td>
                            <td class="py-2.5 px-3 font-mono font-bold text-gray-900">{{ $payment->transaction_reference }}</td>
                            <td class="py-2.5 px-3 capitalize">{{ str_replace('_', ' ', $payment->payment_mode) }}</td>
                            <td class="py-2.5 px-3 text-gray-700">{{ $payment->bankAccount->bank_name ?? 'Primary Operating Account' }}</td>
                            <td class="py-2.5 px-3 text-gray-500">{{ $payment->creator->name ?? 'Finance Officer' }}</td>
                            <td class="py-2.5 px-3 font-mono font-bold text-emerald-700 text-right">
                                ₹{{ number_format($payment->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-gray-400 text-xs">
                                No payments recorded against this bill yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Subcontractor RA Bill Documents & Measurement Sheets -->
    <x-document-manager 
        :record="$subcontractorBill" 
        type="subcontractor_bill" 
        title="Subcontractor Invoices, Measurement Sheets & Joint Records"
        :categories="[
            'measurement_sheet' => 'Signed Measurement Sheet / JMR',
            'supplier_invoice' => 'Subcontractor Tax Invoice / Claim',
            'completion_certificate' => 'Site Engineer Measurement & Work Sign-off',
            'voucher_support' => 'Payment Disbursal / Bank UTR Copy',
            'general' => 'General Supporting File',
        ]"
    />
</div>

<!-- Modal: Record Payment -->
<div id="paymentModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Record Payment Remittance</h3>
                <span class="text-xs text-gray-500">Outstanding: ₹{{ number_format($subcontractorBill->outstandingBalance(), 2) }}</span>
            </div>
            <button type="button" onclick="document.getElementById('paymentModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" action="{{ route('subcontractor-bills.payments.store', $subcontractorBill) }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-gray-700 mb-1">Disbursement Amount (₹) *</label>
                <input type="number" step="0.01" min="0.01" max="{{ $subcontractorBill->outstandingBalance() }}"
                    name="amount" value="{{ $subcontractorBill->outstandingBalance() }}" required
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 font-mono font-bold text-blue-700 text-base"/>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Remittance Date *</label>
                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2"/>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Payment Method *</label>
                <select name="payment_mode" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2" required>
                    <option value="bank_transfer" selected>NEFT / RTGS Online Bank Transfer</option>
                    <option value="cheque">Bank Cheque / Demand Draft</option>
                    <option value="adjustment">Account Adjustment / Advance Contra</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Transaction Ref / UTR Number *</label>
                <input type="text" name="transaction_reference" required
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 font-mono uppercase"
                    placeholder="e.g. UTR-HDFC-99281928"/>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Disbursing Bank Account</label>
                <select name="bank_account_id" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    @foreach($bankAccounts as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->bank_name }} - {{ $bank->account_number }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Payment Notes</label>
                <textarea name="notes" rows="2" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2" placeholder="e.g. Cleared via RTGS batch..."></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('paymentModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold shadow-sm">Post Disbursement & Ledger</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reverse Certified Bill -->
<div id="reverseModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-rose-700">Reverse Certified Subcontractor Bill</h3>
            <button type="button" onclick="document.getElementById('reverseModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" action="{{ route('subcontractor-bills.reverse', $subcontractorBill) }}" class="space-y-4 text-xs">
            @csrf
            <p class="text-gray-600">
                Reversing this bill will post an immutable counter-journal (swapping debits and credits) and release all underlying Measurement Book entries back to approved state.
            </p>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Mandatory Reversal Justification * (min 5 chars)</label>
                <textarea name="reason" rows="3" required minlength="5"
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-red-500"
                    placeholder="Specify audit or clerical reason for reversing certified bill..."></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('reverseModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-semibold shadow-sm">Confirm Reversal</button>
            </div>
        </form>
    </div>
</div>
@endsection
