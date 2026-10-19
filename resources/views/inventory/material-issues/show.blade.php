@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{ returnModalOpen: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('material-issues.index') }}" class="text-xs text-blue-600 hover:underline">← Material Issues Register</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-xs font-bold text-slate-500">{{ $materialIssue->issue_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Material Issue Note</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ $materialIssue->status }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Dispatched from <span class="font-semibold text-slate-800">{{ $materialIssue->warehouse->name }}</span> to
                <span class="font-semibold text-slate-800">{{ $materialIssue->project->name }} ({{ $materialIssue->site->name }})</span> on
                {{ $materialIssue->issue_date->format('d M Y') }}
            </p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('inventory.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'storekeeper', 'project_manager']));
        @endphp

        <!-- Action Bar -->
        <div class="flex items-center gap-2">
            <a href="{{ route('material-issues.print', $materialIssue) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print Delivery Challan</span>
            </a>

            @if($canManage)
            <button type="button" @click="returnModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-xl text-xs font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                <span>Return Material from Site</span>
            </button>
            @endif
        </div>
    </div>

    <!-- Metadata Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Source & Destination Card -->
        <div class="soft-card p-5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Logistics Route</span>
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px]">Source Storage:</span>
                    <span class="font-bold text-slate-900">{{ $materialIssue->warehouse->name }}</span>
                    <span class="text-slate-500 text-[11px] block">({{ $materialIssue->warehouse->location ?? 'Central Yard' }})</span>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-slate-400 block text-[10px]">Destination Project &amp; Site:</span>
                    <span class="font-bold text-blue-600 block">{{ $materialIssue->project->name }}</span>
                    <span class="text-slate-700 font-semibold block">📍 {{ $materialIssue->site->name }}</span>
                </div>
            </div>
        </div>

        <!-- Dispatch Authorization & Personnel -->
        <div class="soft-card p-5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Personnel &amp; Transport</span>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-400">Issued By:</span>
                    <span class="font-semibold text-slate-800">{{ $materialIssue->issuer->name ?? 'Storekeeper' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Requested By:</span>
                    <span class="font-semibold text-slate-800">{{ $materialIssue->requester->name ?? 'Site Staff' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Received By:</span>
                    <span class="font-semibold text-slate-800">{{ $materialIssue->received_by_name ?? 'Site Representative' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Vehicle Number:</span>
                    <span class="font-mono font-bold text-slate-900">{{ $materialIssue->vehicle_number ?? 'Internal Dispatch' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Purpose:</span>
                    <span class="font-medium text-slate-700">{{ $materialIssue->purpose ?? 'Site Consumption' }}</span>
                </div>
            </div>
        </div>

        <!-- Total Cost Valuation -->
        <div class="soft-card p-5 bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex flex-col justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-200 block">Total Issued Valuation</span>
                <div class="text-3xl font-extrabold font-mono mt-2">₹{{ number_format($materialIssue->total_cost, 2) }}</div>
                <div class="text-xs text-blue-100 mt-1">Total Line Items: {{ $materialIssue->items->count() }}</div>
            </div>
            <div class="text-[11px] text-blue-200 pt-3 border-t border-blue-500/50 mt-4">
                <span>Directly costed to Project #{{ $materialIssue->project->code }}</span>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">Dispatched Materials Specification</h3>
            <span class="text-xs text-slate-400">{{ $materialIssue->items->count() }} item(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4">Material Name &amp; Code</th>
                        <th class="py-3 px-4 text-right w-28">Requested Qty</th>
                        <th class="py-3 px-4 text-right w-28">Issued Qty</th>
                        <th class="py-3 px-4 text-center w-24">Unit</th>
                        <th class="py-3 px-4 text-right w-32">Valuation Rate</th>
                        <th class="py-3 px-4 text-right w-36">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($materialIssue->items as $index => $item)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4 font-mono text-center text-slate-400 font-bold">{{ $index + 1 }}</td>
                        <td class="py-3 px-4 text-slate-900 font-medium">
                            <div>{{ $item->material->name }}</div>
                            <span class="font-mono text-[11px] text-slate-400">{{ $item->material->code }}</span>
                            @if($item->remarks)
                            <span class="block text-[11px] text-slate-500 mt-0.5 italic">{{ $item->remarks }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-slate-600">{{ number_format($item->requested_quantity, 3) }}</td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-blue-700">{{ number_format($item->issued_quantity, 3) }}</td>
                        <td class="py-3 px-4 text-center text-slate-600 font-medium uppercase text-[11px]">{{ $item->unit_of_measure }}</td>
                        <td class="py-3 px-4 text-right font-mono text-slate-700">₹{{ number_format($item->unit_rate, 2) }}</td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-bold border-t border-slate-200">
                        <td colspan="6" class="py-3 px-4 text-right text-slate-700">Total Material Cost:</td>
                        <td class="py-3 px-4 text-right font-mono text-blue-700 text-sm">₹{{ number_format($materialIssue->total_cost, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if($materialIssue->remarks)
    <div class="soft-card p-5">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Remarks &amp; Special Instructions</span>
        <p class="text-xs text-slate-700 whitespace-pre-line">{{ $materialIssue->remarks }}</p>
    </div>
    @endif

    <!-- Return Materials Modal -->
    <div x-show="returnModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="returnModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Return Unused Materials to Warehouse</h3>
                </div>
                <button @click="returnModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <p class="text-xs text-slate-600">
                Record surplus materials returned from <span class="font-bold text-slate-900">{{ $materialIssue->site->name }}</span> back into <span class="font-bold text-slate-900">{{ $materialIssue->warehouse->name }}</span>.
            </p>

            <form method="POST" action="{{ route('material-issues.return', $materialIssue) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Return Date *</label>
                    <input type="date" name="return_date" value="{{ date('Y-m-d') }}" required
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-semibold text-slate-700">Materials to Return:</label>
                    @foreach($materialIssue->items as $index => $item)
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between gap-4 text-xs">
                        <div class="min-w-0 flex-1">
                            <span class="font-bold text-slate-900 block">{{ $item->material->name }}</span>
                            <span class="text-[11px] text-slate-500">Originally Issued: {{ number_format($item->issued_quantity, 2) }} {{ $item->unit_of_measure }}</span>
                            <input type="hidden" name="returns[{{ $index }}][item_id]" value="{{ $item->id }}">
                        </div>
                        <div class="w-36">
                            <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">Return Qty ({{ $item->unit_of_measure }})</label>
                            <input type="number" step="0.001" min="0" max="{{ $item->issued_quantity }}"
                                   name="returns[{{ $index }}][quantity]" value="0"
                                   class="w-full text-xs font-mono rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="returnModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition">
                        Confirm Stock Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
