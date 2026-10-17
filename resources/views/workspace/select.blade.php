<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Select Workspace</h1>
        <p class="text-sm text-gray-500 mt-1">Choose a company organization to enter its operational portal.</p>
    </div>

    <div class="space-y-3">
        @forelse ($companies as $comp)
            <form method="POST" action="{{ route('workspace.switch', $comp) }}">
                @csrf
                <button type="submit" class="w-full text-left p-4 bg-white border border-gray-200 hover:border-blue-500 hover:shadow-md rounded-2xl transition duration-150 flex items-center justify-between group cursor-pointer">
                    <div>
                        <div class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition">{{ $comp->name }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $comp->legal_name ?? 'Infrastructure & Construction' }}</div>
                        <div class="text-[11px] font-mono text-gray-400 mt-1">GSTIN: {{ $comp->gstin ?? 'N/A' }}</div>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </button>
            </form>
        @empty
            <div class="p-4 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl text-sm">
                No company workspaces linked to this profile. Create your first workspace below.
            </div>
        @endforelse
    </div>

    <div class="mt-6 pt-6 border-t border-gray-200/80 flex items-center justify-between">
        <a href="{{ route('workspace.create') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">+ Add New Company</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-700 cursor-pointer">Sign Out</button>
        </form>
    </div>
</x-guest-layout>
