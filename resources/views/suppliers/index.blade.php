@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Material Suppliers Directory</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ $stats['total_count'] }} Material Vendors
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Directory of cement, steel, aggregates, machinery spare parts, and fuel vendors. (Subcontractors are managed under Subcontractor Master).</p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('suppliers.manage') || $u->hasPermission('suppliers.*') || $u->hasPermission('procurement.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'procurement_officer', 'accountant']));
        @endphp

        @if($canManage)
        <div class="flex items-center gap-3">
            <a href="{{ route('suppliers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Register Material Supplier</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Financial & Operational Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Suppliers</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $stats['active_count'] }}</div>
            <span class="text-[11px] text-slate-500">{{ $stats['pure_suppliers_count'] }} material, {{ $stats['both_count'] }} dual-role</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total PO Orders</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">₹{{ number_format($stats['total_po_value'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Committed procurement</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Invoiced</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-1">₹{{ number_format($stats['total_invoiced'], 2) }}</div>
            <span class="text-[11px] text-slate-500">₹{{ number_format($stats['total_paid'], 2) }} paid to date</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Outstanding Payable</span>
            <div class="text-2xl font-extrabold {{ $stats['outstanding_payable'] > 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-1">
                ₹{{ number_format($stats['outstanding_payable'], 2) }}
            </div>
            <span class="text-[11px] text-slate-500">Pending vendor disbursements</span>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="soft-card p-4">
        <form method="GET" action="{{ route('suppliers.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-5 relative">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search suppliers by name, contact, GSTIN, PAN, bank..."
                       class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <div class="sm:col-span-2">
                <select name="vendor_type" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">All Types</option>
                    <option value="supplier" {{ request('vendor_type') === 'supplier' ? 'selected' : '' }}>Material Only</option>
                    <option value="both" {{ request('vendor_type') === 'both' ? 'selected' : '' }}>Supplier & Contractor</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <select name="status" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <select name="payment_terms" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">All Terms</option>
                    <option value="immediate" {{ request('payment_terms') === 'immediate' ? 'selected' : '' }}>Immediate</option>
                    <option value="15_days" {{ request('payment_terms') === '15_days' ? 'selected' : '' }}>15 Days</option>
                    <option value="30_days" {{ request('payment_terms') === '30_days' ? 'selected' : '' }}>30 Days</option>
                    <option value="45_days" {{ request('payment_terms') === '45_days' ? 'selected' : '' }}>45 Days</option>
                </select>
            </div>

            <div class="sm:col-span-1">
                <button type="submit" class="w-full py-2 px-3 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 transition">
                    Filter
                </button>
            </div>
        </form>

        @if(request()->hasAny(['search', 'vendor_type', 'status', 'payment_terms']))
        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
            <span>Filtered results</span>
            <a href="{{ route('suppliers.index') }}" class="text-blue-600 hover:text-blue-800 font-medium">Clear all filters</a>
        </div>
        @endif
    </div>

    <!-- Suppliers Table -->
    <div class="soft-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4">Supplier Name</th>
                        <th class="py-3 px-4">Classification</th>
                        <th class="py-3 px-4">Contact Info</th>
                        <th class="py-3 px-4">Tax IDs</th>
                        <th class="py-3 px-4">Payment Terms</th>
                        <th class="py-3 px-4 text-right">Invoiced / Due</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($suppliers as $s)
                    @php
                        $sInvoiced = $s->invoices->sum('total_amount');
                        $sPaid = $s->invoices->sum('paid_amount');
                        $sOutstanding = max(0.0, $sInvoiced - $sPaid);
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4">
                            <a href="{{ route('suppliers.show', $s) }}" class="font-bold text-slate-900 hover:text-blue-600 transition block">
                                {{ $s->name }}
                            </a>
                            <span class="text-[10px] text-slate-400">{{ $s->purchase_orders_count }} purchase order(s)</span>
                        </td>
                        <td class="py-3 px-4">
                            @if($s->vendor_type === 'both')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                    Supplier & Contractor
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                    Material Supplier
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if($s->contact_person)
                                <span class="font-medium text-slate-800 block">{{ $s->contact_person }}</span>
                            @endif
                            @if($s->phone)
                                <span class="text-[11px] text-slate-500 block font-mono">{{ $s->phone }}</span>
                            @endif
                            @if($s->email)
                                <span class="text-[11px] text-slate-400 block">{{ $s->email }}</span>
                            @endif
                            @if(! $s->contact_person && ! $s->phone && ! $s->email)
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-mono text-[11px]">
                            @if($s->gstin)
                                <span class="block text-slate-700"><span class="text-slate-400 text-[10px]">GSTIN:</span> {{ $s->gstin }}</span>
                            @endif
                            @if($s->pan)
                                <span class="block text-slate-500"><span class="text-slate-400 text-[10px]">PAN:</span> {{ $s->pan }}</span>
                            @endif
                            @if(! $s->gstin && ! $s->pan)
                                <span class="text-slate-400 font-sans">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                {{ ucfirst(str_replace('_', ' ', $s->payment_terms)) }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="font-bold text-slate-900">₹{{ number_format($sInvoiced, 2) }}</div>
                            <div class="text-[11px] text-emerald-600">Paid: ₹{{ number_format($sPaid, 2) }}</div>
                            @if($sOutstanding > 0)
                                <div class="text-[10px] font-semibold text-amber-600">Due: ₹{{ number_format($sOutstanding, 2) }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($s->status === 'active')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right space-x-2">
                            <a href="{{ route('suppliers.show', $s) }}" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                View
                            </a>
                            @if($canManage)
                            <a href="{{ route('suppliers.edit', $s) }}" class="text-slate-500 hover:text-slate-700 font-semibold text-xs">
                                Edit
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400">
                            <div class="max-w-xs mx-auto">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                <p class="text-sm font-semibold text-slate-700">No material suppliers found</p>
                                <p class="text-xs text-slate-400 mt-1">Register suppliers for construction materials or adjust your filter query.</p>
                                @if($canManage)
                                <div class="mt-4">
                                    <a href="{{ route('suppliers.create') }}" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition">
                                        + Register Supplier
                                    </a>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $suppliers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
