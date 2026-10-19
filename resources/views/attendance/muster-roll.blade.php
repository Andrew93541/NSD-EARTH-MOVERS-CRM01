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

    <!-- Muster Roll Form (No-refresh AJAX submission) -->
    <div x-data="musterRollManager()">
        <!-- Inline Alert Banner -->
        <div x-show="alertMessage" x-transition class="mb-4 p-3.5 rounded-xl text-xs font-medium flex items-center justify-between"
             :class="alertType === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800'">
            <span x-text="alertMessage"></span>
            <button @click="alertMessage = ''" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form @submit.prevent="submitMuster($event)" method="POST" action="{{ route('attendance.muster.store') }}" class="soft-card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
            @csrf
            <input type="hidden" name="site_id" value="{{ $selectedSite?->id }}"/>
            <input type="hidden" name="date" value="{{ $selectedDate }}"/>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-3">Emp Code</th>
                            <th class="py-3 px-3">Worker Name</th>
                            <th class="py-3 px-3">Designation / Skill</th>
                            <th class="py-3 px-3">Wage Type</th>
                            <th class="py-3 px-3">Attendance Status</th>
                            <th class="py-3 px-3">Overtime (Hrs)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                        @forelse ($employees as $emp)
                            @php
                                $current = $existingAttendance[$emp->id] ?? null;
                                $status = $current?->status ?? 'present';
                                $ot = $current?->overtime_hours ?? 0;
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-3 font-mono font-bold text-slate-500 dark:text-slate-400">{{ $emp->code }}</td>
                                <td class="py-3 px-3 font-bold text-slate-900 dark:text-white">{{ $emp->full_name }}</td>
                                <td class="py-3 px-3 text-slate-500 dark:text-slate-400">{{ $emp->designation ?? 'Site Labour' }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                        {{ str_replace('_', ' ', $emp->employment_type) }}
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-3">
                                        <label class="inline-flex items-center gap-1 cursor-pointer">
                                            <input type="radio" name="attendance[{{ $emp->id }}][status]" value="present" {{ $status === 'present' ? 'checked' : '' }} class="text-blue-600 focus:ring-blue-500"/>
                                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold text-xs">Present</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1 cursor-pointer">
                                            <input type="radio" name="attendance[{{ $emp->id }}][status]" value="half_day" {{ $status === 'half_day' ? 'checked' : '' }} class="text-amber-600 focus:ring-amber-500"/>
                                            <span class="text-amber-700 dark:text-amber-400 font-semibold text-xs">Half Day</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1 cursor-pointer">
                                            <input type="radio" name="attendance[{{ $emp->id }}][status]" value="absent" {{ $status === 'absent' ? 'checked' : '' }} class="text-rose-600 focus:ring-rose-500"/>
                                            <span class="text-rose-700 dark:text-rose-400 font-semibold text-xs">Absent</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <input type="number" step="0.5" min="0" max="12" name="attendance[{{ $emp->id }}][overtime_hours]" value="{{ $ot }}"
                                           class="w-20 px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 dark:text-slate-500">No active workers assigned to this site.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($employees->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end">
                    <button type="submit" :disabled="submitting"
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-xl text-xs font-semibold shadow-sm flex items-center gap-2 transition cursor-pointer">
                        <svg x-show="submitting" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="submitting ? 'Saving Muster Roll...' : 'Save & Synchronize Muster Roll'"></span>
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>

<script>
window.musterRollManager = function() {
    return {
        submitting: false,
        alertMessage: '',
        alertType: 'success',

        async submitMuster(e) {
            this.submitting = true;
            this.alertMessage = '';

            const form = e.target;
            const formData = new FormData(form);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.alertType = 'success';
                    this.alertMessage = data.message || 'Muster roll submitted and synchronized with payroll.';
                    if (window.showToast) {
                        window.showToast(this.alertMessage, 'success');
                    }
                } else if (res.status === 422) {
                    this.alertType = 'error';
                    this.alertMessage = data.message || 'Validation error: please review submitted fields.';
                    if (window.showToast) {
                        window.showToast(this.alertMessage, 'error');
                    }
                } else if (res.status === 403) {
                    this.alertType = 'error';
                    this.alertMessage = data.message || 'Unauthorized action: you are not permitted to submit muster for this site.';
                    if (window.showToast) {
                        window.showToast(this.alertMessage, 'error');
                    }
                } else {
                    this.alertType = 'error';
                    this.alertMessage = data.message || 'An error occurred during save.';
                }
            } catch (err) {
                // If network failure or parse error, fallback to standard form submit
                this.alertType = 'error';
                this.alertMessage = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        }
    };
};
</script>
@endsection
