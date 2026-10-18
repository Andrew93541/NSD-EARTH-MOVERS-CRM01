@extends('layouts.app')

@section('title', 'Prepare Retention Release Claim')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
                <a href="{{ route('retention.index') }}" class="hover:text-blue-600 transition">Retention Register</a>
                <span>/</span>
                <a href="{{ route('retention.contracts.show', $contract) }}" class="hover:text-blue-600 transition">{{ $contract->contract_number }}</a>
                <span>/</span>
                <span class="text-blue-600 font-bold">New Release Claim</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Prepare Retention Release Claim</h1>
            <p class="text-xs text-gray-500 mt-1">Draft a formal claim against verified milestone completion or defect liability clearance.</p>
        </div>
        <a href="{{ route('retention.contracts.show', $contract) }}" class="px-3.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
            Cancel
        </a>
    </div>

    <!-- Eligible Stage Summary Banner -->
    <div class="soft-card p-6 border-l-4 border-l-emerald-500 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                Verified Stage Prerequisite
            </span>
            <span class="text-xs font-mono text-gray-500">Contract: <strong>{{ $contract->contract_number }}</strong></span>
        </div>
        <h2 class="text-lg font-bold text-gray-900">{{ $stage['stage_name'] }}</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-gray-100 text-xs">
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Contract Target Retention</span>
                <strong class="text-gray-900 font-mono text-sm block mt-0.5">₹{{ number_format($stage['target_amount'], 2) }}</strong>
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Previously Claimed</span>
                <strong class="text-blue-600 font-mono text-sm block mt-0.5">₹{{ number_format($stage['previously_claimed'], 2) }}</strong>
            </div>
            <div>
                <span class="text-gray-400 block text-[10px] uppercase font-bold">Maximum Eligible to Claim</span>
                <strong class="text-emerald-600 font-mono text-sm block mt-0.5">₹{{ number_format($stage['remaining_eligible'], 2) }}</strong>
            </div>
        </div>
    </div>

    <!-- Claim Preparation Form -->
    <form method="POST" action="{{ route('retention.claims.store', $contract) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="stage_key" value="{{ $stage['stage_key'] }}">

        <div class="soft-card p-6 space-y-4">
            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Claim Parameters</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Claim Amount (₹) *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-gray-400 font-bold">₹</span>
                        <input type="number" step="0.01" min="1" max="{{ $stage['remaining_eligible'] }}" name="claimed_amount" required value="{{ old('claimed_amount', $stage['remaining_eligible']) }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pl-8 font-mono font-bold text-gray-900 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Maximum permissible for this release: ₹{{ number_format($stage['remaining_eligible'], 2) }}</span>
                    @error('claimed_amount') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Expected Payment Due Date *</label>
                    <input type="date" name="due_date" required value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 focus:ring-2 focus:ring-blue-500">
                    <span class="text-[10px] text-gray-400 mt-1 block">Standard contractual turnaround: 30 days from client submission.</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Claim Notes / Client Submission Justification</label>
                <textarea name="notes" rows="3" placeholder="Reference certified milestone completion, zero defect certificate no., and client inspection clearance..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5">{{ old('notes') }}</textarea>
            </div>

            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-3">
                <input type="checkbox" name="submit_for_review" value="1" id="submit_for_review" checked class="w-4 h-4 text-blue-600 rounded">
                <label for="submit_for_review" class="text-xs font-semibold text-gray-800 cursor-pointer">
                    Submit immediately for internal commercial and management approval (Status: <span class="text-amber-700 font-bold">Under Review</span>).
                </label>
            </div>
        </div>

        <!-- Source RA Bills Allocation Preview -->
        <div class="soft-card p-6 space-y-3">
            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Source RA Bill Traceability</h3>
            <p class="text-xs text-gray-500">The claimed retention will be proportionally drawn from the original client running bills below, maintaining strict balance sheet traceability.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="text-gray-400 font-bold uppercase text-[10px] border-b border-gray-100">
                            <th class="py-2 px-2">Invoice #</th>
                            <th class="py-2 px-2">Date</th>
                            <th class="py-2 px-2 text-right">Withheld (₹)</th>
                            <th class="py-2 px-2 text-right">Previously Claimed (₹)</th>
                            <th class="py-2 px-2 text-right">Available Unclaimed (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($contract->invoices as $inv)
                            <tr>
                                <td class="py-2.5 px-2 font-mono font-bold text-gray-800">{{ $inv->invoice_number }}</td>
                                <td class="py-2.5 px-2 text-gray-500">{{ $inv->issue_date?->format('d M Y') }}</td>
                                <td class="py-2.5 px-2 text-right font-mono">₹{{ number_format($inv->retention_amount, 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-purple-600">₹{{ number_format($inv->claimedRetention(), 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono font-bold text-emerald-600">₹{{ number_format($inv->unclaimedRetention(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('retention.contracts.show', $contract) }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                Cancel
            </a>
            <button type="submit" id="btnSubmitClaim" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Save Retention Claim</span>
            </button>
        </div>
    </form>
</div>
@endsection
