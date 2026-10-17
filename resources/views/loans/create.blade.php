@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Setup Equipment Loan</h2>
            <p class="text-xs text-gray-500 mt-0.5">Financing for hydraulic excavators, tipper trucks, and batching plants.</p>
        </div>
        <a href="{{ route('loans.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back</a>
    </div>

    <form method="POST" action="{{ route('loans.store') }}" class="soft-card p-6 space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Loan Account Number *</label>
                <input type="text" name="loan_account_number" placeholder="TC-EQ-2026-9912" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Financier / Bank Name *</label>
                <input type="text" name="lender_name" placeholder="Tata Capital / HDFC Bank / SREI" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Loan Facility Classification *</label>
                <select name="loan_type" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="equipment_finance">Heavy Equipment Finance (Excavator/Crane)</option>
                    <option value="vehicle_loan">Commercial Vehicle Loan (Tipper/Dumper)</option>
                    <option value="term_loan">Bank Term Loan</option>
                    <option value="working_capital">Working Capital Line</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Asset Model / Machine Description *</label>
                <input type="text" name="asset_description" placeholder="e.g. Komatsu PC210-10M0 Excavator (Serial #KMT-8819)" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Principal Amount (INR) *</label>
                <input type="number" step="0.01" min="1000" name="principal_amount" value="5000000.00" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Annual Interest Rate (%) *</label>
                <input type="number" step="0.01" min="1" max="40" name="annual_interest_rate" value="10.25" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tenure (Months) *</label>
                <input type="number" min="1" max="120" name="tenure_months" value="48" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">First Disbursal Date *</label>
            <input type="date" name="start_date" value="{{ date('Y-m-d') }}" required
                   class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('loans.index') }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Compute Schedule & Setup Loan
            </button>
        </div>
    </form>
</div>
@endsection
