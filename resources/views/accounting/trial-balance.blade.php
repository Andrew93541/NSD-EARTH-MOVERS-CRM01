@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Trial Balance Verification</h2>
            <p class="text-xs text-gray-500 mt-1">Mathematical proof of balanced debits and credits across the general ledger.</p>
        </div>
        <a href="{{ route('accounting.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Ledger</a>
    </div>

    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">GL Code</th>
                        <th class="py-3 px-3">Account Name</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3 text-right">Debit Balance (INR)</th>
                        <th class="py-3 px-3 text-right">Credit Balance (INR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-600">{{ $row['account']->code }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $row['account']->name }}</td>
                            <td class="py-3 px-3 uppercase text-[10px] font-bold text-gray-500">{{ $row['account']->account_type }}</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-gray-900">
                                {{ $row['debit'] > 0 ? '₹' . number_format($row['debit'], 2) : '—' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-gray-900">
                                {{ $row['credit'] > 0 ? '₹' . number_format($row['credit'], 2) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-400">No ledger transactions posted yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 text-sm font-black text-gray-900 bg-gray-50/50">
                        <td colspan="3" class="py-3.5 px-3 uppercase tracking-wider">Balanced Grand Totals</td>
                        <td class="py-3.5 px-3 text-right font-mono text-blue-700">₹{{ number_format($totalDebits, 2) }}</td>
                        <td class="py-3.5 px-3 text-right font-mono text-blue-700">₹{{ number_format($totalCredits, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
