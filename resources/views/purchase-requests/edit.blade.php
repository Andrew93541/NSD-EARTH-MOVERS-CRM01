@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="purchaseRequestEditForm()">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Material Requisition</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 font-mono">
                    {{ $request->pr_number }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Modify request items and resubmit for approval.</p>
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

    <form method="POST" action="{{ route('purchase-requests.update', $request) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Request Details Card -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Site & Requisition Details</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs">
                <div>
                    <label for="project_id" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Project *</label>
                    <select id="project_id" name="project_id" required x-model="selectedProject" @change="updateSites()"
                            class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="">Select Project</option>
                        @foreach ($projects as $prj)
                            <option value="{{ $prj->id }}" {{ old('project_id', $request->project_id) == $prj->id ? 'selected' : '' }}>
                                {{ $prj->name }} ({{ $prj->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="site_id" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Site / Work Front *</label>
                    <select id="site_id" name="site_id" required x-model="selectedSite"
                            class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="">Select Site</option>
                        <template x-for="site in filteredSites" :key="site.id">
                            <option :value="site.id" x-text="site.name" :selected="site.id == selectedSite"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label for="warehouse_id" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Delivery Destination / Store</label>
                    <select id="warehouse_id" name="warehouse_id"
                            class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="">Select Site Store / Yard</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id', $request->warehouse_id) == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ ucfirst(str_replace('_', ' ', $wh->type)) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="required_by_date" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Required On Site By *</label>
                    <input type="date" id="required_by_date" name="required_by_date" value="{{ old('required_by_date', $request->required_by_date?->format('Y-m-d')) }}" required
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                </div>

                <div>
                    <label for="priority" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Requisition Priority *</label>
                    <select id="priority" name="priority" required
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        <option value="normal" {{ old('priority', $request->priority) == 'normal' ? 'selected' : '' }}>Normal (Routine)</option>
                        <option value="urgent" {{ old('priority', $request->priority) == 'urgent' ? 'selected' : '' }}>Urgent (Work critical)</option>
                        <option value="critical" {{ old('priority', $request->priority) == 'critical' ? 'selected' : '' }}>Critical (Work stoppage risk)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Current Status</label>
                    <input type="text" readonly value="{{ strtoupper($request->status) }}"
                           class="w-full px-3 py-2 bg-gray-100 border border-gray-200 rounded-xl font-semibold text-gray-500 cursor-not-allowed"/>
                </div>
            </div>

            <div>
                <label for="reason" class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Purpose / Scope of Work / Justification *</label>
                <textarea id="reason" name="reason" rows="2" required
                          class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">{{ old('reason', $request->reason) }}</textarea>
            </div>
        </div>

        <!-- Material Request Items Grid -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Required Material Line Items</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Modify quantities or add new material requirements.</p>
                </div>
                <button type="button" @click="addItem()" class="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 font-bold rounded-lg text-xs flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Item Row</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                            <th class="py-2 px-2">Material Description *</th>
                            <th class="py-2 px-2">Specifications / Grade</th>
                            <th class="py-2 px-2 w-28 text-right">Quantity *</th>
                            <th class="py-2 px-2 w-32 text-right">Est. Rate (₹)</th>
                            <th class="py-2 px-2 w-32 text-right">Line Total (₹)</th>
                            <th class="py-2 px-2 w-10 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="align-top">
                                <td class="py-2.5 px-2">
                                    <select :name="'items[' + index + '][material_id]'" required
                                            x-model="item.material_id"
                                            @change="onMaterialChange(item)"
                                            class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold focus:ring-1 focus:ring-blue-600 focus:outline-none">
                                        <option value="">Select Material</option>
                                        @foreach ($materials as $mat)
                                            <option value="{{ $mat->id }}"
                                                    data-uom="{{ $mat->unit_of_measure }}"
                                                    data-rate="{{ $mat->standard_cost }}">
                                                {{ $mat->name }} ({{ $mat->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-[10px] text-gray-400 mt-1 block" x-show="item.uom">
                                        Unit: <strong class="text-gray-700" x-text="item.uom"></strong>
                                    </span>
                                </td>

                                <td class="py-2.5 px-2">
                                    <input type="text" :name="'items[' + index + '][specifications]'" x-model="item.specifications"
                                           placeholder="e.g. Fe550D / Grade 53"
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>

                                <td class="py-2.5 px-2">
                                    <input type="number" step="0.001" min="0.001" :name="'items[' + index + '][quantity_requested]'"
                                           x-model.number="item.quantity_requested" required
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono font-bold text-right focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>

                                <td class="py-2.5 px-2">
                                    <input type="number" step="0.01" min="0" :name="'items[' + index + '][estimated_rate]'"
                                           x-model.number="item.estimated_rate"
                                           class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono text-right focus:ring-1 focus:ring-blue-600 focus:outline-none"/>
                                </td>

                                <td class="py-2.5 px-2 text-right font-mono font-bold text-gray-900 pt-3">
                                    ₹<span x-text="(item.quantity_requested * item.estimated_rate).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                </td>

                                <td class="py-2.5 px-2 text-center pt-2.5">
                                    <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Grand Total -->
            <div class="pt-3 border-t border-gray-100 flex justify-end">
                <div class="text-right">
                    <span class="text-xs text-gray-500 font-semibold block">Total Estimated Requisition Value</span>
                    <span class="text-xl font-black font-mono text-blue-700">
                        ₹<span x-text="grandTotal().toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('purchase-requests.show', $request) }}" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" name="action" value="draft" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                Save as Draft
            </button>
            <button type="submit" name="action" value="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Resubmit for PM Approval</span>
            </button>
        </div>
    </form>
</div>

<script>
function purchaseRequestEditForm() {
    const existingItems = @json($existingItems);

    return {
        selectedProject: '{{ old('project_id', $request->project_id) }}',
        selectedSite: '{{ old('site_id', $request->site_id) }}',
        allSites: @json($sites),
        filteredSites: [],
        items: existingItems.length > 0 ? existingItems : [
            { material_id: '', specifications: '', quantity_requested: 1, estimated_rate: 0, uom: '' }
        ],
        init() {
            this.updateSites();
        },
        updateSites() {
            if (!this.selectedProject) {
                this.filteredSites = this.allSites;
            } else {
                this.filteredSites = this.allSites.filter(s => s.project_id == this.selectedProject);
            }
        },
        addItem() {
            this.items.push({ material_id: '', specifications: '', quantity_requested: 1, estimated_rate: 0, uom: '' });
        },
        removeItem(idx) {
            if (this.items.length > 1) {
                this.items.splice(idx, 1);
            }
        },
        onMaterialChange(item) {
            const selectEl = event.target;
            const opt = selectEl.options[selectEl.selectedIndex];
            if (opt) {
                item.uom = opt.getAttribute('data-uom') || '';
                item.estimated_rate = parseFloat(opt.getAttribute('data-rate')) || 0;
            }
        },
        grandTotal() {
            return this.items.reduce((sum, it) => sum + ((it.quantity_requested || 0) * (it.estimated_rate || 0)), 0);
        }
    }
}
</script>
@endsection
