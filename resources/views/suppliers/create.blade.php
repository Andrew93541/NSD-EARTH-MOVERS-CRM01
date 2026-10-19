@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div>
        <a href="{{ route('suppliers.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1 mb-2">
            ← Back to Material Suppliers
        </a>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Register Material Supplier</h1>
        <p class="text-xs text-slate-500 mt-1">Add a vendor supplying cement, steel, aggregates, fuel, machinery spare parts, or hardware.</p>
    </div>

    <!-- Supplier Form -->
    <div class="soft-card p-6">
        <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-6">
            @csrf

            <!-- Basic Identification & Classification -->
            <div>
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">1. Supplier Details & Category</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Supplier / Firm Name <span class="text-rose-500">*</span></label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Registered Vendor / Material Supplier Name"
                               required
                               class="w-full py-2 px-3 text-xs border @error('name') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('name')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Vendor Classification <span class="text-rose-500">*</span></label>
                        <select name="vendor_type" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="supplier" {{ old('vendor_type', 'supplier') === 'supplier' ? 'selected' : '' }}>Material Supplier Only (Cement, Steel, Fuel, etc.)</option>
                            <option value="both" {{ old('vendor_type') === 'both' ? 'selected' : '' }}>Dual Role: Material Supplier & Subcontractor</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Pure labor subcontractors should be registered under Subcontractor Master.</p>
                        @error('vendor_type')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Key Sales / Accounts Contact</label>
                        <input type="text"
                               name="contact_person"
                               value="{{ old('contact_person') }}"
                               placeholder="e.g. Anand Deshmukh, Regional Sales"
                               class="w-full py-2 px-3 text-xs border @error('contact_person') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('contact_person')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Supplier Status <span class="text-rose-500">*</span></label>
                        <select name="status" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active (Eligible for POs & Deliveries)</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive / Blocked</option>
                        </select>
                        @error('status')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Contact & Statutory Tax IDs -->
            <div class="pt-4 border-t border-slate-100">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">2. Communications & Statutory IDs</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                        <input type="email"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="orders@supplier.in"
                               class="w-full py-2 px-3 text-xs border @error('email') border-rose-300 bg-rose-50/50 @else border-slate-200 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('email')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Phone / Mobile</label>
                        <input type="text"
                               name="phone"
                               value="{{ old('phone') }}"
                               placeholder="+91 98900 XXXXX"
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

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Supplier Warehouse / Plant Address</label>
                        <textarea name="address"
                                  rows="2"
                                  placeholder="Plant or dispatch godown address..."
                                  class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('address') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Commercial Terms & Bank Settlement -->
            <div class="pt-4 border-t border-slate-100">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">3. Payment Terms & Bank Account Details</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Credit / Payment Terms <span class="text-rose-500">*</span></label>
                        <select name="payment_terms" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="immediate" {{ old('payment_terms') === 'immediate' ? 'selected' : '' }}>Immediate / Advance on Delivery</option>
                            <option value="15_days" {{ old('payment_terms') === '15_days' ? 'selected' : '' }}>Net 15 Days</option>
                            <option value="30_days" {{ old('payment_terms', '30_days') === '30_days' ? 'selected' : '' }}>Net 30 Days (Standard)</option>
                            <option value="45_days" {{ old('payment_terms') === '45_days' ? 'selected' : '' }}>Net 45 Days</option>
                        </select>
                        @error('payment_terms')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Bank Name</label>
                        <input type="text"
                               name="bank_name"
                               value="{{ old('bank_name') }}"
                               placeholder="e.g. State Bank of India"
                               class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Bank Account Number</label>
                        <input type="text"
                               name="bank_account_number"
                               value="{{ old('bank_account_number') }}"
                               placeholder="e.g. 30894561234"
                               class="w-full py-2 px-3 text-xs font-mono border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">IFSC Code</label>
                        <input type="text"
                               name="ifsc_code"
                               value="{{ old('ifsc_code') }}"
                               placeholder="e.g. SBIN0001234"
                               class="w-full py-2 px-3 text-xs font-mono border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                </div>
            </div>

            <!-- Submit buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('suppliers.index') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-semibold hover:bg-blue-700 shadow-xs transition">
                    Save Material Supplier
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
