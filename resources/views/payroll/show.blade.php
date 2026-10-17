@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ $period->title }}</h2>
                @if ($period->status === 'disbursed')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Disbursed
                    </span>
                @elseif ($period->status === 'approved')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Approved
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Awaiting Approval
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Period: <span class="font-mono text-gray-700 font-semibold">{{ $period->start_date->format('d M, Y') }} — {{ $period->end_date->format('d M, Y') }}</span>
                @if ($period->approved_by)
                    • Approved by <span class="text-gray-700 font-semibold">{{ $period->approver->name }}</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('payroll.index') }}" class="px-3.5 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                ← All Batches
            </a>

            @if ($period->status === 'processing' || $period->status === 'draft')
                <form method="POST" action="{{ route('payroll.approve', $period) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        Approve Payroll Batch
                    </button>
                </form>
            @elseif ($period->status === 'approved')
                <form method="POST" action="{{ route('payroll.disburse', $period) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        Confirm Wage Disbursement
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Net Disbursement</span>
            <div class="text-xl font-black text-gray-900 font-mono mt-1">
                ₹{{ number_format($period->total_disbursement, 2) }}
            </div>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Workers On Payroll</span>
            <div class="text-xl font-black text-gray-900 mt-1">
                {{ $period->records->count() }}
            </div>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Total Days Worked</span>
            <div class="text-xl font-black text-blue-600 mt-1 font-mono">
                {{ $period->records->sum('days_worked') }}
            </div>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Total Overtime Hours</span>
            <div class="text-xl font-black text-amber-600 mt-1 font-mono">
                {{ number_format($period->records->sum('overtime_hours'), 1) }} hrs
            </div>
        </div>
    </div>

    <!-- Itemized Worker Register -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Itemized Employee & Labour Register</h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Emp Code</th>
                        <th class="py-3 px-3">Worker Name</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3 text-center">Days Worked</th>
                        <th class="py-3 px-3 text-center">OT Hrs</th>
                        <th class="py-3 px-3 text-right">Basic Pay</th>
                        <th class="py-3 px-3 text-right">Allowances</th>
                        <th class="py-3 px-3 text-right">OT Pay</th>
                        <th class="py-3 px-3 text-right">Deductions</th>
                        <th class="py-3 px-3 text-right font-extrabold text-gray-900">Net Payable</th>
                        <th class="py-3 px-3 text-right">Payslip</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs">
                    @forelse ($period->records as $rec)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-500">{{ $rec->employee->code }}</td>
                            <td class="py-3 px-3">
                                <span class="font-bold text-gray-900 block">{{ $rec->employee->full_name }}</span>
                                <span class="text-[11px] text-gray-400">{{ $rec->employee->designation }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700">
                                    {{ str_replace('_', ' ', $rec->employee->employment_type) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-bold text-gray-700">
                                {{ $rec->days_worked }}
                            </td>
                            <td class="py-3 px-3 text-center font-mono text-amber-700 font-semibold">
                                {{ $rec->overtime_hours > 0 ? $rec->overtime_hours : '—' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-gray-700">
                                ₹{{ number_format($rec->basic_pay, 2) }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-600">
                                {{ $rec->allowances > 0 ? '₹' . number_format($rec->allowances, 2) : '—' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-amber-600">
                                {{ $rec->overtime_pay > 0 ? '₹' . number_format($rec->overtime_pay, 2) : '—' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-red-600">
                                {{ $rec->deductions > 0 ? '-₹' . number_format($rec->deductions, 2) : '—' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-black text-gray-900">
                                ₹{{ number_format($rec->net_pay, 2) }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('payroll.payslip', [$period, $rec]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
                                    Payslip →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-6 text-center text-gray-400">No worker records found in this batch.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
