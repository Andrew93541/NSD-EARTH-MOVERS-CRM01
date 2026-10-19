@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Construction Work Categories</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ $categories->count() }} Primary Categories
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Configure earthmoving, civil, and specialized construction work scopes, measurement units, and sub-activities.</p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager']));
        @endphp

        @if($canManage)
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('createCategoryModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Work Category</span>
            </button>
        </div>
        @endif
    </div>

    <!-- Category Grid / Tree View -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($categories as $cat)
        <div class="soft-card p-5 space-y-4 border border-slate-100 dark:border-slate-800">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        {{ $cat->code }}
                    </span>
                    <h3 class="text-base font-bold text-slate-900 mt-1">{{ $cat->name }}</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $cat->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $cat->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            @if($cat->description)
            <p class="text-xs text-slate-500">{{ $cat->description }}</p>
            @endif

            <div class="text-xs text-slate-600 font-medium">
                Standard UOM: <span class="font-bold text-slate-900">{{ $cat->unit_of_measure ?: 'N/A' }}</span>
            </div>

            <!-- Subcategories Section -->
            <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-700 mb-2">
                    <span>Subcategories ({{ $cat->subcategories->count() }})</span>
                    @if($canManage)
                    <button onclick="openSubcategoryModal({{ $cat->id }}, '{{ addslashes($cat->name) }}')" class="text-[11px] text-emerald-600 hover:text-emerald-700 font-bold cursor-pointer">
                        + Add Sub
                    </button>
                    @endif
                </div>

                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                    @forelse($cat->subcategories as $sub)
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 text-xs">
                        <span class="font-medium text-slate-800">{{ $sub->name }}</span>
                        <span class="text-[10px] font-mono text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200">
                            {{ $sub->unit_of_measure ?: $cat->unit_of_measure }}
                        </span>
                    </div>
                    @empty
                    <p class="text-[11px] text-slate-400 italic">No subcategories defined.</p>
                    @endforelse
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full p-12 text-center bg-white rounded-2xl border border-slate-100">
            <div class="w-12 h-12 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-sm font-bold text-slate-800">No Construction Categories Configured</h3>
            <p class="text-xs text-slate-500 mt-1">Get started by setting up primary work activities like Excavation, Earthmoving, or Piling.</p>
        </div>
        @endforelse
    </div>

    <!-- Create Category Modal -->
    <div id="createCategoryModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900" id="modalTitle">New Primary Category</h3>
                <button onclick="document.getElementById('createCategoryModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('construction-categories.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="parent_id" id="modalParentId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Category / Activity Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Earth Excavation in Hard Rock" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Code *</label>
                        <input type="text" name="code" required placeholder="e.g. EXC-ROCK" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs uppercase focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Unit of Measure (UOM)</label>
                        <input type="text" name="unit_of_measure" placeholder="e.g. CuM, SqM, MT, Hours" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" placeholder="Optional details or technical scope" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('createCategoryModal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold cursor-pointer">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSubcategoryModal(parentId, parentName) {
    document.getElementById('modalParentId').value = parentId;
    document.getElementById('modalTitle').innerText = 'New Subcategory for ' + parentName;
    document.getElementById('createCategoryModal').classList.remove('hidden');
}
</script>
@endsection
