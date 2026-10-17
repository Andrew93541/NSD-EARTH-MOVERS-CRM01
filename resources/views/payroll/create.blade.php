@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Generate Payroll Batch</h2>
            <p class="text-xs text-gray-500 mt-1">Aggregate verified muster attendance, overtime hours, and salary structures.</p>
        </div>
        <a href="{{ route('payroll.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Payroll</a>
    </div>

    <form method="POST" action="{{ route('payroll.store') }}" class="soft-card p-6 space-y-5">
        @csrf

        <div>
            <label for="title" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Batch Title *</label>
            <input type="text" id="title" name="title" value="{{ old('title', $defaultTitle) }}" required
                   placeholder="e.g. October 2026 Monthly Payroll"
                   class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="start_date" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Period Start Date *</label>
                <input type="date" id="start_date" name="start_date" value="{{ old('start_date', $defaultStart) }}" required
                       class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label for="end_date" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Period End Date *</label>
                <input type="date" id="end_date" name="end_date" value="{{ old('end_date', $defaultEnd) }}" required
                       class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-2xl text-xs text-blue-800 space-y-1.5">
            <span class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Deterministic Calculation Engine
            </span>
            <p class="text-blue-700/90 leading-relaxed text-[11px]">
                Upon submission, the calculation engine inspects each worker's daily muster records between the selected dates.
                It computes exact worked days (including half-days), approved overtime hours at 1.5× rate, allowances, and applies statutory deductions.
            </p>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('payroll.index') }}" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Calculate & Generate Batch
            </button>
        </div>
    </form>

</div>
@endsection
