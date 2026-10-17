@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Create New Project</h2>
            <p class="text-xs text-gray-500 mt-0.5">Establish project parameters, budget, and site leadership.</p>
        </div>
        <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Projects</a>
    </div>

    <form method="POST" action="{{ route('projects.store') }}" class="soft-card p-6 space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Project Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Project Code *</label>
                <input type="text" name="code" value="{{ old('code', 'PRJ-' . date('Y') . '-' . rand(100, 999)) }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Client Organization</label>
                <select name="client_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="">Select Client...</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Project Classification *</label>
                <select name="project_type" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="civil">Civil Works & High-Rise</option>
                    <option value="interior">Interior Contracting & Fit-Out</option>
                    <option value="maintenance">Maintenance Contract</option>
                    <option value="earthmoving">Heavy Earthmoving / Excavation Fleet</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Contract Amount (INR) *</label>
                <input type="number" step="0.01" name="contract_value" value="{{ old('contract_value', '10000000.00') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Approved Project Budget (INR) *</label>
                <input type="number" step="0.01" name="approved_budget" value="{{ old('approved_budget', '9000000.00') }}" required
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Start Date</label>
                <input type="date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}"
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Target Completion Date</label>
                <input type="date" name="target_completion_date" value="{{ old('target_completion_date', date('Y-m-d', strtotime('+6 months'))) }}"
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Project Manager</label>
                <select name="manager_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="">Assign Manager...</option>
                    @foreach ($managers as $mgr)
                        <option value="{{ $mgr->id }}" {{ old('manager_id') == $mgr->id ? 'selected' : '' }}>{{ $mgr->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Site Location & Address</label>
            <input type="text" name="location" value="{{ old('location') }}" placeholder="e.g. Whitefield Tower A, Outer Ring Road, Bengaluru"
                   class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Scope & Engineering Specifications</label>
            <textarea name="description" rows="3" placeholder="Key deliverables, structural requirements, equipment fleet mobilization..."
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none">{{ old('description') }}</textarea>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('projects.index') }}" class="px-4 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Create Project Contract
            </button>
        </div>
    </form>
</div>
@endsection
