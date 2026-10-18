@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="poConversionForm()">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Convert Requisition to Purchase Order</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 font-mono">
                    {{ $request->pr_number }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Issue official procurement order to approved supplier with real-time stock and budget verification.</p>
        </div>
        <a href="{{ route('purchase-requests.show', $request) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Request</a>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-xs text-red-700 space-y-1">
            <strong class="font-bold block">Please review the following errors:</strong>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Safeguards & Financial Health Banner -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Stock Verification -->
        <div class="p-4 bg-gray-50/80 border border-gray-200 rounded-2xl text-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-gray-900 uppercase text-[10px] tracking-wider">Refreshed Stock Availability</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase {{ $stockAnalysis['overall_status'] === 'available' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                    {{ $stockAnalysis['overall_status_label'] }}
                </span>
            </div>
            <p class="text-gray-600 text-[11px] leading-relaxed">
                Total shortfall across indent: <strong class="font-mono text-gray-900">{{ number_format($stockAnalysis['total_shortfall_units'], 2) }} units</strong>.
                Physical inventory changes only through authorized goods receipts.
            </p>
        </div>

        <!-- Project Budget Verification -->
        <div class="p-4 bg-gray-50/80 border border-gray-200 rounded-2xl text-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-gray-900 uppercase text-[10px] tracking-wider">Project Budget Safeguard</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase {{ $budgetAnalysis['badge_class'] }}">
                    {{ $budgetAnalysis['status_label'] }}
                </span>
            </div>
            <div class="text-gray-600 text-[11px] flex justify-between">
                <span>Remaining Budget:</span>
                <span class="font-mono font-bold {{ $budgetAnalysis['remaining_before'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                    ₹{{ number_format($budgetAnalysis['remaining_before'], 2) }}
                </span>
            </div>
            @if ($request->budget_override_approved)
                <div class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-1 rounded border border-emerald-200">
                    ✓ Authorized Budget Override: {{ $request->budget_override_reason }}
                </div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('purchase-requests.convert.store', $request) }}" class="space-y-6">
        @csrf

        <!-- Requisition Reference Banner -->
        <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-2xl text-xs text-blue-900 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <span class="text-blue-700/70 block uppercase font-bold text-[10px]">Project</span>
                <strong class="text-gray-900 mt-0.5 block">{{ $request->project->name }}</strong>
            </div>
            <div>
                <span class="text-blue-700/70 block uppercase font-bold text-[10px]">Site</span>
                <strong class="text-gray-900 mt-0.5 block">{{ $request->site->name }}</strong>
            </div>
            <div>
                <span class="text-blue-700/70 block uppercase font-bold text-[10px]">Approval Status</span>
                <strong class="text-gray-900 mt-0.5 block">Level {{ $request->current_approval_level }} / {{ $request->required_approval_levels }} (Complete)</strong>
            </div>
            <div>
                <span class="text-blue-700/70 block uppercase font-bold text-[10px]">Approval Date</span>
                <strong class="text-gray-900 mt-0.5 block">{{ $request->approved_at?->format('d M, Y') ?? 'Verified' }}</strong>
            </div>
        </div>

        <!-- Supplier & Delivery Particulars -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Purchase Order Particulars</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                    <label for="supplier_id" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Approved Supplier *</label>
                    <select id="supplier_id" name="supplier_id" required
                            class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="">Select Supplier</option>
                        @foreach ($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ old('supplier_id') == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }} ({{ $sup->gstin ?? 'Unregistered' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="po_date" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">PO Date *</label>
                    <input type="date" id="po_date" name="po_date" value="{{ old('po_date', date('Y-m-d')) }}" required
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                </div>

                <div>
                    <label for="delivery_due_date" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Delivery Due Date</label>
                    <input type="date" id="delivery_due_date" name="delivery_due_date" value="{{ old('delivery_due_date', $request->required_by_date?->format('Y-m-d') ?? date('Y-m-d', strtotime('+7 days'))) }}"
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                </div>

                <div>
                    <label for="warehouse_id" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Delivery Yard / Store</label>
                    <select id="warehouse_id" name="warehouse_id"
                            class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="">Select Store</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id', $request->warehouse_id) == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="terms_and_conditions" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Commercial Terms & Payment Conditions</label>
                <input type="text" id="terms_and_conditions" name="terms_and_conditions" value="{{ old('terms_and_conditions', 'Payment 30 days post delivery quality verification and test certificate clearance') }}"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <!-- Material Ordering Grid -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Eligible Requisition Items for Procurement</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Order full or partial quantities. Quantities cannot exceed remaining request balance.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                            <th class="py-2 px-2">Material</th>
                            <th class="py-2 px-2 text-right">Requested</th>
                            <th class="py-2 px-2 text-right">Already Ordered</th>
                            <th class="py-2 px-2 text-right">Remaining</th>
                            <th class="py-2 px-2 w-32 text-right">Order Qty *</th>
                            <th class="py-2 px-2 w-32 text-right">Supplier Rate (₹) *</th>
                            <th class="py-2 px-2 w-20 text-right">GST %</th>
                            <th class="py-2 px-2 w-32 text-right">Line Total (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(item, index) in items" :key="item.pr_item_id">
                            <tr class="align-top">
                                <td class="py-2.5 px-2">
                                    <strong class="text-gray-900 block" x-text="item.material_name"></strong>
                                    <span class="text-gray-400 text-[10px]" x-text="item.specifications || 'Standard'"></span>
                                    <input type="hidden" :name="'items[' + index + '][pr_item_id]'" :value="item.pr_item_id"/>
                                </td>

                                <td class="py-2.5 px-2 text-right font-mono text-gray-600">
                                    <span x-text="item.quantity_requested.toFixed(2)"></span> <span x-text="item.uom"></span>
                                </td>

                                <td class="py-2.5 px-2 text-right font-mono text-blue-600 font-semibold">
                                    <span x-text="item.quantity_ordered.toFixed(2)"></span>
                                </td>

                                <td class="py-2.5 px-2 text-right font-mono font-bold text-gray-900">
                                    <span x-text="item.remaining_quantity.toFixed(2)"></span>
                                </td>

                                <td class="py-2.5 px-2">
                                    <input type="number" step="0.001" min="0" :max="item.remaining_quantity"
                                           :name="'items[' + index + '][quantity_to_order]'"
                                           x-model.number="item.quantity_to_order" required
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono font-bold text-right focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>

                                <td class="py-2.5 px-2">
                                    <input type="number" step="0.01" min="0"
                                           :name="'items[' + index + '][unit_price]'"
                                           x-model.number="item.unit_price" required
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono text-right focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>

                                <td class="py-2.5 px-2">
                                    <input type="number" step="0.01" min="0" max="100"
                                           :name="'items[' + index + '][tax_percent]'"
                                           x-model.number="item.tax_percent"
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono text-right focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>

                                <td class="py-2.5 px-2 text-right font-mono font-bold text-gray-900 pt-3">
                                    ₹<span x-text="lineTotal(item).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Grand Totals Box -->
            <div class="pt-4 border-t border-gray-100 flex justify-end">
                <div class="w-72 space-y-1.5 text-xs">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal:</span>
                        <span class="font-mono font-semibold">₹<span x-text="subtotal().toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>GST Tax:</span>
                        <span class="font-mono font-semibold">₹<span x-text="totalTax().toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                    </div>
                    <div class="flex justify-between text-base font-extrabold text-gray-900 pt-2 border-t border-gray-200">
                        <span>Total PO Value:</span>
                        <span class="font-mono text-blue-700">₹<span x-text="grandTotal().toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('purchase-requests.show', $request) }}" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Generate Official Purchase Order</span>
            </button>
        </div>
    </form>
</div>

<script>
function poConversionForm() {
    const rawItems = @json($convertibleItems);

    return {
        items: rawItems,
        lineTotal(item) {
            const qty = item.quantity_to_order || 0;
            const rate = item.unit_price || 0;
            const tax = item.tax_percent || 0;
            return (qty * rate) * (1 + (tax / 100));
        },
        subtotal() {
            return this.items.reduce((sum, it) => sum + ((it.quantity_to_order || 0) * (it.unit_price || 0)), 0);
        },
        totalTax() {
            return this.items.reduce((sum, it) => {
                const sub = (it.quantity_to_order || 0) * (it.unit_price || 0);
                return sum + (sub * ((it.tax_percent || 0) / 100));
            }, 0);
        },
        grandTotal() {
            return this.subtotal() + this.totalTax();
        }
    }
}
</script>
@endsection
