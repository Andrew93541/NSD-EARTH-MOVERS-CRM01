<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'BuildFlow - Construction CRM' }}</title>
    <script>
        window.buildFlowConfig = {
            userId: {{ Auth::id() ?? 'null' }},
            companyId: {{ Auth::user()?->company_id ?? session('current_company_id') ?? Auth::user()?->currentCompany()?->id ?? 'null' }},
            reverbKey: '{{ config('reverb.apps.apps.0.key', env('REVERB_APP_KEY')) }}'
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased font-sans">

<div x-data="{ mobileNavOpen: false }"
     @keydown.escape.window="mobileNavOpen = false"
     class="min-h-screen flex flex-col">

    <!-- Mobile Drawer Overlay / Backdrop -->
    <div x-show="mobileNavOpen"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileNavOpen = false"
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 lg:hidden"
         style="display: none;"
         aria-hidden="true">
    </div>

    <!-- Mobile Navigation Drawer -->
    <div x-show="mobileNavOpen"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 w-72 max-w-[85vw] bg-white z-50 shadow-2xl flex flex-col lg:hidden"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-label="Mobile Navigation">

        <!-- Mobile Drawer Header -->
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-700 via-blue-600 to-sky-400 flex items-center justify-center text-white shadow-xs">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                        <path d="M2 17L12 22L22 17V12L12 17L2 12V17Z" opacity="0.85"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-base font-bold tracking-tight text-slate-900">BuildFlow</span>
                    <span class="block text-[9px] uppercase font-bold text-blue-600 tracking-wider">CONSTRUCTION CRM</span>
                </div>
            </div>
            <button @click="mobileNavOpen = false"
                    type="button"
                    class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-blue-600"
                    aria-label="Close navigation">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer Scrollable Links -->
        <div class="flex-1 overflow-y-auto p-4">
            @include('layouts.navigation-links')
        </div>

        <!-- Mobile Drawer Footer (User & Logout) -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/60 space-y-2">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-600 text-white font-bold text-xs flex items-center justify-center">
                    {{ substr(Auth::user()?->name ?? 'U', 0, 2) }}
                </div>
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-semibold text-slate-900 block truncate">{{ Auth::user()?->name ?? 'User' }}</span>
                    <span class="text-[10px] text-slate-500 block truncate">{{ $currentCompany->name ?? 'Workspace' }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="pt-2">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>Log Out</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Desktop Sidebar (Sticky Full Height) -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 xl:w-72 lg:fixed lg:inset-y-0 bg-white border-r border-slate-200/90 z-30"
           data-purpose="sidebar-navigation">

        <!-- Brand Logo Header -->
        <div class="p-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-700 via-blue-600 to-sky-400 flex items-center justify-center shadow-xs text-white">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                    <path d="M2 17L12 22L22 17V12L12 17L2 12V17Z" opacity="0.85"></path>
                </svg>
            </div>
            <div>
                <span class="text-lg font-bold tracking-tight text-slate-900">BuildFlow</span>
                <span class="block text-[10px] uppercase font-bold text-blue-600 tracking-wider">CONSTRUCTION CRM</span>
            </div>
        </div>

        <!-- Scrollable Navigation Hubs -->
        <div class="flex-1 overflow-y-auto p-4 space-y-6">
            @include('layouts.navigation-links')
        </div>

        <!-- Desktop Sidebar Footer -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 space-y-2">
            <div class="flex items-center gap-3 px-1">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                    {{ substr(Auth::user()?->name ?? 'AD', 0, 2) }}
                </div>
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-semibold text-slate-900 block truncate">{{ Auth::user()?->name ?? 'User' }}</span>
                    <span class="text-[10px] text-slate-500 block truncate">{{ $currentCompany->name ?? 'NSD Construction' }}</span>
                </div>
            </div>

            <div class="pt-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition"
                            title="Sign out of system">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Log Out</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area (Offset on Desktop) -->
    <div class="lg:pl-64 xl:pl-72 flex flex-col flex-1 min-w-0">

        <!-- Top Navigation Bar -->
        <header class="sticky top-0 bg-white/95 backdrop-blur-sm border-b border-slate-200/80 px-4 sm:px-6 py-3 z-20 flex items-center justify-between gap-4">

            <!-- Left: Mobile Toggle & Page Title / Org -->
            <div class="flex items-center gap-3 min-w-0">
                <!-- Mobile Navigation Toggle Button -->
                <button @click="mobileNavOpen = true"
                        type="button"
                        class="lg:hidden p-2 -ml-2 text-slate-600 hover:text-slate-900 rounded-xl hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600 transition"
                        aria-label="Open sidebar navigation">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hidden sm:block">
                        {{ strtoupper(now()->format('l, d F, Y')) }}
                    </span>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 truncate">
                            {{ $currentCompany->name ?? 'NSD Earth Movers & Construction' }}
                        </h1>
                        @if(isset($currentRole))
                            <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200/80">
                                {{ $currentRole->name }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Search, Quick Actions, Notification Bell, User -->
            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">

                <!-- Search bar -->
                <form action="{{ route('search') }}" method="GET" class="relative hidden md:block">
                    <input type="text" name="q" placeholder="Search projects, materials..." value="{{ request('q') }}"
                           class="w-44 lg:w-56 pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white transition"/>
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </form>

                <!-- New Project Quick Action Button -->
                <a href="{{ route('projects.create') }}"
                   class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-xs transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">New Project</span>
                </a>

                <!-- Notification Center Dropdown -->
                <div x-data="notificationCenter()"
                     class="relative"
                     @click.outside="open = false"
                     @keydown.escape.window="open = false"
                     id="header-notification-center">

                    <!-- Bell Trigger Button -->
                    <button @click="open = !open"
                            type="button"
                            id="notification-bell-btn"
                            class="relative w-8 h-8 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 hover:text-slate-900 transition focus:outline-none focus:ring-2 focus:ring-orange-500"
                            aria-label="View notifications">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>

                        <!-- Dynamic Unread Badge -->
                        <span x-show="unreadCount > 0"
                              x-transition
                              x-text="unreadCount > 9 ? '9+' : unreadCount"
                              id="notification-badge-count"
                              class="absolute -top-1 -right-1 px-1 min-w-[16px] h-[16px] text-[9px] font-black leading-none flex items-center justify-center rounded-full bg-orange-600 text-white shadow-xs ring-2 ring-white">
                        </span>

                        <!-- WebSocket Status Indicator Dot -->
                        <span class="absolute bottom-0.5 right-0.5 w-2 h-2 rounded-full ring-1 ring-white"
                              id="websocket-status-dot"
                              :class="{
                                  'bg-emerald-500': connectionState === 'connected',
                                  'bg-amber-500 animate-pulse': connectionState === 'connecting',
                                  'bg-slate-400': connectionState === 'disconnected' || connectionState === 'offline'
                              }">
                        </span>
                    </button>

                    <!-- Dropdown Panel -->
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                         id="notification-dropdown-menu"
                         class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden divide-y divide-slate-100"
                         style="display: none;">

                        <div class="px-4 py-2.5 bg-slate-50 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Notifications</span>
                            <button x-show="unreadCount > 0"
                                    @click="markAllAsRead()"
                                    type="button"
                                    class="text-[11px] font-semibold text-orange-600 hover:text-orange-700 cursor-pointer">
                                Mark all read
                            </button>
                        </div>

                        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100" id="notification-feed-list">
                            <template x-for="item in notifications" :key="item.id">
                                <div @click="openRecord(item)"
                                     class="p-3 transition cursor-pointer flex items-start gap-3"
                                     :class="!item.is_read ? 'bg-orange-50/30 hover:bg-orange-50/60' : 'hover:bg-slate-50'">
                                    <div class="mt-1 flex-shrink-0">
                                        <span class="w-2 h-2 rounded-full block"
                                              :class="{
                                                  'bg-emerald-500': item.type === 'success',
                                                  'bg-rose-500': item.type === 'danger',
                                                  'bg-amber-500': item.type === 'warning',
                                                  'bg-blue-500': item.type === 'info'
                                              }"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="text-xs truncate font-semibold text-slate-800" x-text="item.title"></span>
                                            <span class="text-[10px] text-slate-400 whitespace-nowrap" x-text="item.created_at_human"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 line-clamp-2 mt-0.5 leading-snug" x-text="item.message"></p>
                                    </div>
                                </div>
                            </template>

                            <div x-show="notifications.length === 0" class="py-6 text-center px-4">
                                <div class="text-xs font-medium text-slate-600">No unread notifications</div>
                            </div>
                        </div>

                        <div class="px-4 py-2 bg-slate-50 text-center">
                            <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-slate-700 hover:text-blue-600 transition">
                                Open Notification Centre →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Badge -->
                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                    {{ substr(Auth::user()?->name ?? 'AD', 0, 2) }}
                </div>
            </div>
        </header>

        <!-- Main Viewport Content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
            <!-- Flash Notifications -->
            @if (session('success'))
                <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-medium">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
            {{ $slot ?? '' }}
        </main>

        <!-- Footer -->
        <footer class="py-3 px-6 border-t border-slate-200/60 text-center text-[11px] text-slate-400">
            <span>BuildFlow Construction CRM & ERP • NSD Earth Movers & Construction Ltd</span>
        </footer>

    </div>

</div>

</body>
</html>
