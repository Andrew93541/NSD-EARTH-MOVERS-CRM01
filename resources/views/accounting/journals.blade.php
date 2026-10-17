@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">General Journal Vouchers</h2>
            <p class="text-xs text-gray-500 mt-1">Double-entry accounting journal vouchers with debits and credits.</p>
        </div>
        <a href="{{ route('accounting.journals.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            + New Journal Entry
        </a>
    </div>

    <div class="space-y-4">
        @forelse ($entries as $je)
            <div class="soft-card p-5 space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="font-mono font-bold text-gray-900 text-sm">{{ $je->entry_number }}</span>
                        <span class="text-gray-400 font-medium">{{ $je->entry_date->format('d M, Y') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-gray-900">Total: ₹{{ number_format($je->total_amount, 2) }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 uppercase">
                            {{ $je->status }}
                        </span>
                    </div>
                </div>

                <p class="text-xs text-gray-700 italic">"{{ $je->narration }}"</p>

                <!-- Lines Table -->
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-[10px] text-gray-400 font-bold uppercase">
                            <th class="py-1">Account</th>
                            <th class="py-1">Line Description</th>
                            <th class="py-1 text-right">Debit (INR)</th>
                            <th class="py-1 text-right">Credit (INR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($je->lines as $line)
                            <tr>
                                <td class="py-2 font-bold text-gray-900">{{ $line->account->code }} - {{ $line->account->name }}</td>
                                <td class="py-2 text-gray-500">{{ $line->description ?? '—' }}</td>
                                <td class="py-2 text-right font-mono font-semibold">{{ $line->debit > 0 ? '₹' . number_format($line->debit, 2) : '—' }}</td>
                                <td class="py-2 text-right font-mono font-semibold">{{ $line->credit > 0 ? '₹' . number_format($line->credit, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="p-8 text-center text-gray-400 soft-card">No journal vouchers posted yet.</div>
        @endforelse
    </div>
</div>
@endsection
