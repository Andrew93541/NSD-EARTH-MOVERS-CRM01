<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>{{ $title ?? 'BuildFlow - Construction CRM' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#dbe2ea] p-4 flex items-center justify-center">
    <div class="w-full max-w-md bg-[#f7f9fc] rounded-[32px] shadow-canvas border border-white/80 p-8">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-700 via-blue-600 to-sky-400 flex items-center justify-center shadow-md shadow-blue-500/20 text-white font-bold">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                    <path d="M2 17L12 22L22 17V12L12 17L2 12V17Z" opacity="0.85"></path>
                </svg>
            </div>
            <div>
                <span class="text-xl font-bold tracking-tight text-gray-900">BuildFlow</span>
                <span class="block text-[10px] uppercase font-semibold text-blue-600 tracking-wider">Construction CRM</span>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium">
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
