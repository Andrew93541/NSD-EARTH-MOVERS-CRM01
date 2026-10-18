@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('subcontractor-bills.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Subcontractor RA Bills</a>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">Generate Subcontractor Running Account (RA) Bill</h2>
            <p class="text-xs text-gray-500">Aggregate approved site measurements into an auditable interim certificate with retention, TDS, and GST calculations.</p>
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

    <form method="POST" action="{{ route('subcontractor-bills.store') }}" id="billForm" class="space-y-6">
        @csrf

        <!-- Work Order Selection Card -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Target Subcontract Work Order</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Approved Work Order *</label>
                    <select name="work_order_id" id="woSelector" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" onchange="window.location.href = '{{ route('subcontractor-bills.create') }}?work_order_id=' + this.value" required>
                        @foreach($workOrders as $wo)
                            <option value="{{ $wo->id }}" {{ ($selectedWo?->id == $wo->id) ? 'selected' : '' }}>
                                {{ $wo->wo_number }} — {{ $wo->subcontractor->name }} ({{ $wo->title }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Bill Sequence & Number *</label>
                    <input type="text" name="bill_number" value="{{ old('bill_number', $suggestedBillNumber) }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Bill Date *</label>
                    <input type="date" name="bill_date" value="{{ old('bill_date', date('Y-m-d')) }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Billing Period Start</label>
                    <input type="date" name="period_start_date" value="{{ old('period_start_date', $unbilledMbEntries->min('period_start_date')?->format('Y-m-d') ?? date('Y-m-01')) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Billing Period End</label>
                    <input type="date" name="period_end_date" value="{{ old('period_end_date', $unbilledMbEntries->max('period_end_date')?->format('Y-m-d') ?? date('Y-m-d')) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>
            </div>
        </div>

        <!-- Eligible Approved Measurement Book Entries -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Eligible Approved Measurement Entries</h3>
                    <p class="text-[11px] text-gray-500">Only verified and approved site measurements can be certified. Check all entries to include in this bill:</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="selectAllBtn" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Select All</button>
                    <span class="text-gray-300">|</span>
                    <button type="button" id="deselectAllBtn" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Deselect All</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3 w-10">Select</th>
                            <th class="py-2.5 px-3">MB Number & Date</th>
                            <th class="py-2.5 px-3">BOQ Item</th>
                            <th class="py-2.5 px-3">Chainage / Description</th>
                            <th class="py-2.5 px-3 text-right">Measured Quantity</th>
                            <th class="py-2.5 px-3 text-right">Unit Rate</th>
                            <th class="py-2.5 px-3 text-right">Gross Work Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($unbilledMbEntries as $mb)
                            @php
                                $lineGross = round((float) $mb->measured_quantity * (float) $mb->workOrderItem->unit_rate, 2);
                            @endphp
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-3 px-3">
                                    <input type="checkbox" name="measurement_entry_ids[]" value="{{ $mb->id }}" data-amount="{{ $lineGross }}"
                                        class="mb-checkbox rounded text-blue-600 focus:ring-blue-500 h-4 w-4" checked/>
                                </td>
                                <td class="py-3 px-3 font-mono">
                                    <span class="font-bold text-gray-900 block">{{ $mb->mb_number }}</span>
                                    <span class="text-gray-400 text-[11px]">{{ $mb->measurement_date->format('d M Y') }}</span>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="font-mono font-bold text-blue-700 block">{{ $mb->workOrderItem->item_code }}</span>
                                    <span class="text-gray-700 text-[11px]">{{ $mb->workOrderItem->description }}</span>
                                </td>
                                <td class="py-3 px-3 text-gray-600">
                                    <span class="font-medium text-gray-800 block">{{ $mb->location_chainage ?? 'General Site' }}</span>
                                    <span class="text-[11px] text-gray-500 truncate block max-w-xs">{{ $mb->description }}</span>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-gray-900 text-right">
                                    {{ number_format($mb->measured_quantity, 3) }}
                                    <span class="text-gray-500 font-normal text-[10px]">{{ $mb->workOrderItem->unit_of_measure }}</span>
                                </td>
                                <td class="py-3 px-3 font-mono text-gray-700 text-right">
                                    ₹{{ number_format($mb->workOrderItem->unit_rate, 2) }}
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-gray-900 text-right">
                                    ₹{{ number_format($lineGross, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-400 text-xs">
                                    No approved unbilled Measurement Book entries found for this Work Order.
                                    <a href="{{ route('measurement-books.create', ['work_order_id' => $selectedWo?->id]) }}" class="text-blue-600 font-semibold ml-1 hover:underline">
                                        Record new measurement →
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deductions & Net Payable Calculator -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Contractual Deductions & Statutory Taxes</h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Retention Withheld (%) *</label>
                    <input type="number" step="0.01" min="0" max="50" name="retention_rate_percent" id="retentionPercentInput"
                        value="{{ old('retention_rate_percent', $selectedWo?->retention_percent ?? 5.00) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-input"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Contractual Retention</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">TDS Rate (%) *</label>
                    <input type="number" step="0.01" min="0" max="30" name="tds_rate_percent" id="tdsPercentInput"
                        value="{{ old('tds_rate_percent', $selectedWo?->tds_rate_percent ?? 2.00) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-input"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Income Tax Section 194C</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">GST Applicable (%) *</label>
                    <input type="number" step="0.01" min="0" max="28" name="gst_rate_percent" id="gstPercentInput"
                        value="{{ old('gst_rate_percent', $selectedWo?->gst_rate_percent ?? 18.00) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-input"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Input Tax Credit (ITC)</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Advance Recovery (₹)</label>
                    <input type="number" step="0.01" min="0" name="advance_recovery_amount" id="advanceRecoveryInput"
                        value="{{ old('advance_recovery_amount', '0.00') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono calc-input"/>
                    <span class="text-[10px] text-gray-400 mt-1 block">Mobilization Advance recoupment</span>
                </div>
            </div>

            <!-- Mathematical Summary Card -->
            <div class="mt-4 p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-2 text-xs">
                <div class="flex justify-between items-center py-1">
                    <span class="text-gray-600 font-medium">Selected Gross Certified Work Value:</span>
                    <span class="font-mono font-bold text-gray-900 text-sm" id="grossWorkDisplay">₹0.00</span>
                </div>
                <div class="flex justify-between items-center py-1 text-emerald-700">
                    <span>(+) GST Input Tax Credit:</span>
                    <span class="font-mono font-bold" id="gstAmountDisplay">+₹0.00</span>
                </div>
                <div class="flex justify-between items-center py-1 text-amber-700">
                    <span>(-) Retention Money Withheld:</span>
                    <span class="font-mono font-bold" id="retentionAmountDisplay">-₹0.00</span>
                </div>
                <div class="flex justify-between items-center py-1 text-rose-700">
                    <span>(-) TDS Deducted u/s 194C:</span>
                    <span class="font-mono font-bold" id="tdsAmountDisplay">-₹0.00</span>
                </div>
                <div class="flex justify-between items-center py-1 text-gray-700">
                    <span>(-) Mobilization Advance Recovery:</span>
                    <span class="font-mono font-bold" id="advanceAmountDisplay">-₹0.00</span>
                </div>
                <div class="border-t border-gray-200 pt-2 flex justify-between items-center text-sm font-black">
                    <span class="text-gray-900 uppercase tracking-wider">Net Amount Payable to Subcontractor:</span>
                    <span class="font-mono text-xl text-blue-700" id="netPayableDisplay">₹0.00</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('subcontractor-bills.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Cancel</a>
            <button type="submit" id="generateBillBtn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/20 transition">Generate Subcontractor RA Bill</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.mb-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');

    const retInput = document.getElementById('retentionPercentInput');
    const tdsInput = document.getElementById('tdsPercentInput');
    const gstInput = document.getElementById('gstPercentInput');
    const advInput = document.getElementById('advanceRecoveryInput');

    const grossDisplay = document.getElementById('grossWorkDisplay');
    const gstDisplay = document.getElementById('gstAmountDisplay');
    const retDisplay = document.getElementById('retentionAmountDisplay');
    const tdsDisplay = document.getElementById('tdsAmountDisplay');
    const advDisplay = document.getElementById('advanceAmountDisplay');
    const netDisplay = document.getElementById('netPayableDisplay');
    const generateBtn = document.getElementById('generateBillBtn');

    function calculateBill() {
        let gross = 0;
        let selectedCount = 0;

        checkboxes.forEach(cb => {
            if (cb.checked) {
                gross += parseFloat(cb.dataset.amount) || 0;
                selectedCount++;
            }
        });

        const retRate = parseFloat(retInput.value) || 0;
        const tdsRate = parseFloat(tdsInput.value) || 0;
        const gstRate = parseFloat(gstInput.value) || 0;
        const advAmount = parseFloat(advInput.value) || 0;

        const retention = Math.round((gross * (retRate / 100)) * 100) / 100;
        const tds = Math.round((gross * (tdsRate / 100)) * 100) / 100;
        const gst = Math.round((gross * (gstRate / 100)) * 100) / 100;
        const net = Math.round((gross + gst - retention - tds - advAmount) * 100) / 100;

        grossDisplay.textContent = '₹' + gross.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        gstDisplay.textContent = '+₹' + gst.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        retDisplay.textContent = '-₹' + retention.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        tdsDisplay.textContent = '-₹' + tds.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        advDisplay.textContent = '-₹' + advAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        netDisplay.textContent = '₹' + net.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (selectedCount === 0) {
            generateBtn.disabled = true;
            generateBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            generateBtn.disabled = false;
            generateBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    checkboxes.forEach(cb => cb.addEventListener('change', calculateBill));
    document.querySelectorAll('.calc-input').forEach(el => el.addEventListener('input', calculateBill));

    selectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(cb => cb.checked = true);
        calculateBill();
    });

    deselectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(cb => cb.checked = false);
        calculateBill();
    });

    calculateBill();
});
</script>
@endsection
