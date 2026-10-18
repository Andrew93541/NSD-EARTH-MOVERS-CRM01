@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Subcontractor Running Account (RA) Bills</h2>
            <p class="text-xs text-gray-500 mt-1">Periodic work certification, 3-way reconciliation (BOQ–MB–Bill), contractual retention (Liability 2030), TDS u/s 194C, and disbursements.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('subcontractor-bills.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Generate RA Bill</span>
            </a>
        </div>
    </div>

    <!-- 5 Financial Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase">Total RA Bills</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_bills'] }}</div>
            <span class="text-[11px] text-gray-400">Total processed bills</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-indigo-600 uppercase">Gross Certified Work</span>
            <div class="text-xl font-black text-indigo-600 mt-1 font-mono">₹{{ number_format($stats['total_certified_value'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Dr Subcontract Work 5040</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-amber-600 uppercase">Retention Withheld</span>
            <div class="text-xl font-black text-amber-600 mt-1 font-mono">₹{{ number_format($stats['total_retention_held'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Cr Retention Liability 2030</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-emerald-600 uppercase">Total Disbursed</span>
            <div class="text-xl font-black text-emerald-600 mt-1 font-mono">₹{{ number_format($stats['total_paid'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Bank payments settled</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-rose-600 uppercase">Outstanding Payable</span>
            <div class="text-xl font-black text-rose-600 mt-1 font-mono">₹{{ number_format($stats['total_outstanding'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Cr Subcon Payable 2020</span>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('subcontractor-bills.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Bill #, subcontractor, WO #..."
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
            </div>
            <div>
                <select name="project_id" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="work_order_id" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Work Orders</option>
                    @foreach($workOrders as $wo)
                        <option value="{{ $wo->id }}" {{ request('work_order_id') == $wo->id ? 'selected' : '' }}>{{ $wo->wo_number }} - {{ $wo->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="certified" {{ request('status') === 'certified' ? 'selected' : '' }}>Certified (Ledger Posted)</option>
                    <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Fully Settled</option>
                    <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>Reversed</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="w-full px-4 py-2 bg-gray-900 text-white text-xs font-semibold rounded-xl hover:bg-gray-800 transition">Filter</button>
                @if(request()->anyFilled(['search', 'project_id', 'work_order_id', 'status']))
                    <a href="{{ route('subcontractor-bills.index') }}" class="px-3 py-2 bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-200 transition">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Bills Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Bill Number & Date</th>
                        <th class="py-3 px-3">Subcontractor & WO</th>
                        <th class="py-3 px-3">Project</th>
                        <th class="py-3 px-3">Gross Certified</th>
                        <th class="py-3 px-3">Retention / TDS</th>
                        <th class="py-3 px-3">Net Payable</th>
                        <th class="py-3 px-3">Paid / Outstanding</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($bills as $bill)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono">
                                <a href="{{ route('subcontractor-bills.show', $bill) }}" class="font-bold text-gray-900 hover:text-blue-600 block">
                                    {{ $bill->bill_number }}
                                </a>
                                <span class="text-gray-400 text-[11px]">{{ $bill->bill_date->format('d M Y') }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <a href="{{ route('subcontractors.show', $bill->subcontractor_id) }}" class="font-semibold text-gray-900 hover:text-blue-600 block">
                                    {{ $bill->subcontractor->name }}
                                </a>
                                <a href="{{ route('work-orders.show', $bill->work_order_id) }}" class="font-mono text-gray-500 text-[11px] hover:underline">
                                    {{ $bill->workOrder->wo_number }}
                                </a>
                            </td>
                            <td class="py-3 px-3 text-gray-700 font-medium">
                                {{ $bill->project->name }}
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">
                                ₹{{ number_format($bill->gross_work_value, 2) }}
                            </td>
                            <td class="py-3 px-3 text-[11px] font-mono">
                                <div class="text-amber-700 font-semibold">Ret: -₹{{ number_format($bill->retention_amount, 2) }}</div>
                                <div class="text-gray-500">TDS: -₹{{ number_format($bill->tds_amount, 2) }}</div>
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-blue-700">
                                ₹{{ number_format($bill->net_payable_amount, 2) }}
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px]">
                                <div class="text-emerald-700 font-bold">Paid: ₹{{ number_format($bill->paid_amount, 2) }}</div>
                                <div class="text-rose-600 font-semibold">Due: ₹{{ number_format($bill->outstandingBalance(), 2) }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $bill->status_badge }}">
                                    {{ ucfirst(str_replace('_', ' ', $bill->status)) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('subcontractor-bills.show', $bill) }}" class="text-blue-600 font-semibold hover:text-blue-800">Reconciliation →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-400 text-xs">
                                No subcontractor RA bills found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $bills->links() }}
        </div>
    </div>
</div>
@endsection
