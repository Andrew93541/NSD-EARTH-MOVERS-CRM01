@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $employee->full_name }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700">
                    {{ $employee->code }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">{{ $employee->designation }} • {{ $employee->department ?? 'Operations' }}</p>
        </div>
        <a href="{{ route('employees.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Directory</a>
    </div>

    <!-- Details Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="soft-card p-5 space-y-3">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-2">Compensation & Work Parameters</h3>
            <div class="flex justify-between text-xs"><span class="text-gray-400">Employment:</span> <strong class="text-gray-900 uppercase text-[10px]">{{ str_replace('_', ' ', $employee->employment_type) }}</strong></div>
            <div class="flex justify-between text-xs"><span class="text-gray-400">Daily Wage:</span> <strong class="text-gray-900">₹{{ number_format($employee->daily_wage_rate, 2) }}</strong></div>
            <div class="flex justify-between text-xs"><span class="text-gray-400">Monthly Salary:</span> <strong class="text-gray-900">₹{{ number_format($employee->monthly_salary, 2) }}</strong></div>
            <div class="flex justify-between text-xs"><span class="text-gray-400">Assigned Site:</span> <strong class="text-gray-900">{{ $employee->assignedSite->name ?? 'Central Pool' }}</strong></div>
        </div>

        <div class="md:col-span-2 soft-card p-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Recent 30-Day Attendance Track</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase">
                            <th class="py-2">Date</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Worked Hours</th>
                            <th class="py-2">Overtime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($employee->attendanceRecords as $rec)
                            <tr>
                                <td class="py-2">{{ $rec->attendance_date->format('d M, Y') }}</td>
                                <td class="py-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $rec->status === 'present' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $rec->status }}
                                    </span>
                                </td>
                                <td class="py-2">{{ $rec->worked_hours }} hrs</td>
                                <td class="py-2 font-bold text-blue-600">{{ $rec->overtime_hours }} hrs</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-400">No recent punches logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
