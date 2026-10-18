@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('work-orders.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Subcontract Work Orders</a>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">Create Subcontractor Work Order</h2>
            <p class="text-xs text-gray-500">Define scope of work, approved BOQ line items, contractual retention, and statutory deduction rates.</p>
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

    <form method="POST" action="{{ route('work-orders.store') }}" id="workOrderForm" class="space-y-6">
        @csrf

        <!-- General WO Contract Metadata -->
        <div class="soft-card p-6 space-y-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Contract Header & Parties</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Project *</label>
                    <select name="project_id" id="projectId" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="">Select Project</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id', $selectedProjectId) == $p->id ? 'selected' : '' }}>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Target Site / Location</label>
                    <select name="site_id" id="siteId" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5">
                        <option value="">All Project Sites / Specific Site</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" data-project="{{ $site->project_id }}" {{ old('site_id') == $site->id ? 'selected' : '' }}>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Subcontractor *</label>
                    <select name="subcontractor_id" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="">Select Subcontractor</option>
                        @foreach($subcontractors as $sub)
                            <option value="{{ $sub->id }}" {{ old('subcontractor_id', $selectedSubcontractorId) == $sub->id ? 'selected' : '' }}>
                                {{ $sub->name }} ({{ $sub->subcontractor_code ?? 'SC-'.$sub->id }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Work Order Number</label>
                    <input type="text" name="wo_number" value="{{ old('wo_number', $suggestedWoNumber) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Work Order Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. Bulk Earth Excavation, Site Grading & Embankment Compaction"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Primary Work Category *</label>
                    <select name="work_category" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="earthwork" {{ old('work_category') === 'earthwork' ? 'selected' : '' }}>Earthwork & Excavation</option>
                        <option value="civil" {{ old('work_category') === 'civil' ? 'selected' : '' }}>Civil & Masonry Works</option>
                        <option value="concreting" {{ old('work_category') === 'concreting' ? 'selected' : '' }}>RCC Concreting & Formwork</option>
                        <option value="road_works" {{ old('work_category') === 'road_works' ? 'selected' : '' }}>Road Construction & Paving</option>
                        <option value="drainage" {{ old('work_category') === 'drainage' ? 'selected' : '' }}>Drainage, Culverts & Piping</option>
                        <option value="structural" {{ old('work_category') === 'structural' ? 'selected' : '' }}>Structural Steel & Erection</option>
                        <option value="other" {{ old('work_category') === 'other' ? 'selected' : '' }}>Other Subcontracted Work</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Work Package / Phase Ref</label>
                    <input type="text" name="work_package" value="{{ old('work_package') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. WP-01 (Site Preparation)"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Payment Terms</label>
                    <select name="payment_terms" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5">
                        <option value="against_certified_bill" {{ old('payment_terms') === 'against_certified_bill' ? 'selected' : '' }}>Against Certified RA Bill</option>
                        <option value="15_days" {{ old('payment_terms') === '15_days' ? 'selected' : '' }}>15 Days after Certification</option>
                        <option value="30_days" {{ old('payment_terms', '30_days') === '30_days' ? 'selected' : '' }}>30 Days after Certification</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Start Date *</label>
                    <input type="date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Target Completion Date *</label>
                    <input type="date" name="completion_date" value="{{ old('completion_date', date('Y-m-d', strtotime('+3 months'))) }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Scope of Work Specification</label>
                    <textarea name="scope_of_work" rows="2"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="Detailed technical specification of work to be executed by the subcontractor...">{{ old('scope_of_work') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Contractual Retention, TDS & GST Parameters -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Retention & Tax Deduction Rules</h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Retention Withheld (%) *</label>
                    <input type="number" step="0.01" min="0" max="50" name="retention_percent" value="{{ old('retention_percent', '5.00') }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Withheld on gross certified value</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Retention Cap Amount (₹)</label>
                    <input type="number" step="0.01" min="0" name="retention_cap_amount" value="{{ old('retention_cap_amount') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="Optional cap limit"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Maximum total retention cap</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">TDS Rate (%) *</label>
                    <input type="number" step="0.01" min="0" max="30" name="tds_rate_percent" value="{{ old('tds_rate_percent', '2.00') }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Section 194C (1% or 2%)</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">GST Applicable (%) *</label>
                    <input type="number" step="0.01" min="0" max="28" name="gst_rate_percent" value="{{ old('gst_rate_percent', '18.00') }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Input Tax Credit (18%)</span>
                </div>
            </div>
        </div>

        <!-- Itemized BOQ Lines -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Itemized Rate Schedule / Bill of Quantities (BOQ)</h3>
                    <p class="text-[11px] text-gray-500">Each itemized line sets an approved quantity cap for physical measurement and billing.</p>
                </div>
                <button type="button" id="addBoqRowBtn" class="px-3 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    + Add BOQ Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="boqTable">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            <th class="py-2 px-2 w-24">Item Code</th>
                            <th class="py-2 px-2 min-w-[200px]">Description of Work</th>
                            <th class="py-2 px-2 w-28">Unit</th>
                            <th class="py-2 px-2 w-28">Approved Qty</th>
                            <th class="py-2 px-2 w-28">Unit Rate (₹)</th>
                            <th class="py-2 px-2 w-32">Total Amount (₹)</th>
                            <th class="py-2 px-2 w-12 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50" id="boqBody">
                        <tr class="boq-row hover:bg-gray-50/60 transition">
                            <td class="py-2 px-2">
                                <input type="text" name="items[0][item_code]" value="BOQ-01" required
                                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5 font-mono"/>
                            </td>
                            <td class="py-2 px-2">
                                <input type="text" name="items[0][description]" value="Bulk earth excavation in ordinary rock with hydraulic excavators" required
                                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5" placeholder="Description of work executed..."/>
                            </td>
                            <td class="py-2 px-2">
                                <select name="items[0][unit_of_measure]" class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5" required>
                                    <option value="cum" selected>cum (Cubic Metre)</option>
                                    <option value="sqm">sqm (Square Metre)</option>
                                    <option value="rmt">rmt (Running Metre)</option>
                                    <option value="MT">MT (Metric Tonne)</option>
                                    <option value="nos">nos (Number)</option>
                                    <option value="brass">brass (100 cu ft)</option>
                                </select>
                            </td>
                            <td class="py-2 px-2">
                                <input type="number" step="0.001" min="0.001" name="items[0][approved_quantity]" value="1000.000" required
                                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5 font-mono qty-input"/>
                            </td>
                            <td class="py-2 px-2">
                                <input type="number" step="0.01" min="0.01" name="items[0][unit_rate]" value="120.00" required
                                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5 font-mono rate-input"/>
                            </td>
                            <td class="py-2 px-2 font-mono font-bold text-gray-900 line-total text-right pr-4">
                                ₹120,000.00
                            </td>
                            <td class="py-2 px-2 text-center">
                                <button type="button" class="text-gray-400 hover:text-red-600 remove-row-btn transition">✕</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 font-bold bg-gray-50/50">
                            <td colspan="5" class="py-3 px-3 text-right text-gray-700 uppercase tracking-wider text-[11px]">Total Subcontract Order Value:</td>
                            <td class="py-3 px-2 font-mono text-base font-black text-blue-600 text-right pr-4" id="orderTotalDisplay">
                                ₹120,000.00
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('work-orders.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/20 transition">Create & Issue Work Order</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = 1;
    const boqBody = document.getElementById('boqBody');
    const addBtn = document.getElementById('addBoqRowBtn');
    const totalDisplay = document.getElementById('orderTotalDisplay');

    function recalculateTotals() {
        let total = 0;
        document.querySelectorAll('.boq-row').forEach(row => {
            const qtyInput = row.querySelector('.qty-input');
            const rateInput = row.querySelector('.rate-input');
            const lineDisplay = row.querySelector('.line-total');

            const qty = parseFloat(qtyInput.value) || 0;
            const rate = parseFloat(rateInput.value) || 0;
            const lineTotal = qty * rate;

            lineDisplay.textContent = '₹' + lineTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            total += lineTotal;
        });

        totalDisplay.textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    boqBody.addEventListener('input', function(e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('rate-input')) {
            recalculateTotals();
        }
    });

    boqBody.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row-btn')) {
            if (document.querySelectorAll('.boq-row').length > 1) {
                e.target.closest('tr').remove();
                recalculateTotals();
            } else {
                alert('At least one BOQ item is required.');
            }
        }
    });

    addBtn.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.className = 'boq-row hover:bg-gray-50/60 transition';
        const codeNum = String(document.querySelectorAll('.boq-row').length + 1).padStart(2, '0');
        tr.innerHTML = `
            <td class="py-2 px-2">
                <input type="text" name="items[${rowIndex}][item_code]" value="BOQ-${codeNum}" required
                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5 font-mono"/>
            </td>
            <td class="py-2 px-2">
                <input type="text" name="items[${rowIndex}][description]" value="" required
                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5" placeholder="Description of work executed..."/>
            </td>
            <td class="py-2 px-2">
                <select name="items[${rowIndex}][unit_of_measure]" class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5" required>
                    <option value="cum" selected>cum (Cubic Metre)</option>
                    <option value="sqm">sqm (Square Metre)</option>
                    <option value="rmt">rmt (Running Metre)</option>
                    <option value="MT">MT (Metric Tonne)</option>
                    <option value="nos">nos (Number)</option>
                    <option value="brass">brass (100 cu ft)</option>
                </select>
            </td>
            <td class="py-2 px-2">
                <input type="number" step="0.001" min="0.001" name="items[${rowIndex}][approved_quantity]" value="100.000" required
                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5 font-mono qty-input"/>
            </td>
            <td class="py-2 px-2">
                <input type="number" step="0.01" min="0.01" name="items[${rowIndex}][unit_rate]" value="100.00" required
                    class="w-full text-xs rounded-xl border-gray-200 px-2 py-1.5 font-mono rate-input"/>
            </td>
            <td class="py-2 px-2 font-mono font-bold text-gray-900 line-total text-right pr-4">
                ₹10,000.00
            </td>
            <td class="py-2 px-2 text-center">
                <button type="button" class="text-gray-400 hover:text-red-600 remove-row-btn transition">✕</button>
            </td>
        `;
        boqBody.appendChild(tr);
        rowIndex++;
        recalculateTotals();
    });

    recalculateTotals();
});
</script>
@endsection
