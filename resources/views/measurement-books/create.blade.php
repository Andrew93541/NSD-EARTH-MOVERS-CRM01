@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('measurement-books.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Measurement Book</a>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">Record Site Measurement Entry</h2>
            <p class="text-xs text-gray-500">Record physical dimensions, calculate measured volumes/areas, and submit for verification against approved BOQ limits.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-2xl text-xs space-y-1">
            <span class="font-bold">Please correct the following errors:</span>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('measurement-books.store') }}" id="mbForm" class="space-y-6">
        @csrf

        <!-- Work Order & Target BOQ Item -->
        <div class="soft-card p-6 space-y-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Contract Work Order & BOQ Reference</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Subcontract Work Order *</label>
                    <select name="work_order_id" id="woSelect" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        @foreach($workOrders as $wo)
                            <option value="{{ $wo->id }}" data-project="{{ $wo->project_id }}" data-site="{{ $wo->site_id }}" {{ (old('work_order_id', $selectedWo?->id) == $wo->id) ? 'selected' : '' }}>
                                {{ $wo->wo_number }} — {{ $wo->subcontractor->name }} ({{ $wo->title }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Approved BOQ Line Item *</label>
                    <select name="work_order_item_id" id="itemSelect" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        @if($selectedWo)
                            @foreach($selectedWo->items as $item)
                                <option value="{{ $item->id }}" data-unit="{{ $item->unit_of_measure }}" data-approved="{{ $item->approved_quantity }}" data-billed="{{ $item->billed_quantity }}">
                                    {{ $item->item_code }} — {{ $item->description }} (Approved: {{ number_format($item->approved_quantity, 3) }} {{ $item->unit_of_measure }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <input type="hidden" name="project_id" id="projectIdInput" value="{{ $selectedWo?->project_id }}"/>
                <input type="hidden" name="site_id" id="siteIdInput" value="{{ $selectedWo?->site_id }}"/>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Measurement Date *</label>
                    <input type="date" name="measurement_date" value="{{ old('measurement_date', date('Y-m-d')) }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Chainage / Site Location</label>
                    <input type="text" name="location_chainage" value="{{ old('location_chainage') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. CH: 14+200 to 14+550 (LHS Embankment)"/>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Execution Work Description *</label>
                    <textarea name="description" rows="2" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="State exact cross section, layer thickness, compaction pass details, or structural elements measured...">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Physical Dimensions & Mathematical Formula -->
        <div class="soft-card p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Physical Dimension Calculator & Formula</h3>
                <span class="text-[11px] font-mono text-blue-700 font-bold" id="activeUnitDisplay">Unit: cum</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="sm:col-span-4">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Calculation Method / Measurement Formula *</label>
                    <select name="measurement_formula" id="formulaSelect" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="dimensions_l_b_h" selected>Length × Breadth × Depth/Height × Multiplier (Volume / cum)</option>
                        <option value="area_depth_a_d">Length × Breadth × Multiplier (Area / sqm)</option>
                        <option value="unit_count_nos">Linear / Unit Count × Multiplier (rmt / nos)</option>
                        <option value="direct_manual">Direct Quantity Override (Surveyor / Total Station Report)</option>
                    </select>
                </div>

                <div id="dimLengthWrapper">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Length (m)</label>
                    <input type="number" step="0.001" min="0" name="length" id="inputLength" value="{{ old('length', '50.000') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-field"/>
                </div>

                <div id="dimBreadthWrapper">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Breadth / Width (m)</label>
                    <input type="number" step="0.001" min="0" name="breadth" id="inputBreadth" value="{{ old('breadth', '10.000') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-field"/>
                </div>

                <div id="dimDepthWrapper">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Depth / Height (m)</label>
                    <input type="number" step="0.001" min="0" name="depth_height" id="inputDepth" value="{{ old('depth_height', '0.500') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-field"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Multiplier / Repetitions</label>
                    <input type="number" step="0.01" min="0.01" name="multiplier_units" id="inputMultiplier" value="{{ old('multiplier_units', '1.00') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-field"/>
                </div>

                <div class="sm:col-span-2 hidden" id="manualOverrideWrapper">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Manual Quantity Override</label>
                    <input type="number" step="0.001" min="0.001" name="manual_quantity_override" id="inputManual" value="{{ old('manual_quantity_override') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-field" placeholder="Enter surveyor total station quantity..."/>
                </div>

                <div class="sm:col-span-2 hidden" id="overrideReasonWrapper">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Override Reason / Drawing Ref *</label>
                    <input type="text" name="override_reason" id="inputReason" value="{{ old('override_reason') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" placeholder="e.g. As per Joint Total Station Survey cross sections"/>
                </div>
            </div>

            <!-- Mathematical Output & Limit Preview Box -->
            <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-blue-800 tracking-wider">Calculated Measured Quantity</span>
                    <div class="text-2xl font-black text-blue-900 font-mono mt-0.5" id="measuredQtyDisplay">250.000 cum</div>
                    <span class="text-[11px] text-blue-700" id="formulaPreview">50.000 × 10.000 × 0.500 × 1.00</span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">Cumulative BOQ Check</span>
                    <div class="text-sm font-bold text-gray-900 font-mono mt-0.5" id="cumulativeCheckDisplay">250.000 / 1000.000 cum</div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 mt-1" id="limitBadge">
                        ✓ Within Approved Limit
                    </span>
                </div>
            </div>
        </div>

        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Site Remarks & Submission</h3>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Site Engineer Notes / Inspection Observations</label>
                <textarea name="site_remarks" rows="2"
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    placeholder="Field observations, weather conditions, level check readings...">{{ old('site_remarks') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Lifecycle Action *</label>
                <select name="status" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5">
                    <option value="submitted" selected>Submit for Verification & Review (Official Submission)</option>
                    <option value="draft">Save as Draft (Working Scratchpad)</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('measurement-books.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Cancel</a>
            <button type="submit" id="submitMbBtn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/20 transition">Save & Submit Measurement</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formulaSelect = document.getElementById('formulaSelect');
    const inputL = document.getElementById('inputLength');
    const inputB = document.getElementById('inputBreadth');
    const inputD = document.getElementById('inputDepth');
    const inputM = document.getElementById('inputMultiplier');
    const inputManual = document.getElementById('inputManual');
    const inputReason = document.getElementById('inputReason');

    const dimDWrapper = document.getElementById('dimDepthWrapper');
    const dimBWrapper = document.getElementById('dimBreadthWrapper');
    const manualWrapper = document.getElementById('manualOverrideWrapper');
    const reasonWrapper = document.getElementById('overrideReasonWrapper');

    const itemSelect = document.getElementById('itemSelect');
    const unitDisplay = document.getElementById('activeUnitDisplay');
    const measuredQtyDisplay = document.getElementById('measuredQtyDisplay');
    const formulaPreview = document.getElementById('formulaPreview');
    const cumulativeDisplay = document.getElementById('cumulativeCheckDisplay');
    const limitBadge = document.getElementById('limitBadge');
    const submitBtn = document.getElementById('submitMbBtn');

    function updateFormulas() {
        const formula = formulaSelect.value;
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const unit = selectedOption ? selectedOption.dataset.unit : 'cum';
        const approvedLimit = selectedOption ? parseFloat(selectedOption.dataset.approved) : 0;
        const billed = selectedOption ? parseFloat(selectedOption.dataset.billed) : 0;

        unitDisplay.textContent = 'Unit: ' + unit;

        if (formula === 'direct_manual') {
            manualWrapper.classList.remove('hidden');
            reasonWrapper.classList.remove('hidden');
        } else {
            manualWrapper.classList.add('hidden');
            reasonWrapper.classList.add('hidden');
        }

        if (formula === 'area_depth_a_d') {
            dimDWrapper.classList.add('hidden');
            dimBWrapper.classList.remove('hidden');
        } else if (formula === 'unit_count_nos') {
            dimDWrapper.classList.add('hidden');
            dimBWrapper.classList.add('hidden');
        } else {
            dimDWrapper.classList.remove('hidden');
            dimBWrapper.classList.remove('hidden');
        }

        let qty = 0;
        let previewStr = '';

        const l = parseFloat(inputL.value) || 0;
        const b = parseFloat(inputB.value) || 0;
        const d = parseFloat(inputD.value) || 0;
        const m = parseFloat(inputM.value) || 1.0;
        const manual = parseFloat(inputManual.value) || 0;

        if (formula === 'direct_manual' && manual > 0) {
            qty = manual;
            previewStr = 'Direct Manual Override: ' + manual.toFixed(3);
        } else if (formula === 'dimensions_l_b_h') {
            qty = l * b * d * m;
            previewStr = `${l.toFixed(3)} × ${b.toFixed(3)} × ${d.toFixed(3)} × ${m.toFixed(2)}`;
        } else if (formula === 'area_depth_a_d') {
            qty = l * b * m;
            previewStr = `${l.toFixed(3)} × ${b.toFixed(3)} × ${m.toFixed(2)}`;
        } else if (formula === 'unit_count_nos') {
            qty = (l || 1.0) * m;
            previewStr = `${(l || 1.0).toFixed(3)} × ${m.toFixed(2)}`;
        }

        qty = Math.round(qty * 1000) / 1000;
        measuredQtyDisplay.textContent = qty.toFixed(3) + ' ' + unit;
        formulaPreview.textContent = previewStr;

        const cumulative = billed + qty;
        cumulativeDisplay.textContent = cumulative.toFixed(3) + ' / ' + approvedLimit.toFixed(3) + ' ' + unit;

        if (approvedLimit > 0 && cumulative > approvedLimit) {
            limitBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 mt-1';
            limitBadge.textContent = `⚠ Exceeds Approved BOQ Limit by ${(cumulative - approvedLimit).toFixed(3)} ${unit}`;
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            limitBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 mt-1';
            limitBadge.textContent = '✓ Within Approved Limit';
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    document.querySelectorAll('.calc-field').forEach(el => el.addEventListener('input', updateFormulas));
    formulaSelect.addEventListener('change', updateFormulas);
    itemSelect.addEventListener('change', updateFormulas);

    updateFormulas();
});
</script>
@endsection
