@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Labor Muster Roll</h2>
            <p class="text-xs text-gray-500 mt-1">Multi-site daily attendance sheet, overtime verification, and wage sync.</p>
        </div>
        <a href="{{ route('employees.create') }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            + Register New Worker
        </a>
    </div>

    <!-- Site & Date Selector -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('attendance.muster') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-gray-700">Site:</label>
                <select name="site_id" onchange="this.form.submit()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" {{ $selectedSite?->id == $site->id ? 'selected' : '' }}>{{ $site->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-gray-700">Date:</label>
                <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()"
                       class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </form>
    </div>

    <!-- Muster Roll Form -->
    <form method="POST" action="{{ route('attendance.muster.store') }}" class="soft-card p-5">
        @csrf
        <input type="hidden" name="site_id" value="{{ $selectedSite?->id }}"/>
        <input type="hidden" name="date" value="{{ $selectedDate }}"/>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Emp Code</th>
                        <th class="py-3 px-3">Worker Name</th>
                        <th class="py-3 px-3">Designation / Skill</th>
                        <th class="py-3 px-3">Wage Type</th>
                        <th class="py-3 px-3">Attendance Status</th>
                        <th class="py-3 px-3">Overtime (Hrs)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs">
                    @forelse ($employees as $emp)
                        @php
                            $current = $existingAttendance[$emp->id] ?? null;
                            $status = $current?->status ?? 'present';
                            $ot = $current?->overtime_hours ?? 0;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3 font-mono font-bold text-gray-500">{{ $emp->code }}</td>
                            <td class="py-3 px-3 font-bold text-gray-900">{{ $emp->full_name }}</td>
                            <td class="py-3 px-3 text-gray-500">{{ $emp->designation ?? 'Site Labour' }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700">
                                    {{ str_replace('_', ' ', $emp->employment_type) }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-3">
                                    <label class="inline-flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="attendance[{{ $emp->id }}][status]" value="present" {{ $status === 'present' ? 'checked' : '' }} class="text-blue-600 focus:ring-blue-500"/>
                                        <span class="text-emerald-700 font-semibold text-xs">Present</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="attendance[{{ $emp->id }}][status]" value="half_day" {{ $status === 'half_day' ? 'checked' : '' }} class="text-amber-600 focus:ring-amber-500"/>
                                        <span class="text-amber-700 font-semibold text-xs">Half Day</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="attendance[{{ $emp->id }}][status]" value="absent" {{ $status === 'absent' ? 'checked' : '' }} class="text-red-600 focus:ring-red-500"/>
                                        <span class="text-red-700 font-semibold text-xs">Absent</span>
                                    </label>
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <input type="number" step="0.5" min="0" max="12" name="attendance[{{ $emp->id }}][overtime_hours]" value="{{ $ot }}"
                                       class="w-20 px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-400">No active workers assigned to this site.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($employees->isNotEmpty())
            <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-end">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    Save & Synchronize Muster Roll
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
