@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header with Breadcrumb and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold mb-1">
                <a href="{{ route('loans.index') }}" class="hover:text-blue-600 transition">Equipment Loans & Debt</a>
                <span>/</span>
                <span class="text-gray-900 font-mono">{{ $loan->loan_account_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $loan->asset_description }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $loan->loan_account_number }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ $loan->status === 'closed' ? 'bg-gray-100 text-gray-600' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                    {{ strtoupper($loan->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Lender: <strong>{{ $loan->lender_name }}</strong> • Interest: <strong>{{ $loan->annual_interest_rate }}% p.a.</strong> • Tenure: <strong>{{ $loan->tenure_months }} Months</strong></p>
        </div>
        <div class="flex items-center gap-2">
            @if($loan->fixedAsset)
                <a href="{{ route('assets.show', $loan->fixedAsset) }}" class="px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-purple-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>View Financed Asset: {{ $loan->fixedAsset->asset_code }}</span>
                </a>
            @endif
            <a href="{{ route('accounting.journals') }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                General Ledger
            </a>
            <a href="{{ route('loans.index') }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
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

    <!-- 4 Key Financial Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Monthly EMI</span>
            <div class="text-2xl font-black text-blue-600 mt-1 font-mono">₹{{ number_format($loan->monthly_emi, 2) }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Contractual commitment</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Sanctioned Principal</span>
            <div class="text-2xl font-black text-gray-900 mt-1 font-mono">₹{{ number_format($loan->principal_amount, 2) }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Total borrowing limit</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Outstanding Principal</span>
            <div class="text-2xl font-black text-emerald-600 mt-1 font-mono">₹{{ number_format($loan->outstanding_principal, 2) }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">Active debt liability</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Total Interest Paid</span>
            <div class="text-2xl font-black text-purple-600 mt-1 font-mono">₹{{ number_format($loan->totalPaidInterest(), 2) }}</div>
            <span class="text-[10px] text-gray-400 mt-1 block">{{ $loan->emiSchedules->where('status', 'paid')->count() }} settled installments</span>
        </div>
    </div>

    <!-- Linked Fixed Asset & Accounting Config Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Asset Block -->
        <div class="soft-card p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Financed Capital Asset</h3>
                @if($loan->fixedAsset)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $loan->fixedAsset->statusBadgeClasses() }}">
                        {{ strtoupper($loan->fixedAsset->status) }}
                    </span>
                @endif
            </div>

            @if($loan->fixedAsset)
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-gray-400 text-[11px] block">Asset Code:</span>
                        <strong class="font-mono text-gray-800">{{ $loan->fixedAsset->asset_code }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[11px] block">Capitalized Cost:</span>
                        <strong class="font-mono text-gray-800">₹{{ number_format($loan->fixedAsset->total_capitalized_cost, 2) }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[11px] block">Net Book Value:</span>
                        <strong class="font-mono text-emerald-600">₹{{ number_format($loan->fixedAsset->current_book_value, 2) }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[11px] block">Depreciation Method:</span>
                        <span class="font-semibold text-gray-700 capitalize">{{ str_replace('_', ' ', $loan->fixedAsset->depreciation_method) }}</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-gray-50 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Asset Cost vs Loan:</span>
                    <span class="font-mono text-[11px] text-gray-700 font-bold">
                        Asset ₹{{ number_format($loan->fixedAsset->current_book_value, 0) }} vs Loan ₹{{ number_format($loan->outstanding_principal, 0) }}
                    </span>
                </div>
            @else
                <div class="py-4 text-center text-xs text-gray-400">
                    <p>No capital asset explicitly linked to this loan facility.</p>
                    <a href="{{ route('assets.create') }}" class="text-blue-600 font-semibold hover:underline mt-1 inline-block">
                        + Register Asset & Link Loan
                    </a>
                </div>
            @endif
        </div>

        <!-- Accounting Accounts Block -->
        <div class="soft-card p-5 space-y-3">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Double-Entry Account Mapping</h3>
            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between py-1 border-b border-gray-50">
                    <span class="text-gray-500">Loan Liability Account (Debit Principal):</span>
                    <span class="font-mono font-bold text-gray-800 bg-gray-50 px-2 py-0.5 rounded">
                        {{ $loan->liabilityAccount ? $loan->liabilityAccount->code . ' - ' . $loan->liabilityAccount->name : '2010 - Equipment Financing Loans & Borrowings' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-gray-50">
                    <span class="text-gray-500">Interest Expense Account (Debit Interest):</span>
                    <span class="font-mono font-bold text-gray-800 bg-gray-50 px-2 py-0.5 rounded">
                        {{ $loan->interestAccount ? $loan->interestAccount->code . ' - ' . $loan->interestAccount->name : '5020 - Loan Interest & Financing Charges' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-1">
                    <span class="text-gray-500">Settling Bank Account (Credit Cash Outflow):</span>
                    <span class="font-mono font-bold text-gray-800 bg-gray-50 px-2 py-0.5 rounded">
                        1010 - Operating Bank Account
                    </span>
                </div>
            </div>
            <div class="p-2.5 bg-blue-50/60 rounded-xl text-[11px] text-blue-900 border border-blue-100/80">
                Every settlement atomically generates a balanced General Ledger journal entry with strict debit/credit validation.
            </div>
        </div>
    </div>

    <!-- Amortization Schedule Table -->
    <div class="soft-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Amortized EMI Schedule & General Ledger Posting Status</h3>
                <p class="text-xs text-gray-500 mt-0.5">Automated split of principal vs interest with traceable journal entry vouchers.</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> GL Posted</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span> Pending</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Inst #</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3 text-right">Principal (₹)</th>
                        <th class="py-3 px-3 text-right">Interest (₹)</th>
                        <th class="py-3 px-3 text-right">Total EMI (₹)</th>
                        <th class="py-3 px-3 text-right">Remaining Principal</th>
                        <th class="py-3 px-3 text-center">Settlement Status</th>
                        <th class="py-3 px-3">GL Journal Voucher</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 font-sans">
                    @foreach ($loan->emiSchedules as $sch)
                        <tr class="hover:bg-gray-50/60 transition {{ $sch->status === 'paid' ? 'bg-emerald-50/20' : '' }}">
                            <td class="py-2.5 px-3 font-mono font-bold text-gray-600">#{{ $sch->installment_number }}</td>
                            <td class="py-2.5 px-3 font-semibold text-gray-900">{{ $sch->due_date->format('d M Y') }}</td>
                            <td class="py-2.5 px-3 text-right font-mono font-semibold text-gray-800">₹{{ number_format($sch->principal_component, 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-purple-600">₹{{ number_format($sch->interest_component, 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-blue-600">₹{{ number_format($sch->total_emi, 2) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-gray-500">₹{{ number_format($sch->remaining_balance, 2) }}</td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sch->status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ strtoupper($sch->status) }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3">
                                @if($sch->journalEntry)
                                    <a href="{{ route('accounting.journals') }}" class="font-mono text-[11px] font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition" title="View in General Ledger">
                                        <span>{{ $sch->journalEntry->entry_number }}</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                @elseif($sch->status === 'paid')
                                    <span class="text-[10px] text-gray-400 font-mono">Paid (Manual)</span>
                                @else
                                    <span class="text-[10px] text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                @if ($sch->status === 'pending')
                                    <button id="btnOpenPayModal" onclick="openPayModal({{ $sch->id }}, {{ $sch->installment_number }}, '{{ number_format($sch->total_emi, 2) }}')" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition cursor-pointer">
                                        Settle & Post GL
                                    </button>
                                @else
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-[11px] text-gray-500 font-semibold">{{ $sch->paid_date ? $sch->paid_date->format('d M') : 'Settled' }}</span>
                                        <button onclick="openReverseModal({{ $sch->id }}, {{ $sch->installment_number }})" class="text-[10px] text-red-500 hover:text-red-700 hover:underline font-semibold cursor-pointer">
                                            Reverse
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Loan Agreements, Sanction Letters & Amortization Schedules -->
    <x-document-manager 
        :record="$loan" 
        type="loan" 
        title="Sanction Letters, Loan Agreements & EMI Schedules"
        :categories="[
            'contract' => 'Bank Sanction Letter & Loan Agreement',
            'emi_schedule' => 'Bank EMI Repayment Schedule',
            'voucher_support' => 'Loan Disbursement Advice / Bank Credit UTR',
            'bank_mandate' => 'Signed NACH Mandate / ECS Authorization',
            'general' => 'NOC / Foreclosure / Debt Correspondence',
        ]"
    />
</div>

<!-- Modal: Settle EMI and Post Journal Entry -->
<div id="emiPayModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900" id="modalTitle">Record EMI Settlement & GL Posting</h3>
            <button onclick="document.getElementById('emiPayModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="emiPayForm" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Settlement Amount</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-xs text-gray-400 font-bold">₹</span>
                    <input type="text" id="modalEmiAmount" readonly class="w-full text-xs border border-gray-200 rounded-xl p-2.5 pl-8 font-mono font-bold bg-gray-50 text-gray-900">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Payment Date *</label>
                    <input type="date" name="paid_date" required value="{{ date('Y-m-d') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Paying Bank Account</label>
                    <select name="bank_account_id" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                        <option value="">Default Operating Bank (1010)</option>
                        @foreach($bankAccounts as $ba)
                            <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Payment Reference / UTR Number *</label>
                <input type="text" name="payment_ref" id="emi_payment_ref" required value="NACH-AUTODEBIT-{{ date('Ymd') }}" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 font-mono">
            </div>

            <div class="p-3 bg-blue-50 rounded-xl text-xs text-blue-900 border border-blue-100">
                Posting this payment will deduct the principal from outstanding debt liability and charge the interest component to finance expenses in the General Ledger.
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('emiPayModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" id="btnConfirmEmiPay" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow transition cursor-pointer">
                    Confirm & Post Journal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reverse EMI Payment -->
<div id="emiReverseModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-red-600">Reverse EMI Settlement</h3>
            <button onclick="document.getElementById('emiReverseModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="emiReverseForm" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Mandatory Reversal Reason *</label>
                <textarea name="reversal_reason" required minlength="5" rows="3" placeholder="Provide audit explanation (e.g. NACH mandate bounce, double bank debit)..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5"></textarea>
            </div>

            <div class="p-3 bg-red-50 rounded-xl text-xs text-red-900 border border-red-100">
                Reversing will generate a counter-balancing journal entry to restore bank balance and loan debt liability.
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('emiReverseModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
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
function openPayModal(scheduleId, instNo, amount) {
    document.getElementById('modalTitle').innerText = 'Settle Installment #' + instNo + ' & Post Journal';
    document.getElementById('modalEmiAmount').value = amount;
    document.getElementById('emiPayForm').action = '/loans/emi/' + scheduleId + '/pay';
    document.getElementById('emiPayModal').classList.remove('hidden');
}

function openReverseModal(scheduleId, instNo) {
    document.getElementById('emiReverseForm').action = '/loans/emi/' + scheduleId + '/reverse';
    document.getElementById('emiReverseModal').classList.remove('hidden');
}
</script>
@endsection
