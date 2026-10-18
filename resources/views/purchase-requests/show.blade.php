@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchase-requests.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Material Indents</a>
                <span class="text-xs text-gray-400">/</span>
                <span class="text-xs font-semibold text-gray-800">{{ $request->pr_number }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 mt-1.5">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $request->pr_number }}</h2>
                @php
                    $statusStyles = [
                        'draft' => 'bg-gray-100 text-gray-700',
                        'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                        'approved' => 'bg-blue-50 text-blue-700 border border-blue-200',
                        'partially_converted' => 'bg-purple-50 text-purple-700 border border-purple-200',
                        'po_created' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                        'rejected' => 'bg-red-50 text-red-700 border border-red-200',
                        'cancelled' => 'bg-gray-100 text-gray-400',
                    ];
                    $statusLabels = [
                        'draft' => 'Draft',
                        'pending' => "Pending Authorization (Level {$request->current_approval_level} of {$request->required_approval_levels})",
                        'approved' => 'Fully Approved',
                        'partially_converted' => 'Partially Converted',
                        'po_created' => 'PO Issued',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ];
                @endphp
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase font-mono {{ $statusStyles[$request->status] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $statusLabels[$request->status] ?? $request->status }}
                </span>

                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $request->priority === 'critical' ? 'bg-red-100 text-red-700 animate-pulse' : ($request->priority === 'urgent' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') }}">
                    {{ $request->priority }}
                </span>

                @if ($request->is_over_budget)
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-50 text-red-700 border border-red-200 flex items-center gap-1">
                        <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Over Budget (+₹{{ number_format($request->budget_overrun_amount, 2) }})</span>
                    </span>
                @endif

                @if (! $request->is_pricing_complete)
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-50 text-amber-800 border border-amber-200">
                        Incomplete Pricing / Rate Estimate Needed
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Project: <strong>{{ $request->project->name }}</strong> ({{ $request->project->code }}) • Site: <strong>{{ $request->site->name }}</strong> • Created by: <strong>{{ $request->requester->name ?? 'Supervisor' }}</strong>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('purchase-requests.index') }}" class="px-3.5 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-white border border-gray-200 rounded-xl transition">
                ← Back to List
            </a>

            @if ($request->status === 'draft')
                <a href="{{ route('purchase-requests.edit', $request) }}" class="px-3.5 py-2 text-xs font-bold text-gray-700 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-xl transition">
                    Edit Draft
                </a>
                <form method="POST" action="{{ route('purchase-requests.submit', $request) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Submit for Level 1 Review</span>
                    </button>
                </form>
            @endif

            @if ($request->status === 'rejected')
                <a href="{{ route('purchase-requests.edit', $request) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                    <span>Revise & Resubmit Request</span>
                </a>
            @endif

            @if ($request->status === 'pending')
                @if ($canApproveCheck['can_approve'])
                    <!-- Approve Button triggers Approval Action Modal -->
                    <button type="button" onclick="document.getElementById('approve-modal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Authorize Level {{ $request->current_approval_level }}</span>
                    </button>
                    <!-- Reject Toggle -->
                    <button type="button" onclick="document.getElementById('reject-modal').classList.remove('hidden')" class="px-3.5 py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl text-xs font-bold transition cursor-pointer">
                        Reject Request
                    </button>
                @else
                    <div class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-[11px] text-gray-600 flex items-center gap-1.5" title="{{ $canApproveCheck['reason'] }}">
                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-8a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Awaiting Tier {{ $request->current_approval_level }} Approver</span>
                    </div>
                @endif
            @endif

            @if (in_array($request->status, ['approved', 'partially_converted']) && $request->remainingItemsCount() > 0)
                <a href="{{ route('purchase-requests.convert', $request) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span>Convert to Purchase Order</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Authorization Guidance Banner (If Pending) -->
    @if ($request->status === 'pending' && ! $canApproveCheck['can_approve'])
        <div class="p-3.5 bg-amber-50/80 border border-amber-200 rounded-2xl text-xs text-amber-900 flex items-start gap-3">
            <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <strong>Approval Authority Notice:</strong> {{ $canApproveCheck['reason'] }}
                <span class="text-amber-800/80 block mt-0.5">Threshold policies and segregation of duties are strictly enforced on the server.</span>
            </div>
        </div>
    @endif

    <!-- Rejection Alert (If Rejected) -->
    @if ($request->status === 'rejected')
        <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-xs text-red-800 space-y-1">
            <div class="flex items-center gap-2 font-bold text-red-900">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Request Rejected on {{ $request->approved_at?->format('d M, Y H:i') }}</span>
            </div>
            <p class="pl-6 text-red-700 leading-relaxed">
                <strong>Reason:</strong> {{ $request->rejection_reason }}
            </p>
        </div>
    @endif

    <!-- 1. PROJECT BUDGET & FINANCIAL CONTROLS CARD -->
    <div class="soft-card p-6 space-y-5 border-l-4 {{ $budgetAnalysis['is_over_budget'] ? 'border-l-red-500' : 'border-l-blue-600' }}">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Project Expenditure Budget & Financial Controls</h3>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $budgetAnalysis['badge_class'] }}">
                        {{ $budgetAnalysis['status_label'] }}
                    </span>
                </div>
                <p class="text-[11px] text-gray-500 mt-0.5">Calculated using live posted expenses, approved supplier invoices, and open purchase order commitments.</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Estimated Requisition Total</span>
                <span class="text-base font-black font-mono text-gray-900">₹{{ number_format($request->estimated_total, 2) }}</span>
            </div>
        </div>

        <!-- 4-Metric Financial Breakdown -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div class="p-3 bg-gray-50/70 rounded-xl border border-gray-100">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Approved Project Budget</span>
                @if ($budgetAnalysis['has_budget'])
                    <strong class="text-gray-900 mt-1 block font-mono text-sm">₹{{ number_format($budgetAnalysis['approved_budget'], 2) }}</strong>
                    <span class="text-gray-400 text-[10px]">Target expenditure cap</span>
                @else
                    <strong class="text-amber-700 mt-1 block text-xs">Budget Not Configured</strong>
                    <span class="text-gray-400 text-[10px]">Set in project master</span>
                @endif
            </div>

            <div class="p-3 bg-gray-50/70 rounded-xl border border-gray-100">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Actual Posted Costs</span>
                <strong class="text-gray-900 mt-1 block font-mono text-sm">₹{{ number_format($budgetAnalysis['actual_costs'], 2) }}</strong>
                <span class="text-gray-400 text-[10px]">Expenses + Supplier Bills</span>
            </div>

            <div class="p-3 bg-gray-50/70 rounded-xl border border-gray-100">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Committed Costs</span>
                <strong class="text-gray-900 mt-1 block font-mono text-sm">₹{{ number_format($budgetAnalysis['committed_costs'], 2) }}</strong>
                <span class="text-gray-400 text-[10px]">Unbilled POs + Open PRs</span>
            </div>

            <div class="p-3 bg-gray-50/70 rounded-xl border border-gray-100">
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Remaining (Post-Request)</span>
                <strong class="mt-1 block font-mono text-sm {{ $budgetAnalysis['remaining_after'] < 0 ? 'text-red-600 font-bold' : 'text-emerald-700 font-bold' }}">
                    {{ $budgetAnalysis['remaining_after'] < 0 ? '-' : '' }}₹{{ number_format(abs($budgetAnalysis['remaining_after']), 2) }}
                </strong>
                <span class="text-gray-400 text-[10px]">
                    Before request: ₹{{ number_format($budgetAnalysis['remaining_before'], 2) }}
                </span>
            </div>
        </div>

        @if ($budgetAnalysis['has_budget'])
            <!-- Utilization Bar -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-gray-500">
                        Budget Consumption: <strong>{{ $budgetAnalysis['utilization_percent_after'] }}%</strong>
                        (Prior to this indent: {{ $budgetAnalysis['utilization_percent_before'] }}%)
                    </span>
                    <span class="font-mono text-gray-600">
                        ₹{{ number_format($budgetAnalysis['total_committed_and_actual'] + (float)$request->estimated_total, 2) }} / ₹{{ number_format($budgetAnalysis['approved_budget'], 2) }}
                    </span>
                </div>
                <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden flex">
                    @php
                        $actualPct = min(100.0, ($budgetAnalysis['actual_costs'] / $budgetAnalysis['approved_budget']) * 100);
                        $commitPct = min(100.0 - $actualPct, ($budgetAnalysis['committed_costs'] / $budgetAnalysis['approved_budget']) * 100);
                        $newPct = min(max(0.0, 100.0 - $actualPct - $commitPct), ((float)$request->estimated_total / $budgetAnalysis['approved_budget']) * 100);
                    @endphp
                    <div class="bg-blue-600 h-full" style="width: {{ $actualPct }}%" title="Actual: {{ round($actualPct, 1) }}%"></div>
                    <div class="bg-purple-500 h-full" style="width: {{ $commitPct }}%" title="Commitments: {{ round($commitPct, 1) }}%"></div>
                    <div class="{{ $budgetAnalysis['is_over_budget'] ? 'bg-red-500 animate-pulse' : 'bg-amber-400' }} h-full" style="width: {{ $newPct }}%" title="This Request: {{ round($newPct, 1) }}%"></div>
                </div>
                <div class="flex items-center gap-4 text-[10px] text-gray-400 pt-1">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-600"></span> Actual Incurred</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500"></span> Open Commitments</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full {{ $budgetAnalysis['is_over_budget'] ? 'bg-red-500' : 'bg-amber-400' }}"></span> Current Request</span>
                </div>
            </div>
        @endif

        @if ($budgetAnalysis['is_over_budget'])
            <div class="p-3.5 bg-red-50 border border-red-200 rounded-xl text-xs text-red-900 space-y-1">
                <div class="flex items-center gap-2 font-bold text-red-800">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Budget Overrun Notice: Estimated cost exceeds remaining approved project budget by ₹{{ number_format($budgetAnalysis['overrun_amount'], 2) }}.</span>
                </div>
                <p class="pl-6 text-red-700/90 leading-relaxed text-[11px]">
                    @if ($request->budget_override_approved)
                        <span class="text-emerald-700 font-bold">✓ Budget override authorized:</span> "{{ $request->budget_override_reason }}"
                    @else
                        This requisition cannot proceed to purchase-order conversion without an authorized budget override recorded by a Company Owner or Administrator.
                    @endif
                </p>
            </div>
        @endif
    </div>

    <!-- 2. REQUESTED MATERIALS & REAL-TIME STOCK SHORTFALL ANALYSIS -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Requested Materials & Inventory Stock Check</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Live stock verified against the Stock Ledger for destination store <strong>{{ $request->warehouse->name ?? 'Central Site Store' }}</strong>.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-gray-400 italic">No silent reservation or stock deduction occurs during indent review.</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-2.5 px-2">Material</th>
                        <th class="py-2.5 px-2 text-right">Requested</th>
                        <th class="py-2.5 px-2 text-right">Available in Yard</th>
                        <th class="py-2.5 px-2 text-right">Ordered in POs</th>
                        <th class="py-2.5 px-2 text-right">Remaining to Procure</th>
                        <th class="py-2.5 px-2 text-right">Stock Shortfall</th>
                        <th class="py-2.5 px-2 text-center">Fulfillment Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($stockAnalysis['line_analyses'] as $line)
                        @php
                            $it = $line['item'];
                            $material = $line['material'];
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-2">
                                <strong class="text-gray-900 block font-bold">{{ $material->name ?? 'Material' }}</strong>
                                <div class="flex items-center gap-2 mt-0.5 text-[10px] text-gray-400">
                                    <span class="font-mono">{{ $material->code ?? '—' }}</span>
                                    <span>•</span>
                                    <span>{{ $it->specifications ?? 'Standard Spec' }}</span>
                                    <span>•</span>
                                    <span class="font-mono">Est: ₹{{ number_format($it->estimated_rate, 2) }}/{{ $line['uom'] }}</span>
                                </div>
                                @if ($line['transfer_available'])
                                    <div class="mt-1 text-[11px] text-blue-700 bg-blue-50/80 px-2 py-0.5 rounded border border-blue-200 inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        <span><strong>Transfer Available:</strong> {{ number_format($line['other_warehouses_stock'], 2) }} {{ $line['uom'] }} in other company yards</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-2 text-right font-mono font-bold text-gray-900">
                                {{ number_format($line['quantity_requested'], 2) }} <span class="text-[10px] text-gray-400">{{ $line['uom'] }}</span>
                            </td>
                            <td class="py-3 px-2 text-right font-mono font-semibold {{ $line['target_warehouse_stock'] > 0 ? 'text-emerald-700' : 'text-gray-400' }}">
                                {{ number_format($line['target_warehouse_stock'], 2) }}
                            </td>
                            <td class="py-3 px-2 text-right font-mono text-blue-600 font-semibold">
                                {{ number_format($line['quantity_ordered'], 2) }}
                            </td>
                            <td class="py-3 px-2 text-right font-mono font-bold text-gray-900">
                                {{ number_format($line['remaining_to_procure'], 2) }}
                            </td>
                            <td class="py-3 px-2 text-right font-mono font-bold {{ $line['shortfall'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ number_format($line['shortfall'], 2) }}
                            </td>
                            <td class="py-3 px-2 text-center">
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border uppercase {{ $line['status_badge_class'] }}">
                                    {{ $line['status_label'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between text-xs text-gray-600">
            <span>Overall Stock Shortfall: <strong class="font-mono text-gray-900">{{ number_format($stockAnalysis['total_shortfall_units'], 2) }} units</strong></span>
            <span>Procurement Action: <strong>{{ $stockAnalysis['overall_status_label'] }}</strong></span>
        </div>
    </div>

    <!-- 3. MULTI-LEVEL APPROVAL AUDIT LOG & DECISIONS -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Multi-Tier Approval Matrix & Audit Log</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Recorded approval decisions, policy thresholds, and budget exception overrides.</p>
            </div>
            <span class="text-xs font-bold text-gray-700">
                Current Level: <strong>{{ $request->current_approval_level }}</strong> / Required: <strong>{{ $request->required_approval_levels }}</strong>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-2.5 px-3">Tier</th>
                        <th class="py-2.5 px-3">Approver</th>
                        <th class="py-2.5 px-3">Decision</th>
                        <th class="py-2.5 px-3">Date & Timestamp</th>
                        <th class="py-2.5 px-3">Budget Override</th>
                        <th class="py-2.5 px-3">Comments / Justification</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($request->approvals as $approval)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    Level {{ $approval->approval_level }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <strong class="text-gray-900 block font-bold">{{ $approval->approver->name ?? 'Approver' }}</strong>
                                <span class="text-gray-400 text-[10px]">{{ $approval->approver->currentRole()?->name ?? 'Authorized' }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $approval->action === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                    {{ strtoupper($approval->action) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-gray-500 font-mono">
                                {{ $approval->created_at->format('d M, Y H:i') }}
                            </td>
                            <td class="py-3 px-3">
                                @if ($approval->is_budget_override)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        Override Authorized
                                    </span>
                                @else
                                    <span class="text-gray-400 text-[11px]">Standard</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-gray-700 max-w-xs">
                                {{ $approval->comments ?? ($approval->override_reason ?? '—') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-center text-gray-400">
                                No authorization decisions recorded yet. Requisition is awaiting Level {{ $request->current_approval_level }} review.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. LINKED PURCHASE ORDERS -->
    @if ($request->purchaseOrders->isNotEmpty())
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Purchase Orders Generated from this Requisition</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                            <th class="py-2 px-3">PO #</th>
                            <th class="py-2 px-3">Supplier</th>
                            <th class="py-2 px-3">PO Date</th>
                            <th class="py-2 px-3 text-right">PO Total Amount</th>
                            <th class="py-2 px-3 text-center">Status</th>
                            <th class="py-2 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($request->purchaseOrders as $linkedPo)
                            <tr>
                                <td class="py-2.5 px-3 font-mono font-bold text-blue-700">{{ $linkedPo->po_number }}</td>
                                <td class="py-2.5 px-3 font-bold text-gray-900">{{ $linkedPo->supplier->name ?? '—' }}</td>
                                <td class="py-2.5 px-3 text-gray-600">{{ $linkedPo->po_date->format('d M, Y') }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900">₹{{ number_format($linkedPo->total_amount, 2) }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700">
                                        {{ str_replace('_', ' ', $linkedPo->status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <a href="{{ route('procurement.show', $linkedPo) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                                        View PO →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- APPROVE MODAL -->
    <div id="approve-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">Authorize Purchase Request (Level {{ $request->current_approval_level }})</h3>
                <button type="button" onclick="document.getElementById('approve-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
            </div>

            <form method="POST" action="{{ route('purchase-requests.approve', $request) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Approval Notes / Comments (Optional)</label>
                    <textarea name="comments" rows="2" placeholder="Review remarks, warehouse fulfillment notes, or supplier instructions..."
                              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
                </div>

                @if ($request->is_over_budget)
                    <div class="p-3.5 bg-red-50 border border-red-200 rounded-xl text-xs text-red-900 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-red-800">
                            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Mandatory Budget Override Authorization</span>
                        </div>
                        <p class="text-[11px] text-red-700">
                            This purchase request exceeds project budget by ₹{{ number_format($request->budget_overrun_amount, 2) }}. Company policy requires explicit authorization and reason.
                        </p>
                        <label class="flex items-center gap-2 pt-1 font-bold text-red-900 cursor-pointer">
                            <input type="checkbox" name="is_budget_override" value="1" required class="rounded text-red-600 focus:ring-red-500">
                            <span>I authorize this project budget overrun exception *</span>
                        </label>
                        <div>
                            <label class="block text-[10px] font-bold text-red-800 uppercase tracking-wider mb-1 mt-2">Override Reason * (Mandatory)</label>
                            <textarea name="override_reason" rows="2" required placeholder="Specify why the budget overrun is necessary (e.g. Critical monsoon deadline, design change order, client reimbursement)..."
                                      class="w-full px-3 py-2 bg-white border border-red-300 rounded-xl text-xs focus:ring-2 focus:ring-red-600 focus:outline-none"></textarea>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('approve-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition">
                        Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- REJECT MODAL -->
    <div id="reject-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-bold text-red-900">Reject Purchase Request</h3>
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
            </div>

            <form method="POST" action="{{ route('purchase-requests.reject', $request) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Reason for Rejection * (Mandatory for Audit Trail)</label>
                    <textarea name="rejection_reason" rows="3" required placeholder="Specify why this material indent cannot be approved (e.g. Budget exceeded, excess inventory available on site, price dispute)..."
                              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-red-600 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-md shadow-red-500/20 transition">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
