@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('subcontractors.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold">← Subcontractors</a>
                <span class="text-gray-400">/</span>
                <span class="font-mono text-gray-500">{{ $subcontractor->subcontractor_code }}</span>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">{{ $subcontractor->name }}</h2>
            <p class="text-xs text-gray-500">Contact: {{ $subcontractor->contact_person }} | {{ $subcontractor->phone }} | {{ $subcontractor->email }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('work-orders.create', ['subcontractor_id' => $subcontractor->id]) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                + Issue Work Order
            </a>
        </div>
    </div>

    <!-- 4 Financial & Operational KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase">Total Contract Value</span>
            <div class="text-xl font-black text-gray-900 mt-1 font-mono">₹{{ number_format($financials['total_order_value'], 2) }}</div>
            <span class="text-[11px] text-gray-400">{{ $workOrders->count() }} Work Orders Issued</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-indigo-600 uppercase">Gross Certified Work</span>
            <div class="text-xl font-black text-indigo-600 mt-1 font-mono">₹{{ number_format($financials['total_certified_gross'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Measured & Certified</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-amber-600 uppercase">Retention Withheld</span>
            <div class="text-xl font-black text-amber-600 mt-1 font-mono">₹{{ number_format($financials['total_retention_held'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Liability Account 2030</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-emerald-600 uppercase">Disbursed Payments</span>
            <div class="text-xl font-black text-emerald-600 mt-1 font-mono">₹{{ number_format($financials['total_paid'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Cleared Bank Outflows</span>
        </div>
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-rose-600 uppercase">Outstanding Balance</span>
            <div class="text-xl font-black text-rose-600 mt-1 font-mono">₹{{ number_format($financials['total_outstanding'], 2) }}</div>
            <span class="text-[11px] text-gray-400">Net Due Payable</span>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Master Metadata -->
        <div class="soft-card p-5 space-y-4">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">Master Information</h3>
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Party Classification</span>
                    <span class="font-semibold text-gray-900 capitalize">{{ $subcontractor->vendor_type }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">GSTIN</span>
                    <span class="font-mono font-semibold text-gray-900">{{ $subcontractor->gstin ?? 'Not Registered' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">PAN</span>
                    <span class="font-mono font-semibold text-gray-900">{{ $subcontractor->pan ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">TDS Section & Rate</span>
                    <span class="font-semibold text-blue-700">{{ $subcontractor->tds_section }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Trade License Number</span>
                    <span class="font-mono text-gray-700">{{ $subcontractor->trade_license_no ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Payment Terms</span>
                    <span class="font-semibold text-gray-800 capitalize">{{ str_replace('_', ' ', $subcontractor->payment_terms) }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Bank Remittance (Protected)</span>
                    <span class="font-mono text-gray-900 block font-semibold">{{ $subcontractor->bank_name ?? '—' }}</span>
                    <span class="font-mono text-gray-500 text-[11px]">A/C: {{ $subcontractor->bank_account_number ? '••••' . substr($subcontractor->bank_account_number, -4) : '—' }} | IFSC: {{ $subcontractor->ifsc_code ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Trade Categories</span>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @if(is_array($subcontractor->work_categories))
                            @foreach($subcontractor->work_categories as $cat)
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gray-100 text-gray-700 capitalize">{{ str_replace('_', ' ', $cat) }}</span>
                            @endforeach
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Work Orders & Bills -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Work Orders -->
            <div class="soft-card p-5">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-3">
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Subcontract Work Orders</h3>
                    <a href="{{ route('work-orders.create', ['subcontractor_id' => $subcontractor->id]) }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">+ New WO</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                <th class="py-2 px-2">WO Number & Title</th>
                                <th class="py-2 px-2">Project</th>
                                <th class="py-2 px-2">Contract Value</th>
                                <th class="py-2 px-2">Certified Value</th>
                                <th class="py-2 px-2">Status</th>
                                <th class="py-2 px-2 text-right">View</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($workOrders as $wo)
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-2.5 px-2">
                                        <a href="{{ route('work-orders.show', $wo) }}" class="font-bold text-gray-900 hover:text-blue-600 block">
                                            {{ $wo->wo_number }}
                                        </a>
                                        <span class="text-gray-500 text-[11px]">{{ $wo->title }}</span>
                                    </td>
                                    <td class="py-2.5 px-2 text-gray-700 font-medium">{{ $wo->project->name ?? '—' }}</td>
                                    <td class="py-2.5 px-2 font-mono font-semibold">₹{{ number_format($wo->effectiveValue(), 2) }}</td>
                                    <td class="py-2.5 px-2 font-mono text-indigo-600 font-bold">₹{{ number_format($wo->totalCertifiedValue(), 2) }}</td>
                                    <td class="py-2.5 px-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $wo->status_badge }}">
                                            {{ ucfirst($wo->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-2 text-right">
                                        <a href="{{ route('work-orders.show', $wo) }}" class="text-blue-600 font-semibold hover:text-blue-800">Details →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-6 text-gray-400 text-xs">No work orders issued to this subcontractor yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Running Account Bills -->
            <div class="soft-card p-5">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-3">
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Subcontractor RA Bills</h3>
                    <a href="{{ route('subcontractor-bills.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">View All Bills →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                <th class="py-2 px-2">Bill Number</th>
                                <th class="py-2 px-2">Date</th>
                                <th class="py-2 px-2">Gross Certified</th>
                                <th class="py-2 px-2">Net Payable</th>
                                <th class="py-2 px-2">Paid</th>
                                <th class="py-2 px-2">Status</th>
                                <th class="py-2 px-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($bills as $bill)
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="py-2.5 px-2 font-mono font-bold text-gray-900">
                                        <a href="{{ route('subcontractor-bills.show', $bill) }}" class="hover:text-blue-600">{{ $bill->bill_number }}</a>
                                    </td>
                                    <td class="py-2.5 px-2 text-gray-500">{{ $bill->bill_date->format('d M Y') }}</td>
                                    <td class="py-2.5 px-2 font-mono">₹{{ number_format($bill->gross_work_value, 2) }}</td>
                                    <td class="py-2.5 px-2 font-mono font-bold text-gray-900">₹{{ number_format($bill->net_payable_amount, 2) }}</td>
                                    <td class="py-2.5 px-2 font-mono text-emerald-600 font-bold">₹{{ number_format($bill->paid_amount, 2) }}</td>
                                    <td class="py-2.5 px-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $bill->status_badge }}">
                                            {{ ucfirst(str_replace('_', ' ', $bill->status)) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-2 text-right">
                                        <a href="{{ route('subcontractor-bills.show', $bill) }}" class="text-blue-600 font-semibold hover:text-blue-800">Reconciliation →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-6 text-gray-400 text-xs">No bills generated for this subcontractor yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
