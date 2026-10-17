@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Client Running Account (RA) Billing</h2>
            <p class="text-xs text-gray-500 mt-1">Progress billing, retention holdbacks, GST invoices, and client collections.</p>
        </div>
        <a href="{{ route('billing.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            + Raise RA Progress Bill
        </a>
    </div>

    <!-- 3 Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Outstanding Receivables</span>
            <div class="text-2xl font-black text-blue-600 mt-1">₹{{ number_format($stats['receivables'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Total client dues</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Realized Collections</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">₹{{ number_format($stats['collected'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Collected to bank account</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Invoices Issued</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_invoices'] }}</div>
            <span class="text-[11px] text-gray-400">Approved certificates</span>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Invoice No</th>
                        <th class="py-3 px-3">Client</th>
                        <th class="py-3 px-3">Project</th>
                        <th class="py-3 px-3">Issue Date</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3">Bill Total</th>
                        <th class="py-3 px-3">Balance Due</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">
                                <a href="{{ route('billing.show', $inv) }}" class="hover:text-blue-600">{{ $inv->invoice_number }}</a>
                            </td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $inv->client->name ?? '—' }}</td>
                            <td class="py-3 px-3 text-gray-600">{{ $inv->project->name ?? '—' }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $inv->issue_date->format('d M, Y') }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $inv->due_date->format('d M, Y') }}</td>
                            <td class="py-3 px-3 font-mono font-bold text-gray-900">₹{{ number_format($inv->total_amount, 2) }}</td>
                            <td class="py-3 px-3 font-mono font-extrabold text-blue-600">₹{{ number_format($inv->balanceDue(), 2) }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $inv->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">
                                    {{ strtoupper($inv->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('billing.show', $inv) }}" class="text-blue-600 font-semibold hover:text-blue-800">View Bill →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-gray-400">No client invoices created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
