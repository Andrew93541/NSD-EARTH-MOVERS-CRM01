@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Client Directory</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $stats['total_count'] }} Registered
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Manage project clients, employer organizations, contract values, and billing portfolios.</p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('clients.manage') || $u->hasPermission('clients.*') || $u->hasPermission('projects.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
        @endphp

        @if($canManage)
        <div class="flex items-center gap-3">
            <a href="{{ route('clients.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Register New Client</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Financial & Operational Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Clients</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $stats['active_count'] }}</div>
            <span class="text-[11px] text-slate-500">{{ $stats['total_projects'] }} associated project(s)</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Contract Portfolio</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">₹{{ number_format($stats['total_contract_value'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Agreed contract values</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Client Bills Raised</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-1">₹{{ number_format($stats['total_billed'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Total certified RA bills</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Outstanding Receivable</span>
            <div class="text-2xl font-extrabold {{ $stats['outstanding_balance'] > 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-1">
                ₹{{ number_format($stats['outstanding_balance'], 2) }}
            </div>
            <span class="text-[11px] text-slate-500">₹{{ number_format($stats['total_received'], 2) }} collected</span>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('clients.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-8 relative">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search by client name, company, contact person, GSTIN, PAN, or city..."
                       class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <div class="sm:col-span-3">
                <select name="status" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="sm:col-span-1 flex gap-2">
                <button type="submit" class="w-full py-2 px-3 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 transition">
                    Filter
                </button>
            </div>
        </form>

        @if(request()->hasAny(['search', 'status']))
        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
            <span>Filtered results</span>
            <a href="{{ route('clients.index') }}" class="text-blue-600 hover:text-blue-800 font-medium">Clear all filters</a>
        </div>
        @endif
    </div>

    <!-- Client Directory Table -->
    <div class="soft-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4">Client / Company</th>
                        <th class="py-3 px-4">Contact Info</th>
                        <th class="py-3 px-4">Tax IDs</th>
                        <th class="py-3 px-4">Projects</th>
                        <th class="py-3 px-4 text-right">Billed / Received</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($clients as $c)
                    @php
                        $cTotalBilled = $c->invoices->whereIn('status', ['submitted', 'approved', 'partially_paid', 'paid', 'overdue'])->sum('total_amount');
                        $cTotalPaid = $c->invoices->sum('paid_amount');
                        $cOutstanding = max(0.0, $cTotalBilled - $cTotalPaid);
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4">
                            <a href="{{ route('clients.show', $c) }}" class="font-bold text-slate-900 hover:text-blue-600 transition block">
                                {{ $c->name }}
                            </a>
                            @if($c->company_name && $c->company_name !== $c->name)
                                <span class="text-[11px] text-slate-400">{{ $c->company_name }}</span>
                            @endif
                            @if($c->city || $c->state)
                                <span class="text-[10px] text-slate-400 block">{{ collect([$c->city, $c->state])->filter()->join(', ') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if($c->contact_person)
                                <span class="font-medium text-slate-800 block">{{ $c->contact_person }}</span>
                            @endif
                            @if($c->phone)
                                <span class="text-[11px] text-slate-500 block font-mono">{{ $c->phone }}</span>
                            @endif
                            @if($c->email)
                                <span class="text-[11px] text-slate-400 block">{{ $c->email }}</span>
                            @endif
                            @if(! $c->contact_person && ! $c->phone && ! $c->email)
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-mono text-[11px]">
                            @if($c->gstin)
                                <span class="block text-slate-700"><span class="text-slate-400 text-[10px]">GSTIN:</span> {{ $c->gstin }}</span>
                            @endif
                            @if($c->pan)
                                <span class="block text-slate-500"><span class="text-slate-400 text-[10px]">PAN:</span> {{ $c->pan }}</span>
                            @endif
                            @if(! $c->gstin && ! $c->pan)
                                <span class="text-slate-400 font-sans">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                {{ $c->projects_count }} project(s)
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="font-bold text-slate-900">₹{{ number_format($cTotalBilled, 2) }}</div>
                            <div class="text-[11px] text-emerald-600">Rec: ₹{{ number_format($cTotalPaid, 2) }}</div>
                            @if($cOutstanding > 0)
                                <div class="text-[10px] font-semibold text-amber-600">Due: ₹{{ number_format($cOutstanding, 2) }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($c->status === 'active')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right space-x-2">
                            <a href="{{ route('clients.show', $c) }}" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                View
                            </a>
                            @if($canManage)
                            <a href="{{ route('clients.edit', $c) }}" class="text-slate-500 hover:text-slate-700 font-semibold text-xs">
                                Edit
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400">
                            <div class="max-w-xs mx-auto">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <p class="text-sm font-semibold text-slate-700">No client records found</p>
                                <p class="text-xs text-slate-400 mt-1">Get started by registering a new client employer or try adjusting your search criteria.</p>
                                @if($canManage)
                                <div class="mt-4">
                                    <a href="{{ route('clients.create') }}" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition">
                                        + Register Client
                                    </a>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $clients->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
