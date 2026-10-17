@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Materials & Inventory</h2>
            <p class="text-xs text-gray-500 mt-1">Multi-warehouse stock tracking, cement, structural steel, rebar, and fleet fuel.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.ledger') }}" class="px-3.5 py-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 rounded-xl text-xs font-semibold shadow-sm transition">
                Stock Movement Ledger
            </a>
            <a href="{{ route('inventory.transfer') }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                + Inter-Site Transfer
            </a>
        </div>
    </div>

    <!-- 3 Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Catalog Materials</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_materials'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Operational Yards & Stores</span>
            <div class="text-2xl font-black text-blue-600 mt-1">{{ $stats['total_warehouses'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Low Stock Alerts</span>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $stats['low_stock'] }}</div>
        </div>
    </div>

    <!-- Materials Table -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Stock on Hand by Material</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Code</th>
                        <th class="py-3 px-3">Material Name</th>
                        <th class="py-3 px-3">Category</th>
                        <th class="py-3 px-3">UOM</th>
                        <th class="py-3 px-3">Standard Cost</th>
                        <th class="py-3 px-3">Available Stock</th>
                        <th class="py-3 px-3">Reorder Threshold</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($materials as $mat)
                        @php $stock = $mat->currentStock(); @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-600">{{ $mat->code }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $mat->name }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $mat->category->name ?? 'General' }}</td>
                            <td class="py-3 px-3 font-semibold">{{ $mat->unit_of_measure }}</td>
                            <td class="py-3 px-3 font-mono">₹{{ number_format($mat->standard_cost, 2) }}</td>
                            <td class="py-3 px-3">
                                <span class="font-extrabold text-sm {{ $stock <= $mat->reorder_level ? 'text-red-600' : 'text-emerald-700' }}">
                                    {{ number_format($stock, 2) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono text-gray-400">{{ number_format($mat->reorder_level, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-400">No materials recorded in inventory.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
