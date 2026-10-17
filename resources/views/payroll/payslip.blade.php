@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Worker Salary Slip</h2>
            <p class="text-xs text-gray-500 mt-1">Official wage certificate for {{ $period->title }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('payroll.show', $period) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Register</a>
            <button onclick="window.print()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print Payslip</span>
            </button>
        </div>
    </div>

    <!-- Printable Payslip Document -->
    <div class="soft-card p-8 space-y-6 bg-white border border-gray-200">
        <!-- Header -->
        <div class="flex items-center justify-between pb-6 border-b border-gray-200">
            <div>
                <h3 class="text-lg font-black text-gray-900">{{ session('currentCompany')?->name ?? 'NSD Earth Movers & Construction Ltd' }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">Heavy Civil Construction & Infrastructure Contractors</p>
                <p class="text-[11px] font-mono text-gray-400 mt-1">GSTIN: 29AAACN1234F1Z8 • Karnataka, India</p>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full font-bold text-xs uppercase font-mono tracking-wider">
                    PAYSLIP #{{ str_pad($record->id, 5, '0', STR_PAD_LEFT) }}
                </span>
                <p class="text-xs text-gray-500 mt-2">Pay Period: <strong class="text-gray-900">{{ $period->start_date->format('d M') }} — {{ $period->end_date->format('d M, Y') }}</strong></p>
            </div>
        </div>

        <!-- Worker Information Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-6 border-b border-gray-100 text-xs">
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Employee Code</span>
                <strong class="text-gray-900 mt-1 block font-mono">{{ $record->employee->code }}</strong>
            </div>
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Employee Name</span>
                <strong class="text-gray-900 mt-1 block">{{ $record->employee->full_name }}</strong>
            </div>
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Designation</span>
                <strong class="text-gray-900 mt-1 block">{{ $record->employee->designation }}</strong>
            </div>
            <div>
                <span class="text-gray-400 block font-bold uppercase text-[10px]">Wage Structure</span>
                <strong class="text-blue-700 mt-1 block uppercase">{{ str_replace('_', ' ', $record->employee->employment_type) }}</strong>
            </div>
        </div>

        <!-- Attendance Summary -->
        <div class="grid grid-cols-3 gap-4 p-4 bg-gray-50 rounded-2xl text-xs">
            <div>
                <span class="text-gray-500 block text-[11px]">Days Worked:</span>
                <strong class="text-base text-gray-900 font-mono">{{ $record->days_worked }} days</strong>
            </div>
            <div>
                <span class="text-gray-500 block text-[11px]">Days Absent:</span>
                <strong class="text-base text-gray-900 font-mono">{{ $record->days_absent }} days</strong>
            </div>
            <div>
                <span class="text-gray-500 block text-[11px]">Overtime Hours:</span>
                <strong class="text-base text-amber-700 font-mono">{{ $record->overtime_hours }} hrs</strong>
            </div>
        </div>

        <!-- Earnings & Deductions Table -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <!-- Earnings -->
            <div class="space-y-3">
                <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[11px] pb-2 border-b border-gray-200">Earnings</h4>
                <div class="flex justify-between py-1 text-gray-600">
                    <span>Basic / Attended Wages:</span>
                    <span class="font-mono font-bold text-gray-900">₹{{ number_format($record->basic_pay, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 text-gray-600">
                    <span>Site Allowances:</span>
                    <span class="font-mono font-bold text-gray-900">₹{{ number_format($record->allowances, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 text-gray-600">
                    <span>Overtime Wages (1.5×):</span>
                    <span class="font-mono font-bold text-gray-900">₹{{ number_format($record->overtime_pay, 2) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-100 font-bold text-gray-900">
                    <span>Total Gross Earnings:</span>
                    <span class="font-mono">₹{{ number_format($record->basic_pay + $record->allowances + $record->overtime_pay, 2) }}</span>
                </div>
            </div>

            <!-- Deductions -->
            <div class="space-y-3">
                <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[11px] pb-2 border-b border-gray-200">Deductions</h4>
                <div class="flex justify-between py-1 text-gray-600">
                    <span>Statutory PF / Contributions:</span>
                    <span class="font-mono font-bold text-red-600">₹{{ number_format($record->deductions, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 text-gray-600">
                    <span>TDS / Advance Recoveries:</span>
                    <span class="font-mono text-gray-400">₹0.00</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-100 font-bold text-gray-900">
                    <span>Total Deductions:</span>
                    <span class="font-mono text-red-600">₹{{ number_format($record->deductions, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Net Payable Amount Highlight -->
        <div class="p-5 bg-gradient-to-r from-blue-700 via-blue-600 to-sky-600 rounded-2xl text-white flex items-center justify-between">
            <div>
                <span class="text-xs uppercase tracking-wider font-semibold opacity-85 block">Net Salary Payable</span>
                <span class="text-sm font-medium opacity-90">Account Transfer to {{ $record->employee->bank_name ?? 'Bank Account' }}</span>
            </div>
            <div class="text-3xl font-black font-mono">
                ₹{{ number_format($record->net_pay, 2) }}
            </div>
        </div>

        <!-- Sign-off Footer -->
        <div class="pt-8 grid grid-cols-2 gap-8 text-center text-xs text-gray-400">
            <div class="border-t border-gray-200 pt-2">
                <span>Employee Signature / Thumb</span>
            </div>
            <div class="border-t border-gray-200 pt-2">
                <span>Authorized Signatory (HR / Finance)</span>
            </div>
        </div>
    </div>

</div>
@endsection
