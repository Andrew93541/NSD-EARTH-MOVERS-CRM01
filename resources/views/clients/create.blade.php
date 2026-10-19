@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div>
        <a href="{{ route('clients.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1 mb-2">
            ← Back to Client Directory
        </a>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Register New Client</h1>
        <p class="text-xs text-slate-500 mt-1">Add a new client organization, employer authority, or private developer to your company portfolio.</p>
    </div>

    <!-- Client Form -->
    <div class="soft-card p-6">
        <form method="POST" action="{{ route('clients.store') }}" class="space-y-6">
            @csrf

            <!-- Basic Identification -->
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">1. Client Entity Details</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Client / Entity Name <span class="text-rose-500">*</span></label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Maharashtra Metro Rail Corp"
                               required
                               class="w-full py-2 px-3 text-xs border @error('name') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('name')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Company / Group Name (Optional)</label>
                        <input type="text"
                               name="company_name"
                               value="{{ old('company_name') }}"
                               placeholder="e.g. MahaMetro Ltd"
                               class="w-full py-2 px-3 text-xs border @error('company_name') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('company_name')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Key Contact Person</label>
                        <input type="text"
                               name="contact_person"
                               value="{{ old('contact_person') }}"
                               placeholder="e.g. Er. Rajiv Sharma, Chief Engineer"
                               class="w-full py-2 px-3 text-xs border @error('contact_person') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('contact_person')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Account Status <span class="text-rose-500">*</span></label>
                        <select name="status" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active (Eligible for Contracts & Billing)</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive / Closed</option>
                        </select>
                        @error('status')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Contact & Tax Identification -->
            <div class="pt-4 border-t border-slate-100">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">2. Communications & Statutory IDs</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Official Email Address</label>
                        <input type="email"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="billing@clientcorp.in"
                               class="w-full py-2 px-3 text-xs border @error('email') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('email')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Telephone / Mobile</label>
                        <input type="text"
                               name="phone"
                               value="{{ old('phone') }}"
                               placeholder="+91 98230 XXXXX"
                               class="w-full py-2 px-3 text-xs border @error('phone') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('phone')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">GSTIN (15 Digits)</label>
                        <input type="text"
                               name="gstin"
                               value="{{ old('gstin') }}"
                               placeholder="27AAAAA0000A1Z5"
                               class="w-full py-2 px-3 text-xs font-mono border @error('gstin') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('gstin')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Permanent Account Number (PAN)</label>
                        <input type="text"
                               name="pan"
                               value="{{ old('pan') }}"
                               placeholder="AAAAA0000A"
                               class="w-full py-2 px-3 text-xs font-mono border @error('pan') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('pan')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Address & Location -->
            <div class="pt-4 border-t border-slate-100">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">3. Address & Regional Jurisdiction</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">City</label>
                        <input type="text"
                               name="city"
                               value="{{ old('city') }}"
                               placeholder="e.g. Pune"
                               class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">State / Province</label>
                        <input type="text"
                               name="state"
                               value="{{ old('state') }}"
                               placeholder="e.g. Maharashtra"
                               class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Official Billing Address</label>
                        <textarea name="billing_address"
                                  rows="2"
                                  placeholder="Corporate headquarters or invoicing office address..."
                                  class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('billing_address') }}</textarea>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Shipping / Site Correspondence Address</label>
                        <textarea name="shipping_address"
                                  rows="2"
                                  placeholder="Leave blank if identical to billing address..."
                                  class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('shipping_address') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Submit buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('clients.index') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-semibold hover:bg-blue-700 shadow-xs transition">
                    Save Client Record
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
