@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="materialIssueForm()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('material-issues.index') }}" class="text-xs text-blue-600 hover:underline">← Material Issues Register</a>
                <span class="text-slate-300">/</span>
                <span class="text-xs text-slate-500">Dispatch Stock</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">Issue Materials to Site</h1>
            <p class="text-xs text-slate-500">Transfer available warehouse inventory to a specific construction project and site location.</p>
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

    <form method="POST" action="{{ route('material-issues.store') }}" class="space-y-6">
        @csrf

        <!-- Card 1: Logistics & Destination -->
        <div class="soft-card p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                1. Dispatch Details & Destination
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Issue Slip # *</label>
                    <input type="text" name="issue_number" value="{{ old('issue_number', $defaultNumber) }}" required
                           class="w-full text-xs font-mono font-bold rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Issue Date *</label>
                    <input type="date" name="issue_date" value="{{ old('issue_date', date('Y-m-d')) }}" required
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Source Warehouse / Yard *</label>
                    <select name="warehouse_id" x-model="selectedWarehouse" required class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Warehouse</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (old('warehouse_id', $selectedWarehouseId) == $wh->id) ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ ucfirst($wh->type ?? 'General') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Issue Purpose</label>
                    <input type="text" name="purpose" value="{{ old('purpose', 'Site Consumption') }}"
                           placeholder="e.g. Pier Concreting, GSB Subbase"
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <!-- Destination Project & Site -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Destination Project *</label>
                    <select name="project_id" x-model="selectedProject" @change="onProjectChange()" required class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Active Project</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ (old('project_id', $selectedProjectId) == $p->id) ? 'selected' : '' }}>
                                {{ $p->code }} — {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Destination Work Site *</label>
                    <select name="site_id" x-model="selectedSite" required class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Site Location</option>
                        <template x-for="site in availableSites" :key="site.id">
                            <option :value="site.id" x-text="site.name" :selected="site.id == selectedSite"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Personnel & Transport -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Requested By (Site Engineer/PM)</label>
                    <select name="requested_by" class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Requester</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('requested_by') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->currentRole()?->name ?? 'Staff' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Received By (Name / Operator)</label>
                    <input type="text" name="received_by_name" value="{{ old('received_by_name') }}" placeholder="e.g. Ramesh Site Foreman"
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Transport Vehicle #</label>
                    <input type="text" name="vehicle_number" value="{{ old('vehicle_number') }}" placeholder="e.g. KA-04-E-4589"
                           class="w-full text-xs font-mono uppercase rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Card 2: Materials & Quantities -->
        <div class="soft-card p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                        2. Materials to Dispatch
                    </h3>
                    <p class="text-[11px] text-slate-400">Issued quantities are deducted from the source warehouse stock ledger immediately upon confirmation.</p>
                </div>
                <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Material</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                            <th class="py-2.5 px-3 w-10">#</th>
                            <th class="py-2.5 px-3">Material *</th>
                            <th class="py-2.5 px-3 w-28 text-center">Unit</th>
                            <th class="py-2.5 px-3 w-32">Req. Qty *</th>
                            <th class="py-2.5 px-3 w-32">Issue Qty *</th>
                            <th class="py-2.5 px-3 w-28 text-right">Est. Rate</th>
                            <th class="py-2.5 px-3 w-32 text-right">Est. Total</th>
                            <th class="py-2.5 px-2 w-12 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="py-2.5 px-3 font-mono text-slate-400 font-bold" x-text="index + 1"></td>
                                <td class="py-2.5 px-3">
                                    <select :name="`items[${index}][material_id]`" x-model="item.material_id" @change="onMaterialChange(index)" required
                                            class="w-full text-xs rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Select Material</option>
                                        @foreach($materials as $m)
                                            <option value="{{ $m->id }}" data-uom="{{ $m->unit_of_measure }}" data-cost="{{ $m->standard_cost }}">
                                                {{ $m->name }} ({{ $m->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono font-medium text-slate-600" x-text="item.unit || '—'"></td>
                                <td class="py-2.5 px-3">
                                    <input type="number" step="0.001" min="0.001" :name="`items[${index}][requested_quantity]`" x-model.number="item.requested_quantity" required
                                           class="w-full text-xs font-mono rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="number" step="0.001" min="0.001" :name="`items[${index}][issued_quantity]`" x-model.number="item.issued_quantity" @input="recalculate()" required
                                           class="w-full text-xs font-mono font-bold text-blue-700 rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-right">
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-600">
                                    ₹<span x-text="formatNumber(item.unit_rate)"></span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                    ₹<span x-text="formatNumber(item.issued_quantity * item.unit_rate)"></span>
                                </td>
                                <td class="py-2.5 px-2 text-center">
                                    <button type="button" @click="removeItem(index)" :disabled="items.length <= 1"
                                            class="p-1 text-slate-400 hover:text-rose-600 rounded disabled:opacity-30" title="Remove line">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-bold border-t border-slate-200">
                            <td colspan="6" class="py-3 px-3 text-right text-slate-700">Total Valuation of Issued Inventory:</td>
                            <td class="py-3 px-3 text-right font-mono text-blue-700 text-sm">
                                ₹<span x-text="formatNumber(totalCost)"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Dispatch Remarks / Instructions</label>
                <textarea name="remarks" rows="2" placeholder="Delivery notes, driver instructions, weather note, or site delivery location details..."
                          class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">{{ old('remarks') }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('material-issues.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer">
                Confirm &amp; Record Material Issue
            </button>
        </div>
    </form>
</div>

<script>
function materialIssueForm() {
    const projectsData = @json($projects);
    const materialsData = @json($materials);

    return {
        selectedProject: '{{ old('project_id', $selectedProjectId) }}',
        selectedSite: '{{ old('site_id', $selectedSiteId) }}',
        selectedWarehouse: '{{ old('warehouse_id', $selectedWarehouseId) }}',
        availableSites: [],
        totalCost: 0,

        items: [
            { material_id: '', unit: '', requested_quantity: 10, issued_quantity: 10, unit_rate: 0 }
        ],

        init() {
            this.onProjectChange();
            if (materialsData.length > 0 && !this.items[0].material_id) {
                this.items[0].material_id = materialsData[0].id;
                this.items[0].unit = materialsData[0].unit_of_measure;
                this.items[0].unit_rate = parseFloat(materialsData[0].standard_cost) || 0;
            }
            this.recalculate();
        },

        onProjectChange() {
            const prj = projectsData.find(p => p.id == this.selectedProject);
            if (prj && prj.sites) {
                this.availableSites = prj.sites;
                if (!this.availableSites.find(s => s.id == this.selectedSite)) {
                    this.selectedSite = this.availableSites.length > 0 ? this.availableSites[0].id : '';
                }
            } else {
                this.availableSites = [];
                this.selectedSite = '';
            }
        },

        addItem() {
            const firstMat = materialsData[0] || {};
            this.items.push({
                material_id: firstMat.id || '',
                unit: firstMat.unit_of_measure || '',
                requested_quantity: 1,
                issued_quantity: 1,
                unit_rate: parseFloat(firstMat.standard_cost) || 0
            });
            this.recalculate();
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
                this.recalculate();
            }
        },

        onMaterialChange(index) {
            const item = this.items[index];
            const mat = materialsData.find(m => m.id == item.material_id);
            if (mat) {
                item.unit = mat.unit_of_measure;
                item.unit_rate = parseFloat(mat.standard_cost) || 0;
            } else {
                item.unit = '';
                item.unit_rate = 0;
            }
            this.recalculate();
        },

        recalculate() {
            let total = 0;
            this.items.forEach(i => {
                const qty = parseFloat(i.issued_quantity) || 0;
                const rate = parseFloat(i.unit_rate) || 0;
                total += qty * rate;
            });
            this.totalCost = Math.round(total * 100) / 100;
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
