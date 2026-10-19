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
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">Statement of Financial Position (Balance Sheet)</h1>
            <p class="text-xs text-slate-500 mt-1">As of {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }} — Strictly balanced double-entry accounting.</p>
        </div>

        <form method="GET" action="{{ route('accounting.balance-sheet') }}" class="flex items-center gap-2">
            <label class="text-xs text-slate-500 font-semibold">As of:</label>
            <input type="date" name="as_of_date" value="{{ $asOfDate }}" class="px-3 py-1.5 border border-slate-200 rounded-xl text-xs">
            <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold cursor-pointer">
                Refresh
            </button>
        </form>
    </div>

    <!-- Balance Check Alert -->
    @php
        $isBalanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;
    @endphp
    @if($isBalanced)
    <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span class="font-semibold">Balance Sheet Invariant Verified:</span> Assets = Liabilities + Equity (₹{{ number_format($totalAssets, 2) }})
        </div>
        <span class="font-mono text-[10px] font-bold uppercase tracking-wider bg-emerald-100 px-2 py-0.5 rounded">Exact Balance</span>
    </div>
    @else
    <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center justify-between">
        <span class="font-semibold">Variance Warning: Assets (₹{{ number_format($totalAssets, 2) }}) != Liabilities + Equity (₹{{ number_format($totalLiabilitiesAndEquity, 2) }})</span>
        <span class="font-mono text-[10px] font-bold bg-amber-100 px-2 py-0.5 rounded">Variance: ₹{{ number_format(abs($totalAssets - $totalLiabilitiesAndEquity), 2) }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Assets Column -->
        <div class="soft-card p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-900">Total Assets</h3>
                <span class="font-mono text-sm font-bold text-blue-600">₹{{ number_format($totalAssets, 2) }}</span>
            </div>
            <div class="divide-y divide-slate-100 text-xs">
                @forelse($assetRows as $row)
                <div class="py-2.5 flex items-center justify-between">
                    <div>
                        <span class="font-mono text-[10px] text-slate-400 mr-2">{{ $row['account']->code }}</span>
                        <span class="font-semibold text-slate-800">{{ $row['account']->name }}</span>
                    </div>
                    <span class="font-mono font-medium text-slate-900">₹{{ number_format($row['amount'], 2) }}</span>
                </div>
                @empty
                <p class="py-4 text-center text-slate-400 italic">No asset balances found.</p>
                @endforelse
            </div>
        </div>

        <!-- Liabilities & Equity Column -->
        <div class="space-y-6">
            <!-- Liabilities -->
            <div class="soft-card p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Liabilities</h3>
                    <span class="font-mono text-sm font-bold text-rose-600">₹{{ number_format($totalLiabilities, 2) }}</span>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    @forelse($liabilityRows as $row)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-mono text-[10px] text-slate-400 mr-2">{{ $row['account']->code }}</span>
                            <span class="font-semibold text-slate-800">{{ $row['account']->name }}</span>
                        </div>
                        <span class="font-mono font-medium text-slate-900">₹{{ number_format($row['amount'], 2) }}</span>
                    </div>
                    @empty
                    <p class="py-4 text-center text-slate-400 italic">No liability balances recorded.</p>
                    @endforelse
                </div>
            </div>

            <!-- Equity -->
            <div class="soft-card p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Owners' Equity</h3>
                    <span class="font-mono text-sm font-bold text-emerald-600">₹{{ number_format($totalEquityWithRetained, 2) }}</span>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    @foreach($equityRows as $row)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-mono text-[10px] text-slate-400 mr-2">{{ $row['account']->code }}</span>
                            <span class="font-semibold text-slate-800">{{ $row['account']->name }}</span>
                        </div>
                        <span class="font-mono font-medium text-slate-900">₹{{ number_format($row['amount'], 2) }}</span>
                    </div>
                    @endforeach
                    <div class="py-2.5 flex items-center justify-between bg-slate-50/50 px-2 rounded">
                        <span class="font-semibold text-slate-700">Cumulative Retained Earnings (from P&L)</span>
                        <span class="font-mono font-bold {{ $cumulativeRetainedEarnings >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            ₹{{ number_format($cumulativeRetainedEarnings, 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
