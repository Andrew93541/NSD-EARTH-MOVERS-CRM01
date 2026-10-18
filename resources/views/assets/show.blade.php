@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header with Breadcrumbs & Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold mb-1">
                <a href="{{ route('assets.index') }}" class="hover:text-blue-600 transition">Fixed Assets</a>
                <span>/</span>
                <span class="text-gray-900 font-mono font-bold">{{ $asset->asset_code }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $asset->name }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $asset->asset_code }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $asset->statusBadgeClasses() }}">
                    {{ strtoupper($asset->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Category: <strong class="capitalize">{{ str_replace('_', ' ', $asset->category) }}</strong> •
                Capitalized: <strong>{{ $asset->capitalization_date->format('d M Y') }}</strong> •
                Location: <strong>{{ $asset->location ?? 'Central Yard' }}</strong>
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if(in_array($asset->status, ['active', 'idle', 'under_maintenance']))
                <button id="btnOpenCalcModal" onclick="document.getElementById('calcDeprModal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Calculate Depreciation</span>
                </button>
            @endif
            <a href="{{ route('assets.period-review') }}" class="px-3.5 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl text-xs font-semibold transition border border-purple-200">
                Fleet Review
            </a>
            <a href="{{ route('assets.index') }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                ← Back
            </a>
        </div>
    </div>

    <!-- Feedback messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-red-50 rounded-2xl border border-red-200 text-xs font-semibold text-red-800 space-y-1">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- 4 Financial Valuation Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- 1. Gross Block -->
        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Capitalized Gross Block</span>
            <strong class="text-xl font-bold font-mono text-gray-900 mt-2 block">₹{{ number_format($asset->total_capitalized_cost, 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Acq: ₹{{ number_format($asset->acquisition_cost, 0) }} + Add: ₹{{ number_format($asset->eligible_capitalized_costs, 0) }}</span>
        </div>

        <!-- 2. Accumulated Depreciation -->
        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Accumulated Depreciation</span>
            <strong class="text-xl font-bold font-mono text-purple-600 mt-2 block">₹{{ number_format($asset->accumulated_depreciation, 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">{{ $asset->depreciationEntries->where('status', 'posted')->count() }} Posted Periods in GL</span>
        </div>

        <!-- 3. Current Net Book Value -->
        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Current Net Book Value</span>
            <strong class="text-xl font-bold font-mono text-emerald-600 mt-2 block">₹{{ number_format($asset->current_book_value, 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Carrying value on Balance Sheet</span>
        </div>

        <!-- 4. Salvage Residual Value -->
        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Salvage Scrap Floor</span>
            <strong class="text-xl font-bold font-mono text-amber-600 mt-2 block">₹{{ number_format($asset->residual_value, 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Useful Life: {{ $asset->useful_life_years }} Years ({{ $asset->useful_life_months }} mos)</span>
        </div>
    </div>

    <!-- Dual Specification & Financing Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Technical & Operational Specs -->
        <div class="soft-card p-5 space-y-3">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Technical & Operational Specifications</h3>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-gray-400 text-[11px] block">Serial / Engine No:</span>
                    <strong class="font-mono text-gray-800">{{ $asset->serial_number ?? 'Not Recorded' }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] block">Depreciation Method:</span>
                    <span class="font-bold text-gray-800 capitalize">{{ str_replace('_', ' ', $asset->depreciation_method) }}</span>
                    @if($asset->depreciation_method === 'written_down_value')
                        <span class="text-purple-600 font-mono font-bold">({{ $asset->depreciation_rate_percent }}% p.a.)</span>
                    @endif
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] block">Monthly Depreciation Charge (SLM):</span>
                    <strong class="font-mono text-gray-800">₹{{ number_format($asset->monthlyStraightLineDepreciation(), 2) }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] block">Deployed Project:</span>
                    @if($asset->project)
                        <a href="{{ route('projects.show', $asset->project) }}" class="text-blue-600 font-bold hover:underline">
                            {{ $asset->project->name }}
                        </a>
                    @else
                        <span class="text-gray-500 italic">Central Yard Depot</span>
                    @endif
                </div>
            </div>
            <div class="pt-2 border-t border-gray-50 text-xs text-gray-500">
                <span>Account Mapping:</span>
                <span class="font-mono text-[11px] text-gray-700 bg-gray-50 px-2 py-0.5 rounded ml-1">
                    Dr 5030 (Depr Exp) / Cr 1520 (Accum Depr)
                </span>
            </div>
        </div>

        <!-- Financed Loan Facility Card -->
        <div class="soft-card p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Equipment Debt Financing</h3>
                @if($asset->loan)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $asset->loan->status === 'closed' ? 'bg-gray-100 text-gray-600' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                        {{ strtoupper($asset->loan->status) }}
                    </span>
                @endif
            </div>

            @if($asset->loan)
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-gray-400 text-[11px] block">Lender / Financier:</span>
                        <strong class="text-gray-800">{{ $asset->loan->lender_name }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[11px] block">Loan Account Number:</span>
                        <strong class="font-mono text-gray-800">{{ $asset->loan->loan_account_number }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[11px] block">Sanctioned Principal:</span>
                        <strong class="font-mono text-gray-800">₹{{ number_format($asset->loan->principal_amount, 2) }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[11px] block">Outstanding Debt Liability:</span>
                        <strong class="font-mono text-emerald-600">₹{{ number_format($asset->loan->outstanding_principal, 2) }}</strong>
                    </div>
                </div>
                <div class="pt-2 border-t border-gray-50 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Monthly EMI: <strong class="font-mono text-gray-900">₹{{ number_format($asset->loan->monthly_emi, 2) }}</strong></span>
                    <a href="{{ route('loans.show', $asset->loan) }}" class="text-blue-600 hover:text-blue-800 font-bold flex items-center gap-1">
                        <span>View Amortization Schedule</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            @else
                <div class="py-5 text-center text-xs text-gray-400">
                    <p>This equipment asset is owned 100% outright with no linked debt facility.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Depreciation Schedule & General Ledger History Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Depreciation History & General Ledger Entries</h2>
                <p class="text-xs text-gray-400 mt-0.5">Chronological record of periodic asset write-offs, approvals, and double-entry postings.</p>
            </div>
            @if(in_array($asset->status, ['active', 'idle', 'under_maintenance']))
                <button onclick="document.getElementById('calcDeprModal').classList.remove('hidden')" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Calculate Next Period</span>
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Period</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4 text-right">Opening Book Value (₹)</th>
                        <th class="py-3 px-4 text-right">Depreciation Charge (₹)</th>
                        <th class="py-3 px-4 text-right">Closing Book Value (₹)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">GL Journal Voucher</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($asset->depreciationEntries as $entry)
                        <tr class="hover:bg-gray-50/50 transition {{ $entry->status === 'posted' ? 'bg-emerald-50/20' : '' }}">
                            <td class="py-3 px-4">
                                <span class="font-bold text-gray-900 block">{{ $entry->period_start_date->format('M Y') }}</span>
                                <span class="text-[10px] text-gray-400">{{ $entry->period_start_date->format('d M') }} - {{ $entry->period_end_date->format('d M Y') }}</span>
                                @if($entry->is_partial_period)
                                    <span class="inline-block text-[9px] bg-purple-50 text-purple-700 px-1.5 py-0.2 rounded font-bold ml-1">Pro-rata ({{ $entry->days_in_period }}d)</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-gray-600 capitalize">
                                {{ str_replace('_', ' ', $entry->depreciation_method) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-gray-600">
                                ₹{{ number_format($entry->opening_book_value, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-purple-600">
                                ₹{{ number_format($entry->depreciation_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-emerald-600">
                                ₹{{ number_format($entry->closing_book_value, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $entry->statusBadgeClasses() }}">
                                    {{ strtoupper($entry->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                @if($entry->journalEntry)
                                    <a href="{{ route('accounting.journals') }}" class="font-mono text-[11px] font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition">
                                        <span>{{ $entry->journalEntry->entry_number }}</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                @else
                                    <span class="text-[10px] text-gray-400">Not Posted</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($entry->status === 'calculated')
                                    <form method="POST" action="{{ route('assets.depreciation.post', $entry) }}" class="inline">
                                        @csrf
                                        <button type="submit" id="btnPostDepr" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold shadow transition cursor-pointer">
                                            Post to GL
                                        </button>
                                    </form>
                                @elseif($entry->status === 'posted')
                                    <button onclick="openReverseDeprModal({{ $entry->id }})" class="text-[10px] text-red-500 hover:text-red-700 hover:underline font-semibold cursor-pointer">
                                        Reverse
                                    </button>
                                @else
                                    <span class="text-[10px] text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400 text-xs">
                                No depreciation entries generated yet. Click "Calculate Depreciation" above to calculate and post monthly write-offs.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Calculate Depreciation for this asset -->
<div id="calcDeprModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Calculate Periodic Depreciation</h3>
            <button onclick="document.getElementById('calcDeprModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('assets.depreciation.calculate', $asset) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Asset Model</label>
                <input type="text" readonly value="{{ $asset->name }} ({{ $asset->asset_code }})" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-gray-50 text-gray-800 font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Period Start Date *</label>
                    <input type="date" name="period_start_date" required value="{{ date('Y-m-01') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Period End Date *</label>
                    <input type="date" name="period_end_date" required value="{{ date('Y-m-t') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
            </div>

            <div class="p-3 bg-purple-50 rounded-xl text-xs text-purple-900 border border-purple-100">
                Depreciation will be computed using the configured <strong>{{ strtoupper(str_replace('_', ' ', $asset->depreciation_method)) }}</strong> formula with pro-rata partial-period scaling if applicable.
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('calcDeprModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitCalcModal" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow transition cursor-pointer">
                    Calculate Depreciation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reverse Depreciation Entry -->
<div id="reverseDeprModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-red-600">Reverse Depreciation Entry</h3>
            <button onclick="document.getElementById('reverseDeprModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="reverseDeprForm" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Mandatory Reversal Reason *</label>
                <textarea name="reversal_reason" required minlength="5" rows="3" placeholder="Provide audit reason (e.g. Asset sold before period-end, incorrect useful life estimate)..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5"></textarea>
            </div>

            <div class="p-3 bg-red-50 rounded-xl text-xs text-red-900 border border-red-100">
                Reversing will generate a counter-balancing journal entry (Debit Accumulated Depreciation, Credit Depreciation Expense) and restore asset book value.
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('reverseDeprModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow transition cursor-pointer">
                    Confirm Reversal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openReverseDeprModal(entryId) {
    document.getElementById('reverseDeprForm').action = '/assets/depreciation/' + entryId + '/reverse';
    document.getElementById('reverseDeprModal').classList.remove('hidden');
}
</script>
@endsection
