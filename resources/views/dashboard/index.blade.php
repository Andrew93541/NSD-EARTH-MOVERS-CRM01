@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Top Row: 4 Metric Cards + Projects Timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- 4 Metric Cards (8 cols on lg) -->
        <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-4">

            <!-- Card 1: Active Projects -->
            <div class="soft-card p-5 flex flex-col justify-between soft-card-hover">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Active Projects</span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-600 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        Live
                    </span>
                </div>
                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-4xl font-black text-gray-900 tracking-tight">{{ $activeProjectsCount }}</span>
                    <span class="text-[11px] text-gray-400 font-medium">Active sites</span>
                </div>
            </div>

            <!-- Card 2: Receivables -->
            <div class="soft-card p-5 flex flex-col justify-between soft-card-hover">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Receivables</span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-600 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        Due
                    </span>
                </div>
                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-3xl font-black text-gray-900 tracking-tight">
                        ₹{{ $totalReceivables >= 10000000 ? number_format($totalReceivables / 10000000, 2) . ' Cr' : ($totalReceivables >= 100000 ? number_format($totalReceivables / 100000, 1) . 'L' : number_format($totalReceivables, 0)) }}
                    </span>
                    <span class="text-[11px] text-gray-400 font-medium">Outstanding</span>
                </div>
            </div>

            <!-- Card 3: Payables -->
            <div class="soft-card p-5 flex flex-col justify-between soft-card-hover">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payables</span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-600 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        Due
                    </span>
                </div>
                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-3xl font-black text-gray-900 tracking-tight">
                        ₹{{ $totalPayables >= 10000000 ? number_format($totalPayables / 10000000, 2) . ' Cr' : ($totalPayables >= 100000 ? number_format($totalPayables / 100000, 1) . 'L' : number_format($totalPayables, 0)) }}
                    </span>
                    <span class="text-[11px] text-gray-400 font-medium">To suppliers</span>
                </div>
            </div>

            <!-- Card 4: Pending Approvals -->
            <div class="soft-card p-5 flex flex-col justify-between soft-card-hover">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Pending Approvals</span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-600 flex items-center gap-1">
                        Action
                    </span>
                </div>
                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-4xl font-black text-gray-900 tracking-tight">{{ $pendingApprovalsCount }}</span>
                    <span class="text-[11px] text-gray-400 font-medium">Awaiting sign-off</span>
                </div>
            </div>
        </div>

        <!-- Projects Timeline Widget (5 cols on lg) -->
        <div class="lg:col-span-5 soft-card p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-gray-900 uppercase tracking-wider">Projects Timeline</span>
                <span class="text-xs text-gray-400 font-medium">Q4 Milestones</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Project 1 Milestone -->
                <div class="p-3 bg-gray-50/80 rounded-2xl border border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-blue-100 text-blue-700 rounded-md">Civil - High-Rise</span>
                            <span class="text-[10px] font-semibold text-gray-500">Structural</span>
                        </div>
                        <h4 class="text-xs font-bold text-gray-900 mt-2">Riverside Residency</h4>
                        <p class="text-[11px] text-gray-500 mt-1 line-clamp-2 leading-tight">
                            14th floor slab casting & reinforcement inspection...
                        </p>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-bold text-blue-600">72% Completed</span>
                            <span class="text-gray-400">Due Nov 30</span>
                        </div>
                        <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-blue-600 h-1.5 rounded-full" style="width: 72%"></div>
                        </div>
                    </div>
                </div>

                <!-- Project 2 Milestone -->
                <div class="p-3 bg-gray-50/80 rounded-2xl border border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-amber-100 text-amber-800 rounded-md">PEB Industrial</span>
                            <span class="text-[10px] font-semibold text-gray-500">Fabrication</span>
                        </div>
                        <h4 class="text-xs font-bold text-gray-900 mt-2">Industrial Park Phase 2</h4>
                        <p class="text-[11px] text-gray-500 mt-1 line-clamp-2 leading-tight">
                            Structural steel erection & heavy crane mobilization on north bay...
                        </p>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-bold text-amber-600">48% Completed</span>
                            <span class="text-gray-400">Due Dec 15</span>
                        </div>
                        <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-amber-500 h-1.5 rounded-full" style="width: 48%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Middle Row: Project Overview Dot Chart + Site Operations -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Dot Matrix / Interactive Overview Chart (7 cols) -->
        <div class="lg:col-span-7 soft-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Project Overview</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Execution volume across all operational work sites</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5 text-xs text-gray-600 font-medium">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span> Completed
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-gray-600 font-medium">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-200"></span> In Progress
                    </span>
                </div>
            </div>

            <!-- Dot Grid Chart Visualization mirroring Stitch Layout -->
            <div class="h-48 flex items-end justify-between gap-2 pt-4 px-2">
                @php
                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    $heights = [45, 60, 35, 75, 90, 65, 80, 55, 70, 85, 92, 78];
                @endphp
                @foreach ($months as $idx => $m)
                    <div class="flex-1 flex flex-col items-center gap-2 group">
                        <div class="w-full max-w-[28px] bg-blue-50/60 rounded-t-lg flex flex-col justify-end p-1 transition-all group-hover:bg-blue-100" style="height: 140px;">
                            <div class="w-full bg-gradient-to-t from-blue-700 to-sky-400 rounded-md transition-all duration-300" style="height: {{ $heights[$idx] }}%;"></div>
                        </div>
                        <span class="text-[11px] font-semibold text-gray-400 group-hover:text-blue-600 transition">{{ $m }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Site Operations (5 cols) -->
        <div class="lg:col-span-5 soft-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Site Operations</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Real-time workforce deployment by location</p>
                </div>
                <a href="{{ route('sites.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">View all sites →</a>
            </div>

            <div class="space-y-3">
                @foreach ($sites->take(4) as $st)
                    <div class="flex items-center justify-between p-3 bg-gray-50/80 rounded-2xl border border-gray-100/80 hover:bg-gray-100/60 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900">{{ $st->name }}</h4>
                                <span class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $st->dailyLogs->first()?->active_workers_count ?? 24 }} workers active
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('sites.show', $st) }}" class="p-2 rounded-xl bg-white border border-gray-200 text-gray-600 hover:text-blue-600 hover:border-blue-400 transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <!-- Bottom Row: Recent Projects Data Grid (Matches Stitch Layout Exactly) -->
    <div class="soft-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Recent Projects</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">Active civil infrastructure, interior, and earthmoving contracts</p>
            </div>
            <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
                View all contracts →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Assigned To</th>
                        <th class="py-3 px-3">Project Name</th>
                        <th class="py-3 px-3">Start Date</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3">Contract Budget</th>
                        <th class="py-3 px-3">Progress</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs">
                    @forelse ($projects as $prj)
                        <tr class="hover:bg-gray-50/60 transition group">
                            <!-- Assigned To -->
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 text-white font-bold text-[10px] flex items-center justify-center">
                                        {{ substr($prj->manager->name ?? 'PM', 0, 2) }}
                                    </div>
                                    <span class="font-semibold text-gray-900">{{ $prj->manager->name ?? 'Unassigned' }}</span>
                                </div>
                            </td>

                            <!-- Project Name -->
                            <td class="py-3.5 px-3 font-bold text-gray-900">
                                <a href="{{ route('projects.show', $prj) }}" class="hover:text-blue-600 transition">
                                    {{ $prj->name }}
                                </a>
                                <span class="block text-[11px] font-normal text-gray-400">{{ $prj->code }}</span>
                            </td>

                            <!-- Start Date -->
                            <td class="py-3.5 px-3 text-gray-500">
                                {{ $prj->start_date ? $prj->start_date->format('d M, Y') : '—' }}
                            </td>

                            <!-- Due Date -->
                            <td class="py-3.5 px-3 text-gray-500">
                                {{ $prj->target_completion_date ? $prj->target_completion_date->format('d M, Y') : '—' }}
                            </td>

                            <!-- Contract Budget in INR -->
                            <td class="py-3.5 px-3 font-extrabold text-gray-900">
                                ₹{{ number_format($prj->contract_value / 10000000, 2) }} Cr
                            </td>

                            <!-- Progress -->
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-2 w-28">
                                    <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $prj->progress_percent }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-bold text-gray-700">{{ $prj->progress_percent }}%</span>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-3">
                                @if ($prj->status === 'on_track')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> On Track
                                    </span>
                                @elseif ($prj->status === 'at_risk')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> At Risk
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Delayed
                                    </span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-3.5 px-3 text-right">
                                <a href="{{ route('projects.show', $prj) }}" class="text-gray-400 hover:text-blue-600 transition p-1">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-gray-400">No project contracts registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
