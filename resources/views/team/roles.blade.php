@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('team.index') }}" class="text-xs text-slate-400 hover:text-slate-600 flex items-center gap-1 font-semibold">
                    ← Back to Team Directory
                </a>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">Roles & Permission Matrix</h1>
            <p class="text-xs text-slate-500 mt-1">Granular access control, privilege boundaries, and capabilities per role.</p>
        </div>

        <button onclick="document.getElementById('createRoleModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create Custom Role</span>
        </button>
    </div>

    <!-- Permission Matrix Table -->
    <div class="soft-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="p-3.5 min-w-[200px]">Module / Capability</th>
                        @foreach($roles as $role)
                        <th class="p-3.5 text-center min-w-[120px]">
                            <div class="font-bold text-slate-900">{{ $role->name }}</div>
                            <span class="text-[9px] font-mono text-slate-400 block font-normal">{{ $role->users_count }} user(s)</span>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach($capabilities as $module => $perms)
                    <tr class="bg-slate-50/70 font-bold text-slate-900 text-[11px]">
                        <td colspan="{{ count($roles) + 1 }}" class="p-2.5 pl-3.5 uppercase tracking-wider text-slate-500">
                            {{ ucfirst($module) }} Management
                        </td>
                    </tr>
                    @foreach($perms as $perm)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-3 pl-6 font-mono text-[11px] text-slate-700">
                            {{ $perm }}
                        </td>
                        @foreach($roles as $role)
                        @php
                            $has = $role->hasPermission($perm);
                            $isWildcard = in_array('*', $role->permissions ?? [], true);
                        @endphp
                        <td class="p-3 text-center">
                            @if($has)
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $isWildcard ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}" title="{{ $isWildcard ? 'Wildcard (*) Access' : 'Granted' }}">
                                ✓
                            </span>
                            @else
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-300">
                                —
                            </span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Custom Role Modal -->
    <div id="createRoleModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900">Create Custom Role</h3>
                <button onclick="document.getElementById('createRoleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('team.roles.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Role Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Quantity Surveyor" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Role Slug *</label>
                        <input type="text" name="slug" required placeholder="e.g. quantity_surveyor" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs lowercase focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" placeholder="Brief scope of responsibilities" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-2">Select Capabilities / Permissions *</label>
                    <div class="max-h-56 overflow-y-auto space-y-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                        @foreach($capabilities as $group => $items)
                        <div>
                            <div class="text-[11px] font-bold uppercase text-slate-600 mb-1">{{ ucfirst($group) }}</div>
                            <div class="grid grid-cols-2 gap-1.5 pl-2">
                                @foreach($items as $item)
                                <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                    <input type="checkbox" name="permissions[]" value="{{ $item }}" class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <span class="font-mono text-[11px]">{{ $item }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('createRoleModal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold cursor-pointer">
                        Create Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
