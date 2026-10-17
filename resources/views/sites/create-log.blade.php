@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Daily Shift Log</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $site->name }} • {{ $site->project->name ?? 'Civil Works' }}</p>
        </div>
        <a href="{{ route('sites.show', $site) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back</a>
    </div>

    <form method="POST" action="{{ route('sites.logs.store', $site) }}" class="soft-card p-6 space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Log Date *</label>
                <input type="date" name="log_date" value="{{ date('Y-m-d') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Active Workers Count *</label>
                <input type="number" min="0" name="active_workers_count" value="24" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Work Completed Today *</label>
            <textarea name="work_summary" rows="3" required placeholder="Describe structural works, concrete pouring, shuttering, or excavation..."
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Delays / Safety Incidents / Bottlenecks</label>
            <textarea name="delays_obstacles" rows="2" placeholder="Material shortage, rain stoppages, equipment breakdown..."
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Weather & Site Conditions</label>
            <input type="text" name="weather_conditions" placeholder="e.g. Sunny 28°C, Ground clear"
                   class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('sites.show', $site) }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Submit Shift Report
            </button>
        </div>
    </form>
</div>
@endsection
