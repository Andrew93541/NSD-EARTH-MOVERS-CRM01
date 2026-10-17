@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900 tracking-tight">Search Results for: "{{ $q }}"</h2>
        <p class="text-xs text-gray-500 mt-1">Cross-entity matches scoped to {{ $currentCompany->name }}.</p>
    </div>

    <!-- Results Categories -->
    <div class="space-y-5">
        <!-- Projects -->
        @if ($projects->isNotEmpty())
            <div class="soft-card p-5">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Matching Projects</h3>
                <div class="space-y-2">
                    @foreach ($projects as $prj)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl text-xs">
                            <div>
                                <a href="{{ route('projects.show', $prj) }}" class="font-bold text-gray-900 hover:text-blue-600">{{ $prj->name }}</a>
                                <span class="block text-[11px] text-gray-400 font-mono">{{ $prj->code }} • {{ $prj->location }}</span>
                            </div>
                            <a href="{{ route('projects.show', $prj) }}" class="text-blue-600 font-semibold">View →</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Materials -->
        @if ($materials->isNotEmpty())
            <div class="soft-card p-5">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Matching Catalog Materials</h3>
                <div class="space-y-2">
                    @foreach ($materials as $mat)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl text-xs">
                            <div>
                                <span class="font-bold text-gray-900">{{ $mat->name }}</span>
                                <span class="block text-[11px] text-gray-400 font-mono">{{ $mat->code }} • Rate: ₹{{ $mat->standard_cost }}/{{ $mat->unit_of_measure }}</span>
                            </div>
                            <span class="font-bold text-emerald-600">Stock: {{ $mat->currentStock() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Employees -->
        @if ($employees->isNotEmpty())
            <div class="soft-card p-5">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Matching Personnel & Operators</h3>
                <div class="space-y-2">
                    @foreach ($employees as $emp)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl text-xs">
                            <div>
                                <a href="{{ route('employees.show', $emp) }}" class="font-bold text-gray-900 hover:text-blue-600">{{ $emp->full_name }}</a>
                                <span class="block text-[11px] text-gray-400 font-mono">{{ $emp->code }} • {{ $emp->designation }}</span>
                            </div>
                            <a href="{{ route('employees.show', $emp) }}" class="text-blue-600 font-semibold">Profile →</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($projects->isEmpty() && $materials->isEmpty() && $employees->isEmpty())
            <div class="soft-card p-8 text-center text-gray-400 text-xs">
                No matching records found for "{{ $q }}". Try a different project code, material, or worker name.
            </div>
        @endif
    </div>
</div>
@endsection
