@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Supplier Invoices & Payables</h2>
            <p class="text-xs text-gray-500 mt-1">Vendor bills pending three-way reconciliation, approval, and disbursement.</p>
        </div>
        <a href="{{ route('procurement.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to POs</a>
    </div>

    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Invoice Number</th>
                        <th class="py-3 px-3">Supplier</th>
                        <th class="py-3 px-3">Linked PO</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3">Total Amount</th>
                        <th class="py-3 px-3">Balance Due</th>
                        <th class="py-3 px-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">{{ $inv->invoice_number }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $inv->supplier->name ?? '—' }}</td>
                            <td class="py-3 px-3 font-mono text-gray-500">{{ $inv->purchaseOrder->po_number ?? 'Direct' }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $inv->due_date->format('d M, Y') }}</td>
                            <td class="py-3 px-3 font-mono">₹{{ number_format($inv->total_amount, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-extrabold text-red-600">₹{{ number_format($inv->balanceDue(), 2) }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700">
                                    {{ strtoupper($inv->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-400">No supplier invoices recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
