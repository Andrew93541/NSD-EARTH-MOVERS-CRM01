@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Top Row: 6 Financial & Operational Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">

        <!-- Card 1: Active Projects -->
        <div class="soft-card p-4 flex flex-col justify-between soft-card-hover border-l-4 border-l-blue-600">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Active Projects</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700">
                    Live
                </span>
            </div>
            <div class="mt-3">
                <span class="text-3xl font-black text-gray-900 tracking-tight">{{ $activeProjectsCount }}</span>
                <p class="text-[11px] text-gray-500 mt-1 flex items-center justify-between">
                    <span>In progress</span>
                    <span class="font-semibold text-gray-700">{{ $completedProjectsCount }} completed</span>
                </p>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100">
                <a href="{{ route('projects.index') }}" class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 transition">
                    View project register →
                </a>
            </div>
        </div>

        <!-- Card 2: Money to Receive -->
        <div class="soft-card p-4 flex flex-col justify-between soft-card-hover border-l-4 border-l-emerald-600">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Money to Receive</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">
                    Receivables
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-gray-900 tracking-tight">
                    ₹{{ $totalReceivables >= 10000000 ? number_format($totalReceivables / 10000000, 2) . ' Cr' : ($totalReceivables >= 100000 ? number_format($totalReceivables / 100000, 1) . 'L' : number_format($totalReceivables, 0)) }}
                </span>
                <p class="text-[11px] text-gray-500 mt-1 truncate" title="₹{{ number_format($totalReceivables, 2) }}">
                    Client RA bills balance due
                </p>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100">
                <a href="{{ route('billing.index') }}" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-800 transition">
                    View client billing →
                </a>
            </div>
        </div>

        <!-- Card 3: Money to Pay (Payables: Suppliers + Subcontractors) -->
        <div class="soft-card p-4 flex flex-col justify-between soft-card-hover border-l-4 border-l-rose-600">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Money to Pay</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700">
                    Payables
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-gray-900 tracking-tight">
                    ₹{{ $totalPayables >= 10000000 ? number_format($totalPayables / 10000000, 2) . ' Cr' : ($totalPayables >= 100000 ? number_format($totalPayables / 100000, 1) . 'L' : number_format($totalPayables, 0)) }}
                </span>
                <p class="text-[11px] text-gray-500 mt-1 truncate" title="Suppliers: ₹{{ number_format($supplierPayables, 2) }} | Subcontractors: ₹{{ number_format($subcontractorPayables, 2) }}">
                    Suppliers & contractors
                </p>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100">
                <span class="text-[10px] text-gray-500 block truncate">
                    Vendors: ₹{{ $supplierPayables >= 100000 ? number_format($supplierPayables / 100000, 1) . 'L' : number_format($supplierPayables, 0) }} • Subs: ₹{{ $subcontractorPayables >= 100000 ? number_format($subcontractorPayables / 100000, 1) . 'L' : number_format($subcontractorPayables, 0) }}
                </span>
            </div>
        </div>

        <!-- Card 4: Money Received (Client Collections) -->
        <div class="soft-card p-4 flex flex-col justify-between soft-card-hover border-l-4 border-l-teal-600">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Money Received</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700">
                    Collected
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-gray-900 tracking-tight">
                    ₹{{ $totalReceived >= 10000000 ? number_format($totalReceived / 10000000, 2) . ' Cr' : ($totalReceived >= 100000 ? number_format($totalReceived / 100000, 1) . 'L' : number_format($totalReceived, 0)) }}
                </span>
                <p class="text-[11px] text-gray-500 mt-1">
                    Payments received to date
                </p>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100">
                <span class="text-[10px] text-gray-500 block truncate">
                    Settled on active contracts
                </span>
            </div>
        </div>

        <!-- Card 5: Recorded Expenses (Site & Operational) -->
        <div class="soft-card p-4 flex flex-col justify-between soft-card-hover border-l-4 border-l-indigo-600">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Recorded Expenses</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700">
                    Approved
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-gray-900 tracking-tight">
                    ₹{{ $totalExpenses >= 10000000 ? number_format($totalExpenses / 10000000, 2) . ' Cr' : ($totalExpenses >= 100000 ? number_format($totalExpenses / 100000, 1) . 'L' : number_format($totalExpenses, 0)) }}
                </span>
                <p class="text-[11px] text-gray-500 mt-1">
                    Site & petty cash costs
                </p>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100">
                <a href="{{ route('expenses.index') }}" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 transition">
                    View expense ledger →
                </a>
            </div>
        </div>

        <!-- Card 6: Pending Approvals (Actions Requiring Sign-Off) -->
        <div class="soft-card p-4 flex flex-col justify-between soft-card-hover border-l-4 border-l-amber-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Pending Approvals</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700">
                    Action
                </span>
            </div>
            <div class="mt-3">
                <span class="text-3xl font-black text-gray-900 tracking-tight">{{ $pendingApprovalsCount }}</span>
                <p class="text-[11px] text-gray-500 mt-1">
                    Awaiting authorization
                </p>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100">
                <span class="text-[10px] text-gray-500 block truncate" title="PR: {{ $pendingPRsCount }} | PO: {{ $draftPOsCount }} | Bills: {{ $pendingInvoicesCount + $submittedSubconBillsCount }} | MB: {{ $submittedMBCount }}">
                    PR: {{ $pendingPRsCount }} • PO: {{ $draftPOsCount }} • Bills: {{ $pendingInvoicesCount + $submittedSubconBillsCount }}
                </span>
            </div>
        </div>

    </div>

    <!-- Middle Row: Financial Reconciliation & Site Operations -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Financial Position & Liabilities Reconciliation (7 cols) -->
        <div class="lg:col-span-7 soft-card p-5 flex flex-col justify-between">
            <div>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                    <div>
                        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Cash Flow & Liabilities Reconciler</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Real-time ledger breakdown across vendors, contractors, and client billings</p>
                    </div>
                    <span class="px-2 py-1 bg-slate-100 text-slate-700 text-[10px] font-semibold rounded-lg">
                        Tenant Scoped
                    </span>
                </div>

                <!-- Reconciliation Rows -->
                <div class="space-y-4 pt-2">
                    <!-- Client Billing Balance -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-gray-700 flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                Client RA Receivables (Money to Receive)
                            </span>
                            <span class="text-gray-900 font-bold">₹{{ number_format($totalReceivables, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            @php
                                $maxAmount = max($totalReceivables, $totalPayables, $totalReceived, $totalExpenses, 1.0);
                                $recPct = min(100, round(($totalReceivables / $maxAmount) * 100));
                            @endphp
                            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width: {{ $recPct }}%"></div>
                        </div>
                    </div>

                    <!-- Supplier Invoices -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-gray-700 flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                Material Supplier Payables (Verified Invoices)
                            </span>
                            <span class="text-gray-900 font-bold">₹{{ number_format($supplierPayables, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            @php $supPct = min(100, round(($supplierPayables / $maxAmount) * 100)); @endphp
                            <div class="bg-rose-500 h-2 rounded-full transition-all duration-500" style="width: {{ $supPct }}%"></div>
                        </div>
                    </div>

                    <!-- Subcontractor RA Bills -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-gray-700 flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                Subcontractor RA Liabilities (Certified Net Balance)
                            </span>
                            <span class="text-gray-900 font-bold">₹{{ number_format($subcontractorPayables, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            @php $subPct = min(100, round(($subcontractorPayables / $maxAmount) * 100)); @endphp
                            <div class="bg-amber-500 h-2 rounded-full transition-all duration-500" style="width: {{ $subPct }}%"></div>
                        </div>
                    </div>

                    <!-- Client Receipts Collected -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-gray-700 flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-teal-500"></span>
                                Settled Client Collections (Money Received)
                            </span>
                            <span class="text-gray-900 font-bold">₹{{ number_format($totalReceived, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            @php $colPct = min(100, round(($totalReceived / $maxAmount) * 100)); @endphp
                            <div class="bg-teal-500 h-2 rounded-full transition-all duration-500" style="width: {{ $colPct }}%"></div>
                        </div>
                    </div>

                    <!-- Approved Expenses -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-gray-700 flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                Operational & Site Petty Cash Expenses
                            </span>
                            <span class="text-gray-900 font-bold">₹{{ number_format($totalExpenses, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            @php $expPct = min(100, round(($totalExpenses / $maxAmount) * 100)); @endphp
                            <div class="bg-indigo-500 h-2 rounded-full transition-all duration-500" style="width: {{ $expPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-3 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                <span>Total Combined Payables: <strong class="text-rose-600 font-bold">₹{{ number_format($totalPayables, 2) }}</strong></span>
                <span>Net Billing Position: <strong class="{{ ($totalReceivables - $totalPayables) >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-bold">₹{{ number_format($totalReceivables - $totalPayables, 2) }}</strong></span>
            </div>
        </div>

        <!-- Site Operations (5 cols) -->
        <div class="lg:col-span-5 soft-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Site Operations</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Real-time workforce deployment by location</p>
                </div>
                <a href="{{ route('sites.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">View all sites →</a>
            </div>

            <div class="space-y-3">
                @forelse ($sites->take(4) as $st)
                    <div class="flex items-center justify-between p-3 bg-gray-50/80 rounded-2xl border border-gray-100/80 hover:bg-gray-100/60 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900">{{ $st->name }}</h4>
                                <span class="text-[11px] text-gray-500 flex items-center gap-1 mt-0.5">
                                    @php $workers = $st->dailyLogs->first()?->active_workers_count; @endphp
                                    @if ($workers !== null)
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ $workers }} workers active today
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        No daily log filed today
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('sites.show', $st) }}" class="p-2 rounded-xl bg-white border border-gray-200 text-gray-600 hover:text-blue-600 hover:border-blue-400 transition shadow-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-gray-400">
                        No operational sites registered.
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- Third Row: Project Milestones & Upcoming Obligations -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Projects Timeline Milestones (7 cols) -->
        <div class="lg:col-span-7 soft-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Active Project Milestones</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Live execution progress from project register</p>
                </div>
                <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
                    All contracts →
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @forelse ($timelineProjects as $tp)
                    <div class="p-4 bg-gray-50/80 rounded-2xl border border-gray-100 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-1.5 flex-wrap">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md
                                    {{ $tp->status === 'on_track' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : ($tp->status === 'at_risk' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-rose-100 text-rose-800 border border-rose-200') }}">
                                    {{ strtoupper(str_replace('_', ' ', $tp->status)) }}
                                </span>
                                <span class="text-[10px] font-mono font-bold text-gray-400 uppercase">{{ $tp->code }}</span>
                            </div>

                            <h4 class="text-xs font-bold text-gray-900 mt-2">
                                <a href="{{ route('projects.show', $tp) }}" class="hover:text-blue-600 transition">{{ $tp->name }}</a>
                            </h4>

                            <p class="text-[11px] text-gray-500 mt-1 line-clamp-2 leading-relaxed">
                                {{ $tp->description ?: 'Operational project works underway at ' . ($tp->location ?: 'active site') }}
                            </p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-200/60">
                            <div class="flex items-center justify-between text-[11px] mb-1 font-semibold">
                                <span class="text-blue-600 font-bold">{{ $tp->progress_percent }}% Completed</span>
                                <span class="text-gray-400">{{ $tp->target_completion_date ? 'Due ' . $tp->target_completion_date->format('M d, Y') : 'Ongoing' }}</span>
                            </div>
                            <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" style="width: {{ $tp->progress_percent }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 py-8 text-center text-xs text-gray-400">
                        No active project milestones recorded.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Obligations & Scheduled Payments (5 cols) -->
        <div class="lg:col-span-5 soft-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Upcoming Obligations</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Bills & EMI schedules due within 14 days</p>
                </div>
                <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                    Due Soon
                </span>
            </div>

            <div class="space-y-3">
                <!-- Overdue/Due Supplier Bills -->
                @forelse ($overdueBills as $bill)
                    <div class="p-3 bg-rose-50/50 rounded-2xl border border-rose-100 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 bg-rose-100 text-rose-800 rounded">Vendor Bill</span>
                                <h5 class="text-xs font-bold text-gray-900">{{ $bill->supplier->name ?? 'Supplier' }}</h5>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                Inv #{{ $bill->invoice_number }} • Due {{ $bill->due_date ? $bill->due_date->format('d M') : 'soon' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-rose-700 block">₹{{ number_format($bill->total_amount - $bill->paid_amount, 2) }}</span>
                            <span class="text-[10px] text-rose-500 font-semibold">Unpaid</span>
                        </div>
                    </div>
                @empty
                @endforelse

                <!-- Upcoming EMIs -->
                @forelse ($upcomingEmis as $emi)
                    <div class="p-3 bg-indigo-50/50 rounded-2xl border border-indigo-100 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 bg-indigo-100 text-indigo-800 rounded">Equipment EMI</span>
                                <h5 class="text-xs font-bold text-gray-900">{{ $emi->loan->facility_number ?? 'Fleet Loan' }}</h5>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                Installment #{{ $emi->installment_number }} • Due {{ $emi->due_date ? $emi->due_date->format('d M') : 'soon' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-indigo-700 block">₹{{ number_format($emi->total_emi, 2) }}</span>
                            <span class="text-[10px] text-indigo-500 font-semibold">Pending</span>
                        </div>
                    </div>
                @empty
                @endforelse

                @if ($overdueBills->isEmpty() && $upcomingEmis->isEmpty())
                    <div class="py-8 text-center text-xs text-gray-400">
                        <svg class="w-8 h-8 mx-auto text-emerald-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        No urgent bills or loan EMI payments due in the next 14 days.
                    </div>
                @endif
            </div>
        </div>

    </div>

    <!-- Bottom Row: Recent Projects Data Grid -->
    <div class="soft-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Recent Project Contracts</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Active civil infrastructure, interior, and earthmoving works in company register</p>
            </div>
            <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
                View all contracts →
            </a>
        </div>

        <div class="overflow-x-auto -mx-5 px-5">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Project Name & Code</th>
                        <th class="py-3 px-3">Client</th>
                        <th class="py-3 px-3">Start Date</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3">Contract Value</th>
                        <th class="py-3 px-3">Progress</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs">
                    @forelse ($projects as $prj)
                        <tr class="hover:bg-gray-50/60 transition group">
                            <!-- Project Name -->
                            <td class="py-3.5 px-3 font-bold text-gray-900">
                                <a href="{{ route('projects.show', $prj) }}" class="hover:text-blue-600 transition">
                                    {{ $prj->name }}
                                </a>
                                <span class="block text-[11px] font-normal text-gray-400 font-mono">{{ $prj->code }}</span>
                            </td>

                            <!-- Client -->
                            <td class="py-3.5 px-3 text-gray-600 font-medium">
                                {{ $prj->client->name ?? 'Direct Contract' }}
                            </td>

                            <!-- Start Date -->
                            <td class="py-3.5 px-3 text-gray-500">
                                {{ $prj->start_date ? $prj->start_date->format('d M, Y') : '—' }}
                            </td>

                            <!-- Due Date -->
                            <td class="py-3.5 px-3 text-gray-500">
                                {{ $prj->target_completion_date ? $prj->target_completion_date->format('d M, Y') : '—' }}
                            </td>

                            <!-- Contract Budget in INR -->
                            <td class="py-3.5 px-3 font-extrabold text-gray-900">
                                ₹{{ $prj->contract_value >= 10000000 ? number_format($prj->contract_value / 10000000, 2) . ' Cr' : number_format($prj->contract_value, 0) }}
                            </td>

                            <!-- Progress -->
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-2 w-28">
                                    <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $prj->progress_percent }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-bold text-gray-700">{{ $prj->progress_percent }}%</span>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-3">
                                @if ($prj->status === 'on_track')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> On Track
                                    </span>
                                @elseif ($prj->status === 'at_risk')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> At Risk
                                    </span>
                                @elseif ($prj->status === 'completed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Completed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> {{ ucfirst(str_replace('_', ' ', $prj->status)) }}
                                    </span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-3.5 px-3 text-right">
                                <a href="{{ route('projects.show', $prj) }}" class="text-gray-400 hover:text-blue-600 transition p-1" title="Open project detail">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400">No project contracts registered for this company.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
