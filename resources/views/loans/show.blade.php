@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $loan->asset_description }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700">
                    {{ $loan->loan_account_number }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Lender: {{ $loan->lender_name }} • Interest: {{ $loan->annual_interest_rate }}% p.a. • Tenure: {{ $loan->tenure_months }} Months</p>
        </div>
        <a href="{{ route('loans.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Loans</a>
    </div>

    <!-- 3 Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Monthly EMI</span>
            <div class="text-2xl font-black text-blue-600 mt-1">₹{{ number_format($loan->monthly_emi, 2) }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Original Sanctioned Principal</span>
            <div class="text-2xl font-black text-gray-900 mt-1">₹{{ number_format($loan->principal_amount, 2) }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Remaining Principal Balance</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">₹{{ number_format($loan->outstanding_principal, 2) }}</div>
        </div>
    </div>

    <!-- Amortization Schedule Table -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Amortized EMI Schedule & Settlement Tracking</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Inst #</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3">Principal (INR)</th>
                        <th class="py-3 px-3">Interest (INR)</th>
                        <th class="py-3 px-3">Total Installment</th>
                        <th class="py-3 px-3">Remaining Principal</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($loan->emiSchedules as $sch)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-2.5 px-3 font-mono font-bold text-gray-500">#{{ $sch->installment_number }}</td>
                            <td class="py-2.5 px-3 font-semibold text-gray-900">{{ $sch->due_date->format('d M, Y') }}</td>
                            <td class="py-2.5 px-3 font-mono">₹{{ number_format($sch->principal_component, 2) }}</td>
                            <td class="py-2.5 px-3 font-mono text-gray-500">₹{{ number_format($sch->interest_component, 2) }}</td>
                            <td class="py-2.5 px-3 font-mono font-bold text-gray-900">₹{{ number_format($sch->total_emi, 2) }}</td>
                            <td class="py-2.5 px-3 font-mono text-gray-500">₹{{ number_format($sch->remaining_balance, 2) }}</td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $sch->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ strtoupper($sch->status) }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                @if ($sch->status === 'pending')
                                    <form method="POST" action="{{ route('loans.emi.pay', $sch) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="hidden" name="paid_date" value="{{ date('Y-m-d') }}"/>
                                        <input type="hidden" name="payment_ref" value="AUTODEBIT-NACH"/>
                                        <button type="submit" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[10px] font-semibold transition cursor-pointer">
                                            Mark Paid
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-gray-400">Paid {{ $sch->paid_date ? $sch->paid_date->format('d M') : '' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
