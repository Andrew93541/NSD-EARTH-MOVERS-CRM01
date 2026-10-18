@extends('layouts.app')

@section('title', 'Retention Detail - ' . $contract->contract_number)

@section('content')
<div class="space-y-6">
    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
                <a href="{{ route('retention.index') }}" class="hover:text-blue-600 transition">Retention Register</a>
                <span>/</span>
                <span class="text-blue-600 font-bold">{{ $contract->contract_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $contract->title }}</h1>
            <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                <span class="font-medium text-gray-700">Project: <strong>{{ $contract->project?->name ?? '—' }}</strong></span>
                <span>•</span>
                <span>Client: <strong>{{ $contract->client?->name ?? '—' }}</strong></span>
                <span>•</span>
                <span class="font-mono">Value: ₹{{ number_format($contract->contract_value, 2) }}</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('certModal').classList.remove('hidden')" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Record Certificate</span>
            </button>
            <a href="{{ route('retention.index') }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                Back to Register
            </a>
        </div>
    </div>

    <!-- Contract Financial Summary KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Total Retention Withheld</span>
            <strong class="text-xl font-bold font-mono text-gray-900 mt-2 block">₹{{ number_format($summary['total_withheld'], 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">
                Rate: {{ number_format($summary['retention_rate_percent'], 1) }}%
                @if($summary['max_retention_cap'])
                    (Capped at ₹{{ number_format($summary['max_retention_cap'], 0) }})
                @endif
            </span>
        </div>

        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Eligible to Claim</span>
            <strong class="text-xl font-bold font-mono text-emerald-600 mt-2 block">₹{{ number_format($summary['total_remaining_eligible'], 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Across verified stages</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Claimed / In Process</span>
            <strong class="text-xl font-bold font-mono text-blue-600 mt-2 block">₹{{ number_format($summary['total_claimed'], 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Approved: ₹{{ number_format($summary['total_approved'], 2) }}</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Received / Outstanding</span>
            <strong class="text-xl font-bold font-mono text-gray-900 mt-2 block">₹{{ number_format($summary['total_received'], 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Remaining Withheld: ₹{{ number_format($summary['outstanding_withheld'], 2) }}</span>
        </div>
    </div>

    <!-- Contractual Release Stages & Eligibility Progression -->
    <div id="stages" class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
            <div>
                <h2 class="text-base font-bold text-gray-900">Contractual Release Stages & Milestone Eligibility</h2>
                <p class="text-xs text-gray-500 mt-0.5">Eligibility criteria defined in the contract agreement. Claims can only be drafted when all stage prerequisites are verified.</p>
            </div>
            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg">
                {{ $contract->release_terms_type === 'single_full_release' ? 'Single 100% Post-DLP Release' : 'Staged 50% PCC / 50% DLP Release' }}
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
            @foreach($summary['stages'] as $stg)
                <div class="p-5 rounded-2xl border {{ $stg['is_eligible'] ? 'border-emerald-200 bg-emerald-50/20' : 'border-gray-200 bg-gray-50/50' }} space-y-3 relative">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider {{ $stg['is_eligible'] ? 'text-emerald-700' : 'text-gray-400' }} block">
                                Stage Release ({{ number_format($stg['percentage'], 0) }}%)
                            </span>
                            <h3 class="text-sm font-bold text-gray-900 mt-0.5">{{ $stg['stage_name'] }}</h3>
                        </div>
                        @if($stg['is_eligible'])
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200 flex items-center gap-1">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Eligible for Claim</span>
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border bg-amber-50 text-amber-700 border-amber-200">
                                Pending Prerequisite
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3 py-2 border-y {{ $stg['is_eligible'] ? 'border-emerald-100' : 'border-gray-200/70' }} text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-gray-400 block font-sans">Target Retention</span>
                            <strong class="text-gray-900 block mt-0.5">₹{{ number_format($stg['target_amount'], 2) }}</strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-gray-400 block font-sans">Available to Claim</span>
                            <strong class="{{ $stg['remaining_eligible'] > 0 ? 'text-emerald-600 font-bold' : 'text-gray-500' }} block mt-0.5">
                                ₹{{ number_format($stg['remaining_eligible'], 2) }}
                            </strong>
                        </div>
                    </div>

                    <!-- Prerequisites Status -->
                    <div class="space-y-1.5 text-xs text-gray-600">
                        @if($stg['cert_available'])
                            <div class="flex items-center gap-2 text-emerald-700 font-medium">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Certificate Verified: <strong>{{ $stg['cert']->certificate_number ?? 'PCC/DLCC' }}</strong></span>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-gray-500">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>{{ $stg['ineligible_reason'] }}</span>
                            </div>
                        @endif

                        @if($stg['stage_key'] === 'stage_2_dlp')
                            <div class="text-[11px] text-gray-500 pt-1">
                                DLP Duration: <strong>{{ $contract->dlp_duration_months }} Months</strong>
                                @if($summary['dlp_expiry_date'])
                                    (Expires: <strong>{{ $summary['dlp_expiry_date'] }}</strong>)
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Action -->
                    <div class="pt-2 flex items-center justify-between">
                        <span class="text-[11px] text-gray-400">
                            Previously Claimed: ₹{{ number_format($stg['previously_claimed'], 2) }}
                        </span>
                        @if($stg['is_eligible'] && $stg['remaining_eligible'] > 0)
                            <a href="{{ route('retention.claims.create', ['contract' => $contract, 'stage_key' => $stg['stage_key']]) }}" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                                <span>Draft Release Claim</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        @elseif($stg['remaining_eligible'] <= 0 && $stg['target_amount'] > 0)
                            <span class="text-xs font-bold text-gray-400">Fully Claimed</span>
                        @else
                            <button disabled class="px-3 py-1.5 bg-gray-200 text-gray-400 rounded-xl text-xs font-semibold cursor-not-allowed">
                                Not Eligible
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Source RA Bills Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Source Client RA Bills & Withheld Retention</h2>
                <p class="text-xs text-gray-400 mt-0.5">Traceability of retention deductions across individual progress running bills.</p>
            </div>
            <span class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-bold">{{ $contract->invoices->count() }} Invoices</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Invoice #</th>
                        <th class="py-3 px-4">Issue Date</th>
                        <th class="py-3 px-4 text-right">Certified Subtotal (₹)</th>
                        <th class="py-3 px-4 text-center">Rate</th>
                        <th class="py-3 px-4 text-right">Retention Withheld (₹)</th>
                        <th class="py-3 px-4 text-right">Claimed (₹)</th>
                        <th class="py-3 px-4 text-right">Unclaimed (₹)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($contract->invoices as $inv)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                <a href="{{ route('billing.show', $inv) }}" class="hover:text-blue-600 transition">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $inv->issue_date?->format('d M Y') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($inv->subtotal, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-gray-600">
                                {{ number_format($inv->retention_rate_percent, 1) }}%
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-blue-600">
                                ₹{{ number_format($inv->retention_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-purple-600 font-semibold">
                                ₹{{ number_format($inv->claimedRetention(), 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">
                                ₹{{ number_format($inv->unclaimedRetention(), 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase bg-gray-100 text-gray-700 border-gray-200">
                                    {{ $inv->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400 text-xs">
                                No RA bills linked to this contract yet. Future progress bills will automatically deduct configured retention.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Verified Certificates & Audit Register -->
    <div class="soft-card p-6 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
            <div>
                <h2 class="text-base font-bold text-gray-900">Completion & Defect Clearance Certificates</h2>
                <p class="text-xs text-gray-500 mt-0.5">Audit trail of engineering completion notes and defect-free certifications.</p>
            </div>
            <button onclick="document.getElementById('certModal').classList.remove('hidden')" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Certificate</span>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($contract->certificates as $cert)
                <div class="p-4 rounded-xl border border-gray-200 bg-white space-y-2">
                    <div class="flex items-start justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-blue-50 text-blue-700 border-blue-200">
                            {{ $cert->typeLabel() }}
                        </span>
                        <span class="text-[10px] text-gray-400 font-mono">{{ $cert->issue_date?->format('d M Y') }}</span>
                    </div>
                    <div>
                        <strong class="text-sm font-bold text-gray-900 block font-mono">{{ $cert->certificate_number }}</strong>
                        <span class="text-[11px] text-gray-500 block truncate">Authority: {{ $cert->issuing_authority }}</span>
                    </div>
                    <div class="text-xs text-gray-600 pt-1 border-t border-gray-100 flex items-center justify-between">
                        <span>Defects: {{ $cert->defect_count_resolved }}/{{ $cert->defect_count_reported }}</span>
                        <span class="font-bold {{ $cert->all_defects_rectified ? 'text-emerald-600' : 'text-red-500' }}">
                            {{ $cert->all_defects_rectified ? 'Rectified' : 'Pending' }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="col-span-3 py-6 text-center text-gray-400 text-xs bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                    No completion certificates recorded. Click "Record Certificate" to upload Practical Completion or DLP Clearance certificates.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Retention Claims History -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Submitted Retention Release Claims</h2>
                <p class="text-xs text-gray-400 mt-0.5">Claims history, client billing, receipts, and outstanding settlement balances.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Claim #</th>
                        <th class="py-3 px-4">Stage</th>
                        <th class="py-3 px-4 text-right">Claimed (₹)</th>
                        <th class="py-3 px-4 text-right">Approved (₹)</th>
                        <th class="py-3 px-4 text-right">Received (₹)</th>
                        <th class="py-3 px-4 text-right">Balance Due (₹)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($contract->retentionClaims as $claim)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                <a href="{{ route('retention.claims.show', $claim) }}" class="hover:text-blue-600 transition">
                                    {{ $claim->claim_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                {{ $claim->stage_name }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($claim->claimed_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-blue-600 font-semibold">
                                {{ $claim->approved_amount ? '₹' . number_format($claim->approved_amount, 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-600 font-semibold">
                                ₹{{ number_format($claim->received_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                ₹{{ number_format($claim->balanceDue(), 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase {{ $claim->statusBadgeClass() }}">
                                    {{ $claim->statusLabel() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('retention.claims.show', $claim) }}" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400 text-xs">
                                No release claims drafted yet for this contract.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record Certificate -->
<div id="certModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Record Completion / DLP Certificate</h3>
            <button onclick="document.getElementById('certModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('retention.contracts.certificates.store', $contract) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Certificate Type *</label>
                <select name="certificate_type" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                    <option value="practical_completion">Practical Completion Certificate (PCC)</option>
                    <option value="handover_note">Project Handover & Taking-Over Certificate</option>
                    <option value="dlp_clearance">Defect Liability Period (DLP) Clearance Certificate</option>
                    <option value="defect_free_certificate">Final Defect-Free Certificate</option>
                    <option value="final_measurement_clearance">Joint Final Measurement Clearance</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Certificate # *</label>
                    <input type="text" name="certificate_number" required placeholder="e.g. PCC-2026-081" class="w-full text-xs border border-gray-200 rounded-xl p-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Issuing Authority *</label>
                    <input type="text" name="issuing_authority" required placeholder="e.g. Independent Engineer / Client" class="w-full text-xs border border-gray-200 rounded-xl p-2">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Issue Date *</label>
                    <input type="date" name="issue_date" required value="{{ date('Y-m-d') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Effective Date *</label>
                    <input type="date" name="effective_date" required value="{{ date('Y-m-d') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Defects Reported</label>
                    <input type="number" name="defect_count_reported" min="0" value="0" class="w-full text-xs border border-gray-200 rounded-xl p-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Defects Rectified</label>
                    <input type="number" name="defect_count_resolved" min="0" value="0" class="w-full text-xs border border-gray-200 rounded-xl p-2 font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Upload Document (PDF / Scan)</label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs border border-gray-200 rounded-xl p-2">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Inspection Remarks</label>
                <textarea name="inspection_remarks" rows="2" placeholder="Summary of punch list, joint site inspection notes..." class="w-full text-xs border border-gray-200 rounded-xl p-2"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('certModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow transition">
                    Verify & Save Certificate
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
