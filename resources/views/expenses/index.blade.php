@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Site Operations & Fleet Expenses</h2>
            <p class="text-xs text-gray-500 mt-1">Direct project expenses, excavator fuel, machinery maintenance, and petty cash.</p>
        </div>
        <a href="{{ route('expenses.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            + Record Site Expense
        </a>
    </div>

    <!-- Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Operational Expenses</span>
            <div class="text-2xl font-black text-gray-900 mt-1">₹{{ number_format($totalExpenses, 2) }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Configured Expense Heads</span>
            <div class="text-2xl font-black text-blue-600 mt-1">{{ $categories->count() }} Categories</div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Voucher</th>
                        <th class="py-3 px-3">Title</th>
                        <th class="py-3 px-3">Category</th>
                        <th class="py-3 px-3">Project / Site</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Amount</th>
                        <th class="py-3 px-3">Mode</th>
                        <th class="py-3 px-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($expenses as $exp)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-600">{{ $exp->expense_number }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $exp->title }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $exp->category->name ?? 'Direct Cost' }}</td>
                            <td class="py-3 px-3 text-gray-600">{{ $exp->project->name ?? 'General Overhead' }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $exp->expense_date->format('d M, Y') }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900 font-mono">₹{{ number_format($exp->amount, 2) }}</td>
                            <td class="py-3 px-3 uppercase text-[10px] font-bold text-gray-500">{{ $exp->payment_mode }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700">
                                    {{ strtoupper($exp->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-gray-400">No operational expenses recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
