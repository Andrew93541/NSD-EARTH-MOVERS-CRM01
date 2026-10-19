@extends('layouts.app', ['title' => 'User Profile & Account Settings'])

@section('content')
<div x-data="profileManager({
    activeTab: '{{ $activeTab ?? 'overview' }}',
    currentTheme: localStorage.getItem('theme') || '{{ $user->preferences['theme'] ?? 'system' }}',
    emailNotifications: {{ ($user->preferences['notifications_email'] ?? false) ? 'true' : 'false' }},
    browserNotifications: {{ ($user->preferences['notifications_browser'] ?? true) ? 'true' : 'false' }},
    avatarUrl: '{{ $user->avatar_url }}',
    userName: '{{ addslashes($user->name) }}',
    userInitials: '{{ $user->initials }}'
})" class="space-y-6">

    <!-- Top Breadcrumb & Title Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                <a href="{{ route('dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">Dashboard</a>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200">Account Settings</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mt-1">Profile & Security</h1>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Active Account
            </span>
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Member of <strong class="text-slate-700 dark:text-slate-200">{{ $company->name ?? 'NSD Earth Movers & Construction Ltd' }}</strong>
            </span>
        </div>
    </div>

    <!-- Main Layout: Sidebar Summary + Tab Content -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Sidebar: Profile Card & Tab Navigation -->
        <div class="lg:col-span-4 space-y-6">

            <!-- Profile Summary Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 text-center shadow-xs">
                <!-- Avatar with Initials Fallback -->
                <div class="relative inline-block mx-auto mb-4">
                    <template x-if="avatarUrl">
                        <img :src="avatarUrl" alt="User Avatar" class="w-24 h-24 rounded-2xl object-cover ring-4 ring-blue-500/20 shadow-md">
                    </template>
                    <template x-if="!avatarUrl">
                        <div class="w-24 h-24 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-extrabold text-2xl flex items-center justify-center shadow-md ring-4 ring-blue-500/20">
                            <span x-text="userInitials"></span>
                        </div>
                    </template>
                </div>

                <h2 class="text-lg font-bold text-slate-900 dark:text-white truncate" x-text="userName"></h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $user->email }}</p>

                <!-- Role & Scope Pills -->
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    @if($role)
                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200/80 dark:border-blue-700/50">
                            {{ $role->name }}
                        </span>
                    @endif
                    <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        {{ $permittedScope }}
                    </span>
                </div>

                <!-- Navigation Tabs in Card -->
                <nav class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 space-y-1 text-left" aria-label="Profile Sections">
                    <button @click="activeTab = 'overview'"
                            type="button"
                            :class="activeTab === 'overview' ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>Account Overview</span>
                    </button>

                    <button @click="activeTab = 'edit'"
                            type="button"
                            :class="activeTab === 'edit' ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Profile</span>
                    </button>

                    <button @click="activeTab = 'security'"
                            type="button"
                            :class="activeTab === 'security' ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Security & Password</span>
                    </button>

                    <button @click="activeTab = 'preferences'"
                            type="button"
                            :class="activeTab === 'preferences' ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Display & Preferences</span>
                    </button>

                    @if($assignedProjects->isNotEmpty() || $assignedSites->isNotEmpty())
                        <button @click="activeTab = 'scope'"
                                type="button"
                                :class="activeTab === 'scope' ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span>Assigned Boundaries</span>
                        </button>
                    @endif
                </nav>
            </div>
        </div>

        <!-- Right Content: Interactive Panels -->
        <div class="lg:col-span-8">

            <!-- TAB 1: OVERVIEW -->
            <div x-show="activeTab === 'overview'" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Account Overview</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                            <span class="text-slate-400 dark:text-slate-500 block uppercase text-[10px] font-bold tracking-wider">Full Name</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm mt-0.5 block" x-text="userName"></span>
                        </div>
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                            <span class="text-slate-400 dark:text-slate-500 block uppercase text-[10px] font-bold tracking-wider">Email Address</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm mt-0.5 block">{{ $user->email }}</span>
                        </div>
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                            <span class="text-slate-400 dark:text-slate-500 block uppercase text-[10px] font-bold tracking-wider">Contact Phone</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm mt-0.5 block">{{ $user->phone ?? 'Not specified' }}</span>
                        </div>
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                            <span class="text-slate-400 dark:text-slate-500 block uppercase text-[10px] font-bold tracking-wider">Current Role</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400 text-sm mt-0.5 block">{{ $role->name ?? 'Standard User' }}</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold text-slate-900 dark:text-white block">Security & Access Tier</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Strict single-company tenancy strictly enforced for NSD Earth Movers & Construction Ltd.</span>
                        </div>
                        <button @click="activeTab = 'edit'" type="button" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                            Edit Profile Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: EDIT PROFILE (No-refresh AJAX form) -->
            <div x-show="activeTab === 'edit'" style="display: none;" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Profile Information</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Update your display name, contact phone, and avatar image.</p>
                        </div>
                    </div>

                    <!-- Inline Alert Banner -->
                    <div x-show="editMessage" x-transition class="mb-5 p-3 rounded-xl text-xs font-medium"
                         :class="editStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800'">
                        <span x-text="editMessage"></span>
                    </div>

                    <form @submit.prevent="submitProfileUpdate()" class="space-y-5">
                        @csrf
                        <div>
                            <label for="profile_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="profile_name" name="name" x-model="editForm.name" required
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none transition">
                            <span x-show="editErrors.name" x-text="editErrors.name?.[0]" class="text-[11px] text-rose-500 mt-1 block"></span>
                        </div>

                        <div>
                            <label for="profile_phone" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Phone Number
                            </label>
                            <input type="tel" id="profile_phone" name="phone" x-model="editForm.phone" placeholder="+91 98765 43210"
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none transition">
                            <span x-show="editErrors.phone" x-text="editErrors.phone?.[0]" class="text-[11px] text-rose-500 mt-1 block"></span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Email Address (Read-Only)
                            </label>
                            <input type="email" value="{{ $user->email }}" disabled
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-100 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 cursor-not-allowed">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 block">Email and company role assignments are managed by workspace administrators in Team Management.</span>
                        </div>

                        <div>
                            <label for="profile_avatar" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Profile Photo / Avatar
                            </label>
                            <input type="file" id="profile_avatar" @change="handleAvatarFileSelect($event)" accept="image/png,image/jpeg,image/webp"
                                   class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-950/60 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900 transition">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 block">Accepted formats: PNG, JPG, JPEG, WEBP. Maximum file size: 2MB.</span>
                            <span x-show="editErrors.avatar" x-text="editErrors.avatar?.[0]" class="text-[11px] text-rose-500 mt-1 block"></span>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <button type="submit" :disabled="editSubmitting"
                                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-2 transition">
                                <svg x-show="editSubmitting" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="editSubmitting ? 'Saving Changes...' : 'Save Profile Changes'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 3: ACCOUNT SECURITY (Change Password) -->
            <div x-show="activeTab === 'security'" style="display: none;" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Change Account Password</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ensure your account is protected with a secure password.</p>
                        </div>
                    </div>

                    <!-- Inline Alert Banner -->
                    <div x-show="passwordMessage" x-transition class="mb-5 p-3 rounded-xl text-xs font-medium"
                         :class="passwordStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800'">
                        <span x-text="passwordMessage"></span>
                    </div>

                    <form @submit.prevent="submitPasswordChange()" class="space-y-5">
                        @csrf
                        <div>
                            <label for="current_password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Current Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" id="current_password" x-model="passwordForm.current_password" required
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none transition">
                            <span x-show="passwordErrors.current_password" x-text="passwordErrors.current_password?.[0]" class="text-[11px] text-rose-500 mt-1 block"></span>
                        </div>

                        <div>
                            <label for="new_password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                New Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" id="new_password" x-model="passwordForm.password" required
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none transition">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 block">Minimum 8 characters. Must combine letters, numbers, and symbols.</span>
                            <span x-show="passwordErrors.password" x-text="passwordErrors.password?.[0]" class="text-[11px] text-rose-500 mt-1 block"></span>
                        </div>

                        <div>
                            <label for="new_password_confirmation" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Confirm New Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" id="new_password_confirmation" x-model="passwordForm.password_confirmation" required
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:outline-none transition">
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <button type="submit" :disabled="passwordSubmitting"
                                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-2 transition">
                                <svg x-show="passwordSubmitting" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="passwordSubmitting ? 'Updating...' : 'Update Password'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 4: PREFERENCES (Theme & Notifications) -->
            <div x-show="activeTab === 'preferences'" style="display: none;" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Display & Notification Preferences</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Customize your interface theme and real-time alert preferences.</p>
                        </div>
                    </div>

                    <!-- Inline Alert Banner -->
                    <div x-show="prefMessage" x-transition class="mb-5 p-3 rounded-xl text-xs font-medium"
                         :class="prefStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800'">
                        <span x-text="prefMessage"></span>
                    </div>

                    <form @submit.prevent="submitPreferences()" class="space-y-6">
                        @csrf
                        <!-- Theme Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                Application Theme
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                       :class="prefForm.theme === 'light' ? 'bg-blue-50/50 dark:bg-blue-950/30 border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                                    <input type="radio" name="theme" value="light" x-model="prefForm.theme" @change="onThemeChanged('light')" class="text-blue-600 focus:ring-blue-500">
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Light Mode</span>
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Clean white background</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                       :class="prefForm.theme === 'dark' ? 'bg-blue-50/50 dark:bg-blue-950/30 border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                                    <input type="radio" name="theme" value="dark" x-model="prefForm.theme" @change="onThemeChanged('dark')" class="text-blue-600 focus:ring-blue-500">
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Dark Mode</span>
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Sleek slate-950 interface</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                       :class="prefForm.theme === 'system' ? 'bg-blue-50/50 dark:bg-blue-950/30 border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                                    <input type="radio" name="theme" value="system" x-model="prefForm.theme" @change="onThemeChanged('system')" class="text-blue-600 focus:ring-blue-500">
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">System Default</span>
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Match OS preference</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Notification Preferences -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                Notifications & WebSockets
                            </label>

                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="notifications_browser" x-model="prefForm.notifications_browser"
                                       class="mt-1 rounded text-blue-600 focus:ring-blue-500">
                                <label for="notifications_browser" class="cursor-pointer">
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Real-Time In-App Alerts</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block">Receive live WebSocket notifications for approvals, stock alerts, and project status.</span>
                                </label>
                            </div>

                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="notifications_email" x-model="prefForm.notifications_email"
                                       class="mt-1 rounded text-blue-600 focus:ring-blue-500">
                                <label for="notifications_email" class="cursor-pointer">
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Email Digest & Summaries</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block">Receive email notifications for urgent authorization requests.</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <button type="submit" :disabled="prefSubmitting"
                                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-2 transition">
                                <svg x-show="prefSubmitting" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="prefSubmitting ? 'Saving...' : 'Save Preferences'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 5: SCOPE & ASSIGNMENTS -->
            @if($assignedProjects->isNotEmpty() || $assignedSites->isNotEmpty())
            <div x-show="activeTab === 'scope'" style="display: none;" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Assigned Operational Boundaries</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">These records define the boundaries where you have operational viewing, requisition, and management permissions.</p>

                    @if($assignedProjects->isNotEmpty())
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Assigned Projects ({{ $assignedProjects->count() }})</h4>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-100 dark:border-slate-800 rounded-xl overflow-hidden">
                                @foreach($assignedProjects as $p)
                                    <div class="p-3 flex items-center justify-between text-xs hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                        <div>
                                            <span class="font-bold text-slate-900 dark:text-white block">{{ $p->name }}</span>
                                            <span class="text-[10px] text-slate-500 dark:text-slate-400">Code: {{ $p->code }} • Status: {{ ucfirst(str_replace('_', ' ', $p->status)) }}</span>
                                        </div>
                                        <a href="{{ route('projects.show', $p) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                            Open Project →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($assignedSites->isNotEmpty())
                        <div class="space-y-3 mt-6">
                            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Assigned Construction Sites ({{ $assignedSites->count() }})</h4>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-100 dark:border-slate-800 rounded-xl overflow-hidden">
                                @foreach($assignedSites as $s)
                                    <div class="p-3 flex items-center justify-between text-xs hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                        <div>
                                            <span class="font-bold text-slate-900 dark:text-white block">{{ $s->name }}</span>
                                            <span class="text-[10px] text-slate-500 dark:text-slate-400">Project: {{ $s->project?->name ?? 'General' }}</span>
                                        </div>
                                        <a href="{{ route('sites.show', $s) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                            Open Site Log →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif

        </div>

    </div>

