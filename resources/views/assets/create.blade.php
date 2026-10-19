@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold mb-1">
                <a href="{{ route('assets.index') }}" class="hover:text-blue-600 transition">Fixed Assets</a>
                <span>/</span>
                <span class="text-gray-900 font-bold">New Registration</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Register Capital Equipment / Fixed Asset</h1>
            <p class="text-xs text-gray-500 mt-1">Capitalize heavy construction machinery, earthmoving equipment, or vehicles with automated depreciation.</p>
        </div>
        <a href="{{ route('assets.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
            Cancel
        </a>
    </div>

    <!-- Error Alerts -->
    @if($errors->any())
        <div class="p-4 bg-red-50 rounded-2xl border border-red-200 text-xs font-semibold text-red-800 space-y-1">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('assets.store') }}" class="space-y-6">
        @csrf

        <!-- Section 1: General Equipment Identification -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">1. Equipment Identification</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Asset Code *</label>
                    <input type="text" name="asset_code" value="{{ old('asset_code', 'AST-EQ-' . rand(100, 999)) }}" required placeholder="e.g. AST-EX200-01" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono font-bold uppercase focus:ring-2 focus:ring-blue-500">
                    <span class="text-[10px] text-gray-400 mt-1 block">Unique company asset identification code.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Equipment Name / Model *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Tata Hitachi EX200 LC Hydraulic Excavator" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-medium focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Category *</label>
                    <select name="category" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white focus:ring-2 focus:ring-blue-500">
                        <option value="excavator" {{ old('category') === 'excavator' ? 'selected' : '' }}>Hydraulic Excavator</option>
                        <option value="backhoe_loader" {{ old('category') === 'backhoe_loader' ? 'selected' : '' }}>Backhoe Loader (JCB)</option>
                        <option value="dumper_tipper" {{ old('category') === 'dumper_tipper' ? 'selected' : '' }}>Dumper / Tipper Truck</option>
                        <option value="crane" {{ old('category') === 'crane' ? 'selected' : '' }}>Mobile / Crawler Crane</option>
                        <option value="compactor" {{ old('category') === 'compactor' ? 'selected' : '' }}>Soil / Tandem Compactor</option>
                        <option value="concrete_machinery" {{ old('category') === 'concrete_machinery' ? 'selected' : '' }}>Concrete Transit Mixer / Batching Plant</option>
                        <option value="generator" {{ old('category') === 'generator' ? 'selected' : '' }}>Diesel Generator / Air Compressor</option>
                        <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>Other Plant Machinery</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Chassis / Engine / Serial #</label>
                    <input type="text" name="serial_number" value="{{ old('serial_number') }}" placeholder="e.g. THEX-2026-9921008" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
                </div>
            </div>
        </div>

        <!-- Section 2: Capitalization & Cost Details -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">2. Capitalization & Cost Basis</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Purchase Date *</label>
                    <input type="date" name="purchase_date" value="{{ old('purchase_date', date('Y-m-d')) }}" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Capitalization Date *</label>
                    <input type="date" name="capitalization_date" value="{{ old('capitalization_date', date('Y-m-d')) }}" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                    <span class="text-[10px] text-gray-400 mt-1 block">Date put into commercial service.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Depreciation Start Date *</label>
                    <input type="date" name="depreciation_start_date" value="{{ old('depreciation_start_date', date('Y-m-d')) }}" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Acquisition Cost (₹) *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-gray-400 font-bold">₹</span>
                        <input type="number" step="0.01" min="1" name="acquisition_cost" id="acquisition_cost" value="{{ old('acquisition_cost') }}" required placeholder="e.g. 6500000.00" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pl-8 font-mono font-bold text-gray-900" oninput="updateTotalCapitalized()">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Eligible Additions (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-gray-400 font-bold">₹</span>
                        <input type="number" step="0.01" min="0" name="eligible_capitalized_costs" id="eligible_capitalized_costs" value="{{ old('eligible_capitalized_costs', '0.00') }}" placeholder="0.00" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pl-8 font-mono" oninput="updateTotalCapitalized()">
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Freight, transit insurance, commissioning.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Salvage Residual Value (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-gray-400 font-bold">₹</span>
                        <input type="number" step="0.01" min="0" name="residual_value" value="{{ old('residual_value', '0.00') }}" placeholder="0.00" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pl-8 font-mono">
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Expected scrap value at end of useful life.</span>
                </div>
            </div>

            <!-- Dynamic Total Capitalized Display -->
            <div class="p-3 bg-gray-50 rounded-xl flex items-center justify-between border border-gray-100">
                <span class="text-xs text-gray-600 font-semibold">Total Capitalized Gross Block:</span>
                <span class="font-mono text-sm font-black text-gray-900" id="totalCapitalizedDisplay">₹0.00</span>
            </div>
        </div>

        <!-- Section 3: Depreciation Configuration -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">3. Depreciation Engine Configuration</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Useful Life (Years) *</label>
                    <input type="number" step="0.5" min="1" max="30" name="useful_life_years" id="useful_life_years" value="{{ old('useful_life_years', '8') }}" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-bold" oninput="updateEstimatedMonthly()">
                    <span class="text-[10px] text-gray-400 mt-1 block">Standard construction machinery: 8 - 10 years.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Depreciation Method *</label>
                    <select name="depreciation_method" id="depreciation_method" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white font-semibold" onchange="toggleWdvRate()">
                        <option value="straight_line" {{ old('depreciation_method') === 'straight_line' ? 'selected' : '' }}>Straight-Line Method (SLM)</option>
                        <option value="written_down_value" {{ old('depreciation_method') === 'written_down_value' ? 'selected' : '' }}>Written-Down Value (WDV)</option>
                    </select>
                </div>

                <div id="wdvRateContainer" class="{{ old('depreciation_method') === 'written_down_value' ? '' : 'hidden' }}">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Annual WDV Rate (%)</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="1" max="50" name="depreciation_rate_percent" value="{{ old('depreciation_rate_percent', '15.00') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">%</span>
                    </div>
                </div>
            </div>

            <div class="p-3 bg-purple-50 rounded-xl text-xs text-purple-900 border border-purple-100 flex items-center justify-between">
                <span>Estimated Monthly Depreciation Charge (SLM):</span>
                <strong class="font-mono text-sm text-purple-700" id="estimatedMonthlyDisplay">₹0.00</strong>
            </div>
        </div>

        <!-- Section 4: Operational Deployment & Financing Links -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">4. Deployment & Equipment Financing</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Deployed Project</label>
                    <select name="project_id" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                        <option value="">Central Yard / Unallocated</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}" {{ old('project_id') == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] text-gray-400 mt-1 block">Allocates periodic depreciation cost center.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Linked Financing Loan Facility</label>
                    <select name="loan_id" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                        <option value="">Owned Outright (No Active Debt)</option>
                        @foreach($loans as $l)
                            <option value="{{ $l->id }}" {{ old('loan_id') == $l->id ? 'selected' : '' }}>
                                {{ $l->lender_name }} - {{ $l->loan_account_number }} (Bal: ₹{{ number_format($l->outstanding_principal, 0) }})
                            </option>
                        @endforeach
                    </select>
                    <span class="text-[10px] text-gray-400 mt-1 block">Cross-links asset to EMI schedule.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Site / Physical Yard Location</label>
                    <input type="text" name="location" value="{{ old('location') }}" placeholder="e.g. Central Workshop, Bengaluru Yard" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Asset Notes & Commissioning Log</label>
                <textarea name="notes" rows="2" placeholder="Commissioning remarks, warranty details, or supplier invoice reference..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Submit & Actions -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('assets.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Save Capital Asset</span>
            </button>
        </div>
    </form>
</div>

<script>
function updateTotalCapitalized() {
    const acq = parseFloat(document.getElementById('acquisition_cost').value) || 0;
    const add = parseFloat(document.getElementById('eligible_capitalized_costs').value) || 0;
    const total = acq + add;
    document.getElementById('totalCapitalizedDisplay').innerText = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    updateEstimatedMonthly();
}

function updateEstimatedMonthly() {
    const acq = parseFloat(document.getElementById('acquisition_cost').value) || 0;
    const add = parseFloat(document.getElementById('eligible_capitalized_costs').value) || 0;
    const years = parseFloat(document.getElementById('useful_life_years').value) || 8;
    const total = acq + add;
    const months = years * 12;
    if (months > 0) {
        const monthly = total / months;
        document.getElementById('estimatedMonthlyDisplay').innerText = '₹' + monthly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

function toggleWdvRate() {
    const method = document.getElementById('depreciation_method').value;
    const container = document.getElementById('wdvRateContainer');
    if (method === 'written_down_value') {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

// Initial calculation on page load
updateTotalCapitalized();
</script>
@endsection
