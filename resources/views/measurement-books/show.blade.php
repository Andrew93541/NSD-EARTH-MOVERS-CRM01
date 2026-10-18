@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('measurement-books.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold">← Measurement Book</a>
                <span class="text-gray-400">/</span>
                <span class="font-mono text-gray-500">{{ $measurementBook->mb_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">Measurement Sheet #{{ $measurementBook->mb_number }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $measurementBook->status_badge }}">
                    {{ ucfirst($measurementBook->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Measured on <span class="font-medium text-gray-800">{{ $measurementBook->measurement_date->format('d M Y') }}</span> by {{ $measurementBook->measuredBy->name ?? 'Site Engineer' }} |
                Work Order: <a href="{{ route('work-orders.show', $measurementBook->work_order_id) }}" class="text-blue-600 font-semibold hover:underline">{{ $measurementBook->workOrder->wo_number }}</a>
            </p>
        </div>

        <!-- Approval / Rejection Actions -->
        <div class="flex flex-wrap items-center gap-2">
            @if(in_array($measurementBook->status, ['draft', 'submitted']))
                <form method="POST" action="{{ route('measurement-books.approve', $measurementBook) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Approve Measurement</span>
                    </button>
                </form>

                <button type="button" onclick="document.getElementById('rejectModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-semibold transition">
                    Reject Entry
                </button>
            @endif

            @if($measurementBook->status === 'rejected')
                <button type="button" onclick="document.getElementById('resubmitModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    Correct & Resubmit
                </button>
            @endif

            @if($measurementBook->status === 'approved' && !$measurementBook->certified_in_bill_id)
                <a href="{{ route('subcontractor-bills.create', ['work_order_id' => $measurementBook->work_order_id]) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    Include in RA Bill →
                </a>
            @endif

            @if($measurementBook->certified_in_bill_id)
                <a href="{{ route('subcontractor-bills.show', $measurementBook->certified_in_bill_id) }}" class="px-4 py-2 bg-gray-900 text-white rounded-xl text-xs font-semibold hover:bg-gray-800 transition">
                    View Certified RA Bill →
                </a>
            @endif
        </div>
    </div>

    @if($measurementBook->status === 'rejected')
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-900 flex items-start gap-3">
            <span class="text-lg">⚠</span>
            <div>
                <span class="font-bold block">Measurement Rejected by Engineer:</span>
                <p class="mt-0.5">{{ $measurementBook->rejection_reason }}</p>
                <span class="text-[10px] text-rose-700 mt-1 block">Reviewed on {{ $measurementBook->reviewed_at?->format('d M Y H:i') }}</span>
            </div>
        </div>
    @endif

    <!-- Calculation & Dimension Analysis Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Physical Dimensions breakdown -->
        <div class="lg:col-span-2 soft-card p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Physical Dimension Measurements</h3>
                <span class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 text-[10px] font-bold rounded-md uppercase">
                    {{ str_replace('_', ' ', $measurementBook->measurement_formula) }}
                </span>
            </div>

            <!-- 4 Measurement Metrics -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-gray-50/70 p-4 rounded-2xl border border-gray-100">
                <div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase">Length (L)</span>
                    <div class="text-base font-black text-gray-900 font-mono mt-0.5">{{ $measurementBook->length !== null ? number_format($measurementBook->length, 3).' m' : '—' }}</div>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase">Breadth (B)</span>
                    <div class="text-base font-black text-gray-900 font-mono mt-0.5">{{ $measurementBook->breadth !== null ? number_format($measurementBook->breadth, 3).' m' : '—' }}</div>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase">Depth / Height (D)</span>
                    <div class="text-base font-black text-gray-900 font-mono mt-0.5">{{ $measurementBook->depth_height !== null ? number_format($measurementBook->depth_height, 3).' m' : '—' }}</div>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase">Multiplier / Units</span>
                    <div class="text-base font-black text-gray-900 font-mono mt-0.5">×{{ number_format($measurementBook->multiplier_units, 2) }}</div>
                </div>
            </div>

            @if($measurementBook->manual_quantity_override !== null)
                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 space-y-1">
                    <span class="font-bold flex items-center gap-1.5">
                        <span>ℹ Manual Quantity Override:</span>
                        <span class="font-mono text-amber-950 font-black">{{ number_format($measurementBook->manual_quantity_override, 3) }} {{ $measurementBook->workOrderItem->unit_of_measure }}</span>
                    </span>
                    <p class="text-[11px] text-amber-800">Reason: {{ $measurementBook->override_reason }}</p>
                </div>
            @endif

            <!-- Measured Total Box -->
            <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-blue-800 tracking-wider">Certified Measured Quantity</span>
                    <div class="text-3xl font-black text-blue-900 font-mono mt-1">
                        {{ number_format($measurementBook->measured_quantity, 3) }}
                        <span class="text-base font-semibold text-blue-700">{{ $measurementBook->workOrderItem->unit_of_measure }}</span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">Cumulative Execution</span>
                    <div class="text-base font-black text-gray-900 font-mono mt-1">
                        {{ number_format($measurementBook->cumulative_quantity, 3) }}
                        <span class="text-xs text-gray-500 font-normal">/ {{ number_format($measurementBook->workOrderItem->approved_quantity, 3) }}</span>
                    </div>
                    <span class="text-[11px] text-emerald-700 font-bold block mt-0.5">
                        Remaining: {{ number_format(max(0, $measurementBook->workOrderItem->approved_quantity - $measurementBook->cumulative_quantity), 3) }} {{ $measurementBook->workOrderItem->unit_of_measure }}
                    </span>
                </div>
            </div>

            <!-- Scope Details -->
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Execution Description</span>
                    <p class="text-gray-800 font-medium leading-relaxed">{{ $measurementBook->description }}</p>
                </div>
                @if($measurementBook->site_remarks)
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-gray-400 block text-[10px] uppercase font-bold">Site Engineer Remarks</span>
                        <p class="text-gray-600">{{ $measurementBook->site_remarks }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Metadata & Workflow Lifecycle -->
        <div class="soft-card p-5 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Contract & Audit Trail</h3>
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Target BOQ Item</span>
                    <span class="font-mono font-bold text-blue-700 block">{{ $measurementBook->workOrderItem->item_code }}</span>
                    <span class="text-gray-800">{{ $measurementBook->workOrderItem->description }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Project & Site</span>
                    <span class="font-semibold text-gray-900 block">{{ $measurementBook->project->name }}</span>
                    <span class="text-gray-500 text-[11px]">{{ $measurementBook->site->name ?? 'All Project Sites' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Chainage / Location</span>
                    <span class="font-mono text-gray-900 font-semibold">{{ $measurementBook->location_chainage ?? 'General Site Area' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Measurement Period</span>
                    <span class="font-medium text-gray-800">{{ $measurementBook->period_start_date->format('d M y') }} → {{ $measurementBook->period_end_date->format('d M y') }}</span>
                </div>
                <div class="pt-2 border-t border-gray-100">
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Prepared By</span>
                    <span class="font-medium text-gray-900 block">{{ $measurementBook->measuredBy->name ?? '—' }}</span>
                    <span class="text-[10px] text-gray-400">{{ $measurementBook->submitted_at?->format('d M Y H:i') }}</span>
                </div>
                @if($measurementBook->reviewed_by)
                    <div>
                        <span class="text-gray-400 block text-[10px] uppercase font-bold">Reviewed & Verified By</span>
                        <span class="font-medium text-gray-900 block">{{ $measurementBook->reviewedBy->name }}</span>
                        <span class="text-[10px] text-gray-400">{{ $measurementBook->reviewed_at?->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($measurementBook->certified_in_bill_id)
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-gray-400 block text-[10px] uppercase font-bold">Certified In RA Bill</span>
                        <a href="{{ route('subcontractor-bills.show', $measurementBook->certified_in_bill_id) }}" class="font-mono font-bold text-indigo-600 hover:underline">
                            {{ $measurementBook->certifiedInBill?->bill_number ?? 'RA-Bill #'.$measurementBook->certified_in_bill_id }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Field Survey Sheets, Cross-Sections & Inspection Photos -->
    <x-document-manager 
        :record="$measurementBook" 
        type="measurement_book" 
        title="Field Survey Sheets, Level Profiles & Chainage Photos"
        :categories="[
            'measurement_sheet' => 'Field Survey Level Sheet',
            'inspection_photo' => 'Site Progress Photo at Chainage',
            'drawing' => 'Cross-Section & Profile Sketch',
            'general' => 'Surveyor Field Remarks Memo',
        ]"
    />
</div>

<!-- Modal: Reject Measurement -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-900">Reject Measurement Entry</h3>
            <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" action="{{ route('measurement-books.reject', $measurementBook) }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-gray-700 mb-1">Reason for Rejection * (Mandatory min. 5 chars)</label>
                <textarea name="rejection_reason" rows="3" required minlength="5"
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-red-500"
                    placeholder="Provide specific reason (e.g. Layer thickness exceeded survey levels; cross section chainage incorrect)..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-semibold shadow-sm">Reject Measurement</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Correct and Resubmit -->
<div id="resubmitModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-900">Correct & Resubmit Measurement</h3>
            <button type="button" onclick="document.getElementById('resubmitModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" action="{{ route('measurement-books.resubmit', $measurementBook) }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-gray-700 mb-1">Calculation Method</label>
                <select name="measurement_formula" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="dimensions_l_b_h" {{ $measurementBook->measurement_formula === 'dimensions_l_b_h' ? 'selected' : '' }}>Length × Breadth × Depth × Multiplier</option>
                    <option value="area_depth_a_d" {{ $measurementBook->measurement_formula === 'area_depth_a_d' ? 'selected' : '' }}>Length × Breadth × Multiplier</option>
                    <option value="unit_count_nos" {{ $measurementBook->measurement_formula === 'unit_count_nos' ? 'selected' : '' }}>Unit Count × Multiplier</option>
                    <option value="direct_manual" {{ $measurementBook->measurement_formula === 'direct_manual' ? 'selected' : '' }}>Direct Manual Override</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Length (m)</label>
                    <input type="number" step="0.001" min="0" name="length" value="{{ $measurementBook->length }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-2.5 py-2 font-mono"/>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Breadth (m)</label>
                    <input type="number" step="0.001" min="0" name="breadth" value="{{ $measurementBook->breadth }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-2.5 py-2 font-mono"/>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Depth (m)</label>
                    <input type="number" step="0.001" min="0" name="depth_height" value="{{ $measurementBook->depth_height }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-2.5 py-2 font-mono"/>
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1">Description / Correction Notes</label>
                <textarea name="description" rows="2" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">{{ $measurementBook->description }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('resubmitModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shadow-sm">Resubmit Measurement</button>
            </div>
        </form>
    </div>
</div>
@endsection
