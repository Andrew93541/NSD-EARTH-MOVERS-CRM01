@extends('layouts.app')

@section('title', 'Retention Claim - ' . $claim->claim_number)

@section('content')
<div class="space-y-6">
    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
                <a href="{{ route('retention.index') }}" class="hover:text-blue-600 transition">Retention Register</a>
                <span>/</span>
                <a href="{{ route('retention.contracts.show', $claim->contract) }}" class="hover:text-blue-600 transition">{{ $claim->contract->contract_number }}</a>
                <span>/</span>
                <span class="text-blue-600 font-bold font-mono">{{ $claim->claim_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 font-mono">{{ $claim->claim_number }}</h1>
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold border uppercase {{ $claim->statusBadgeClass() }}">
                    {{ $claim->statusLabel() }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Stage: <strong>{{ $claim->stage_name }}</strong> • Project: <strong>{{ $claim->project?->name }}</strong> • Client: <strong>{{ $claim->client?->name }}</strong>
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            @if($claim->status === 'draft')
                <form method="POST" action="{{ route('retention.claims.submit', $claim) }}">
                    @csrf
                    <button type="submit" id="btnSubmitForReview" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Submit for Review</span>
                    </button>
                </form>
            @endif

            @if($claim->status === 'submitted')
                <button id="btnOpenApproveModal" onclick="document.getElementById('approveModal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Approve Claim</span>
                </button>
                <button id="btnOpenRejectModal" onclick="document.getElementById('rejectModal').classList.remove('hidden')" class="px-3.5 py-2 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>Reject</span>
                </button>
            @endif

            @if($claim->status === 'approved')
                <button id="btnOpenClientSubmitModal" onclick="document.getElementById('clientSubmitModal').classList.remove('hidden')" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-500/20 transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span>Mark Submitted to Client</span>
                </button>
            @endif

            @if(in_array($claim->status, ['approved', 'client_submitted', 'partially_received']) && $claim->balanceDue() > 0)
                <button id="btnOpenReceiptModal" onclick="document.getElementById('receiptModal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Record Client Receipt</span>
                </button>
            @endif

            <a href="{{ route('retention.contracts.show', $claim->contract) }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                Contract Detail
            </a>
        </div>
    </div>

    <!-- Financial Metrics Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Claimed Retention</span>
            <strong class="text-xl font-bold font-mono text-gray-900 mt-2 block">₹{{ number_format($claim->claimed_amount, 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">Prepared by: {{ $claim->creator?->name ?? 'User' }}</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Approved Amount</span>
            <strong class="text-xl font-bold font-mono text-blue-600 mt-2 block">
                {{ $claim->approved_amount !== null ? '₹' . number_format($claim->approved_amount, 2) : 'Under Review' }}
            </strong>
            <span class="text-[11px] text-gray-500 mt-1 block">
                {{ $claim->reviewer ? 'By ' . $claim->reviewer->name : 'Pending approval' }}
            </span>
        </div>

        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Received from Client</span>
            <strong class="text-xl font-bold font-mono text-emerald-600 mt-2 block">₹{{ number_format($claim->received_amount, 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">{{ $claim->payments->count() }} Client Receipts</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Net Balance Due</span>
            <strong class="text-xl font-bold font-mono text-purple-600 mt-2 block">₹{{ number_format($claim->balanceDue(), 2) }}</strong>
            <span class="text-[11px] text-gray-500 mt-1 block">
                @if($claim->due_date)
                    Due: {{ $claim->due_date->format('d M Y') }}
                @else
                    Pending settlement
                @endif
            </span>
        </div>
    </div>

    <!-- Visual Lifecycle Timeline -->
    <div class="soft-card p-6 space-y-3">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Claim Lifecycle State</h3>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2">
            <!-- 1. Draft -->
            <div class="p-3 rounded-xl border {{ in_array($claim->status, ['draft', 'submitted', 'approved', 'client_submitted', 'partially_received', 'received', 'closed']) ? 'border-blue-200 bg-blue-50/50' : 'border-gray-200 opacity-50' }}">
                <span class="text-[10px] font-bold uppercase text-blue-600">1. Draft</span>
                <strong class="text-xs font-bold text-gray-900 block mt-0.5">Prepared</strong>
                <span class="text-[10px] text-gray-400 block mt-0.5">{{ $claim->created_at?->format('d M Y') }}</span>
            </div>

            <!-- 2. Review -->
            <div class="p-3 rounded-xl border {{ in_array($claim->status, ['submitted', 'approved', 'client_submitted', 'partially_received', 'received', 'closed']) ? 'border-amber-200 bg-amber-50/50' : 'border-gray-200 opacity-50' }}">
                <span class="text-[10px] font-bold uppercase text-amber-600">2. Review</span>
                <strong class="text-xs font-bold text-gray-900 block mt-0.5">{{ $claim->status === 'rejected' ? 'Rejected' : 'Internal Sign-off' }}</strong>
                <span class="text-[10px] text-gray-400 block mt-0.5">{{ $claim->submission_date?->format('d M Y') ?? '—' }}</span>
            </div>

            <!-- 3. Approved -->
            <div class="p-3 rounded-xl border {{ in_array($claim->status, ['approved', 'client_submitted', 'partially_received', 'received', 'closed']) ? 'border-blue-300 bg-blue-50/70' : 'border-gray-200 opacity-50' }}">
                <span class="text-[10px] font-bold uppercase text-blue-700">3. Approved</span>
                <strong class="text-xs font-bold text-gray-900 block mt-0.5">Management Cleared</strong>
                <span class="text-[10px] text-gray-400 block mt-0.5">{{ $claim->reviewed_at?->format('d M Y') ?? '—' }}</span>
            </div>

            <!-- 4. Client Submitted -->
            <div class="p-3 rounded-xl border {{ in_array($claim->status, ['client_submitted', 'partially_received', 'received', 'closed']) ? 'border-purple-200 bg-purple-50/60' : 'border-gray-200 opacity-50' }}">
                <span class="text-[10px] font-bold uppercase text-purple-600">4. Billed to Client</span>
                <strong class="text-xs font-bold text-gray-900 block mt-0.5">{{ $claim->client_reference ?? 'Submitted' }}</strong>
                <span class="text-[10px] text-gray-400 block mt-0.5">{{ $claim->client_submitted_at?->format('d M Y') ?? '—' }}</span>
            </div>

            <!-- 5. Settled -->
            <div class="p-3 rounded-xl border {{ in_array($claim->status, ['received', 'closed']) ? 'border-emerald-300 bg-emerald-50/70' : 'border-gray-200 opacity-50' }}">
                <span class="text-[10px] font-bold uppercase text-emerald-700">5. Settled</span>
                <strong class="text-xs font-bold text-gray-900 block mt-0.5">Payment Received</strong>
                <span class="text-[10px] text-gray-400 block mt-0.5">₹{{ number_format($claim->received_amount, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Rejection Banner if applicable -->
    @if($claim->status === 'rejected')
        <div class="p-4 bg-red-50 rounded-2xl border border-red-200 text-xs text-red-900 space-y-1">
            <div class="flex items-center gap-2 font-bold text-red-800">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Claim Rejected during Internal Review</span>
            </div>
            <p><strong>Reason:</strong> {{ $claim->rejection_reason }}</p>
            <p class="text-[11px] text-red-600">Reviewed by {{ $claim->reviewer?->name ?? 'Reviewer' }} on {{ $claim->reviewed_at?->format('d M Y, h:i A') }}</p>
        </div>
    @endif

    <!-- Source RA Bills Itemized Allocation Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Source RA Bill Drawdown Breakdown</h2>
                <p class="text-xs text-gray-400 mt-0.5">Specific client running bills backing this release claim, ensuring zero double-claiming.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Invoice #</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4 text-right">Invoice Subtotal (₹)</th>
                        <th class="py-3 px-4 text-right">Originally Withheld (₹)</th>
                        <th class="py-3 px-4 text-right">Previously Claimed (₹)</th>
                        <th class="py-3 px-4 text-right">Allocated to This Claim (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($claim->invoiceItems as $item)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                @if($item->invoice)
                                    <a href="{{ route('billing.show', $item->invoice) }}" class="hover:text-blue-600 transition">
                                        {{ $item->invoice->invoice_number }}
                                    </a>
                                @else
                                    #{{ $item->client_invoice_id }}
                                @endif
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $item->invoice?->issue_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono">
                                ₹{{ number_format($item->invoice?->subtotal ?? 0, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-gray-700">
                                ₹{{ number_format($item->source_retention_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-purple-600">
                                ₹{{ number_format($item->previously_claimed_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-blue-600">
                                ₹{{ number_format($item->claim_allocated_amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-400 text-xs">
                                No invoice allocations logged.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Client Receipts History Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Recorded Client Receipts & Bank Settlements</h2>
                <p class="text-xs text-gray-400 mt-0.5">Cash remittances received against this retention release claim.</p>
            </div>
            @if(in_array($claim->status, ['approved', 'client_submitted', 'partially_received']) && $claim->balanceDue() > 0)
                <button onclick="document.getElementById('receiptModal').classList.remove('hidden')" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Record Receipt</span>
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Receipt #</th>
                        <th class="py-3 px-4">Payment Date</th>
                        <th class="py-3 px-4">Mode</th>
                        <th class="py-3 px-4">Reference / UTR</th>
                        <th class="py-3 px-4 text-right">Amount (₹)</th>
                        <th class="py-3 px-4">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($claim->payments as $pay)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                {{ $pay->receipt_number }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $pay->payment_date?->format('d M Y') }}
                            </td>
                            <td class="py-3 px-4 uppercase text-[10px] font-bold text-gray-700">
                                {{ str_replace('_', ' ', $pay->payment_mode) }}
                            </td>
                            <td class="py-3 px-4 font-mono text-gray-600">
                                {{ $pay->reference_number ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">
                                ₹{{ number_format($pay->amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $pay->recorder?->name ?? 'User' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-400 text-xs">
                                No receipts recorded yet. Once client remits the payment, record it here to update receivables.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supporting Handover Protocols & Defect Liability Certificates -->
    <x-document-manager 
        :record="$claim" 
        type="retention_claim" 
        title="Defect Liability Certificates & Release Evidence"
        :categories="[
            'completion_certificate' => 'Defects Liability Clearance Certificate (DLC)',
            'measurement_sheet' => 'Final Reconciled Joint Measurement Sheet',
            'contract' => 'Formal Retention Release Claim / Indemnity Undertaking',
            'voucher_support' => 'Client Bank Remittance / Advice Copy',
            'general' => 'General Retention Correspondence',
        ]"
    />
</div>

<!-- Modal: Approve Claim -->
<div id="approveModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Approve Retention Release Claim</h3>
            <button onclick="document.getElementById('approveModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('retention.claims.approve', $claim) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Approved Amount (₹) *</label>
                <input type="number" step="0.01" min="1" max="{{ $claim->claimed_amount }}" name="approved_amount" value="{{ $claim->claimed_amount }}" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono font-bold">
                <span class="text-[10px] text-gray-400 mt-1 block">Claimed amount: ₹{{ number_format($claim->claimed_amount, 2) }}</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Approval Comments / Audit Notes</label>
                <textarea name="approval_remarks" rows="3" placeholder="Verified milestone completion and zero defect warranty clearance..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" id="btnConfirmApprove" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow transition cursor-pointer">
                    Confirm Approval
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reject Claim -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-red-600">Reject Retention Release Claim</h3>
            <button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('retention.claims.reject', $claim) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Mandatory Rejection Reason *</label>
                <textarea name="rejection_reason" required minlength="5" rows="3" placeholder="Specify why the claim is rejected (e.g. pending defect rectification, missing consultant seal)..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow transition">
                    Reject Claim
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Mark Submitted to Client -->
<div id="clientSubmitModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Record Client Dispatch & Submission</h3>
            <button onclick="document.getElementById('clientSubmitModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('retention.claims.client-submit', $claim) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Client Inward / Claim Ref Number *</label>
                <input type="text" name="client_reference" required placeholder="e.g. CLI-RET-2026-902" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Submission / Dispatch Date *</label>
                <input type="date" name="client_submission_date" required value="{{ date('Y-m-d') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
            </div>

            <div class="p-3 bg-purple-50 rounded-xl text-xs text-purple-900 border border-purple-100">
                Recording client submission will officially update the outstanding client receivables register and post the accounting voucher without duplicating revenue.
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('clientSubmitModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" id="btnConfirmClientSubmit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow transition cursor-pointer">
                    Confirm Dispatch to Client
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Record Client Receipt -->
<div id="receiptModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Record Client Retention Receipt</h3>
            <button onclick="document.getElementById('receiptModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('retention.claims.receipts.store', $claim) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Receipt Amount (₹) *</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-xs text-gray-400 font-bold">₹</span>
                    <input type="number" step="0.01" min="1" max="{{ $claim->balanceDue() }}" name="amount" required value="{{ $claim->balanceDue() }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pl-8 font-mono font-bold">
                </div>
                <span class="text-[10px] text-gray-400 mt-1 block">Maximum outstanding balance: ₹{{ number_format($claim->balanceDue(), 2) }}</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Payment Date *</label>
                    <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Payment Mode *</label>
                    <select name="payment_mode" required class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                        <option value="bank_transfer">Bank Transfer (NEFT/RTGS)</option>
                        <option value="cheque">Cheque / Demand Draft</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Bank Reference / UTR Number</label>
                <input type="text" name="reference_number" placeholder="e.g. UTR-HDFC-992019" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Deposited Bank Account</label>
                <select name="bank_account_id" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                    <option value="">Select Bank Account</option>
                    @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('receiptModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" id="btnConfirmReceipt" class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold shadow transition cursor-pointer">
                    Record Receipt
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
