@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Record Operational Expense</h2>
            <p class="text-xs text-gray-500 mt-0.5">Submit site fuel bills, machinery lubricants, or emergency purchases.</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back</a>
    </div>

    <form method="POST" action="{{ route('expenses.store') }}" class="soft-card p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Expense Title *</label>
            <input type="text" name="title" placeholder="e.g. Excavator diesel refueling (400 Litres)" required
                   class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Expense Head *</label>
                <select name="category_id" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Charge to Project</label>
                <select name="project_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="">General Overhead</option>
                    @foreach ($projects as $prj)
                        <option value="{{ $prj->id }}">{{ $prj->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Amount (INR) *</label>
                <input type="number" step="0.01" min="1" name="amount" required value="37000.00"
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Date *</label>
                <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Payment Mode *</label>
                <select name="payment_mode" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="bank_transfer">Bank Transfer / NEFT</option>
                    <option value="upi">Company UPI / QR</option>
                    <option value="company_card">Corporate Card</option>
                    <option value="cash">Site Petty Cash</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Receipt Reference & Vendor Details</label>
            <input type="text" name="receipt_reference" placeholder="Fuel pump bill #88921, Indian Oil dealer..."
                   class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('expenses.index') }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Record Expense
            </button>
        </div>
    </form>
</div>
@endsection
