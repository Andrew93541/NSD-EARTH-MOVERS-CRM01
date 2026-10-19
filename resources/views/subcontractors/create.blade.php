@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('subcontractors.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Subcontractors</a>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">Register New Subcontractor</h2>
            <p class="text-xs text-gray-500">Capture contracting party details, statutory tax classification, and bank remit credentials.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-2xl text-xs space-y-1">
            <span class="font-bold">Please correct the following errors:</span>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('subcontractors.store') }}" class="space-y-6">
        @csrf

        <div class="soft-card p-6 space-y-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Business Identity & Contact</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Legal / Trade Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. Specialised Earthworks & Civils Pvt Ltd"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Subcontractor Code</label>
                    <input type="text" name="subcontractor_code" value="{{ old('subcontractor_code', $suggestedCode) }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Entity Classification *</label>
                    <select name="vendor_type" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="subcontractor" {{ old('vendor_type') === 'subcontractor' ? 'selected' : '' }}>Subcontractor Only (Labour / Plant Work)</option>
                        <option value="both" {{ old('vendor_type') === 'both' ? 'selected' : '' }}>Dual Vendor (Supplier & Subcontractor)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Primary Contact Person</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. Rajesh Sharma (Managing Partner)"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="+91 98765 43210"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="billing@apexearthworks.in"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Status *</label>
                    <select name="status" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="active" {{ old('status') !== 'inactive' ? 'selected' : '' }}>Active (Eligible for WOs)</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive / Suspended</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Registered Business Address</label>
                    <textarea name="address" rows="2"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="Plot 42, Heavy Industrial Area, Sector 58...">{{ old('address') }}</textarea>
                </div>
            </div>
        </div>

        <div class="soft-card p-6 space-y-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Tax, Statutory & Payment Terms</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">GSTIN Number</label>
                    <input type="text" name="gstin" value="{{ old('gstin') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono uppercase focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="07AAAAA0000A1Z5"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">PAN Card Number</label>
                    <input type="text" name="pan" value="{{ old('pan') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono uppercase focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="ABCDE1234F"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Applicable TDS Deduction Section *</label>
                    <select name="tds_section" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="194C_company_2%" {{ old('tds_section') === '194C_company_2%' ? 'selected' : '' }}>Section 194C - Company / Corporate (2% TDS)</option>
                        <option value="194C_individual_1%" {{ old('tds_section') === '194C_individual_1%' ? 'selected' : '' }}>Section 194C - Individual / HUF / Proprietor (1% TDS)</option>
                        <option value="194J_professional_10%" {{ old('tds_section') === '194J_professional_10%' ? 'selected' : '' }}>Section 194J - Technical & Architectural (10% TDS)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Contractor Trade License / Labour License</label>
                    <input type="text" name="trade_license_no" value="{{ old('trade_license_no') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="LIC-2026-DL-8821"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Contractual Payment Terms *</label>
                    <select name="payment_terms" class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5" required>
                        <option value="against_certified_bill" {{ old('payment_terms') === 'against_certified_bill' ? 'selected' : '' }}>Against Certified RA Bill</option>
                        <option value="15_days" {{ old('payment_terms') === '15_days' ? 'selected' : '' }}>Net 15 Days after Certification</option>
                        <option value="30_days" {{ old('payment_terms', '30_days') === '30_days' ? 'selected' : '' }}>Net 30 Days after Certification</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="soft-card p-6 space-y-5">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Banking & Remittance Coordinates (Restricted Access)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Bank Name</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="State Bank of India"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Bank Account Number</label>
                    <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="987654321012"/>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">IFSC Code</label>
                    <input type="text" name="ifsc_code" value="{{ old('ifsc_code') }}"
                        class="w-full text-xs rounded-xl border-gray-200 px-3 py-2.5 font-mono uppercase focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="SBIN0001234"/>
                </div>
            </div>
        </div>

        <div class="soft-card p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Qualified Work Categories</h3>
            <p class="text-xs text-gray-500">Select all specialized construction scopes this subcontractor is vetted to perform:</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="work_categories[]" value="earthwork" class="rounded text-blue-600 focus:ring-blue-500"
                        {{ is_array(old('work_categories')) && in_array('earthwork', old('work_categories')) ? 'checked' : 'checked' }}>
                    <span class="font-medium text-gray-800">Earth Excavation & Embankment</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="work_categories[]" value="civil" class="rounded text-blue-600 focus:ring-blue-500"
                        {{ is_array(old('work_categories')) && in_array('civil', old('work_categories')) ? 'checked' : '' }}>
                    <span class="font-medium text-gray-800">Civil & Brick Masonry</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="work_categories[]" value="concreting" class="rounded text-blue-600 focus:ring-blue-500"
                        {{ is_array(old('work_categories')) && in_array('concreting', old('work_categories')) ? 'checked' : '' }}>
                    <span class="font-medium text-gray-800">RCC Concreting & Shuttering</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="work_categories[]" value="road_works" class="rounded text-blue-600 focus:ring-blue-500"
                        {{ is_array(old('work_categories')) && in_array('road_works', old('work_categories')) ? 'checked' : '' }}>
                    <span class="font-medium text-gray-800">Road Paving & Asphalting</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="work_categories[]" value="drainage" class="rounded text-blue-600 focus:ring-blue-500"
                        {{ is_array(old('work_categories')) && in_array('drainage', old('work_categories')) ? 'checked' : '' }}>
                    <span class="font-medium text-gray-800">Drainage & Culvert Works</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="work_categories[]" value="structural" class="rounded text-blue-600 focus:ring-blue-500"
                        {{ is_array(old('work_categories')) && in_array('structural', old('work_categories')) ? 'checked' : '' }}>
                    <span class="font-medium text-gray-800">Structural Steel Fabrication</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('subcontractors.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/20 transition">Register Subcontractor</button>
        </div>
    </form>
</div>
@endsection
