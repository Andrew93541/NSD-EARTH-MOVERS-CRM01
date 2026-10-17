@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Workforce Attendance</h2>
            <p class="text-xs text-gray-500 mt-1">Daily headcounts, shift exceptions, and biometric sync records.</p>
        </div>
        <a href="{{ route('attendance.muster') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            Open Labor Muster Roll
        </a>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Present Today</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $stats['present'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Half-Day</span>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $stats['half_day'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Absent</span>
            <div class="text-2xl font-black text-red-600 mt-1">{{ $stats['absent'] }}</div>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Workforce</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_workers'] }}</div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="soft-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Attendance Log ({{ $selectedDate }})</h3>
            <form method="GET" action="{{ route('attendance.index') }}">
                <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()"
                       class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold focus:outline-none"/>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Worker</th>
                        <th class="py-3 px-3">Site</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Worked Hours</th>
                        <th class="py-3 px-3">Overtime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs">
                    @forelse ($attendance as $record)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $record->employee->full_name ?? '—' }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $record->site->name ?? 'Central Pool' }}</td>
                            <td class="py-3 px-3">
                                @if ($record->status === 'present')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">Present</span>
                                @elseif ($record->status === 'half_day')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700">Half Day</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700">Absent</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono">{{ $record->worked_hours }} hrs</td>
                            <td class="py-3 px-3 font-mono font-bold text-blue-600">{{ $record->overtime_hours }} hrs</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">No attendance entries recorded for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
