@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Staff, Operators & Labour</h2>
            <p class="text-xs text-gray-500 mt-1">Directory of machine operators, bar benders, engineers, and site workforce.</p>
        </div>
        <a href="{{ route('employees.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Register New Employee</span>
        </a>
    </div>

    <!-- Directory Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($employees as $emp)
            <div class="soft-card p-5 soft-card-hover flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 text-white font-bold text-sm flex items-center justify-center shadow-sm">
                            {{ substr($emp->first_name, 0, 1) }}{{ substr($emp->last_name ?? '', 0, 1) }}
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">
                                <a href="{{ route('employees.show', $emp) }}" class="hover:text-blue-600 transition">{{ $emp->full_name }}</a>
                            </h3>
                            <span class="text-[11px] text-gray-400 font-mono">{{ $emp->code }} • {{ $emp->designation ?? 'Site Crew' }}</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100 text-xs space-y-1.5 text-gray-600">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Department:</span>
                            <span class="font-semibold text-gray-800">{{ $emp->department ?? 'General Operations' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Assigned Site:</span>
                            <span class="font-semibold text-gray-800">{{ $emp->assignedSite->name ?? 'Central Pool' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Wage Type:</span>
                            <span class="font-semibold text-blue-600 uppercase text-[10px]">{{ str_replace('_', ' ', $emp->employment_type) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Daily: ₹{{ number_format($emp->daily_wage_rate, 2) }}</span>
                    <a href="{{ route('employees.show', $emp) }}" class="font-semibold text-blue-600 hover:text-blue-800">Profile →</a>
                </div>
            </div>
        @empty
            <div class="col-span-3 p-8 text-center text-gray-400 soft-card">No employees registered yet.</div>
        @endforelse
    </div>

    <div>{{ $employees->links() }}</div>
</div>
@endsection
