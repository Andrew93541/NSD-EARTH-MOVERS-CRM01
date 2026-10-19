@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Site Material Issues &amp; Consumption</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $stats['total_issues'] }} Dispatches
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Issue warehouse and yard inventory directly to construction projects and work sites with real-time stock ledger deduction.</p>
        </div>

        @php
            $u = auth()->user();
            $canIssue = $u && ($u->hasPermission('*') || $u->hasPermission('inventory.*') || $u->hasPermission('inventory.issue') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'storekeeper', 'project_manager', 'procurement_officer']));
        @endphp

        @if($canIssue)
        <div class="flex items-center gap-3">
            <a href="{{ route('material-issues.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Issue Materials to Site</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Dispatches</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $stats['total_issues'] }}</div>
            <span class="text-[11px] text-slate-500">Recorded material issue slips</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Value Issued</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-1">₹{{ number_format($stats['total_cost_issued'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Valued at standard inventory rates</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Projects Supplied</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1">{{ $stats['active_projects_receiving'] }}</div>
            <span class="text-[11px] text-slate-500">Active project destinations</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Construction Sites</span>
            <div class="text-2xl font-extrabold text-indigo-600 mt-1">{{ $stats['active_sites_receiving'] }}</div>
            <span class="text-[11px] text-slate-500">Receiving physical delivery</span>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('material-issues.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Search Slip</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Slip #, Vehicle, Receiver..."
                       class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Source Warehouse</label>
                <select name="warehouse_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Destination Project</label>
                <select name="project_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Projects</option>
                    @foreach($projects as $prj)
                        <option value="{{ $prj->id }}" {{ request('project_id') == $prj->id ? 'selected' : '' }}>{{ $prj->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold transition">
                    Filter
                </button>
                @if(request()->anyFilled(['search', 'warehouse_id', 'project_id', 'from_date', 'to_date']))
                <a href="{{ route('material-issues.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-medium transition" title="Clear Filters">
                    Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Material Issues Register Table -->
    <div class="soft-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Issue Slip #</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Source Warehouse</th>
                        <th class="py-3 px-4">Destination Project &amp; Site</th>
                        <th class="py-3 px-4">Materials Issued</th>
                        <th class="py-3 px-4 text-right">Total Cost</th>
                        <th class="py-3 px-4">Issued By / Receiver</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($issues as $issue)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4 font-mono font-bold text-blue-600">
                            <a href="{{ route('material-issues.show', $issue) }}" class="hover:underline">
                                {{ $issue->issue_number }}
                            </a>
                        </td>
                        <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">
                            {{ $issue->issue_date->format('d M Y') }}
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            <span class="font-medium text-slate-900">{{ $issue->warehouse->name }}</span>
                            <span class="block text-[11px] text-slate-400 capitalize">{{ $issue->warehouse->type ?? 'Warehouse' }}</span>
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            <div class="font-bold text-slate-900">{{ $issue->project->name }}</div>
                            <div class="text-[11px] text-blue-600 font-medium">📍 {{ $issue->site->name }}</div>
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            @foreach($issue->items->take(2) as $item)
                                <div class="text-xs">
                                    <span class="font-semibold">{{ $item->material->name }}</span>:
                                    <span class="font-mono text-slate-600">{{ number_format($item->issued_quantity, 2) }} {{ $item->unit_of_measure }}</span>
                                </div>
                            @endforeach
                            @if($issue->items->count() > 2)
                                <div class="text-[10px] text-slate-400">+{{ $issue->items->count() - 2 }} more item(s)</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">
                            ₹{{ number_format($issue->total_cost, 2) }}
                        </td>
                        <td class="py-3 px-4 text-slate-600">
                            <div><span class="text-slate-400 text-[10px]">By:</span> {{ $issue->issuer->name ?? 'Storekeeper' }}</div>
                            @if($issue->received_by_name)
                            <div class="text-[11px] text-slate-500"><span class="text-slate-400 text-[10px]">Rec:</span> {{ $issue->received_by_name }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                {{ $issue->status }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('material-issues.show', $issue) }}" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Slip">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('material-issues.print', $issue) }}" target="_blank" class="p-1.5 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition" title="Print Delivery Note">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-500">
                            <div class="max-w-sm mx-auto">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <p class="font-medium text-slate-700">No material dispatches found</p>
                                <p class="text-xs text-slate-400 mt-1">Issue warehouse materials to a site to begin tracking physical inventory consumption.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($issues->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $issues->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
