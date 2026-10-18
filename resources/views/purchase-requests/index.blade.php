@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Site Material Requests & Indents</h2>
            <p class="text-xs text-gray-500 mt-1">Field requisitions, Project Manager approvals, and procurement PO conversions.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('purchase-requests.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ New Material Request</span>
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="soft-card p-5">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Total Indents</span>
            <div class="text-2xl font-black text-gray-900 tracking-tight mt-2 font-mono">
                {{ $stats['total_requests'] }}
            </div>
            <span class="text-[11px] text-gray-400 mt-1 block">All logged site requests</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-xs font-bold text-amber-500 uppercase tracking-wider block">Pending Approval</span>
            <div class="text-2xl font-black text-amber-600 tracking-tight mt-2 font-mono">
                {{ $stats['pending_approval'] }}
            </div>
            <span class="text-[11px] text-amber-600/80 mt-1 block">Awaiting PM review</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-xs font-bold text-blue-500 uppercase tracking-wider block">Approved for PO</span>
            <div class="text-2xl font-black text-blue-600 tracking-tight mt-2 font-mono">
                {{ $stats['approved'] }}
            </div>
            <span class="text-[11px] text-blue-600/80 mt-1 block">Ready for procurement conversion</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-xs font-bold text-emerald-500 uppercase tracking-wider block">Converted to PO</span>
            <div class="text-2xl font-black text-emerald-600 tracking-tight mt-2 font-mono">
                {{ $stats['converted'] }}
            </div>
            <span class="text-[11px] text-emerald-600/80 mt-1 block">POs issued to suppliers</span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('purchase-requests.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs">
            <div>
                <label class="block font-bold text-gray-600 uppercase text-[10px] mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none">
                    <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending Review</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="partially_converted" {{ request('status') == 'partially_converted' ? 'selected' : '' }}>Partially Converted</option>
                    <option value="po_created" {{ request('status') == 'po_created' ? 'selected' : '' }}>PO Created</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-600 uppercase text-[10px] mb-1">Priority</label>
                <select name="priority" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none">
                    <option value="all" {{ request('priority') == 'all' ? 'selected' : '' }}>All Priorities</option>
                    <option value="normal" {{ request('priority') == 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                    <option value="critical" {{ request('priority') == 'critical' ? 'selected' : '' }}>Critical</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-600 uppercase text-[10px] mb-1">Project</label>
                <select name="project_id" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none">
                    <option value="all">All Projects</option>
                    @foreach ($projects as $prj)
                        <option value="{{ $prj->id }}" {{ request('project_id') == $prj->id ? 'selected' : '' }}>{{ $prj->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-600 uppercase text-[10px] mb-1">Site</label>
                <select name="site_id" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none">
                    <option value="all">All Sites</option>
                    @foreach ($sites as $st)
                        <option value="{{ $st->id }}" {{ request('site_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full px-4 py-2 bg-gray-900 hover:bg-black text-white rounded-xl font-bold transition">
                    Filter
                </button>
                <a href="{{ route('purchase-requests.index') }}" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl font-bold text-center transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Requests Table -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Material Requisitions Register</h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-3">Request #</th>
                        <th class="py-3 px-3">Project & Site</th>
                        <th class="py-3 px-3">Requester</th>
                        <th class="py-3 px-3">Required By</th>
                        <th class="py-3 px-3">Items</th>
                        <th class="py-3 px-3 text-right">Est. Cost</th>
                        <th class="py-3 px-3 text-center">Priority</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($requests as $req)
                        <tr class="hover:bg-gray-50/70 transition">
                            <td class="py-3 px-3 font-mono font-bold text-blue-700">
                                <a href="{{ route('purchase-requests.show', $req) }}" class="hover:underline">
                                    {{ $req->pr_number }}
                                </a>
                            </td>
                            <td class="py-3 px-3">
                                <strong class="text-gray-900 block">{{ $req->project->name ?? '—' }}</strong>
                                <span class="text-gray-400 text-[11px]">{{ $req->site->name ?? 'All Site Operations' }}</span>
                            </td>
                            <td class="py-3 px-3 text-gray-600">
                                {{ $req->requester->name ?? 'Site Supervisor' }}
                                <span class="text-gray-400 block text-[10px]">{{ $req->request_date->format('d M, Y') }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold {{ $req->required_by_date && $req->required_by_date->isPast() ? 'text-red-600' : 'text-gray-700' }}">
                                {{ $req->required_by_date ? $req->required_by_date->format('d M, Y') : 'Immediate' }}
                            </td>
                            <td class="py-3 px-3 font-mono text-gray-700">
                                {{ $req->items->count() }} item(s)
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-gray-900">
                                <div>₹{{ number_format($req->estimated_total > 0 ? $req->estimated_total : $req->totalEstimatedCost(), 2) }}</div>
                                @if ($req->is_over_budget)
                                    <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-red-600 bg-red-50 px-1.5 py-0.2 rounded border border-red-200 uppercase">
                                        Over Budget
                                    </span>
                                @endif
                                @if (! $req->is_pricing_complete)
                                    <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200 uppercase">
                                        Unpriced
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if ($req->priority === 'critical')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 uppercase animate-pulse">
                                        Critical
                                    </span>
                                @elseif ($req->priority === 'urgent')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 uppercase">
                                        Urgent
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 uppercase">
                                        Normal
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center">
                                @php
                                    $statusClasses = [
                                        'draft' => 'bg-gray-100 text-gray-600',
                                        'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                                        'approved' => 'bg-blue-50 text-blue-700 border border-blue-200',
                                        'partially_converted' => 'bg-purple-50 text-purple-700 border border-purple-200',
                                        'po_created' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                        'rejected' => 'bg-red-50 text-red-700 border border-red-200',
                                        'cancelled' => 'bg-gray-100 text-gray-400',
                                    ];
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusClasses[$req->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    @if ($req->status === 'pending')
                                        Pending L{{ $req->current_approval_level }}/{{ $req->required_approval_levels }}
                                    @elseif ($req->status === 'approved')
                                        Approved
                                    @elseif ($req->status === 'partially_converted')
                                        Partial PO
                                    @elseif ($req->status === 'po_created')
                                        PO Issued
                                    @else
                                        {{ ucfirst($req->status) }}
                                    @endif
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('purchase-requests.show', $req) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition">
                                    View →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-gray-400">
                                No material purchase requests matching the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection
