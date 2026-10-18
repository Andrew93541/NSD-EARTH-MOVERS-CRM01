@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Subcontractor Work Orders & BOQ</h2>
            <p class="text-xs text-gray-500 mt-1">Legally binding work orders, approved Bill of Quantities (BOQ), contractual retention, and controlled revisions.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('work-orders.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Create Work Order</span>
            </a>
        </div>
    </div>

    <!-- 4 Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Subcontract Commitment</span>
            <div class="text-2xl font-black text-gray-900 mt-1 font-mono">₹{{ number_format($metrics['total_value'], 2) }}</div>
            <span class="text-[11px] text-gray-400">{{ $metrics['total_count'] }} Work Orders</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-indigo-600 uppercase">Gross Certified Work</span>
            <div class="text-2xl font-black text-indigo-600 mt-1 font-mono">₹{{ number_format($metrics['total_certified'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Measured and billed</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-amber-600 uppercase">Unbilled Order Balance</span>
            <div class="text-2xl font-black text-amber-600 mt-1 font-mono">₹{{ number_format($metrics['open_commitment'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Budget committed cost</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-emerald-600 uppercase">Active Work Orders</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $metrics['active_count'] }}</div>
            <span class="text-[11px] text-gray-400">Executing on sites</span>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('work-orders.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="WO Number, title, subcontractor..."
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
                <select name="subcontractor_id" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Subcontractors</option>
                    @foreach($subcontractors as $sub)
                        <option value="{{ $sub->id }}" {{ request('subcontractor_id') == $sub->id ? 'selected' : '' }}>{{ $sub->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved / Active</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="w-full px-4 py-2 bg-gray-900 text-white text-xs font-semibold rounded-xl hover:bg-gray-800 transition">Filter</button>
                @if(request()->anyFilled(['search', 'project_id', 'subcontractor_id', 'status', 'category']))
                    <a href="{{ route('work-orders.index') }}" class="px-3 py-2 bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-200 transition">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Work Orders Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">WO Number & Title</th>
                        <th class="py-3 px-3">Project & Site</th>
                        <th class="py-3 px-3">Subcontractor</th>
                        <th class="py-3 px-3">Duration</th>
                        <th class="py-3 px-3">Contract Value</th>
                        <th class="py-3 px-3">Certified Gross</th>
                        <th class="py-3 px-3">Unbilled Commitment</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($workOrders as $wo)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3">
                                <a href="{{ route('work-orders.show', $wo) }}" class="font-bold text-gray-900 hover:text-blue-600 block">
                                    {{ $wo->wo_number }}
                                </a>
                                <span class="text-gray-500 text-[11px]">{{ $wo->title }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-medium text-gray-900 block">{{ $wo->project->name }}</span>
                                <span class="text-gray-400 text-[11px]">{{ $wo->site->name ?? 'All Project Sites' }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <a href="{{ route('subcontractors.show', $wo->subcontractor_id) }}" class="font-semibold text-gray-800 hover:text-blue-600 block">
                                    {{ $wo->subcontractor->name }}
                                </a>
                                <span class="text-[10px] text-gray-400 capitalize">{{ str_replace('_', ' ', $wo->work_category) }}</span>
                            </td>
                            <td class="py-3 px-3 text-[11px] text-gray-500">
                                <div>{{ $wo->start_date->format('d M y') }} →</div>
                                <div>{{ $wo->completion_date->format('d M y') }}</div>
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold text-gray-900">
                                ₹{{ number_format($wo->effectiveValue(), 2) }}
                                @if($wo->revised_value > 0)
                                    <span class="block text-[10px] text-amber-600 font-bold">Revised</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-indigo-600 font-bold">
                                ₹{{ number_format($wo->totalCertifiedValue(), 2) }}
                            </td>
                            <td class="py-3 px-3 font-mono text-amber-700 font-semibold">
                                ₹{{ number_format($wo->unbilledCommitment(), 2) }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $wo->status_badge }}">
                                    {{ ucfirst($wo->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('work-orders.show', $wo) }}" class="text-blue-600 font-semibold hover:text-blue-800">BOQ & MB →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-400 text-xs">
                                No subcontractor work orders found matching your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $workOrders->links() }}
        </div>
    </div>
</div>
@endsection
