@extends('layouts.app')

@section('content')
    <div class="space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ $company->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Configured Entity
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Official corporate identity, statutory tax registrations, and operational headquarters.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-medium">Single-Company CRM</span>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs flex items-center justify-between">
                <div class="flex items-center gap-2 font-medium">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <!-- Operational Statistics Row -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4">
            <div class="soft-card p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Active Projects</span>
                <div class="text-xl font-extrabold text-slate-900 mt-1">{{ $stats['total_projects'] }}</div>
                <span class="text-[11px] text-slate-500">Under execution</span>
            </div>
            <div class="soft-card p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Sites & Yards</span>
                <div class="text-xl font-extrabold text-blue-600 mt-1">{{ $stats['total_sites'] }}</div>
                <span class="text-[11px] text-slate-500">Active locations</span>
            </div>
            <div class="soft-card p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Workforce</span>
                <div class="text-xl font-extrabold text-slate-900 mt-1">{{ $stats['total_employees'] }}</div>
                <span class="text-[11px] text-slate-500">Staff & labor</span>
            </div>
            <div class="soft-card p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Clients</span>
                <div class="text-xl font-extrabold text-emerald-600 mt-1">{{ $stats['total_clients'] }}</div>
                <span class="text-[11px] text-slate-500">Contract clients</span>
            </div>
            <div class="soft-card p-4 col-span-2 sm:col-span-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Vendors & Suppliers</span>
                <div class="text-xl font-extrabold text-slate-900 mt-1">{{ $stats['total_suppliers'] }}</div>
                <span class="text-[11px] text-slate-500">Material vendors</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Cols: Corporate Details / Form -->
            <div class="lg:col-span-2 space-y-6">
                <div class="soft-card p-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Statutory & Contact Information</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Parameters applied across invoices, purchase orders, and payment vouchers.</p>
                        </div>
                        @if($canEdit)
                            <span class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg">Admin Editable</span>
                        @else
                            <span class="text-[11px] font-medium text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg">Read Only Reference</span>
                        @endif
                    </div>

                    @if($canEdit)
                    <form method="POST" action="{{ route('company.profile.update') }}" class="space-y-5">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Company Display Name</label>
                                <input type="text" value="{{ $company->name }}" disabled
                                       class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-500 cursor-not-allowed"/>
                                <span class="text-[10px] text-slate-400 mt-1 block">Primary system brand name</span>
                            </div>

                            <div>
                                <label for="legal_name" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Legal Registered Name <span class="text-rose-500">*</span></label>
                                <input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" required
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none transition"/>
                                @error('legal_name')<span class="text-rose-600 text-[11px] mt-1 block">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="gstin" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Corporate GSTIN</label>
                                <input type="text" id="gstin" name="gstin" value="{{ old('gstin', $company->gstin) }}" placeholder="e.g. 29AAACN1234F1Z5"
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none transition"/>
                                @error('gstin')<span class="text-rose-600 text-[11px] mt-1 block">{{ $message }}</span>@enderror
                            </div>

                            <div>
                                <label for="pan" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Corporate PAN</label>
                                <input type="text" id="pan" name="pan" value="{{ old('pan', $company->pan) }}" placeholder="e.g. AAACN1234F"
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-600 focus:outline-none transition"/>
                                @error('pan')<span class="text-rose-600 text-[11px] mt-1 block">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Official Email <span class="text-rose-500">*</span></label>
                                <input type="email" id="email" name="email" value="{{ old('email', $company->email) }}" required
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none transition"/>
                                @error('email')<span class="text-rose-600 text-[11px] mt-1 block">{{ $message }}</span>@enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Official Phone <span class="text-rose-500">*</span></label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone', $company->phone) }}" required
                                       class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none transition"/>
                                @error('phone')<span class="text-rose-600 text-[11px] mt-1 block">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div>
                            <label for="address" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Registered Corporate Address <span class="text-rose-500">*</span></label>
                            <textarea id="address" name="address" rows="3" required
                                      class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none transition">{{ old('address', $company->address) }}</textarea>
                            @error('address')<span class="text-rose-600 text-[11px] mt-1 block">{{ $message }}</span>@enderror
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                            <button type="submit"
                                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                    @else
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Company Name</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $company->name }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Legal Entity</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $company->legal_name ?? '—' }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">GSTIN</span>
                                <span class="text-sm font-mono font-semibold text-slate-900">{{ $company->gstin ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">PAN</span>
                                <span class="text-sm font-mono font-semibold text-slate-900">{{ $company->pan ?? '—' }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Official Email</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $company->email ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Official Phone</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $company->phone ?? '—' }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Registered Office</span>
                            <span class="text-sm text-slate-700">{{ $company->address ?? '—' }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Right Col: System & Operational Context Card -->
            <div class="space-y-6">
                <div class="soft-card p-5 space-y-4">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Operational Identity</h3>
                    
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Architecture</span>
                            <span class="font-bold text-slate-900">Single-Company Dedicated</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">System Entity</span>
                            <span class="font-bold text-blue-600">{{ $company->name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Currency</span>
                            <span class="font-bold text-slate-900">{{ $company->currency ?? 'INR' }} ({{ $company->currency_symbol ?? '₹' }})</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Tenant Workspaces</span>
                            <span class="font-semibold text-emerald-600">Locked to NSD</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">System Identifier</span>
                            <span class="font-mono text-slate-600">ID: {{ $company->id }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 text-[11px] text-slate-600 leading-relaxed">
                        This installation is configured exclusively for <strong>NSD Earth Movers & Construction Ltd</strong>. Multi-tenant company onboarding and external tenant routing are disabled.
                    </div>
                </div>

                <!-- Your Assigned Role Card -->
                <div class="soft-card p-5 space-y-3">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Your Corporate Access</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold text-sm flex items-center justify-center">
                            {{ substr(Auth::user()->name ?? 'U', 0, 2) }}
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">{{ Auth::user()->name }}</span>
                            <span class="text-[11px] text-slate-500">{{ Auth::user()->email }}</span>
                        </div>
                    </div>
                    <div class="pt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500">Assigned Role:</span>
                        <span class="font-bold text-blue-600">{{ $currentRole->name ?? 'Staff' }}</span>
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection
