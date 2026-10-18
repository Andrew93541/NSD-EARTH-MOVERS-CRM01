@extends('layouts.app')

@section('title', 'Retention & DLP Register')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Commercials</span>
                <span>•</span>
                <span class="text-blue-600 font-bold">Client Retention & Defect Liability</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Client Retention Register</h1>
            <p class="text-xs text-gray-500 mt-1">Track contractual retention withheld on RA bills, stage eligibility, DLP clearance, and client release claims.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('retention.contracts.create') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Configure Contract Terms</span>
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards (Stitch Design Style) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- 1. Total Withheld -->
        <div class="soft-card p-5 relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Withheld</span>
                <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <strong class="text-xl font-bold font-mono text-gray-900 block">₹{{ number_format($metrics['total_withheld'], 2) }}</strong>
                <span class="text-[11px] text-gray-500 mt-1 block">Deducted on client RA bills</span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-blue-600"></div>
        </div>

        <!-- 2. Eligible for Release -->
        <div class="soft-card p-5 relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Eligible to Claim</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <strong class="text-xl font-bold font-mono text-emerald-600 block">₹{{ number_format($metrics['total_eligible'], 2) }}</strong>
                <span class="text-[11px] text-gray-500 mt-1 block">Milestones & certificates verified</span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
        </div>

        <!-- 3. Pending Review -->
        <div class="soft-card p-5 relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">In Review</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <strong class="text-xl font-bold font-mono text-amber-600 block">₹{{ number_format($metrics['pending_review_amount'], 2) }}</strong>
                <span class="text-[11px] text-gray-500 mt-1 block">Internal approval queue</span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-500"></div>
        </div>

        <!-- 4. Outstanding with Client -->
        <div class="soft-card p-5 relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Billed to Client</span>
                <div class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <strong class="text-xl font-bold font-mono text-purple-600 block">₹{{ number_format($metrics['outstanding_with_client'], 2) }}</strong>
                <span class="text-[11px] text-gray-500 mt-1 block">Awaiting client remittance</span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-purple-500"></div>
        </div>

        <!-- 5. Total Received -->
        <div class="soft-card p-5 relative overflow-hidden group col-span-2 md:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Received / Settled</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <strong class="text-xl font-bold font-mono text-emerald-700 block">₹{{ number_format($metrics['total_received'], 2) }}</strong>
                <span class="text-[11px] text-gray-500 mt-1 block">Net balance: ₹{{ number_format($metrics['net_outstanding_withheld'], 2) }}</span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-700"></div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('retention.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Project</label>
                <select name="project_id" class="w-full text-xs border border-gray-200 rounded-lg p-2 focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-white">
                    <option value="">All Projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Client</label>
                <select name="client_id" class="w-full text-xs border border-gray-200 rounded-lg p-2 focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-white">
                    <option value="">All Clients</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Search Ref / Contract</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Contract # or title..." class="w-full text-xs border border-gray-200 rounded-lg p-2 focus:ring-1 focus:ring-blue-600 focus:border-blue-600">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-xs font-bold hover:bg-gray-800 transition flex items-center justify-center gap-1.5 flex-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filter</span>
                </button>
                @if(request()->anyFilled(['project_id', 'client_id', 'search', 'status']))
                    <a href="{{ route('retention.index') }}" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-semibold transition">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Contract Retention Registers Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Contractual Retention & Defect Liability Terms</h2>
                <p class="text-xs text-gray-400 mt-0.5">Summary of client projects with active retention clauses, milestone eligibility, and release schedules.</p>
            </div>
            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-bold">{{ $contracts->total() }} Contracts</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Contract / Project</th>
                        <th class="py-3 px-4">Client</th>
                        <th class="py-3 px-4">Retention Terms</th>
                        <th class="py-3 px-4 text-right">Withheld (₹)</th>
                        <th class="py-3 px-4 text-right">Eligible (₹)</th>
                        <th class="py-3 px-4 text-right">Received (₹)</th>
                        <th class="py-3 px-4 text-right">Net Balance (₹)</th>
                        <th class="py-3 px-4 text-center">DLP Status</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($contracts as $cnt)
                        @php
                            $sum = $contractsSummaries[$cnt->id] ?? null;
                            $withheld = $sum['total_withheld'] ?? 0;
                            $eligible = $sum['total_remaining_eligible'] ?? 0;
                            $received = $sum['total_received'] ?? 0;
                            $balance = $sum['outstanding_withheld'] ?? 0;
                            $hasPcc = $sum['has_pcc'] ?? false;
                            $dlpExpired = $sum['is_dlp_expired'] ?? false;
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3.5 px-4">
                                <a href="{{ route('retention.contracts.show', $cnt) }}" class="font-bold text-gray-900 hover:text-blue-600 transition block">
                                    {{ $cnt->contract_number }}
                                </a>
                                <span class="text-[11px] text-gray-500 block truncate max-w-xs">{{ $cnt->title }}</span>
                                <div class="flex items-center gap-1.5 mt-1 text-[10px] text-blue-600 font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                    <span>{{ $cnt->project?->name ?? 'Project' }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-medium text-gray-700">
                                {{ $cnt->client?->name ?? '—' }}
                                <span class="block text-[10px] text-gray-400 font-mono">{{ $cnt->client?->gstin ?? '' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-gray-600">
                                <span class="font-bold text-gray-900">{{ number_format($cnt->retention_rate_percent, 1) }}%</span>
                                <span class="text-[10px] text-gray-400 block">
                                    {{ $cnt->release_terms_type === 'single_full_release' ? '100% Post-DLP' : '50% PCC / 50% DLP' }}
                                </span>
                                @if($cnt->max_retention_cap)
                                    <span class="text-[10px] text-purple-600 font-mono block">Cap: ₹{{ number_format($cnt->max_retention_cap, 0) }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($withheld, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold {{ $eligible > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                                ₹{{ number_format($eligible, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-emerald-700 font-semibold">
                                ₹{{ number_format($received, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($balance, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($dlpExpired)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">
                                        DLP Expired
                                    </span>
                                @elseif($sum['dlp_expiry_date'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-blue-50 text-blue-700 border-blue-200" title="Active until {{ $sum['dlp_expiry_date'] }}">
                                        DLP Active ({{ $cnt->dlp_duration_months }}m)
                                    </span>
                                @elseif($hasPcc)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-purple-50 text-purple-700 border-purple-200">
                                        PCC Issued
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-gray-50 text-gray-600 border-gray-200">
                                        Under Construction
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('retention.contracts.show', $cnt) }}" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition">
                                        View Details
                                    </a>
                                    @if($eligible > 0)
                                        <a href="{{ route('retention.contracts.show', $cnt) }}#stages" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-sm">
                                            Claim
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-sm font-semibold text-gray-700">No client contracts configured yet.</p>
                                <p class="text-xs text-gray-400 mt-1">Configure contract retention terms to track stage releases and defect liability.</p>
                                <div class="mt-4">
                                    <a href="{{ route('retention.contracts.create') }}" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow hover:bg-blue-700 transition">
                                        Configure First Contract
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contracts->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $contracts->links() }}
            </div>
        @endif
    </div>

    <!-- Recent Retention Release Claims Section -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Active Retention Release Claims</h2>
                <p class="text-xs text-gray-400 mt-0.5">Claims submitted for internal review, billed to clients, or partially received.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Claim #</th>
                        <th class="py-3 px-4">Contract / Project</th>
                        <th class="py-3 px-4">Release Stage</th>
                        <th class="py-3 px-4 text-right">Claimed (₹)</th>
                        <th class="py-3 px-4 text-right">Approved (₹)</th>
                        <th class="py-3 px-4 text-right">Received (₹)</th>
                        <th class="py-3 px-4 text-right">Balance Due (₹)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($activeClaims as $claim)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                <a href="{{ route('retention.claims.show', $claim) }}" class="hover:text-blue-600 transition">
                                    {{ $claim->claim_number }}
                                </a>
                                @if($claim->client_reference)
                                    <span class="block text-[10px] text-gray-400">Client Ref: {{ $claim->client_reference }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-gray-800 block">{{ $claim->project?->name ?? '—' }}</span>
                                <span class="text-[10px] text-gray-400">{{ $claim->client?->name ?? '' }}</span>
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                {{ $claim->stage_name }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($claim->claimed_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-blue-600 font-semibold">
                                {{ $claim->approved_amount ? '₹' . number_format($claim->approved_amount, 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-600 font-semibold">
                                ₹{{ number_format($claim->received_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($claim->balanceDue(), 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border uppercase {{ $claim->statusBadgeClass() }}">
                                    {{ $claim->statusLabel() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('retention.claims.show', $claim) }}" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-gray-400 text-xs">
                                No active claims yet. Eligible contracts will enable release claim drafting.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
