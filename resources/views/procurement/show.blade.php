@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $order->po_number }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                    {{ $order->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($order->status === 'partially_received' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700') }}">
                    {{ str_replace('_', ' ', $order->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Vendor: {{ $order->supplier->name ?? '—' }} • Project: {{ $order->project->name ?? '—' }}</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('procurement.index') }}" class="px-3.5 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-white border border-gray-200 rounded-xl transition">
                ← Back to POs
            </a>
            @if (!in_array($order->status, ['completed', 'cancelled']))
                <a href="{{ route('procurement.receive', $order) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>Receive Materials (GRN)</span>
                </a>
            @endif
        </div>
    </div>

    <!-- PO Invoice Sheet -->
    <div class="soft-card p-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-gray-100 text-xs">
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Vendor Particulars</span>
                <strong class="text-gray-900 text-sm block mt-1">{{ $order->supplier->name }}</strong>
                <span class="text-gray-500 block">GSTIN: {{ $order->supplier->gstin ?? 'N/A' }}</span>
                <span class="text-gray-500 block">Phone: {{ $order->supplier->phone ?? '—' }}</span>
            </div>
            <div class="sm:text-right">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">PO Information</span>
                <span class="text-gray-700 block mt-1">Date: <strong>{{ $order->po_date->format('d M, Y') }}</strong></span>
                <span class="text-gray-700 block">Due Delivery: <strong>{{ $order->delivery_due_date ? $order->delivery_due_date->format('d M, Y') : 'Immediate' }}</strong></span>
                <span class="text-gray-700 block">Payment Terms: <strong>{{ str_replace('_', ' ', $order->supplier->payment_terms ?? '30 Days') }}</strong></span>
            </div>
        </div>

        <!-- Line items table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-2.5">Item Description</th>
                        <th class="py-2.5 text-right">Ordered Qty</th>
                        <th class="py-2.5 text-right">Received Qty</th>
                        <th class="py-2.5 text-right">Unit Rate</th>
                        <th class="py-2.5 text-right">GST %</th>
                        <th class="py-2.5 text-right">Total (INR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-3 font-bold text-gray-900">
                                {{ $item->material->name ?? 'Material' }}
                            </td>
                            <td class="py-3 text-right font-mono text-gray-700 font-semibold">
                                {{ number_format($item->quantity_ordered, 2) }} {{ $item->material->unit_of_measure ?? 'nos' }}
                            </td>
                            <td class="py-3 text-right font-mono font-bold {{ (float) $item->quantity_received >= (float) $item->quantity_ordered ? 'text-emerald-600' : ((float) $item->quantity_received > 0 ? 'text-amber-600' : 'text-gray-400') }}">
                                {{ number_format($item->quantity_received, 2) }}
                            </td>
                            <td class="py-3 text-right font-mono">₹{{ number_format($item->unit_price, 2) }}</td>
                            <td class="py-3 text-right font-mono">{{ $item->tax_percent }}%</td>
                            <td class="py-3 text-right font-bold text-gray-900 font-mono">₹{{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals summary -->
        <div class="pt-4 border-t border-gray-100 flex justify-end">
            <div class="w-72 space-y-1.5 text-xs">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal:</span>
                    <span class="font-mono font-semibold">₹{{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-500">
                    <span>GST:</span>
                    <span class="font-mono font-semibold">₹{{ number_format($order->tax_amount, 2) }}</span>
                </div>
                <div class="flex justify-between text-base font-extrabold text-gray-900 pt-2 border-t border-gray-200">
                    <span>Total Purchase Value:</span>
                    <span class="font-mono text-blue-700">₹{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Goods Receipt Notes (GRNs) History -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Goods Receipts & Quality Inspections (GRN)</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Physical gate inward entries and warehouse inventory credits.</p>
            </div>
            <span class="text-xs font-bold text-blue-700 font-mono">
                {{ $order->goodsReceipts->count() }} GRN(s)
            </span>
        </div>

        @if ($order->goodsReceipts->isEmpty())
            <div class="py-8 text-center text-gray-400 text-xs">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                No goods receipt notes recorded for this purchase order yet.
                @if (!in_array($order->status, ['completed', 'cancelled']))
                    <div class="mt-2">
                        <a href="{{ route('procurement.receive', $order) }}" class="text-blue-600 font-bold hover:underline">
                            + Record Material Inward Gate Entry
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="space-y-4">
                @foreach ($order->goodsReceipts as $grn)
                    <div class="p-4 bg-gray-50/70 border border-gray-100 rounded-2xl space-y-3 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-200/60 pb-2.5">
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-blue-700 bg-blue-50 px-2.5 py-0.5 rounded text-xs">
                                    {{ $grn->grn_number }}
                                </span>
                                <span class="text-gray-700 font-semibold">Challan: <strong class="text-gray-900 font-mono">{{ $grn->delivery_challan_number }}</strong></span>
                                @if ($grn->vehicle_number)
                                    <span class="text-gray-500 font-mono">Truck: {{ $grn->vehicle_number }}</span>
                                @endif
                            </div>
                            <div class="text-gray-500 text-[11px]">
                                Date: <strong class="text-gray-900">{{ $grn->receipt_date->format('d M, Y') }}</strong>
                                • Yard: <strong class="text-gray-900">{{ $grn->warehouse->name ?? 'Central Yard' }}</strong>
                                • Inspected by: <strong class="text-gray-900">{{ $grn->receiver->name ?? 'Storekeeper' }}</strong>
                            </div>
                        </div>

                        <!-- GRN Items -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-gray-400 font-bold uppercase text-[10px]">
                                        <th class="py-1">Material</th>
                                        <th class="py-1 text-right">Received</th>
                                        <th class="py-1 text-right">Accepted (In Stock)</th>
                                        <th class="py-1 text-right">Rejected</th>
                                        <th class="py-1 pl-3">Rejection Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($grn->items as $gItem)
                                        <tr>
                                            <td class="py-1.5 font-bold text-gray-900">{{ $gItem->material->name ?? 'Material' }}</td>
                                            <td class="py-1.5 text-right font-mono text-gray-600">{{ number_format($gItem->quantity_received, 2) }}</td>
                                            <td class="py-1.5 text-right font-mono font-bold text-emerald-700">{{ number_format($gItem->quantity_accepted, 2) }}</td>
                                            <td class="py-1.5 text-right font-mono font-bold {{ (float) $gItem->quantity_rejected > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($gItem->quantity_rejected, 2) }}</td>
                                            <td class="py-1.5 pl-3 text-gray-500 text-[11px]">{{ $gItem->rejection_reason ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Delivery Challan & Weighbridge Slip -->
                        <div class="pt-2 border-t border-gray-200/60">
                            <x-document-manager 
                                :record="$grn" 
                                type="goods_receipt" 
                                title="GRN Delivery Challan & Weighbridge Slip"
                                :categories="[
                                    'delivery_challan' => 'Physical Signed Delivery Challan',
                                    'inspection_photo' => 'Material Unloading / Damage Photograph',
                                    'general' => 'Gate Inward Supporting Note',
                                ]"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- 3-Way Matching: Linked Supplier Invoices -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Vendor Invoices & Three-Way Reconciliation</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">PO &rarr; Goods Receipt &rarr; Commercial Invoice matching.</p>
            </div>
            <button onclick="document.getElementById('invoice-form-section').classList.toggle('hidden')" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                + Record Vendor Invoice
            </button>
        </div>

        <!-- Hidden Inward Invoice Form -->
        <div id="invoice-form-section" class="hidden p-4 bg-gray-50 rounded-2xl border border-gray-200">
            <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Record Supplier Commercial Invoice</h4>
            <form method="POST" action="{{ route('procurement.invoice.store', $order) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                    <div>
                        <label for="invoice_number" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Invoice Number *</label>
                        <input type="text" id="invoice_number" name="invoice_number" required placeholder="e.g. INV-SUP-9912"
                               class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-mono font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                    </div>
                    <div>
                        <label for="invoice_date" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Invoice Date *</label>
                        <input type="date" id="invoice_date" name="invoice_date" value="{{ date('Y-m-d') }}" required
                               class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                    </div>
                    <div>
                        <label for="due_date" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Payment Due Date *</label>
                        <input type="date" id="due_date" name="due_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required
                               class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                    </div>
                    <div>
                        <label for="subtotal" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Taxable Subtotal (₹) *</label>
                        <input type="number" step="0.01" id="subtotal" name="subtotal" value="{{ $order->subtotal }}" required
                               class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-mono font-bold focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-2">
                    <div class="w-64">
                        <label for="tax_amount" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">GST Tax Amount (₹)</label>
                        <input type="number" step="0.01" id="tax_amount" name="tax_amount" value="{{ $order->tax_amount }}"
                               class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-mono font-bold focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        Save Supplier Invoice
                    </button>
                </div>
            </form>
        </div>

        @if ($order->supplierInvoices->isEmpty())
            <div class="py-6 text-center text-gray-400 text-xs">
                No vendor commercial invoices recorded for this PO.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                            <th class="py-2">Invoice #</th>
                            <th class="py-2">Date</th>
                            <th class="py-2">Due Date</th>
                            <th class="py-2 text-right">Total Amount</th>
                            <th class="py-2 text-right">Balance Due</th>
                            <th class="py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($order->supplierInvoices as $sInv)
                            <tr>
                                <td class="py-2.5 font-bold font-mono text-gray-900">{{ $sInv->invoice_number }}</td>
                                <td class="py-2.5 text-gray-600">{{ $sInv->invoice_date->format('d M, Y') }}</td>
                                <td class="py-2.5 text-gray-600">{{ $sInv->due_date->format('d M, Y') }}</td>
                                <td class="py-2.5 text-right font-mono font-bold text-gray-900">₹{{ number_format($sInv->total_amount, 2) }}</td>
                                <td class="py-2.5 text-right font-mono font-extrabold text-red-600">₹{{ number_format($sInv->balanceDue(), 2) }}</td>
                                <td class="py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 uppercase font-mono">
                                        {{ $sInv->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- PO & Vendor Delivery Supporting Documents -->
    <x-document-manager 
        :record="$order" 
        type="purchase_order" 
        title="PO Agreements, Delivery Challans & Vendor Invoices"
        :categories="[
            'supplier_invoice' => 'Supplier Commercial Tax Invoice',
            'delivery_challan' => 'Vendor Delivery Challan / Gate Pass',
            'contract' => 'Signed PO Copy / Commercial Agreement',
            'inspection_photo' => 'Material Inspection & Unloading Evidence',
            'general' => 'General Procurement Attachment',
        ]"
    />
</div>
@endsection
