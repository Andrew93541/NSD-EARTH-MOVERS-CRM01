@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Projects & Contracts</h2>
            <p class="text-xs text-gray-500 mt-1">Civil construction, interior fit-outs, maintenance, and heavy earthmoving works.</p>
        </div>
        <a href="{{ route('projects.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create New Project</span>
        </a>
    </div>

    <!-- Filters -->
    <div class="soft-card p-4 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('projects.index') }}" class="flex flex-wrap items-center gap-3 w-full">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Filter by code, name, location..."
                   class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none flex-1 min-w-[200px]"/>

            <select name="type" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                <option value="">All Project Types</option>
                <option value="civil" {{ request('type') == 'civil' ? 'selected' : '' }}>Civil Construction</option>
                <option value="interior" {{ request('type') == 'interior' ? 'selected' : '' }}>Interior Fit-Out</option>
                <option value="maintenance" {{ request('type') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                <option value="earthmoving" {{ request('type') == 'earthmoving' ? 'selected' : '' }}>Earthmoving Fleet</option>
            </select>

            <select name="status" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                <option value="">All Statuses</option>
                <option value="on_track" {{ request('status') == 'on_track' ? 'selected' : '' }}>On Track</option>
                <option value="at_risk" {{ request('status') == 'at_risk' ? 'selected' : '' }}>At Risk</option>
                <option value="delayed" {{ request('status') == 'delayed' ? 'selected' : '' }}>Delayed</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-xl text-xs font-semibold hover:bg-gray-800 transition">
                Apply Filters
            </button>

            @if(request()->anyFilled(['search', 'type', 'status']))
                <a href="{{ route('projects.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
            @endif
        </form>
    </div>

    <!-- Projects Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @forelse ($projects as $project)
            <div class="soft-card p-5 soft-card-hover flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono font-bold text-gray-400 uppercase">{{ $project->code }}</span>
                        @if ($project->status === 'on_track')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">On Track</span>
                        @elseif ($project->status === 'at_risk')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">At Risk</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Delayed</span>
                        @endif
                    </div>

                    <h3 class="text-base font-bold text-gray-900 mt-2">
                        <a href="{{ route('projects.show', $project) }}" class="hover:text-blue-600 transition">{{ $project->name }}</a>
                    </h3>

                    <p class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">{{ $project->description }}</p>

                    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                        <span>Client: <strong class="text-gray-900">{{ $project->client->name ?? 'Direct Contract' }}</strong></span>
                        <span>Budget: <strong class="text-gray-900">₹{{ number_format($project->contract_value / 10000000, 2) }} Cr</strong></span>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between text-xs mb-1.5 font-semibold">
                        <span class="text-gray-500">Progress</span>
                        <span class="text-blue-600 font-bold">{{ $project->progress_percent }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress_percent }}%"></div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-[11px] text-gray-400">Manager: {{ $project->manager->name ?? 'Unassigned' }}</span>
                        <a href="{{ route('projects.show', $project) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition">View Details →</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-2 p-8 text-center text-gray-400 soft-card">No projects match the specified filter criteria.</div>
        @endforelse
    </div>

    <div>{{ $projects->links() }}</div>
</div>
@endsection
