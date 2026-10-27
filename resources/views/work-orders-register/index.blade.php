@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Work Orders Register</h2>
            <p class="text-xs text-gray-500 mt-1">Master register for Client-issued contracts (PDF intake & verification) and Internal operational work orders.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('work-orders-register.create', ['tab' => 'client']) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Upload Client WO (PDF)</span>
            </a>
            <a href="{{ route('work-orders-register.create', ['tab' => 'internal']) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ New Internal WO</span>
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Total Active Register</span>
            <div class="text-2xl font-black text-gray-900 mt-1 font-mono">{{ $metrics['total_count'] }}</div>
            <span class="text-[11px] text-gray-400">{{ $metrics['client_count'] }} Client • {{ $metrics['internal_count'] }} Internal</span>
        </div>
        <div class="soft-card p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">Client Work Orders Value</span>
            <div class="text-2xl font-black text-indigo-600 mt-1 font-mono">₹{{ number_format($metrics['client_value'], 2) }}</div>
            <span class="text-[11px] text-gray-400">{{ $metrics['client_count'] }} Contracts in pipeline</span>
        </div>
        <div class="soft-card p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider">Internal Operations Value</span>
            <div class="text-2xl font-black text-blue-600 mt-1 font-mono">₹{{ number_format($metrics['internal_value'], 2) }}</div>
            <span class="text-[11px] text-gray-400">{{ $metrics['internal_count'] }} Self-directed jobs</span>
        </div>
        <div class="soft-card p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[11px] font-bold {{ ($metrics['needs_verification'] > 0 || $metrics['duplicate_flagged'] > 0) ? 'text-amber-600' : 'text-emerald-600' }} uppercase tracking-wider">Verification & Safeguards</span>
            <div class="text-2xl font-black {{ ($metrics['needs_verification'] > 0 || $metrics['duplicate_flagged'] > 0) ? 'text-amber-600' : 'text-emerald-600' }} mt-1 font-mono">
                {{ $metrics['needs_verification'] }}
            </div>
            <div class="flex items-center gap-2 mt-1 text-[11px]">
                <span class="{{ $metrics['needs_verification'] > 0 ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">Pending Staff Review</span>
                @if($metrics['duplicate_flagged'] > 0)
                    <span class="px-1.5 py-0.5 bg-rose-50 text-rose-700 font-bold rounded">{{ $metrics['duplicate_flagged'] }} Duplicates</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Filter & Search Panel -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm space-y-4">
        <!-- Type Tabs -->
        <div class="flex items-center gap-2 border-b border-gray-100 pb-3 text-xs">
            <a href="{{ route('work-orders-register.index', array_merge(request()->except('order_type', 'page'))) }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition {{ !request()->filled('order_type') ? 'bg-slate-900 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                All Types ({{ $metrics['total_count'] }})
            </a>
            <a href="{{ route('work-orders-register.index', array_merge(request()->except('order_type', 'page'), ['order_type' => 'client_issued'])) }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition {{ request('order_type') === 'client_issued' ? 'bg-indigo-600 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                Client Issued ({{ $metrics['client_count'] }})
            </a>
            <a href="{{ route('work-orders-register.index', array_merge(request()->except('order_type', 'page'), ['order_type' => 'internal'])) }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition {{ request('order_type') === 'internal' ? 'bg-blue-600 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                Internal Operations ({{ $metrics['internal_count'] }})
            </a>
        </div>

        <form method="GET" action="{{ route('work-orders-register.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            @if(request()->filled('order_type'))
                <input type="hidden" name="order_type" value="{{ request('order_type') }}">
            @endif

            <!-- Search -->
            <div class="lg:col-span-2">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search WO #, External Ref, Title, Client..."
                       class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Status Filter -->
            <div>
                <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <!-- Verification Filter -->
            <div>
                <select name="extraction_status" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    <option value="">All Verification States</option>
                    <option value="extracted" {{ request('extraction_status') === 'extracted' ? 'selected' : '' }}>Extracted (Needs Review)</option>
                    <option value="scanned_manual_entry_required" {{ request('extraction_status') === 'scanned_manual_entry_required' ? 'selected' : '' }}>Scanned (Manual Entry)</option>
                    <option value="verified" {{ request('extraction_status') === 'verified' ? 'selected' : '' }}>Verified by Staff</option>
                    <option value="none" {{ request('extraction_status') === 'none' ? 'selected' : '' }}>None (Internal)</option>
                </select>
            </div>

            <!-- Filter Actions -->
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl transition w-full">
                    Filter
                </button>
                <a href="{{ route('work-orders-register.index') }}" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-xl transition text-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Work Orders Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3.5 px-4">Internal CRM ID</th>
                        <th class="py-3.5 px-4">Type</th>
                        <th class="py-3.5 px-4">Client / External Ref</th>
                        <th class="py-3.5 px-4">Project & Site</th>
                        <th class="py-3.5 px-4">Key Dates</th>
                        <th class="py-3.5 px-4 text-right">Value (INR)</th>
                        <th class="py-3.5 px-4 text-center">Document Verification</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($workOrders as $wo)
                    <tr class="hover:bg-slate-50/50 transition {{ $wo->is_duplicate_flagged ? 'bg-rose-50/20' : '' }}">
                        <!-- Order Number -->
                        <td class="py-3.5 px-4">
                            <a href="{{ route('work-orders-register.show', $wo) }}" class="font-bold text-blue-600 hover:text-blue-800 hover:underline block font-mono text-[13px]">
                                {{ $wo->order_number }}
                            </a>
                            <span class="text-[11px] text-gray-500 line-clamp-1 max-w-[200px]" title="{{ $wo->title }}">{{ $wo->title }}</span>
                            @if($wo->is_duplicate_flagged)
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-600 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded mt-0.5">
                                    ⚠️ Duplicate Flagged
                                </span>
                            @endif
                        </td>

                        <!-- Type Badge -->
                        <td class="py-3.5 px-4">
                            @if($wo->isClientIssued())
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-semibold rounded-md text-[11px] border border-indigo-100">
                                    Client Issued
                                </span>
                            @else
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-700 font-semibold rounded-md text-[11px] border border-blue-100">
                                    Internal Job
                                </span>
                            @endif
                        </td>

                        <!-- Client & External Ref -->
                        <td class="py-3.5 px-4">
                            @if($wo->client)
                                <span class="font-semibold text-gray-900 block">{{ $wo->client->company_name ?: $wo->client->name }}</span>
                            @elseif($wo->isInternal())
                                <span class="text-gray-400 italic">Self-Directed</span>
                            @else
                                <span class="text-gray-400 italic">Unassigned Client</span>
                            @endif

                            @if($wo->external_reference_number)
                                <span class="text-[11px] text-gray-500 font-mono flex items-center gap-1 mt-0.5">
                                    Ref: <span class="font-semibold text-slate-700">{{ $wo->external_reference_number }}</span>
                                </span>
                            @endif
                        </td>

                        <!-- Project & Site -->
                        <td class="py-3.5 px-4">
                            <span class="font-medium text-gray-900 block">{{ $wo->project ? $wo->project->name : 'General Operations' }}</span>
                            @if($wo->site)
                                <span class="text-[11px] text-gray-400 block">{{ $wo->site->name }}</span>
                            @endif
                        </td>

                        <!-- Dates -->
                        <td class="py-3.5 px-4 text-[11px] text-gray-600 whitespace-nowrap">
                            <div>Issued: <span class="font-medium">{{ $wo->issue_date ? $wo->issue_date->format('d M Y') : '—' }}</span></div>
                            <div class="text-gray-400">Target: <span class="font-medium text-gray-600">{{ $wo->target_completion_date ? $wo->target_completion_date->format('d M Y') : '—' }}</span></div>
                        </td>

                        <!-- Valuation -->
                        <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900">
                            ₹{{ number_format($wo->total_amount, 2) }}
                        </td>

                        <!-- Verification Status -->
                        <td class="py-3.5 px-4 text-center">
                            @if($wo->extraction_status === 'verified')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 font-semibold rounded-full text-[10px] border border-emerald-200">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Verified
                                </span>
                            @elseif($wo->extraction_status === 'extracted')
                                <a href="{{ route('work-orders-register.verify', $wo) }}" class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 font-semibold rounded-full text-[10px] border border-amber-200 hover:bg-amber-100">
                                    Review Extracted Data →
                                </a>
                            @elseif($wo->extraction_status === 'scanned_manual_entry_required')
                                <a href="{{ route('work-orders-register.verify', $wo) }}" class="inline-flex items-center gap-1 px-2 py-0.5 bg-rose-50 text-rose-700 font-semibold rounded-full text-[10px] border border-rose-200 hover:bg-rose-100">
                                    Scanned (Manual Entry) →
                                </a>
                            @else
                                <span class="text-gray-400 text-[10px]">N/A</span>
                            @endif
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3.5 px-4 text-center">
                            @php
                                $statusStyles = [
                                    'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    'pending_approval' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'approved' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'in_progress' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                    'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                ];
                                $style = $statusStyles[$wo->status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                            @endphp
                            <span class="px-2 py-0.5 font-semibold rounded-full text-[10px] border {{ $style }}">
                                {{ ucfirst(str_replace('_', ' ', $wo->status)) }}
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                @if(in_array($wo->extraction_status, ['extracted', 'scanned_manual_entry_required']))
                                    <a href="{{ route('work-orders-register.verify', $wo) }}" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg text-[11px] transition shadow-xs">
                                        Verify
                                    </a>
                                @endif
                                <a href="{{ route('work-orders-register.show', $wo) }}" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-[11px] transition">
                                    Details →
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-gray-400">
                            <div class="max-w-sm mx-auto space-y-2">
                                <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-sm font-semibold text-gray-600">No work orders found</p>
                                <p class="text-xs text-gray-400">Upload a client-issued work order PDF or create an internal work order to start managing operations.</p>
                                <div class="flex justify-center gap-2 pt-2">
                                    <a href="{{ route('work-orders-register.create', ['tab' => 'client']) }}" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold">Upload Client WO</a>
                                    <a href="{{ route('work-orders-register.create', ['tab' => 'internal']) }}" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold">New Internal WO</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($workOrders->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $workOrders->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
