@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Active Work Sites</h2>
            <p class="text-xs text-gray-500 mt-1">Multi-site monitoring, worker headcount, and supervisor daily shift logs.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @forelse ($sites as $site)
            <div class="soft-card p-5 soft-card-hover flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-wider">{{ $site->project->name ?? 'General Site' }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ $site->status }}
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-gray-900 mt-2">
                        <a href="{{ route('sites.show', $site) }}" class="hover:text-blue-600 transition">{{ $site->name }}</a>
                    </h3>

                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ $site->location ?? 'Site Yard Location' }}</span>
                    </p>

                    <div class="mt-4 p-3 bg-gray-50 rounded-xl border border-gray-100/80 text-xs">
                        <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Latest Log Status</div>
                        @if ($site->dailyLogs->isNotEmpty())
                            <p class="text-gray-700 leading-snug line-clamp-2">{{ $site->dailyLogs->first()->work_summary }}</p>
                            <span class="text-[10px] text-gray-400 mt-1 block">Active on site: <strong>{{ $site->dailyLogs->first()->active_workers_count }} workers</strong></span>
                        @else
                            <p class="text-gray-400 italic">No shift logs submitted today.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Supervisor: <strong class="text-gray-900">{{ $site->supervisor->name ?? 'Unassigned' }}</strong></span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('sites.logs.create', $site) }}" class="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg font-semibold transition">
                            + Add Log
                        </a>
                        <a href="{{ route('sites.show', $site) }}" class="px-3 py-1.5 bg-gray-900 text-white hover:bg-gray-800 rounded-lg font-semibold transition">
                            Site View
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-2 p-8 text-center text-gray-400 soft-card">No operational sites created yet.</div>
        @endforelse
    </div>
</div>
@endsection
