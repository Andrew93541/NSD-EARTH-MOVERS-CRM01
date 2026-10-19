<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>{{ $title ?? 'NSDCRM — NSD Earth Movers & Construction Ltd' }}</title>
    <script>
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#dbe2ea] dark:bg-slate-950 text-slate-800 dark:text-slate-100 p-4 flex items-center justify-center transition-colors duration-200">
    <div class="w-full max-w-md bg-[#f7f9fc] dark:bg-slate-900 rounded-[32px] shadow-canvas border border-white/80 dark:border-slate-800 p-6 sm:p-8">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-700 via-blue-600 to-sky-400 flex items-center justify-center shadow-md shadow-blue-500/20 text-white font-bold">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                        <path d="M2 17L12 22L22 17V12L12 17L2 12V17Z" opacity="0.85"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">NSDCRM</span>
                    <span class="block text-[10px] uppercase font-semibold text-blue-600 dark:text-blue-400 tracking-wider">NSD Earth Movers & Construction Ltd</span>
                </div>
            </div>

            <!-- Dark Mode Switcher -->
            <div x-data="darkModeManager()">
                <button @click="toggle()"
                        type="button"
                        class="p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white shadow-2xs transition"
                        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                        :aria-label="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                    <svg x-show="isDark" class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg x-show="!isDark" class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 rounded-xl text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-xl text-sm font-medium">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </div>
</body>
</html>
