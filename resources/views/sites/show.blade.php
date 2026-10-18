@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $site->name }}</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active Site</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Project: {{ $site->project->name ?? 'General' }} • Supervisor: {{ $site->supervisor->name ?? 'Unassigned' }}</p>
        </div>

        <a href="{{ route('sites.logs.create', $site) }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Submit Daily Log</span>
        </a>
    </div>

    <!-- Daily Site Logs History -->
    <div class="soft-card p-5">
        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4">Daily Shift Progress Reports</h3>
        <div class="space-y-4">
            @forelse ($site->dailyLogs as $log)
                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-gray-900">{{ $log->log_date->format('l, d M, Y') }}</span>
                        <span class="px-2.5 py-0.5 bg-blue-100 text-blue-700 rounded-md font-semibold">
                            {{ $log->active_workers_count }} Workers on Site
                        </span>
                    </div>
                    <p class="text-gray-700 leading-relaxed">{{ $log->work_summary }}</p>

                    @if ($log->delays_obstacles)
                        <div class="p-2.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-[11px]">
                            <strong>Obstacles / Delays:</strong> {{ $log->delays_obstacles }}
                        </div>
                    @endif

                    <div class="text-[10px] text-gray-400 pt-1">
                        Reported by: {{ $log->creator->name ?? 'Site Supervisor' }} on {{ $log->created_at->format('h:i A') }}
                    </div>
                </div>
            @empty
                <p class="text-xs text-gray-400 italic">No shift logs on file for this site.</p>
            @endforelse
        </div>
    </div>

    <!-- Site Documents & Inspection Evidence -->
    <x-document-manager 
        :record="$site" 
        type="site" 
        title="Site Safety Permits & Field Records"
        :categories="[
            'permit' => 'Site Entry / Blasting / Work Permit',
            'inspection_report' => 'Safety & Quality Inspection Report',
            'progress_photo' => 'Site Progress / Excavation Photo',
            'drawing' => 'Site Layout / Survey Plan',
            'general' => 'Site Correspondence / Memo',
        ]"
    />
</div>
@endsection
