@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">Subcontractor Register & Directory</h2>
            <p class="text-xs text-gray-500 mt-1">Specialist trade contractors, earthmoving subcontractors, civil gangs, and statutory compliance.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('subcontractors.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Register Subcontractor</span>
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-gray-400 uppercase">Total Subcontractors</span>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ $stats['total_count'] }}</div>
            <span class="text-[11px] text-gray-400">Registered contracting entities</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-emerald-600 uppercase">Active Status</span>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $stats['active_count'] }}</div>
            <span class="text-[11px] text-gray-400">Available for work orders</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-amber-600 uppercase">Earthmoving & Excavation</span>
            <div class="text-2xl font-black text-amber-600 mt-1">{{ $stats['earthwork_count'] }}</div>
            <span class="text-[11px] text-gray-400">Heavy earthwork specialists</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[11px] font-bold text-blue-600 uppercase">Civil & Structural</span>
            <div class="text-2xl font-black text-blue-600 mt-1">{{ $stats['civil_count'] }}</div>
            <span class="text-[11px] text-gray-400">Concrete & drainage teams</span>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('subcontractors.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, code, PAN, GSTIN..."
                    class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
            </div>
            <div>
                <select name="status" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div>
                <select name="category" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2">
                    <option value="">All Trade Categories</option>
                    <option value="earthwork" {{ request('category') === 'earthwork' ? 'selected' : '' }}>Earthwork & Excavation</option>
                    <option value="civil" {{ request('category') === 'civil' ? 'selected' : '' }}>Civil & Masonry</option>
                    <option value="concreting" {{ request('category') === 'concreting' ? 'selected' : '' }}>Concreting & Structural</option>
                    <option value="road_works" {{ request('category') === 'road_works' ? 'selected' : '' }}>Road & Paving Works</option>
                    <option value="drainage" {{ request('category') === 'drainage' ? 'selected' : '' }}>Drainage & Culverts</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="w-full px-4 py-2 bg-gray-900 text-white text-xs font-semibold rounded-xl hover:bg-gray-800 transition">Filter</button>
                @if(request()->anyFilled(['search', 'status', 'category']))
                    <a href="{{ route('subcontractors.index') }}" class="px-3 py-2 bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl hover:bg-gray-200 transition">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Subcontractors List Table -->
    <div class="soft-card p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-3">Subcontractor Code & Name</th>
                        <th class="py-3 px-3">Contact & Phone</th>
                        <th class="py-3 px-3">GSTIN / PAN</th>
                        <th class="py-3 px-3">Trade Categories</th>
                        <th class="py-3 px-3">TDS Section</th>
                        <th class="py-3 px-3">Work Orders</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($subcontractors as $sub)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-3">
                                <a href="{{ route('subcontractors.show', $sub) }}" class="font-bold text-gray-900 hover:text-blue-600 block">
                                    {{ $sub->name }}
                                </a>
                                <span class="font-mono text-[11px] text-gray-400">{{ $sub->subcontractor_code ?? 'SC-'.$sub->id }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-medium text-gray-800 block">{{ $sub->contact_person ?? '—' }}</span>
                                <span class="text-gray-400 text-[11px]">{{ $sub->phone ?? '—' }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px]">
                                <span class="text-gray-900 block font-semibold">{{ $sub->gstin ?? 'Unregistered' }}</span>
                                <span class="text-gray-400">{{ $sub->pan ?? '—' }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap gap-1">
                                    @if(is_array($sub->work_categories))
                                        @foreach($sub->work_categories as $cat)
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gray-100 text-gray-700 capitalize">{{ str_replace('_', ' ', $cat) }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px] text-gray-700 font-semibold">
                                {{ $sub->tds_section ?? '194C (2%)' }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ $sub->subcontractor_work_orders_count }} WO
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sub->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($sub->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('subcontractors.show', $sub) }}" class="text-blue-600 font-semibold hover:text-blue-800 text-xs">View Profile →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400 text-xs">
                                No subcontractors found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $subcontractors->links() }}
        </div>
    </div>
</div>
@endsection
