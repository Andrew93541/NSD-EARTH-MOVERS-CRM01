@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('suppliers.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    ← Material Suppliers Directory
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $supplier->name }}</h1>
                @if($supplier->vendor_type === 'both')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                        Material Supplier & Subcontractor
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        Material Supplier
                    </span>
                @endif

                @if($supplier->status === 'active')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Active
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        Inactive
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Terms: {{ ucfirst(str_replace('_', ' ', $supplier->payment_terms)) }} • Registered {{ $supplier->created_at?->format('d M Y') }}
            </p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('suppliers.manage') || $u->hasPermission('suppliers.*') || $u->hasPermission('procurement.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'procurement_officer', 'accountant']));
            $canProcure = $u && ($u->hasPermission('*') || $u->hasPermission('procurement.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'procurement_officer']));
        @endphp

        <div class="flex items-center gap-2">
            @if($canProcure)
            <a href="{{ route('procurement.create', ['supplier_id' => $supplier->id]) }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                + Issue Purchase Order
            </a>
            @endif
            @if($canManage)
            <a href="{{ route('suppliers.edit', $supplier) }}" class="px-3.5 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                Edit Details
            </a>
            @endif
        </div>
    </div>

    <!-- 5 Financial Stat Cards for Supplier Account -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total PO Orders</span>
            <div class="text-xl font-extrabold text-slate-900 mt-1">₹{{ number_format($financials['po_committed'], 2) }}</div>
            <span class="text-[11px] text-slate-500">{{ $purchaseOrders->count() }} total PO(s)</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Open PO Deliveries</span>
            <div class="text-xl font-extrabold text-blue-600 mt-1">{{ $financials['open_orders_count'] }}</div>
            <span class="text-[11px] text-slate-500">{{ $financials['total_grns_count'] }} GRN receipts</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Invoiced</span>
            <div class="text-xl font-extrabold text-slate-900 mt-1">₹{{ number_format($financials['total_invoiced'], 2) }}</div>
            <span class="text-[11px] text-slate-500">{{ $invoices->count() }} invoice(s)</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Paid to Date</span>
            <div class="text-xl font-extrabold text-emerald-600 mt-1">₹{{ number_format($financials['total_paid'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Disbursed settlement</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Outstanding Payable</span>
            <div class="text-xl font-extrabold {{ $financials['outstanding_payable'] > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">
                ₹{{ number_format($financials['outstanding_payable'], 2) }}
            </div>
            <span class="text-[11px] text-slate-500">Due for payment</span>
        </div>
    </div>

    <!-- Contact & Commercial Info Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="soft-card p-5 space-y-4">
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Commercial Terms & IDs</h2>
            
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sales / Business Contact</span>
                    <span class="font-semibold text-slate-800">{{ $supplier->contact_person ?? 'Not designated' }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Phone Number</span>
                        <span class="font-mono text-slate-700">{{ $supplier->phone ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Email Address</span>
                        <span class="text-slate-700">{{ $supplier->email ?? '—' }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">GSTIN</span>
                        <span class="font-mono font-semibold text-slate-900">{{ $supplier->gstin ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">PAN</span>
                        <span class="font-mono font-semibold text-slate-700">{{ $supplier->pan ?? '—' }}</span>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Payment Terms</span>
                    <span class="font-semibold text-slate-800">{{ ucfirst(str_replace('_', ' ', $supplier->payment_terms)) }}</span>
                </div>

                @if($supplier->address)
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Warehouse / Plant Address</span>
                    <p class="text-slate-600 text-xs mt-0.5 leading-relaxed">{{ $supplier->address }}</p>
                </div>
                @endif

                @if($supplier->bank_name || $supplier->bank_account_number)
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Bank Settlement Account</span>
                    <p class="text-slate-800 font-semibold text-xs mt-0.5">{{ $supplier->bank_name }}</p>
                    <p class="text-slate-600 font-mono text-[11px]">A/C: {{ $supplier->bank_account_number }}</p>
                    <p class="text-slate-500 font-mono text-[10px]">IFSC: {{ $supplier->ifsc_code }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Purchase Orders History -->
        <div class="lg:col-span-2 soft-card p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Purchase Orders (POs)</h2>
                    <p class="text-[11px] text-slate-400">Issued procurement orders for materials</p>
                </div>
                @if($canProcure)
                <a href="{{ route('procurement.create', ['supplier_id' => $supplier->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    + Issue PO
                </a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="py-2 px-3">PO Number</th>
                            <th class="py-2 px-3">Project</th>
                            <th class="py-2 px-3">PO Date</th>
                            <th class="py-2 px-3 text-right">Amount</th>
                            <th class="py-2 px-3 text-center">Status</th>
                            <th class="py-2 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                        @forelse($purchaseOrders as $po)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-2 px-3 font-mono font-semibold text-slate-900">
                                {{ $po->po_number }}
                            </td>
                            <td class="py-2 px-3 font-medium text-slate-800">
                                {{ $po->project->name ?? '—' }}
                            </td>
                            <td class="py-2 px-3 text-[11px]">
                                {{ $po->po_date?->format('d M Y') }}
                            </td>
                            <td class="py-2 px-3 text-right font-bold text-slate-900">
                                ₹{{ number_format($po->total_amount, 2) }}
                            </td>
                            <td class="py-2 px-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                    {{ ucfirst(str_replace('_', ' ', $po->status)) }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-right">
                                <a href="{{ route('procurement.show', $po) }}" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                    View PO →
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400">
                                No purchase orders issued to this supplier yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Deliveries / Goods Receipts (GRN) -->
    <div class="soft-card p-5 space-y-4">
        <div>
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Deliveries & Goods Receipts (GRN)</h2>
            <p class="text-[11px] text-slate-400">Material gate entries and warehouse receipt inspections</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-2 px-3">GRN Number</th>
                        <th class="py-2 px-3">PO Number</th>
                        <th class="py-2 px-3">Project / Warehouse</th>
                        <th class="py-2 px-3">Receipt Date</th>
                        <th class="py-2 px-3">Delivery Challan</th>
                        <th class="py-2 px-3">Vehicle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($goodsReceipts as $grn)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-2 px-3 font-mono font-semibold text-slate-900">
                            {{ $grn->grn_number }}
                        </td>
                        <td class="py-2 px-3 font-mono text-slate-700">
                            {{ $grn->purchaseOrder->po_number ?? '—' }}
                        </td>
                        <td class="py-2 px-3">
                            <span class="font-semibold text-slate-800 block">{{ $grn->purchaseOrder->project->name ?? '—' }}</span>
                            <span class="text-[10px] text-slate-400">{{ $grn->warehouse->name ?? 'Main Yard' }}</span>
                        </td>
                        <td class="py-2 px-3 text-[11px]">
                            {{ $grn->receipt_date?->format('d M Y') }}
                        </td>
                        <td class="py-2 px-3 font-mono text-[11px] text-slate-700">
                            {{ $grn->delivery_challan_number ?? '—' }}
                        </td>
                        <td class="py-2 px-3 font-mono text-[11px] text-slate-600">
                            {{ $grn->vehicle_number ?? '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-400">
                            No goods receipts logged for this supplier.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supplier Invoices History -->
    <div class="soft-card p-5 space-y-4">
        <div>
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Supplier Invoices & Billing</h2>
            <p class="text-[11px] text-slate-400">Invoices billed by supplier against approved purchase orders</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-2.5 px-3">Invoice Number</th>
                        <th class="py-2.5 px-3">PO Reference</th>
                        <th class="py-2.5 px-3">Dates</th>
                        <th class="py-2.5 px-3 text-right">Invoice Amount</th>
                        <th class="py-2.5 px-3 text-right">Paid</th>
                        <th class="py-2.5 px-3 text-right">Balance Due</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($invoices as $inv)
                    @php
                        $balance = max(0.0, $inv->total_amount - $inv->paid_amount);
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-2.5 px-3 font-mono font-semibold text-slate-900">
                            {{ $inv->invoice_number }}
                        </td>
                        <td class="py-2.5 px-3 font-mono text-slate-700">
                            {{ $inv->purchaseOrder->po_number ?? '—' }}
                        </td>
                        <td class="py-2.5 px-3 text-[11px]">
                            <span>{{ $inv->invoice_date?->format('d M Y') }}</span>
                            <span class="text-slate-400 block text-[10px]">Due: {{ $inv->due_date?->format('d M Y') }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold text-slate-900">
                            ₹{{ number_format($inv->total_amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right text-emerald-600 font-medium">
                            ₹{{ number_format($inv->paid_amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold {{ $balance > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                            ₹{{ number_format($balance, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                {{ ucfirst(str_replace('_', ' ', $inv->status)) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400">
                            No supplier invoices registered yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supplier Documentation Vault -->
    <x-document-manager 
        :record="$supplier" 
        type="supplier" 
        title="Supplier Documentation & Commercial Rate Agreements"
        :categories="[
            'contract' => 'Rate Contract / Vendor Agreement',
            'tax' => 'Vendor GST / PAN Certificate',
            'bank' => 'Bank Mandate / Cancelled Cheque',
            'general' => 'General Vendor Document',
        ]"
    />
</div>
@endsection
