@extends('layouts.app')

@section('title', 'Configure Contract Retention Terms')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumbs & Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
                <a href="{{ route('retention.index') }}" class="hover:text-blue-600 transition">Retention Register</a>
                <span>/</span>
                <span class="text-blue-600 font-bold">New Contract</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Configure Contract Retention Terms</h1>
            <p class="text-xs text-gray-500 mt-1">Define project-specific retention deduction rules, defect liability periods, and release schedules.</p>
        </div>
        <a href="{{ route('retention.index') }}" class="px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
            Cancel
        </a>
    </div>

    <!-- Contract Terms Form -->
    <form method="POST" action="{{ route('retention.contracts.store') }}" class="space-y-6">
        @csrf

        <!-- 1. Project & Client Association -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">1. Project & Client Association</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Project *</label>
                    <select name="project_id" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Project</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }} ({{ $p->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('project_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Client *</label>
                    <select name="client_id" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Client</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Contract Number / Ref *</label>
                    <input type="text" name="contract_number" required value="{{ old('contract_number', 'CNT-' . date('Y') . '-' . rand(100, 999)) }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
                    @error('contract_number') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Agreement / Contract Title *</label>
                    <input type="text" name="title" required value="{{ old('title') }}" placeholder="e.g. Civil Construction Agreement & Structural Works" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                    @error('title') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Total Contract Value (₹) *</label>
                    <input type="number" step="0.01" name="contract_value" required value="{{ old('contract_value', '50000000.00') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
                    @error('contract_value') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Target Completion Date</label>
                    <input type="date" name="completion_date" value="{{ old('completion_date') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
            </div>
        </div>

        <!-- 2. Contractual Retention Configuration -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">2. Contractual Retention Terms</h2>
            
            <div class="flex items-center gap-3 p-3 bg-blue-50/60 rounded-xl border border-blue-100 text-xs text-blue-900">
                <input type="checkbox" name="retention_applicable" value="1" id="retention_applicable" checked class="w-4 h-4 text-blue-600 rounded">
                <label for="retention_applicable" class="font-semibold cursor-pointer">
                    Retention is applicable to certified client progress RA bills under this contract.
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Retention Percentage (%) *</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0" max="25" name="retention_rate_percent" required value="{{ old('retention_rate_percent', '5.00') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pr-8 font-mono">
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">%</span>
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Standard construction default: 5.00% to 10.00% of certified work.</span>
                    @error('retention_rate_percent') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Maximum Retention Cap (₹)</label>
                    <input type="number" step="0.01" min="0" name="max_retention_cap" value="{{ old('max_retention_cap') }}" placeholder="Leave blank if uncapped" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
                    <span class="text-[10px] text-gray-400 mt-1 block">Optional ceiling cap specified in contract (e.g. 5% of Contract Value).</span>
                </div>
            </div>
        </div>

        <!-- 3. Defect Liability Period & Release Schedule -->
        <div class="soft-card p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">3. Defect Liability Period (DLP) & Release Schedule</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">DLP Duration (Months) *</label>
                    <input type="number" min="0" max="60" name="dlp_duration_months" required value="{{ old('dlp_duration_months', '12') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
                    <span class="text-[10px] text-gray-400 mt-1 block">Duration during which contractor rectifies defects (typically 12 or 24 months).</span>
                    @error('dlp_duration_months') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">DLP Start Date Basis *</label>
                    <select name="dlp_start_date_basis" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                        <option value="practical_completion" {{ old('dlp_start_date_basis') === 'practical_completion' ? 'selected' : '' }}>
                            Practical Completion Certificate (PCC) Date
                        </option>
                        <option value="handover" {{ old('dlp_start_date_basis') === 'handover' ? 'selected' : '' }}>
                            Client Handover / Taking-Over Date
                        </option>
                        <option value="final_bill" {{ old('dlp_start_date_basis') === 'final_bill' ? 'selected' : '' }}>
                            Final Bill Certification Date
                        </option>
                    </select>
                </div>
            </div>

            <div class="pt-2">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Release Schedule Terms *</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/80 transition">
                        <input type="radio" name="release_terms_type" value="staged_50_50" checked class="mt-0.5 text-blue-600">
                        <div>
                            <span class="text-xs font-bold text-gray-900 block">Staged Release (50% PCC / 50% DLP)</span>
                            <span class="text-[11px] text-gray-500 mt-0.5 block">50% released on Practical Completion Certificate; remaining 50% on DLP expiry & defect clearance.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/80 transition">
                        <input type="radio" name="release_terms_type" value="single_full_release" {{ old('release_terms_type') === 'single_full_release' ? 'checked' : '' }} class="mt-0.5 text-blue-600">
                        <div>
                            <span class="text-xs font-bold text-gray-900 block">Single 100% Release (Post-DLP)</span>
                            <span class="text-[11px] text-gray-500 mt-0.5 block">100% retention withheld until full Defect Liability Period has expired and final DLCC is issued.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="pt-2">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Contract Notes / Clause References</label>
                <textarea name="notes" rows="3" placeholder="Reference relevant contract clauses (e.g. Clause 14.3: Retention Money & GCC Clause 33: Defect Liability)..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('retention.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Save Contract Retention Terms</span>
            </button>
        </div>
    </form>
</div>
@endsection
