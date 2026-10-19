@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="quotationForm()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('quotations.index') }}" class="text-xs text-blue-600 hover:underline">← Quotations Register</a>
                <span class="text-slate-300">/</span>
                <span class="text-xs text-slate-500">New Estimate</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">Create Commercial Quotation</h1>
            <p class="text-xs text-slate-500">Prepare an itemized commercial estimate for client review and bid submission.</p>
        </div>
    </div>

    @if ($errors->any())
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs space-y-1">
        <div class="font-bold">Please correct the following errors:</div>
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('quotations.store') }}" class="space-y-6">
        @csrf

        <!-- Card 1: Quotation Details -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                1. Header & Client Details
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Quotation Number *</label>
                    <input type="text" name="quotation_number" value="{{ old('quotation_number', $defaultNumber) }}" required
                           class="w-full text-xs font-mono font-bold rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Client Organization *</label>
                    <select name="client_id" required class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Client</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }} ({{ $c->company_name ?? 'Client' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Quotation Date *</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Valid Until *</label>
                    <input type="date" name="valid_until" value="{{ old('valid_until', date('Y-m-d', strtotime('+30 days'))) }}" required
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Proposed Project Title *</label>
                    <input type="text" name="project_title" value="{{ old('project_title') }}" required
                           placeholder="e.g. Metro Line 3 Underground Earthworks & Excavation Package"
                           class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Scope of Work & Technical Description</label>
                    <textarea name="project_description" rows="2" placeholder="Brief outline of site scope, mobilization, machinery deployed, and delivery schedule..."
                              class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">{{ old('project_description') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Card 2: Itemized Bill of Quantities / Scope Lines -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                    2. Schedule of Quantities & Line Items
                </h3>
                <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Item</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                            <th class="py-2.5 px-3 w-10">#</th>
                            <th class="py-2.5 px-3">Item Description *</th>
                            <th class="py-2.5 px-3 w-28">Quantity *</th>
                            <th class="py-2.5 px-3 w-28">Unit *</th>
                            <th class="py-2.5 px-3 w-32">Rate (₹) *</th>
                            <th class="py-2.5 px-3 w-36 text-right">Amount (₹)</th>
                            <th class="py-2.5 px-2 w-12 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="py-2.5 px-3 font-mono text-slate-400 font-bold" x-text="index + 1"></td>
                                <td class="py-2.5 px-3">
                                    <input type="text" :name="`items[${index}][description]`" x-model="item.description" required
                                           placeholder="e.g. Bulk excavation in hard rock including disposal within 5km"
                                           class="w-full text-xs rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="number" step="0.001" min="0.001" :name="`items[${index}][quantity]`" x-model.number="item.quantity" @input="recalculate()" required
                                           class="w-full text-xs font-mono rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="text" :name="`items[${index}][unit]`" x-model="item.unit" required placeholder="cum, MT, nos"
                                           class="w-full text-xs rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_rate]`" x-model.number="item.unit_rate" @input="recalculate()" required
                                           class="w-full text-xs font-mono rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                    ₹<span x-text="formatNumber(item.quantity * item.unit_rate)"></span>
                                </td>
                                <td class="py-2.5 px-2 text-center">
                                    <button type="button" @click="removeItem(index)" :disabled="items.length <= 1"
                                            class="p-1 text-slate-400 hover:text-rose-600 rounded disabled:opacity-30 disabled:hover:text-slate-400" title="Remove line">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card 3: Tax Treatment, Commercial Discount & Totals -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="soft-card p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                    3. Commercial Terms & Tax Treatment
                </h3>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tax Treatment *</label>
                        <select name="tax_type" x-model="taxType" @change="onTaxTypeChange()" class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                            <option value="gst_18">GST 18% (CGST 9% + SGST 9%)</option>
                            <option value="igst_18">IGST 18% (Inter-state)</option>
                            <option value="gst_12">GST 12% (CGST 6% + SGST 6%)</option>
                            <option value="gst_5">GST 5% (CGST 2.5% + SGST 2.5%)</option>
                            <option value="exempt">Tax Exempt / Zero-Rated (0%)</option>
                            <option value="custom">Custom Tax Rate</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Applicable Tax Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="tax_rate" x-model.number="taxRate" @input="recalculate()"
                               :readonly="taxType !== 'custom'"
                               class="w-full text-xs font-mono rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right"
                               :class="taxType !== 'custom' ? 'bg-slate-50 text-slate-500' : ''">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Commercial Discount</label>
                        <select name="discount_type" x-model="discountType" @change="recalculate()" class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">No Discount</option>
                            <option value="percentage">Percentage Discount (%)</option>
                            <option value="fixed">Fixed Lump Sum (₹)</option>
                        </select>
                    </div>

                    <div x-show="discountType === 'percentage'">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Discount Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount_rate" x-model.number="discountRate" @input="recalculate()"
                               class="w-full text-xs font-mono rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                    </div>

                    <div x-show="discountType === 'fixed'">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Discount Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="discount_amount" x-model.number="discountFixed" @input="recalculate()"
                               class="w-full text-xs font-mono rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Terms & Conditions</label>
                    <textarea name="terms_conditions" rows="3" placeholder="Payment terms: 30 days from billing. Mobilization advance: 10%. Price validity: 30 days..."
                              class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">1. Rates quoted are inclusive of all fuel, equipment, and skilled manpower.
2. Mobilization advance of 10% payable on contract signing.
3. Monthly running account (RA) bills settled within 30 calendar days.
4. Taxes levied as per applicable GST laws at the time of invoicing.</textarea>
                </div>
            </div>

            <!-- Financial Calculation Summary Panel -->
            <div class="soft-card p-6 bg-slate-50/75 border border-slate-200 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-200 pb-2">
                        Financial Summary
                    </h3>

                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal (Schedule of Rates)</span>
                            <span class="font-mono font-semibold text-slate-900">₹<span x-text="formatNumber(subtotal)"></span></span>
                        </div>

                        <div class="flex justify-between text-rose-600" x-show="discountAmount > 0">
                            <span>Discount <span x-show="discountType === 'percentage'" x-text="`(${discountRate}%)`"></span></span>
                            <span class="font-mono font-semibold">-₹<span x-text="formatNumber(discountAmount)"></span></span>
                        </div>

                        <div class="flex justify-between text-slate-700 pt-2 border-t border-slate-200 font-semibold">
                            <span>Taxable Value</span>
                            <span class="font-mono text-slate-900">₹<span x-text="formatNumber(taxableAmount)"></span></span>
                        </div>

                        <div class="flex justify-between text-slate-600" x-show="taxType === 'gst_18'">
                            <span>CGST (9.0%)</span>
                            <span class="font-mono font-semibold">₹<span x-text="formatNumber(taxAmount / 2)"></span></span>
                        </div>
                        <div class="flex justify-between text-slate-600" x-show="taxType === 'gst_18'">
                            <span>SGST (9.0%)</span>
                            <span class="font-mono font-semibold">₹<span x-text="formatNumber(taxAmount / 2)"></span></span>
                        </div>

                        <div class="flex justify-between text-slate-600" x-show="taxType !== 'gst_18'">
                            <span>Estimated Tax (<span x-text="taxRate"></span>%)</span>
                            <span class="font-mono font-semibold">₹<span x-text="formatNumber(taxAmount)"></span></span>
                        </div>

                        <div class="pt-4 border-t-2 border-slate-300 flex justify-between items-baseline">
                            <span class="text-sm font-bold text-slate-900">Total Quotation Value</span>
                            <span class="text-2xl font-extrabold text-blue-600 font-mono">₹<span x-text="formatNumber(grandTotal)"></span></span>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition cursor-pointer">
                        Save Quotation (Draft)
                    </button>
                    <p class="text-[11px] text-slate-400 text-center mt-2">Saved as Draft. Can be sent, revised, or converted into an active project once accepted.</p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function quotationForm() {
    return {
        items: [
            { description: 'Site clearance and topsoil removal up to 300mm depth', quantity: 1500, unit: 'sqm', unit_rate: 45.00 },
            { description: 'Mechanical excavation in all types of soil including loading onto tippers', quantity: 2200, unit: 'cum', unit_rate: 180.00 },
            { description: 'Supply and compaction of granular sub-base (GSB) material in 150mm layers', quantity: 850, unit: 'cum', unit_rate: 950.00 }
        ],
        taxType: 'gst_18',
        taxRate: 18.00,
        discountType: '',
        discountRate: 0.00,
        discountFixed: 0.00,

        subtotal: 0,
        discountAmount: 0,
        taxableAmount: 0,
        taxAmount: 0,
        grandTotal: 0,

        init() {
            this.onTaxTypeChange();
            this.recalculate();
        },

        addItem() {
            this.items.push({ description: '', quantity: 1, unit: 'nos', unit_rate: 0 });
            this.recalculate();
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
                this.recalculate();
            }
        },

        onTaxTypeChange() {
            switch(this.taxType) {
                case 'gst_18':
                case 'igst_18':
                    this.taxRate = 18.00;
                    break;
                case 'gst_12':
                    this.taxRate = 12.00;
                    break;
                case 'gst_5':
                    this.taxRate = 5.00;
                    break;
                case 'exempt':
                    this.taxRate = 0.00;
                    break;
                case 'custom':
                    // keep current tax rate
                    break;
            }
            this.recalculate();
        },

        recalculate() {
            let sum = 0;
            this.items.forEach(i => {
                let q = parseFloat(i.quantity) || 0;
                let r = parseFloat(i.unit_rate) || 0;
                sum += q * r;
            });
            this.subtotal = Math.round(sum * 100) / 100;

            let disc = 0;
            if (this.discountType === 'percentage') {
                disc = Math.round((this.subtotal * (parseFloat(this.discountRate) || 0) / 100) * 100) / 100;
            } else if (this.discountType === 'fixed') {
                disc = Math.min(this.subtotal, parseFloat(this.discountFixed) || 0);
            }
            this.discountAmount = disc;

            this.taxableAmount = Math.max(0, this.subtotal - this.discountAmount);

            let tax = 0;
            if (this.taxType !== 'exempt' && this.taxRate > 0) {
                tax = Math.round((this.taxableAmount * (parseFloat(this.taxRate) || 0) / 100) * 100) / 100;
            }
            this.taxAmount = tax;

            this.grandTotal = Math.round((this.taxableAmount + this.taxAmount) * 100) / 100;
        },

        formatNumber(num) {
            return (parseFloat(num) || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    }
}
</script>
@endsection
