@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Quotations & Commercial Estimates</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $stats['total_count'] }} Records
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Prepare, issue, and manage client estimates and commercial tender bids for NSD Earth Movers & Construction Ltd.</p>
        </div>

        @php
            $u = auth()->user();
            $canCreate = $u && ($u->hasPermission('*') || $u->hasPermission('quotations.*') || $u->hasPermission('projects.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
        @endphp

        @if($canCreate)
        <div class="flex items-center gap-3">
            <a href="{{ route('quotations.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Quotation / Estimate</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Pipeline Value</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">₹{{ number_format($stats['total_value'], 2) }}</div>
            <span class="text-[11px] text-slate-500">{{ $stats['total_count'] }} total estimate(s)</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Accepted / Awarded Value</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1">₹{{ number_format($stats['accepted_value'], 2) }}</div>
            <span class="text-[11px] text-emerald-600 font-medium">{{ $stats['converted_count'] }} converted to active projects</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pending Client Decision</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-1">{{ $stats['pending_count'] }}</div>
            <span class="text-[11px] text-slate-500">Sent estimates awaiting acceptance</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Project Conversion Rate</span>
            @php
                $conversionRate = $stats['total_count'] > 0 ? round(($stats['converted_count'] / $stats['total_count']) * 100, 1) : 0;
            @endphp
            <div class="text-2xl font-extrabold text-blue-600 mt-1">{{ $conversionRate }}%</div>
            <span class="text-[11px] text-slate-500">Estimates converted to live projects</span>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('quotations.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Search Estimate</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Quote #, Client, Title..."
                       class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Status Filter</label>
                <select name="status" class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent to Client</option>
                    <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
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
                @if(request()->anyFilled(['search', 'status', 'from_date', 'to_date']))
                <a href="{{ route('quotations.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-medium transition" title="Clear Filters">
                    Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Quotation Register Table -->
    <div class="soft-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Quotation #</th>
                        <th class="py-3 px-4">Client</th>
                        <th class="py-3 px-4">Project Title</th>
                        <th class="py-3 px-4">Date / Validity</th>
                        <th class="py-3 px-4 text-right">Taxable Subtotal</th>
                        <th class="py-3 px-4 text-right">Total Amount</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Project Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($quotations as $quote)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4 font-mono font-bold text-blue-600">
                            <a href="{{ route('quotations.show', $quote) }}" class="hover:underline">
                                {{ $quote->quotation_number }}
                            </a>
                        </td>
                        <td class="py-3 px-4 font-medium text-slate-900">
                            <div>{{ $quote->client->name }}</div>
                            <div class="text-[11px] text-slate-400">{{ $quote->client->company_name ?? 'Client' }}</div>
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            <span class="font-semibold block truncate max-w-xs">{{ $quote->project_title }}</span>
                            <span class="text-[11px] text-slate-400">{{ $quote->items->count() }} line item(s)</span>
                        </td>
                        <td class="py-3 px-4 text-slate-600">
                            <div>{{ $quote->date->format('d M Y') }}</div>
                            <div class="text-[11px] text-slate-400">Valid: {{ $quote->valid_until->format('d M Y') }}</div>
                        </td>
                        <td class="py-3 px-4 text-right font-medium text-slate-700">
                            ₹{{ number_format($quote->subtotal, 2) }}
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-slate-900">
                            ₹{{ number_format($quote->total_amount, 2) }}
                            <span class="block text-[10px] text-slate-400 font-normal">({{ strtoupper(str_replace('_', ' ', $quote->tax_type)) }})</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            @php
                                $badgeClass = match($quote->status) {
                                    'draft' => 'bg-slate-100 text-slate-700 border-slate-300',
                                    'sent' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    'expired' => 'bg-gray-100 text-gray-500 border-gray-300',
                                    default => 'bg-slate-50 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                                {{ ucfirst($quote->status) }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($quote->convertedProject)
                                <a href="{{ route('projects.show', $quote->convertedProject) }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition" title="View converted project">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    <span>{{ $quote->convertedProject->code }}</span>
                                </a>
                            @elseif($quote->status === 'accepted')
                                <span class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">Ready to Convert</span>
                            @else
                                <span class="text-[10px] text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('quotations.show', $quote) }}" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Details">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('quotations.print', $quote) }}" target="_blank" class="p-1.5 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition" title="Print Quotation">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-500">
                            <div class="max-w-sm mx-auto">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="font-medium text-slate-700">No commercial quotations found</p>
                                <p class="text-xs text-slate-400 mt-1">Create your first client estimate to start the pre-project commercial pipeline.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($quotations->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $quotations->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
