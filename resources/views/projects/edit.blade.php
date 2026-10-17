@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Edit Project: {{ $project->name }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">Update project status, budget, completion percentage, or manager.</p>
        </div>
        <a href="{{ route('projects.show', $project) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back</a>
    </div>

    <form method="POST" action="{{ route('projects.update', $project) }}" class="soft-card p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Project Name *</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Classification *</label>
                <select name="project_type" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="civil" {{ $project->project_type == 'civil' ? 'selected' : '' }}>Civil Works & High-Rise</option>
                    <option value="interior" {{ $project->project_type == 'interior' ? 'selected' : '' }}>Interior Contracting</option>
                    <option value="maintenance" {{ $project->project_type == 'maintenance' ? 'selected' : '' }}>Maintenance Contract</option>
                    <option value="earthmoving" {{ $project->project_type == 'earthmoving' ? 'selected' : '' }}>Heavy Earthmoving Fleet</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Execution Status *</label>
                <select name="status" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="on_track" {{ $project->status == 'on_track' ? 'selected' : '' }}>On Track</option>
                    <option value="at_risk" {{ $project->status == 'at_risk' ? 'selected' : '' }}>At Risk</option>
                    <option value="delayed" {{ $project->status == 'delayed' ? 'selected' : '' }}>Delayed</option>
                    <option value="completed" {{ $project->status == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="on_hold" {{ $project->status == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Progress Percentage (0-100) *</label>
                <input type="number" min="0" max="100" name="progress_percent" value="{{ old('progress_percent', $project->progress_percent) }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Project Manager</label>
                <select name="manager_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="">Unassigned</option>
                    @foreach ($managers as $mgr)
                        <option value="{{ $mgr->id }}" {{ $project->manager_id == $mgr->id ? 'selected' : '' }}>{{ $mgr->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Contract Value (INR) *</label>
                <input type="number" step="0.01" name="contract_value" value="{{ old('contract_value', $project->contract_value) }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Approved Budget (INR) *</label>
                <input type="number" step="0.01" name="approved_budget" value="{{ old('approved_budget', $project->approved_budget) }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Scope & Notes</label>
            <textarea name="description" rows="3"
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">{{ old('description', $project->description) }}</textarea>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('projects.show', $project) }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
