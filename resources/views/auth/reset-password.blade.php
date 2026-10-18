<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Set New Password</h1>
        <p class="text-sm text-gray-500 mt-1">Please enter your account email and specify a strong new password.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Corporate Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">New Password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password"
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
            <p class="text-[11px] text-gray-400 mt-1">Minimum 8 characters with a mix of letters and numbers.</p>
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Confirm New Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-md shadow-blue-500/25 transition duration-200 cursor-pointer">
            Reset Password
        </button>
    </form>

    <div class="mt-6 text-center">
        <a href="{{ route('login') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">← Back to Login</a>
    </div>
</x-guest-layout>