</div>

<script>
window.profileManager = function(initialConfig) {
    return {
        activeTab: initialConfig.activeTab || 'overview',
        avatarUrl: initialConfig.avatarUrl || null,
        userName: initialConfig.userName || '',
        userInitials: initialConfig.userInitials || 'U',

        // Edit Profile State
        editForm: {
            name: initialConfig.userName || '',
            phone: '{{ addslashes($user->phone ?? '') }}',
            avatarFile: null
        },
        editSubmitting: false,
        editMessage: '',
        editStatus: '',
        editErrors: {},

        // Password State
        passwordForm: {
            current_password: '',
            password: '',
            password_confirmation: ''
        },
        passwordSubmitting: false,
        passwordMessage: '',
        passwordStatus: '',
        passwordErrors: {},

        // Preferences State
        prefForm: {
            theme: initialConfig.currentTheme || 'system',
            notifications_email: initialConfig.emailNotifications,
            notifications_browser: initialConfig.browserNotifications
        },
        prefSubmitting: false,
        prefMessage: '',
        prefStatus: '',

        handleAvatarFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                this.editForm.avatarFile = file;
                const reader = new FileReader();
                reader.onload = (event) => {
                    this.avatarUrl = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        },

        async submitProfileUpdate() {
            this.editSubmitting = true;
            this.editMessage = '';
            this.editErrors = {};

            const formData = new FormData();
            formData.append('_method', 'PUT');
            formData.append('name', this.editForm.name);
            if (this.editForm.phone) formData.append('phone', this.editForm.phone);
            if (this.editForm.avatarFile) formData.append('avatar', this.editForm.avatarFile);

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch('{{ route('profile.update') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.editStatus = 'success';
                    this.editMessage = data.message || 'Profile updated successfully.';
                    this.userName = data.user.name;
                    if (data.user.avatar_url) this.avatarUrl = data.user.avatar_url;
                    if (data.user.initials) this.userInitials = data.user.initials;
                    window.dispatchEvent(new CustomEvent('user-profile-updated', { detail: data.user }));
                } else if (res.status === 422) {
                    this.editStatus = 'error';
                    this.editErrors = data.errors || {};
                    this.editMessage = data.message || 'Please check highlighted errors.';
                } else {
                    this.editStatus = 'error';
                    this.editMessage = data.message || 'Server error occurred.';
                }
            } catch (err) {
                this.editStatus = 'error';
                this.editMessage = 'Network error. Please try again.';
            } finally {
                this.editSubmitting = false;
            }
        },

        async submitPasswordChange() {
            this.passwordSubmitting = true;
            this.passwordMessage = '';
            this.passwordErrors = {};

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch('{{ route('profile.password.update') }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.passwordForm)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.passwordStatus = 'success';
                    this.passwordMessage = data.message || 'Password changed successfully.';
                    this.passwordForm.current_password = '';
                    this.passwordForm.password = '';
                    this.passwordForm.password_confirmation = '';
                } else if (res.status === 422) {
                    this.passwordStatus = 'error';
                    this.passwordErrors = data.errors || {};
                    this.passwordMessage = data.message || 'Validation error occurred.';
                } else {
                    this.passwordStatus = 'error';
                    this.passwordMessage = data.message || 'Server error occurred.';
                }
            } catch (err) {
                this.passwordStatus = 'error';
                this.passwordMessage = 'Network error. Please try again.';
            } finally {
                this.passwordSubmitting = false;
            }
        },

        onThemeChanged(mode) {
            if (mode === 'dark') {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else if (mode === 'light') {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                localStorage.removeItem('theme');
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        },

        async submitPreferences() {
            this.prefSubmitting = true;
            this.prefMessage = '';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch('{{ route('profile.preferences.update') }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.prefForm)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.prefStatus = 'success';
                    this.prefMessage = data.message || 'Preferences saved.';
                } else {
                    this.prefStatus = 'error';
                    this.prefMessage = data.message || 'Failed to save preferences.';
                }
            } catch (err) {
                this.prefStatus = 'error';
                this.prefMessage = 'Network error. Please try again.';
            } finally {
                this.prefSubmitting = false;
            }
        }
    };
};
</script>
@endsection
