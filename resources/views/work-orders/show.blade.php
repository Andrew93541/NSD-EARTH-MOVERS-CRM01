@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('work-orders.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold">← Work Orders</a>
                <span class="text-gray-400">/</span>
                <span class="font-mono text-gray-500">{{ $workOrder->wo_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ $workOrder->title }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $workOrder->status_badge }}">
                    {{ ucfirst($workOrder->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Subcontractor: <a href="{{ route('subcontractors.show', $workOrder->subcontractor_id) }}" class="text-blue-600 font-semibold hover:underline">{{ $workOrder->subcontractor->name }}</a> |
                Project: <span class="font-medium text-gray-800">{{ $workOrder->project->name }}</span> ({{ $workOrder->site->name ?? 'All Sites' }})
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(in_array($workOrder->status, ['draft', 'submitted']))
                <form method="POST" action="{{ route('work-orders.approve', $workOrder) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Approve Work Order</span>
                    </button>
                </form>
            @endif

            @if(in_array($workOrder->status, ['approved', 'active']))
                <a href="{{ route('measurement-books.create', ['work_order_id' => $workOrder->id]) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Record MB Entry</span>
                </a>

                <a href="{{ route('subcontractor-bills.create', ['work_order_id' => $workOrder->id]) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/></svg>
                    <span>Generate RA Bill</span>
                </a>

                <button type="button" onclick="document.getElementById('variationModal').classList.remove('hidden')"
                    class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                    + Add Variation
                </button>
            @endif
        </div>
    </div>

    <!-- 4 KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase">Original Contract Value</span>
            <div class="text-xl font-black text-gray-900 mt-1 font-mono">₹{{ number_format($workOrder->contract_value, 2) }}</div>
            <span class="text-[11px] text-gray-400">Base approved BOQ</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-amber-600 uppercase">Effective Revised Value</span>
            <div class="text-xl font-black text-amber-600 mt-1 font-mono">₹{{ number_format($workOrder->effectiveValue(), 2) }}</div>
            <span class="text-[11px] text-gray-400">{{ $workOrder->variations->count() }} Approved Variations</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-indigo-600 uppercase">Gross Certified Work</span>
            <div class="text-xl font-black text-indigo-600 mt-1 font-mono">₹{{ number_format($workOrder->totalCertifiedValue(), 2) }}</div>
            <span class="text-[11px] text-gray-400">Billed in certified RA bills</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-emerald-600 uppercase">Unbilled Order Balance</span>
            <div class="text-xl font-black text-emerald-600 mt-1 font-mono">₹{{ number_format($workOrder->unbilledCommitment(), 2) }}</div>
            <span class="text-[11px] text-gray-400">Committed project cost</span>
        </div>
    </div>

    <!-- Contract Rules & Metadata -->
    <div class="soft-card p-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 text-xs">
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Category</span>
                <span class="font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $workOrder->work_category) }}</span>
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Execution Period</span>
                <span class="font-medium text-gray-800">{{ $workOrder->start_date->format('d M y') }} → {{ $workOrder->completion_date->format('d M y') }}</span>
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Retention Withheld</span>
                <span class="font-mono font-bold text-gray-900">{{ $workOrder->retention_percent }}%</span>
                @if($workOrder->retention_cap_amount)
                    <span class="text-gray-400 text-[10px] block">Cap: ₹{{ number_format($workOrder->retention_cap_amount, 2) }}</span>
                @endif
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">TDS Deduction</span>
                <span class="font-mono font-bold text-gray-900">{{ $workOrder->tds_rate_percent }}%</span>
                <span class="text-gray-400 text-[10px] block">u/s 194C</span>
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">GST Applicable</span>
                <span class="font-mono font-bold text-gray-900">{{ $workOrder->gst_rate_percent }}%</span>
                <span class="text-gray-400 text-[10px] block">Input Tax Credit</span>
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Payment Terms</span>
                <span class="font-medium text-gray-800 capitalize">{{ str_replace('_', ' ', $workOrder->payment_terms) }}</span>
            </div>
        </div>
        @if($workOrder->scope_of_work)
            <div class="mt-4 pt-3 border-t border-gray-100 text-xs">
                <span class="text-gray-400 block text-[10px] uppercase font-bold mb-1">Scope of Work</span>
                <p class="text-gray-700 leading-relaxed">{{ $workOrder->scope_of_work }}</p>
            </div>
        @endif
    </div>

    <!-- BOQ Itemized Schedule -->
    <div class="soft-card p-5 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Approved Bill of Quantities (BOQ) Schedule</h3>
                <p class="text-[11px] text-gray-500">Execution tracking against approved contract limits. Quantities cannot exceed approved amounts without an authorized variation.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-2.5 px-3">Item Code</th>
                        <th class="py-2.5 px-3">Description</th>
                        <th class="py-2.5 px-3">Unit</th>
                        <th class="py-2.5 px-3">Approved Qty</th>
                        <th class="py-2.5 px-3">Billed Qty</th>
                        <th class="py-2.5 px-3">Remaining Qty</th>
                        <th class="py-2.5 px-3">Progress</th>
                        <th class="py-2.5 px-3">Unit Rate</th>
                        <th class="py-2.5 px-3 text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($workOrder->items as $item)
                        @php
                            $approved = (float) $item->approved_quantity;
                            $billed = (float) $item->billed_quantity;
                            $remaining = max(0.0, $approved - $billed);
                            $pct = $approved > 0 ? min(100, round(($billed / $approved) * 100, 1)) : 0;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">{{ $item->item_code }}</td>
                            <td class="py-3 px-3 font-medium text-gray-800">{{ $item->description }}</td>
                            <td class="py-3 px-3 text-gray-500 font-mono">{{ $item->unit_of_measure }}</td>
                            <td class="py-3 px-3 font-mono font-semibold">{{ number_format($approved, 3) }}</td>
                            <td class="py-3 px-3 font-mono text-indigo-600 font-bold">{{ number_format($billed, 3) }}</td>
                            <td class="py-3 px-3 font-mono text-emerald-700 font-semibold">{{ number_format($remaining, 3) }}</td>
                            <td class="py-3 px-3 w-32">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-500 font-mono">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono text-gray-700">₹{{ number_format($item->unit_rate, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-gray-900 text-right">₹{{ number_format($item->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 font-bold bg-gray-50/50">
                        <td colspan="8" class="py-3 px-3 text-right uppercase text-[10px] text-gray-500 tracking-wider">Total Contract BOQ Value:</td>
                        <td class="py-3 px-3 font-mono text-sm font-black text-blue-600 text-right">₹{{ number_format($workOrder->contract_value, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Controlled Variations / Revisions Table -->
    @if($workOrder->variations->isNotEmpty())
        <div class="soft-card p-5 space-y-3">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Controlled Variations & Revisions Audit Trail</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            <th class="py-2 px-2">Variation #</th>
                            <th class="py-2 px-2">Type</th>
                            <th class="py-2 px-2">Item Affected</th>
                            <th class="py-2 px-2">Previous Qty/Rate</th>
                            <th class="py-2 px-2">Revised Qty/Rate</th>
                            <th class="py-2 px-2">Cost Impact</th>
                            <th class="py-2 px-2">Reason</th>
                            <th class="py-2 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($workOrder->variations as $var)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-2 px-2 font-mono font-bold text-gray-900">{{ $var->variation_number }}</td>
                                <td class="py-2 px-2 capitalize">{{ str_replace('_', ' ', $var->variation_type) }}</td>
                                <td class="py-2 px-2 font-mono text-gray-700">{{ $var->workOrderItem ? $var->workOrderItem->item_code : 'General Scope' }}</td>
                                <td class="py-2 px-2 font-mono text-gray-500">{{ $var->previous_quantity ? number_format($var->previous_quantity, 3) : '—' }}</td>
                                <td class="py-2 px-2 font-mono font-bold text-gray-900">{{ $var->revised_quantity ? number_format($var->revised_quantity, 3) : '—' }}</td>
                                <td class="py-2 px-2 font-mono font-bold {{ $var->value_impact >= 0 ? 'text-blue-600' : 'text-rose-600' }}">
                                    {{ $var->value_impact >= 0 ? '+' : '' }}₹{{ number_format($var->value_impact, 2) }}
                                </td>
                                <td class="py-2 px-2 text-gray-600">{{ $var->reason }}</td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $var->status_badge }}">
                                        {{ ucfirst($var->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Recent MB Entries & RA Bills Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Measurements -->
        <div class="soft-card p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Site Measurements (MB)</h3>
                <a href="{{ route('measurement-books.index', ['work_order_id' => $workOrder->id]) }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">View All MB →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            <th class="py-2 px-2">MB #</th>
                            <th class="py-2 px-2">Date</th>
                            <th class="py-2 px-2">Item</th>
                            <th class="py-2 px-2">Quantity</th>
                            <th class="py-2 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($recentMbEntries as $mb)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-2 px-2 font-mono font-bold">
                                    <a href="{{ route('measurement-books.show', $mb) }}" class="text-gray-900 hover:text-blue-600">{{ $mb->mb_number }}</a>
                                </td>
                                <td class="py-2 px-2 text-gray-500">{{ $mb->measurement_date->format('d M y') }}</td>
                                <td class="py-2 px-2 font-mono text-[11px]">{{ $mb->workOrderItem->item_code ?? '—' }}</td>
                                <td class="py-2 px-2 font-mono font-bold text-gray-900">{{ number_format($mb->measured_quantity, 3) }}</td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold border {{ $mb->status_badge }}">
                                        {{ ucfirst($mb->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-400 text-xs">No site measurements recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Bills -->
        <div class="soft-card p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Running Account Bills</h3>
                <a href="{{ route('subcontractor-bills.index', ['work_order_id' => $workOrder->id]) }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">View All Bills →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            <th class="py-2 px-2">Bill #</th>
                            <th class="py-2 px-2">Date</th>
                            <th class="py-2 px-2">Gross Work</th>
                            <th class="py-2 px-2">Net Payable</th>
                            <th class="py-2 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($workOrder->bills as $b)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-2 px-2 font-mono font-bold">
                                    <a href="{{ route('subcontractor-bills.show', $b) }}" class="text-gray-900 hover:text-blue-600">{{ $b->bill_number }}</a>
                                </td>
                                <td class="py-2 px-2 text-gray-500">{{ $b->bill_date->format('d M y') }}</td>
                                <td class="py-2 px-2 font-mono">₹{{ number_format($b->gross_work_value, 2) }}</td>
                                <td class="py-2 px-2 font-mono font-bold text-gray-900">₹{{ number_format($b->net_payable_amount, 2) }}</td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold border {{ $b->status_badge }}">
                                        {{ ucfirst(str_replace('_', ' ', $b->status)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-400 text-xs">No running account bills certified yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Subcontractor Work Order Agreements & Annexures -->
    <x-document-manager 
        :record="$workOrder" 
        type="work_order" 
        title="Subcontractor Work Order Agreements & Technical Annexures"
        :categories="[
            'contract' => 'Signed Work Order Contract Agreement',
            'drawing' => 'Construction Scope Drawings',
            'specification' => 'Schedule of Rates & BOQ Annexure',
            'general' => 'General Work Order Addendum',
        ]"
    />
</div>

<!-- Modal for Controlled Work Order Variation -->
<div id="variationModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-900">Add Controlled Work Order Variation</h3>
            <button type="button" onclick="document.getElementById('variationModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" action="{{ route('work-orders.variations.store', $workOrder) }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-gray-700 mb-1">Variation Type *</label>
                <select name="variation_type" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2" required>
                    <option value="quantity_increase">Quantity Increase (BOQ Scope Expansion)</option>
                    <option value="rate_revision">Rate Revision (Approved Escalation / Price Adjustment)</option>
                    <option value="scope_addition">Scope Addition (Extra Work Specification)</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Target BOQ Item *</label>
                <select name="work_order_item_id" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2" required>
                    @foreach($workOrder->items as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->item_code }} — {{ $item->description }} (Current: {{ number_format($item->approved_quantity, 3) }} {{ $item->unit_of_measure }} @ ₹{{ $item->unit_rate }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">New Total Approved Quantity</label>
                    <input type="number" step="0.001" min="0.001" name="revised_quantity"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 font-mono" placeholder="e.g. 1500.000"/>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">New Unit Rate (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="revised_rate"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 font-mono" placeholder="Optional rate revision"/>
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Engineering / Commercial Justification *</label>
                <textarea name="reason" rows="3" required minlength="5"
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500"
                    placeholder="Provide site justification, drawing revision reference, or geotechnical change order reasons..."></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('variationModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shadow-sm">Authorize Variation</button>
            </div>
        </form>
    </div>
</div>
@endsection
