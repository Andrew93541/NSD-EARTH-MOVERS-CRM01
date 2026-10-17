@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $order->po_number }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 uppercase">
                    {{ $order->status }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Vendor: {{ $order->supplier->name ?? '—' }} • Project: {{ $order->project->name ?? '—' }}</p>
        </div>
        <a href="{{ route('procurement.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to POs</a>
    </div>

    <!-- PO Invoice Sheet -->
    <div class="soft-card p-6 space-y-6">
        <div class="grid grid-cols-2 gap-4 pb-4 border-b border-gray-100 text-xs">
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Vendor Particulars</span>
                <strong class="text-gray-900 text-sm block mt-1">{{ $order->supplier->name }}</strong>
                <span class="text-gray-500 block">GSTIN: {{ $order->supplier->gstin ?? 'N/A' }}</span>
                <span class="text-gray-500 block">Phone: {{ $order->supplier->phone ?? '—' }}</span>
            </div>
            <div class="text-right">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">PO Information</span>
                <span class="text-gray-700 block mt-1">Date: <strong>{{ $order->po_date->format('d M, Y') }}</strong></span>
                <span class="text-gray-700 block">Due Delivery: <strong>{{ $order->delivery_due_date ? $order->delivery_due_date->format('d M, Y') : 'Immediate' }}</strong></span>
                <span class="text-gray-700 block">Terms: <strong>{{ str_replace('_', ' ', $order->supplier->payment_terms ?? '30 Days') }}</strong></span>
            </div>
        </div>

        <!-- Line items table -->
        <div>
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-2">Item Description</th>
                        <th class="py-2 text-right">Quantity</th>
                        <th class="py-2 text-right">Unit Rate</th>
                        <th class="py-2 text-right">GST %</th>
                        <th class="py-2 text-right">Total (INR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-3 font-bold text-gray-900">{{ $item->material->name ?? 'Material' }}</td>
                            <td class="py-3 text-right font-mono">{{ number_format($item->quantity_ordered, 2) }} {{ $item->material->unit_of_measure ?? 'nos' }}</td>
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
            <div class="w-64 space-y-1.5 text-xs">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal:</span>
                    <span class="font-mono">₹{{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-500">
                    <span>GST (18%):</span>
                    <span class="font-mono">₹{{ number_format($order->tax_amount, 2) }}</span>
                </div>
                <div class="flex justify-between text-base font-extrabold text-gray-900 pt-2 border-t border-gray-200">
                    <span>Total Payable:</span>
                    <span class="font-mono">₹{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
