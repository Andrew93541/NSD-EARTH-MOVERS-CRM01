@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Chart of Accounts & General Ledger</h2>
            <p class="text-xs text-gray-500 mt-1">Double-entry accounting structure, balance verification, and trial balances.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('accounting.trial-balance') }}" class="px-3.5 py-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 rounded-xl text-xs font-semibold shadow-sm transition">
                Trial Balance Report
            </a>
            <a href="{{ route('accounting.journals.create') }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                + Post Journal Voucher
            </a>
        </div>
    </div>

    <!-- 4 Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Accounts</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_accounts'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Asset Accounts</span>
            <div class="text-2xl font-black text-blue-600 mt-1">{{ $stats['assets_count'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Liability Accounts</span>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $stats['liabilities_count'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Revenue Heads</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $stats['revenue_count'] }}</div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="soft-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Account Directory</h3>
            <a href="{{ route('accounting.journals') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">View Journal Book →</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">GL Code</th>
                        <th class="py-3 px-3">Account Name</th>
                        <th class="py-3 px-3">Primary Type</th>
                        <th class="py-3 px-3">Sub-Classification</th>
                        <th class="py-3 px-3">System Protected</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($accounts as $acc)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-blue-600">{{ $acc->code }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $acc->name }}</td>
                            <td class="py-3 px-3 uppercase text-[10px] font-bold">
                                <span class="px-2 py-0.5 rounded {{ $acc->account_type === 'asset' ? 'bg-blue-50 text-blue-700' : ($acc->account_type === 'liability' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                    {{ $acc->account_type }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-gray-500 font-mono text-[11px]">{{ str_replace('_', ' ', $acc->sub_type ?? '—') }}</td>
                            <td class="py-3 px-3 text-gray-400">{{ $acc->is_system ? 'Yes (Core)' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-400">No chart of accounts established.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
