@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold mb-1">
                <span>COMMERCIALS</span>
                <span>•</span>
                <span class="text-blue-600 font-bold">CAPITAL ASSET REGISTER</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Fixed Assets & Equipment Fleet</h1>
            <p class="text-xs text-gray-500 mt-1">Capitalized plant, machinery, earthmoving equipment, depreciation schedules, and financing.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('assets.period-review') }}" class="px-4 py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-purple-200 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Periodic Depreciation Review</span>
            </a>
            <a href="{{ route('assets.create') }}" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Register Capital Asset</span>
            </a>
        </div>
    </div>

    <!-- Feedback messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- 5 KPI Cards (Stitch Aligned) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- 1. Total Capitalized Cost (Gross Block) -->
        <div class="soft-card p-5">
            <div class="flex items-center justify-between text-gray-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Gross Block</span>
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <strong class="text-xl font-bold font-mono text-gray-900 block">₹{{ number_format($totalCapitalizedCost, 2) }}</strong>
            <span class="text-[11px] text-gray-400 mt-1 block">Total acquisition & additions</span>
        </div>

        <!-- 2. Accumulated Depreciation -->
        <div class="soft-card p-5">
            <div class="flex items-center justify-between text-gray-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Accumulated Depr</span>
                <span class="p-1.5 bg-purple-50 text-purple-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                </span>
            </div>
            <strong class="text-xl font-bold font-mono text-purple-600 block">₹{{ number_format($totalAccumDepreciation, 2) }}</strong>
            <span class="text-[11px] text-gray-400 mt-1 block">Non-cash written off in GL</span>
        </div>

        <!-- 3. Net Book Value (Net Block) -->
        <div class="soft-card p-5">
            <div class="flex items-center justify-between text-gray-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Net Book Value</span>
                <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <strong class="text-xl font-bold font-mono text-emerald-600 block">₹{{ number_format($totalNetBookValue, 2) }}</strong>
            <span class="text-[11px] text-gray-400 mt-1 block">Balance sheet asset value</span>
        </div>

        <!-- 4. Active Fleet -->
        <div class="soft-card p-5">
            <div class="flex items-center justify-between text-gray-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Active Fleet</span>
                <span class="p-1.5 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <strong class="text-xl font-bold font-mono text-gray-900 block">{{ $activeFleetCount }} Units</strong>
            <span class="text-[11px] text-gray-400 mt-1 block">Deployed & operational</span>
        </div>

        <!-- 5. Financed Equipment -->
        <div class="soft-card p-5">
            <div class="flex items-center justify-between text-gray-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Financed Fleet</span>
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <strong class="text-xl font-bold font-mono text-blue-600 block">{{ $financedEquipmentCount }} Units</strong>
            <span class="text-[11px] text-gray-400 mt-1 block">Backed by active EMI loans</span>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('assets.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Search Asset</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Code, name, or serial #..." class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Category</label>
                <select name="category" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                    <option value="">All Equipment Categories</option>
                    <option value="excavator" {{ request('category') === 'excavator' ? 'selected' : '' }}>Hydraulic Excavators</option>
                    <option value="backhoe_loader" {{ request('category') === 'backhoe_loader' ? 'selected' : '' }}>Backhoe Loaders</option>
                    <option value="dumper_tipper" {{ request('category') === 'dumper_tipper' ? 'selected' : '' }}>Dumpers & Tippers</option>
                    <option value="crane" {{ request('category') === 'crane' ? 'selected' : '' }}>Mobile / Tower Cranes</option>
                    <option value="compactor" {{ request('category') === 'compactor' ? 'selected' : '' }}>Soil & Asphalt Compactors</option>
                    <option value="concrete_machinery" {{ request('category') === 'concrete_machinery' ? 'selected' : '' }}>Concrete Machinery</option>
                    <option value="generator" {{ request('category') === 'generator' ? 'selected' : '' }}>Generators & Compressors</option>
                    <option value="other" {{ request('category') === 'other' ? 'selected' : '' }}>Other Plant Equipment</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Deployed Project</label>
                <select name="project_id" class="w-full text-xs border border-gray-200 rounded-xl p-2.5 bg-white">
                    <option value="">All Projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filter</span>
                </button>
                @if(request()->anyFilled(['search', 'category', 'project_id', 'status']))
                    <a href="{{ route('assets.index') }}" class="px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-xs font-semibold transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Fixed Assets Register Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Capital Asset Master Register</h2>
                <p class="text-xs text-gray-400 mt-0.5">Asset acquisition, useful life, accumulated depreciation, and net book balances.</p>
            </div>
            <span class="text-xs font-bold text-gray-500 bg-gray-50 px-3 py-1 rounded-lg border border-gray-200">
                {{ $assets->total() }} Assets
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-400 font-bold uppercase text-[10px]">
                        <th class="py-3 px-4">Asset Code / Model</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4">Capitalized Date</th>
                        <th class="py-3 px-4 text-right">Gross Block (₹)</th>
                        <th class="py-3 px-4 text-right">Accum Depr (₹)</th>
                        <th class="py-3 px-4 text-right">Net Book Value (₹)</th>
                        <th class="py-3 px-4">Deployed Project</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($assets as $asset)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('assets.show', $asset) }}" class="font-bold text-gray-900 hover:text-blue-600 block transition">
                                    {{ $asset->name }}
                                </a>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="font-mono text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.2 rounded font-semibold">{{ $asset->asset_code }}</span>
                                    @if($asset->loan)
                                        <span class="text-[9px] bg-blue-50 text-blue-700 px-1.5 py-0.2 rounded font-bold border border-blue-100">
                                            Financed: {{ $asset->loan->lender_name }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-gray-600 capitalize">
                                {{ str_replace('_', ' ', $asset->category) }}
                            </td>
                            <td class="py-3 px-4 text-gray-500">
                                {{ $asset->capitalization_date->format('d M Y') }}
                                <span class="block text-[10px] text-gray-400 capitalize">{{ str_replace('_', ' ', $asset->depreciation_method) }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-gray-800">
                                ₹{{ number_format($asset->total_capitalized_cost, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-purple-600">
                                ₹{{ number_format($asset->accumulated_depreciation, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">
                                ₹{{ number_format($asset->current_book_value, 2) }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                @if($asset->project)
                                    <a href="{{ route('projects.show', $asset->project) }}" class="hover:text-blue-600 transition font-medium">
                                        {{ $asset->project->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Central Yard</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $asset->statusBadgeClasses() }}">
                                    {{ strtoupper($asset->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('assets.show', $asset) }}" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400 text-xs">
                                <svg class="w-8 h-8 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                <span>No capital fixed assets found. Register your first equipment unit above.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assets->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
