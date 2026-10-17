@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Equipment Finance & Term Loans</h2>
            <p class="text-xs text-gray-500 mt-1">Hydraulic excavators, backhoe loaders, tipper trucks, and banking EMI schedules.</p>
        </div>
        <a href="{{ route('loans.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            + Setup Equipment Finance
        </a>
    </div>

    <!-- 2 Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Outstanding Loan Principal</span>
            <div class="text-2xl font-black text-gray-900 mt-1">₹{{ number_format($totalOutstanding, 2) }}</div>
            <span class="text-[11px] text-gray-400">Total liability across active equipment loans</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Monthly Fleet EMI Commitment</span>
            <div class="text-2xl font-black text-blue-600 mt-1">₹{{ number_format($monthlyCommitment, 2) }}/mo</div>
            <span class="text-[11px] text-gray-400">Recurring debt service deduction</span>
        </div>
    </div>

    <!-- Loans Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Loan Account</th>
                        <th class="py-3 px-3">Financier / Bank</th>
                        <th class="py-3 px-3">Equipment Asset Description</th>
                        <th class="py-3 px-3">Principal Amount</th>
                        <th class="py-3 px-3">Interest Rate</th>
                        <th class="py-3 px-3">Monthly EMI</th>
                        <th class="py-3 px-3">Balance Principal</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($loans as $loan)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">
                                <a href="{{ route('loans.show', $loan) }}" class="hover:text-blue-600">{{ $loan->loan_account_number }}</a>
                            </td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $loan->lender_name }}</td>
                            <td class="py-3 px-3 text-gray-700 font-semibold">{{ $loan->asset_description }}</td>
                            <td class="py-3 px-3 font-mono">₹{{ number_format($loan->principal_amount, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-bold">{{ $loan->annual_interest_rate }}%</td>
                            <td class="py-3 px-3 font-mono font-extrabold text-blue-600">₹{{ number_format($loan->monthly_emi, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">₹{{ number_format($loan->outstanding_principal, 2) }}</td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('loans.show', $loan) }}" class="text-blue-600 font-semibold hover:text-blue-800">EMI Schedule →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-gray-400">No equipment loans or debt facilities configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
