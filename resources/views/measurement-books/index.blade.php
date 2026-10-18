@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Digital Measurement Book (MB)</h2>
            <p class="text-xs text-gray-500 mt-1">Certified site measurements of physical work executed, dimension formulas ($L \times B \times D$), chainages, and review lifecycle.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('measurement-books.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Record Measurement</span>
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total MB Entries</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_entries'] }}</div>
            <span class="text-[11px] text-gray-400">Total recorded sheets</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-amber-600 uppercase">Pending Review</span>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $stats['submitted_pending_review'] }}</div>
            <span class="text-[11px] text-gray-400">Awaiting engineer check</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-emerald-600 uppercase">Approved & Unbilled</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $stats['approved_ready_to_bill'] }}</div>
            <span class="text-[11px] text-gray-400">Eligible for RA bill</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-indigo-600 uppercase">Certified in Bills</span>
            <div class="text-2xl font-black text-indigo-600 mt-1">{{ $stats['certified'] }}</div>
            <span class="text-[11px] text-gray-400">Billed & journaled</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-rose-600 uppercase">Rejected / Revisions</span>
            <div class="text-2xl font-black text-rose-600 mt-1">{{ $stats['rejected'] }}</div>
            <span class="text-[11px] text-gray-400">Returned for correction</span>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('measurement-books.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="MB #, description, chainage..."
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
                    <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted for Review</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved (Ready to Bill)</option>
                    <option value="certified" {{ request('status') === 'certified' ? 'selected' : '' }}>Certified in RA Bill</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="w-full px-4 py-2 bg-gray-900 text-white text-xs font-semibold rounded-xl hover:bg-gray-800 transition">Filter</button>
                @if(request()->anyFilled(['search', 'project_id', 'work_order_id', 'status']))
                    <a href="{{ route('measurement-books.index') }}" class="px-3 py-2 bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-200 transition">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- MB Entries Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">MB Number & Date</th>
                        <th class="py-3 px-3">Work Order & Subcontractor</th>
                        <th class="py-3 px-3">BOQ Item & Description</th>
                        <th class="py-3 px-3">Chainage / Location</th>
                        <th class="py-3 px-3">Measured Quantity</th>
                        <th class="py-3 px-3">Cumulative Qty</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Measured By</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($mbEntries as $mb)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono">
                                <a href="{{ route('measurement-books.show', $mb) }}" class="font-bold text-gray-900 hover:text-blue-600 block">
                                    {{ $mb->mb_number }}
                                </a>
                                <span class="text-gray-400 text-[11px]">{{ $mb->measurement_date->format('d M Y') }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <a href="{{ route('work-orders.show', $mb->work_order_id) }}" class="font-bold text-gray-900 hover:text-blue-600 block">
                                    {{ $mb->workOrder->wo_number }}
                                </a>
                                <span class="text-gray-500 text-[11px]">{{ $mb->subcontractor->name }}</span>
                            </td>
                            <td class="py-3 px-3 max-w-[240px]">
                                <span class="font-mono font-bold text-blue-700 block">{{ $mb->workOrderItem->item_code }}</span>
                                <span class="text-gray-700 text-[11px] truncate block" title="{{ $mb->description }}">{{ $mb->description }}</span>
                            </td>
                            <td class="py-3 px-3 text-gray-600 font-medium">
                                {{ $mb->location_chainage ?? 'General Site' }}
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">
                                {{ number_format($mb->measured_quantity, 3) }}
                                <span class="text-[10px] text-gray-500 font-normal">{{ $mb->workOrderItem->unit_of_measure }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-indigo-700 font-semibold">
                                {{ number_format($mb->cumulative_quantity, 3) }}
                                <span class="text-[10px] text-gray-400 font-normal">/ {{ number_format($mb->workOrderItem->approved_quantity, 3) }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $mb->status_badge }}">
                                    {{ ucfirst($mb->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-gray-500 text-[11px]">
                                {{ $mb->measuredBy->name ?? 'Site Eng.' }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('measurement-books.show', $mb) }}" class="text-blue-600 font-semibold hover:text-blue-800">Review →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-400 text-xs">
                                No Measurement Book entries found matching your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $mbEntries->links() }}
        </div>
    </div>
</div>
@endsection
