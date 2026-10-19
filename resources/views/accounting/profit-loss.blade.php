@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('accounting.index') }}" class="text-xs text-slate-400 hover:text-slate-600 flex items-center gap-1 font-semibold">
                    ← General Ledger
                </a>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">Statement of Profit & Loss</h1>
            <p class="text-xs text-slate-500 mt-1">Derived strictly from posted double-entry journal vouchers for period: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}.</p>
        </div>

        <form method="GET" action="{{ route('accounting.profit-loss') }}" class="flex items-center gap-2">
            <input type="date" name="start_date" value="{{ $startDate }}" class="px-3 py-1.5 border border-slate-200 rounded-xl text-xs">
            <span class="text-xs text-slate-400">to</span>
            <input type="date" name="end_date" value="{{ $endDate }}" class="px-3 py-1.5 border border-slate-200 rounded-xl text-xs">
            <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold cursor-pointer">
                Filter
            </button>
        </form>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Operating Revenue</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1">₹{{ number_format($totalRevenue, 2) }}</div>
            <span class="text-[11px] text-slate-500">Credit balance of revenue accounts</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Operating Expenses</span>
            <div class="text-2xl font-extrabold text-rose-600 mt-1">₹{{ number_format($totalExpenses, 2) }}</div>
            <span class="text-[11px] text-slate-500">Debit balance of expense accounts</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Net Operating Profit / (Loss)</span>
            <div class="text-2xl font-extrabold {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' }} mt-1">
                ₹{{ number_format($netProfit, 2) }}
            </div>
            <span class="text-[11px] text-slate-500">Transfers to Retained Earnings</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Revenue Breakdown -->
        <div class="soft-card p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-900">Operating Revenues</h3>
                <span class="font-mono text-xs font-bold text-emerald-600">₹{{ number_format($totalRevenue, 2) }}</span>
            </div>
            <div class="divide-y divide-slate-100 text-xs">
                @forelse($revenueRows as $row)
                <div class="py-2.5 flex items-center justify-between">
                    <div>
                        <span class="font-mono text-[10px] text-slate-400 mr-2">{{ $row['account']->code }}</span>
                        <span class="font-semibold text-slate-800">{{ $row['account']->name }}</span>
                    </div>
                    <span class="font-mono font-medium text-slate-900">₹{{ number_format($row['amount'], 2) }}</span>
                </div>
                @empty
                <p class="py-4 text-center text-slate-400 italic">No revenue recorded in this period.</p>
                @endforelse
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="soft-card p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-900">Operating Expenses</h3>
                <span class="font-mono text-xs font-bold text-rose-600">₹{{ number_format($totalExpenses, 2) }}</span>
            </div>
            <div class="divide-y divide-slate-100 text-xs">
                @forelse($expenseRows as $row)
                <div class="py-2.5 flex items-center justify-between">
                    <div>
                        <span class="font-mono text-[10px] text-slate-400 mr-2">{{ $row['account']->code }}</span>
                        <span class="font-semibold text-slate-800">{{ $row['account']->name }}</span>
                    </div>
                    <span class="font-mono font-medium text-slate-900">₹{{ number_format($row['amount'], 2) }}</span>
                </div>
                @empty
                <p class="py-4 text-center text-slate-400 italic">No expenses recorded in this period.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
