@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Team & User Directory</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $stats['total_users'] }} Users
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Manage organization team members, assigned company roles, security statuses, and access boundaries.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('team.roles') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Permission Matrix & Roles</span>
            </a>
        </div>
    </div>

    <!-- Quick Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Organization Members</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $stats['total_users'] }}</div>
            <span class="text-[11px] text-slate-500">Active CRM accounts</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Configured Roles</span>
            <div class="text-2xl font-extrabold text-indigo-600 mt-1">{{ $stats['total_roles'] }}</div>
            <span class="text-[11px] text-slate-500">System and custom profiles</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Company Owners</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1">{{ $stats['active_owners'] }}</div>
            <span class="text-[11px] text-slate-500">Protected primary root tier</span>
        </div>
    </div>

    <!-- User Table -->
    <div class="soft-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="p-3.5">User</th>
                        <th class="p-3.5">Role</th>
                        <th class="p-3.5">Linked Employee Profile</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($users as $user)
                    @php
                        $userPivot = $user->companies->first()?->pivot;
                        $userRole = $roles->firstWhere('id', $userPivot?->role_id);
                        $isOwner = $userRole?->slug === 'owner';
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs">
                                    {{ $user->initials }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3.5">
                            @if($isOwner)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                👑 Company Owner
                            </span>
                            @elseif($userRole)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                {{ $userRole->name }}
                            </span>
                            @else
                            <span class="text-slate-400 italic">No Role Assigned</span>
                            @endif
                        </td>
                        <td class="p-3.5">
                            @if($user->employee)
                            <span class="font-medium text-slate-800">{{ $user->employee->full_name }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ $user->employee->designation }}</span>
                            @else
                            <span class="text-slate-400 italic">—</span>
                            @endif
                        </td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $userPivot?->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ ucfirst($userPivot?->status ?? 'active') }}
                            </span>
                        </td>
                        <td class="p-3.5 text-right">
                            <button onclick="openRoleModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $userPivot?->role_id ?? 'null' }}, '{{ $userPivot?->status ?? 'active' }}')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer">
                                Manage Role
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-400">No users found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <!-- Role Assignment Modal -->
    <div id="roleModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900" id="roleModalTitle">Assign Role</h3>
                <button onclick="document.getElementById('roleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="roleAssignForm" method="POST" action="" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Select Role *</label>
                    <select name="role_id" id="modalRoleId" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500">
                        @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }} ({{ $role->slug }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Account Status *</label>
                    <select name="status" id="modalStatus" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-relaxed">
                    <strong>Protection Policy:</strong> The organization must always retain at least one active Company Owner. Deleting or demoting the last owner is strictly blocked.
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('roleModal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold cursor-pointer">
                        Save Role Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRoleModal(userId, userName, currentRoleId, currentStatus) {
    document.getElementById('roleAssignForm').action = '/team/users/' + userId + '/role';
    document.getElementById('roleModalTitle').innerText = 'Assign Role: ' + userName;
    if (currentRoleId) {
        document.getElementById('modalRoleId').value = currentRoleId;
    }
    document.getElementById('modalStatus').value = currentStatus;
    document.getElementById('roleModal').classList.remove('hidden');
}
</script>
@endsection
