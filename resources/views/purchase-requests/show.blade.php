@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
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
                        'pending' => 'Pending Review',
                        'approved' => 'Approved',
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
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Project: <strong>{{ $request->project->name }}</strong> • Site: <strong>{{ $request->site->name }}</strong>
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
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Submit for Approval</span>
                    </button>
                </form>
            @endif

            @if ($request->status === 'rejected')
                <a href="{{ route('purchase-requests.edit', $request) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                    <span>Revise & Resubmit Request</span>
                </a>
            @endif

            @if ($request->status === 'pending')
                <!-- Approve Button -->
                <form method="POST" action="{{ route('purchase-requests.approve', $request) }}" onsubmit="return confirm('Approve this purchase request for procurement order creation?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Approve Request</span>
                    </button>
                </form>

                <!-- Reject Toggle -->
                <button type="button" onclick="document.getElementById('reject-modal').classList.toggle('hidden')" class="px-3.5 py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl text-xs font-bold transition">
                    Reject Request
                </button>
            @endif

            @if (in_array($request->status, ['approved', 'partially_converted']) && $request->remainingItemsCount() > 0)
                <a href="{{ route('purchase-requests.convert', $request) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span>Convert to Purchase Order</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Rejection Alert (If Rejected) -->
    @if ($request->status === 'rejected')
        <div class="p-4 bg-red-50/90 border border-red-200 rounded-2xl text-xs text-red-800 space-y-1">
            <div class="flex items-center gap-2 font-bold text-red-900">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Request Rejected by {{ $request->approver->name ?? 'Project Manager' }} on {{ $request->approved_at?->format('d M, Y H:i') }}</span>
            </div>
            <p class="pl-6 text-red-700/90">
                <strong>Reason:</strong> {{ $request->rejection_reason }}
            </p>
        </div>
    @endif

    <!-- Reject Reason Form (Hidden by default) -->
    <div id="reject-modal" class="hidden p-5 bg-white border border-red-200 rounded-2xl shadow-lg space-y-3">
        <h4 class="text-xs font-bold text-red-800 uppercase tracking-wider">Reject Purchase Request</h4>
        <form method="POST" action="{{ route('purchase-requests.reject', $request) }}" class="space-y-3">
            @csrf
            <div>
                <label for="rejection_reason" class="block text-xs font-bold text-gray-700 uppercase text-[10px] mb-1">Reason for Rejection * (Mandatory for Audit Trail)</label>
                <textarea id="rejection_reason" name="rejection_reason" rows="2" required placeholder="Specify why this material indent cannot be approved (e.g. Budget exceeded, excess inventory on site, alternative source)..."
                          class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-red-600 focus:outline-none"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition">
                    Confirm Rejection
                </button>
            </div>
        </form>
    </div>

    <!-- Visual Status Timeline -->
    <div class="soft-card p-6">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Requisition Lifecycle Timeline</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
            <!-- Stage 1: Created -->
            <div class="relative pl-6 pb-2 border-l-2 border-blue-600">
                <div class="absolute -left-1.5 top-0 w-3 h-3 rounded-full bg-blue-600"></div>
                <strong class="text-gray-900 block font-bold">1. Draft Created</strong>
                <span class="text-gray-400 text-[11px] block mt-0.5">By {{ $request->requester->name ?? 'Supervisor' }}</span>
                <span class="text-gray-400 text-[10px]">{{ $request->request_date->format('d M, Y') }}</span>
            </div>

            <!-- Stage 2: Submitted -->
            <div class="relative pl-6 pb-2 border-l-2 {{ $request->submitted_at ? 'border-blue-600' : 'border-gray-200' }}">
                <div class="absolute -left-1.5 top-0 w-3 h-3 rounded-full {{ $request->submitted_at ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                <strong class="block font-bold {{ $request->submitted_at ? 'text-gray-900' : 'text-gray-400' }}">2. Submitted</strong>
                <span class="text-gray-400 text-[11px] block mt-0.5">{{ $request->submitted_at ? $request->submitted_at->format('d M, Y H:i') : 'Pending submission' }}</span>
            </div>

            <!-- Stage 3: Approval / Rejection -->
            @php
                $stage3Done = in_array($request->status, ['approved', 'partially_converted', 'po_created', 'rejected']);
                $stage3Color = ($request->status === 'rejected') ? 'border-red-600' : ($stage3Done ? 'border-emerald-600' : 'border-gray-200');
                $stage3Dot = ($request->status === 'rejected') ? 'bg-red-600' : ($stage3Done ? 'bg-emerald-600' : 'bg-gray-300');
            @endphp
            <div class="relative pl-6 pb-2 border-l-2 {{ $stage3Color }}">
                <div class="absolute -left-1.5 top-0 w-3 h-3 rounded-full {{ $stage3Dot }}"></div>
                <strong class="block font-bold {{ $stage3Done ? 'text-gray-900' : 'text-gray-400' }}">
                    {{ $request->status === 'rejected' ? '3. Rejected' : '3. PM Approval' }}
                </strong>
                <span class="text-gray-400 text-[11px] block mt-0.5">
                    @if ($stage3Done)
                        By {{ $request->approver->name ?? 'PM' }} on {{ $request->approved_at?->format('d M, Y') }}
                    @else
                        Awaiting review
                    @endif
                </span>
            </div>

            <!-- Stage 4: PO Conversion -->
            @php
                $stage4Done = in_array($request->status, ['partially_converted', 'po_created']);
            @endphp
            <div class="relative pl-6 pb-2 border-l-2 {{ $stage4Done ? 'border-emerald-600' : 'border-gray-200' }}">
                <div class="absolute -left-1.5 top-0 w-3 h-3 rounded-full {{ $stage4Done ? 'bg-emerald-600' : 'bg-gray-300' }}"></div>
                <strong class="block font-bold {{ $stage4Done ? 'text-gray-900' : 'text-gray-400' }}">4. Purchase Order</strong>
                <span class="text-gray-400 text-[11px] block mt-0.5">
                    @if ($stage4Done)
                        {{ $request->purchaseOrders->count() }} PO(s) Generated
                    @else
                        Pending PO issue
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- Request Particulars & Justification -->
    <div class="soft-card p-6 space-y-4">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Requisition Metadata</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs pb-4 border-b border-gray-100">
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Project</span>
                <strong class="text-gray-900 mt-1 block">{{ $request->project->name }}</strong>
                <span class="text-gray-400 text-[11px]">{{ $request->project->code }}</span>
            </div>
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Site Location</span>
                <strong class="text-gray-900 mt-1 block">{{ $request->site->name }}</strong>
                <span class="text-gray-400 text-[11px]">Supervisor: {{ $request->site->supervisor->name ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Destination Yard</span>
                <strong class="text-gray-900 mt-1 block">{{ $request->warehouse->name ?? 'Central Site Store' }}</strong>
            </div>
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Required-by Date</span>
                <strong class="text-gray-900 mt-1 block font-mono">{{ $request->required_by_date ? $request->required_by_date->format('d M, Y') : 'Immediate' }}</strong>
            </div>
        </div>

        <div>
            <span class="text-gray-400 block font-bold uppercase text-[10px] mb-1">Reason / Site Justification</span>
            <p class="text-xs text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100 leading-relaxed">
                {{ $request->reason }}
            </p>
        </div>
    </div>

    <!-- Requested Materials Grid -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Requested Material Lines</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">Quantities requested, ordered in POs, and current yard stock.</p>
            </div>
            <span class="text-xs font-bold text-blue-700 font-mono">
                Total Est: ₹{{ number_format($request->totalEstimatedCost(), 2) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-2.5">Material</th>
                        <th class="py-2.5">Specs / Grade</th>
                        <th class="py-2.5 text-right">Requested Qty</th>
                        <th class="py-2.5 text-right">Est. Unit Rate</th>
                        <th class="py-2.5 text-right">Est. Line Total</th>
                        <th class="py-2.5 text-right">Already Ordered</th>
                        <th class="py-2.5 text-right">Remaining to Order</th>
                        <th class="py-2.5 text-right">Current Yard Stock</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($request->items as $item)
                        @php
                            $rem = $item->remainingQuantity();
                            $stock = $stockContext[$item->material_id] ?? 0;
                        @endphp
                        <tr>
                            <td class="py-3">
                                <strong class="text-gray-900 block">{{ $item->material->name }}</strong>
                                <span class="text-gray-400 text-[10px] font-mono">{{ $item->material->code }}</span>
                            </td>
                            <td class="py-3 text-gray-600">
                                {{ $item->specifications ?? 'Standard Spec' }}
                            </td>
                            <td class="py-3 text-right font-mono font-bold text-gray-900">
                                {{ number_format($item->quantity_requested, 2) }} {{ $item->material->unit_of_measure }}
                            </td>
                            <td class="py-3 text-right font-mono text-gray-600">
                                ₹{{ number_format($item->estimated_rate, 2) }}
                            </td>
                            <td class="py-3 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($item->estimatedTotal(), 2) }}
                            </td>
                            <td class="py-3 text-right font-mono text-blue-600 font-semibold">
                                {{ number_format($item->quantity_ordered, 2) }}
                            </td>
                            <td class="py-3 text-right font-mono font-bold {{ $rem > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ number_format($rem, 2) }}
                            </td>
                            <td class="py-3 text-right font-mono text-gray-500">
                                {{ number_format($stock, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Linked Purchase Orders (If converted) -->
    @if ($request->purchaseOrders->isNotEmpty())
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Purchase Orders Generated from this Requisition</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                            <th class="py-2">PO #</th>
                            <th class="py-2">Supplier</th>
                            <th class="py-2">PO Date</th>
                            <th class="py-2 text-right">PO Total Amount</th>
                            <th class="py-2 text-center">Status</th>
                            <th class="py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($request->purchaseOrders as $linkedPo)
                            <tr>
                                <td class="py-2.5 font-mono font-bold text-blue-700">{{ $linkedPo->po_number }}</td>
                                <td class="py-2.5 font-bold text-gray-900">{{ $linkedPo->supplier->name ?? '—' }}</td>
                                <td class="py-2.5 text-gray-600">{{ $linkedPo->po_date->format('d M, Y') }}</td>
                                <td class="py-2.5 text-right font-mono font-bold text-gray-900">₹{{ number_format($linkedPo->total_amount, 2) }}</td>
                                <td class="py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700">
                                        {{ str_replace('_', ' ', $linkedPo->status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 text-right">
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
</div>
@endsection
