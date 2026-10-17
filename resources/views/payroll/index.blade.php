@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Workforce Payroll & Wages</h2>
            <p class="text-xs text-gray-500 mt-1">Attendance-linked wage calculations, overtime reconciliation, and payslip generation.</p>
        </div>
        <a href="{{ route('payroll.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5 self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Generate New Payroll Batch</span>
        </a>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-5">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Total Disbursed</span>
            <div class="text-2xl font-black text-gray-900 tracking-tight mt-2 font-mono">
                ₹{{ number_format($stats['total_disbursed'], 2) }}
            </div>
            <span class="text-[11px] text-gray-400 mt-1 block">Approved & disbursed wages</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Pending Approval</span>
            <div class="text-2xl font-black text-amber-600 tracking-tight mt-2">
                {{ $stats['pending_approval'] }}
            </div>
            <span class="text-[11px] text-gray-400 mt-1 block">Batches awaiting sign-off</span>
        </div>

        <div class="soft-card p-5">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Active Workforce</span>
            <div class="text-2xl font-black text-gray-900 tracking-tight mt-2">
                {{ $stats['active_employees'] }}
            </div>
            <span class="text-[11px] text-gray-400 mt-1 block">Staff & site labor eligible</span>
        </div>
    </div>

    <!-- Payroll Batches Table -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Historical Payroll Registers</h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Batch Title</th>
                        <th class="py-3 px-3">Date Range</th>
                        <th class="py-3 px-3">Workers Count</th>
                        <th class="py-3 px-3">Total Disbursement</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Approved By</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs">
                    @forelse ($periods as $prd)
                        <tr class="hover:bg-gray-50/60 transition group">
                            <td class="py-3.5 px-3 font-bold text-gray-900">
                                <a href="{{ route('payroll.show', $prd) }}" class="hover:text-blue-600 transition">
                                    {{ $prd->title }}
                                </a>
                            </td>
                            <td class="py-3.5 px-3 text-gray-500 font-mono">
                                {{ $prd->start_date->format('d M, Y') }} — {{ $prd->end_date->format('d M, Y') }}
                            </td>
                            <td class="py-3.5 px-3 text-gray-600 font-semibold">
                                {{ $prd->records->count() }} workers
                            </td>
                            <td class="py-3.5 px-3 font-mono font-extrabold text-gray-900">
                                ₹{{ number_format($prd->total_disbursement, 2) }}
                            </td>
                            <td class="py-3.5 px-3">
                                @if ($prd->status === 'disbursed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Disbursed
                                    </span>
                                @elseif ($prd->status === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Approved
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Processing
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-gray-500">
                                {{ $prd->approver->name ?? 'Pending' }}
                            </td>
                            <td class="py-3.5 px-3 text-right">
                                <a href="{{ route('payroll.show', $prd) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
                                    View Register →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400">
                                No payroll batches generated yet. Click "Generate New Payroll Batch" above to synchronize wages with attendance.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($periods->hasPages())
            <div class="mt-4 pt-4 border-t border-gray-100">
                {{ $periods->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
