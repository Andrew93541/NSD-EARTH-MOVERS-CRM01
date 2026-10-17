@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Stock Movement Ledger</h2>
            <p class="text-xs text-gray-500 mt-1">Immutable transaction history for all receipts, issues, transfers, and balance checks.</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Inventory</a>
    </div>

    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Material</th>
                        <th class="py-3 px-3">Warehouse / Yard</th>
                        <th class="py-3 px-3">Transaction</th>
                        <th class="py-3 px-3">Direction</th>
                        <th class="py-3 px-3">Quantity</th>
                        <th class="py-3 px-3">Valuation</th>
                        <th class="py-3 px-3">Balance After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($entries as $entry)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 text-gray-500">{{ $entry->entry_date->format('d M, Y') }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $entry->material->name ?? '—' }}</td>
                            <td class="py-3 px-3 text-gray-600">{{ $entry->warehouse->name ?? '—' }}</td>
                            <td class="py-3 px-3 font-mono text-[11px] text-gray-500">{{ str_replace('_', ' ', $entry->transaction_type) }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $entry->direction === 'in' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ strtoupper($entry->direction) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 font-bold text-gray-900 font-mono">{{ number_format($entry->quantity, 2) }}</td>
                            <td class="py-3 px-3 font-mono">₹{{ number_format($entry->total_amount, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-blue-600">{{ number_format($entry->balance_quantity, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-gray-400">No stock movements recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
