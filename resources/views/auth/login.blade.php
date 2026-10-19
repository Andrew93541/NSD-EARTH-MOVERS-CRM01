<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Sign in to your workspace</h1>
        <p class="text-sm text-gray-500 mt-1">Access projects, muster rolls, and financial ledgers.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Corporate Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="name@nsdinfra.com" required autofocus
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Forgot password?</a>
            </div>
            <input type="password" id="password" name="password" placeholder="••••••••••••" required
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
        </div>

        <div class="flex items-center">
            <input type="checkbox" id="remember" name="remember" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"/>
            <label for="remember" class="ml-2 text-sm text-gray-600">Remember this workstation for 30 days</label>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-700 via-blue-600 to-sky-600 hover:from-blue-800 hover:to-blue-700 text-white font-semibold rounded-xl text-sm shadow-md shadow-blue-500/25 transition duration-200 cursor-pointer">
            Sign In to NSDCRM
        </button>
    </form>
</x-guest-layout>
