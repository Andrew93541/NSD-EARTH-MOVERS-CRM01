<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>{{ $title ?? 'BuildFlow - Construction CRM' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen p-2 sm:p-4 lg:p-6 flex items-center justify-center bg-[#dbe2ea]">

<!-- MainAppCanvas: Matches Stitch Master Screen Frame -->
<div class="w-full max-w-[1720px] bg-[#f7f9fc] rounded-[36px] shadow-canvas border border-white/80 p-4 lg:p-6 flex flex-col xl:flex-row gap-6 overflow-hidden min-h-[960px]">

    <!-- Sidebar Navigation -->
    <aside class="w-full xl:w-[270px] flex-shrink-0 flex flex-col justify-between" data-purpose="sidebar-navigation">
        <div class="space-y-6">
            <!-- Brand Logo -->
            <div class="flex items-center gap-3 px-3 pt-2">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-700 via-blue-600 to-sky-400 flex items-center justify-center shadow-md shadow-blue-500/20 text-white font-bold">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                        <path d="M2 17L12 22L22 17V12L12 17L2 12V17Z" opacity="0.85"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-gray-900">BuildFlow</span>
                    <span class="block text-[10px] uppercase font-bold text-blue-600 tracking-wider">CONSTRUCTION CRM</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="space-y-5">
                <div>
                    <span class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-2">MAIN MENU</span>
                    <div class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('dashboard', 'home') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('projects.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('projects.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>Projects</span>
                        </a>

                        <a href="{{ route('sites.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('sites.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Site Operations</span>
                        </a>

                        <a href="{{ route('inventory.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('inventory.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <span>Materials & Stock</span>
                        </a>

                        <a href="{{ route('attendance.muster') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('attendance.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span>Labor Muster Roll</span>
                        </a>

                        <a href="{{ route('employees.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('employees.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>Staff & Operators</span>
                        </a>

                        <a href="{{ route('payroll.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('payroll.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 4h5m-5 4h5M7 4h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                            <span>Payroll & Wages</span>
                        </a>
                    </div>
                </div>

                <div>
                    <span class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-2">MANAGEMENT & FINANCE</span>
                    <div class="space-y-1">
                        <a href="{{ route('billing.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('billing.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/></svg>
                            <span>Client RA Billing</span>
                        </a>

                        <a href="{{ route('procurement.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('procurement.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span>Procurement & POs</span>
                        </a>

                        <a href="{{ route('expenses.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('expenses.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <span>Site Expenses</span>
                        </a>

                        <a href="{{ route('loans.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('loans.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Equipment Loans & EMI</span>
                        </a>

                        <a href="{{ route('accounting.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('accounting.*') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Double-Entry Ledger</span>
                        </a>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Bottom Actions (Workspace Switcher & Log Out) -->
        <div class="mt-6 pt-4 border-t border-gray-100 space-y-1">
            <a href="{{ route('workspace.select') }}" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-gray-600 hover:text-blue-600 hover:bg-gray-100/70 rounded-xl transition">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                <span>Switch Workspace</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50/60 rounded-xl transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>Log Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Canvas -->
    <main class="flex-1 flex flex-col min-w-0">
        <!-- Top Navigation Bar -->
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-200/60">
            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block">
                    {{ strtoupper(now()->format('l, d F, Y')) }}
                </span>
                <div class="flex items-center gap-3 mt-1">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">
                        Welcome back, {{ Auth::user()?->name ?? 'Team Member' }}
                    </h1>
                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-lg border border-blue-200/80">
                        {{ $currentCompany->name ?? 'NSD Earth Movers' }}
                    </span>
                </div>
            </div>

            <!-- Top Actions & Profile -->
            <div class="flex items-center gap-3">
                <!-- Search bar -->
                <form action="{{ route('search') }}" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Search projects, materials..." value="{{ request('q') }}"
                           class="w-48 sm:w-64 pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </form>

                <!-- New Project Button -->
                <a href="{{ route('projects.create') }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Project</span>
                </a>

                <!-- User Avatar -->
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-400 text-white font-bold text-xs flex items-center justify-center shadow-sm">
                    {{ substr(Auth::user()?->name ?? 'AD', 0, 2) }}
                </div>
            </div>
        </header>

        <!-- Flash Notifications -->
        @if (session('success'))
            <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-2xl text-xs font-semibold">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Main Workspace Viewport -->
        <div class="mt-6 flex-1">
            @yield('content')
        </div>
    </main>

</div>

</body>
</html>
