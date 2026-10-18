@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Procurement & Purchase Orders</h2>
            <p class="text-xs text-gray-500 mt-1">Vendor requisitions, purchase orders, deliveries, and three-way matching.</p>
        </div>
        <div class="flex items-center gap-2">
            @php
                $userRole = Auth::user()?->currentRole()?->slug;
                $isAdmin = Auth::user()?->hasPermission('*') || in_array($userRole, ['owner', 'admin']);
            @endphp
            @if ($isAdmin)
                <a href="{{ route('procurement.policies.index') }}" class="px-3.5 py-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 rounded-xl text-xs font-semibold shadow-sm transition">
                    Approval Limits & Policies
                </a>
            @endif
            <a href="{{ route('procurement.invoices') }}" class="px-3.5 py-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 rounded-xl text-xs font-semibold shadow-sm transition">
                Supplier Invoices
            </a>
            <a href="{{ route('procurement.create') }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                + Create Purchase Order
            </a>
        </div>
    </div>

    <!-- 3 Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total POs Issued</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_pos'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Pending Deliveries</span>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $stats['pending_pos'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Cumulative Committed Value</span>
            <div class="text-2xl font-black text-blue-600 mt-1">₹{{ number_format($stats['total_po_value'], 2) }}</div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Recent Purchase Orders</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">PO Number</th>
                        <th class="py-3 px-3">Supplier</th>
                        <th class="py-3 px-3">Project</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Total Amount</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($orders as $po)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">
                                <a href="{{ route('procurement.show', $po) }}" class="hover:text-blue-600">{{ $po->po_number }}</a>
                            </td>
                            <td class="py-3 px-3 font-semibold text-gray-900">{{ $po->supplier->name ?? '—' }}</td>
                            <td class="py-3 px-3 text-gray-600">{{ $po->project->name ?? '—' }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $po->po_date->format('d M, Y') }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900 font-mono">₹{{ number_format($po->total_amount, 2) }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700">
                                    {{ strtoupper($po->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('procurement.show', $po) }}" class="text-blue-600 font-semibold hover:text-blue-800">View PO →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-400">No purchase orders on file.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
