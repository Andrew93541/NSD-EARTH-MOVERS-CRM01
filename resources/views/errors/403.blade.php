<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 Forbidden — NSDCRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f8fafc] text-gray-800 antialiased font-sans min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center space-y-6 bg-white p-8 rounded-2xl border border-gray-100 shadow-xl shadow-blue-900/5">
        <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mx-auto text-2xl font-bold ring-8 ring-amber-50/50">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <div class="space-y-2">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-800">403 Access Restricted</span>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Authorization Required</h1>
            <p class="text-sm text-gray-500 leading-relaxed">
                {{ $exception->getMessage() ?: 'You do not have sufficient role privileges or company workspace permissions to view this record.' }}
            </p>
        </div>
        <div class="pt-2 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition shadow-sm">
                Return to Dashboard
            </a>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-xl transition border border-gray-200 cursor-pointer">
                    Sign Out
                </button>
            </form>
        </div>
    </div>
</body>
</html>
