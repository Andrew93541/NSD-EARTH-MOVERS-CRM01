<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Create Company Workspace</h1>
        <p class="text-sm text-gray-500 mt-1">Set up a new construction or contracting entity.</p>
    </div>

    <form method="POST" action="{{ route('workspace.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Company Trading Name *</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. NSD Earth Movers & Construction Ltd" required autofocus
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
        </div>

        <div>
            <label for="legal_name" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Registered Legal Entity Name</label>
            <input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name') }}" placeholder="e.g. NSD Earth Movers & Construction Private Limited"
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="gstin" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">GSTIN</label>
                <input type="text" id="gstin" name="gstin" value="{{ old('gstin') }}" placeholder="27AAACA1234A1Z5" maxlength="15"
                       class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
            </div>
            <div>
                <label for="pan" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">PAN Number</label>
                <input type="text" id="pan" name="pan" value="{{ old('pan') }}" placeholder="AAACA1234A" maxlength="10"
                       class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-mono uppercase focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition"/>
            </div>
        </div>

        <div>
            <label for="address" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Registered Head Office</label>
            <textarea id="address" name="address" rows="2" placeholder="Suite, Street, City, State, PIN"
                      class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition">{{ old('address') }}</textarea>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-700 via-blue-600 to-sky-600 hover:from-blue-800 hover:to-blue-700 text-white font-semibold rounded-xl text-sm shadow-md shadow-blue-500/25 transition duration-200 cursor-pointer">
            Initialize Workspace
        </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200/80 flex items-center justify-between">
        <a href="{{ route('workspace.select') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">← Back to Workspace Selection</a>
    </div>
</x-guest-layout>
