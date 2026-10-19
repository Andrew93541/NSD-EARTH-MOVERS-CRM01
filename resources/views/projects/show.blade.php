@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header with status and actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('projects.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    ← Projects Register
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $project->name }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $project->code }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Client: 
                @if($project->client)
                    <a href="{{ route('clients.show', $project->client) }}" class="font-semibold text-blue-600 hover:underline">{{ $project->client->name }}</a>
                @else
                    <span class="text-slate-700 font-medium">Direct Contract</span>
                @endif
                • Location: {{ $project->location ?? 'Site Yard' }} • Manager: {{ $project->manager->name ?? 'Unassigned' }}
            </p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('projects.manage') || $u->hasPermission('projects.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager']));
            $canBilling = $u && ($u->hasPermission('*') || $u->hasPermission('billing.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant']));
            $canProcure = $u && ($u->hasPermission('*') || $u->hasPermission('procurement.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'procurement_officer']));
        @endphp

        <div class="flex items-center gap-2">
            @if($canBilling)
            <a href="{{ route('billing.create', ['project_id' => $project->id]) }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                + Raise RA Bill
            </a>
            @endif
            @if($canProcure)
            <a href="{{ route('procurement.create', ['project_id' => $project->id]) }}" class="px-3.5 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                + Issue PO
            </a>
            @endif
            @if($canManage)
            <a href="{{ route('projects.edit', $project) }}" class="px-3.5 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                Edit Parameters
            </a>
            @endif
        </div>
    </div>

    <!-- 6 Verified Financial Overview Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 sm:gap-4">
        <!-- 1. Contract Value -->
        <div class="soft-card p-3 sm:p-3.5 min-w-0">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Contract Value</span>
            <div class="text-[14px] sm:text-[15px] xl:text-base font-extrabold text-slate-900 mt-1 tracking-tight whitespace-nowrap" title="₹{{ number_format($financials['contract_value'], 2) }}">
                ₹{{ number_format($financials['contract_value'], fmod($financials['contract_value'], 1) !== 0.0 ? 2 : 0) }}
            </div>
            <span class="text-[11px] text-slate-500 block truncate">Agreed contract sum</span>
        </div>

        <!-- 2. Client Bills Raised -->
        <div class="soft-card p-3 sm:p-3.5 min-w-0">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Client Bills Raised</span>
            <div class="text-[14px] sm:text-[15px] xl:text-base font-extrabold text-blue-600 mt-1 tracking-tight whitespace-nowrap" title="₹{{ number_format($financials['client_bills_raised'], 2) }}">
                ₹{{ number_format($financials['client_bills_raised'], fmod($financials['client_bills_raised'], 1) !== 0.0 ? 2 : 0) }}
            </div>
            <span class="text-[11px] text-slate-500 block truncate">{{ $clientInvoices->whereIn('status', ['submitted', 'approved', 'partially_paid', 'paid', 'overdue'])->count() }} certified bill(s)</span>
        </div>

        <!-- 3. Money Received -->
        <div class="soft-card p-3 sm:p-3.5 min-w-0">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Money Received</span>
            <div class="text-[14px] sm:text-[15px] xl:text-base font-extrabold text-emerald-600 mt-1 tracking-tight whitespace-nowrap" title="₹{{ number_format($financials['money_received'], 2) }}">
                ₹{{ number_format($financials['money_received'], fmod($financials['money_received'], 1) !== 0.0 ? 2 : 0) }}
            </div>
            <span class="text-[11px] text-slate-500 block truncate">Actual collections</span>
        </div>

        <!-- 4. Outstanding Client Balance -->
        <div class="soft-card p-3 sm:p-3.5 min-w-0">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Client Outstanding</span>
            <div class="text-[14px] sm:text-[15px] xl:text-base font-extrabold {{ $financials['outstanding_client_balance'] > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1 tracking-tight whitespace-nowrap" title="₹{{ number_format($financials['outstanding_client_balance'], 2) }}">
                ₹{{ number_format($financials['outstanding_client_balance'], fmod($financials['outstanding_client_balance'], 1) !== 0.0 ? 2 : 0) }}
            </div>
            <span class="text-[11px] text-slate-500 block truncate">Billed minus received</span>
        </div>

        <!-- 5. Unbilled Contract Balance -->
        <div class="soft-card p-3 sm:p-3.5 min-w-0">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block truncate">Unbilled Contract</span>
            <div class="text-[14px] sm:text-[15px] xl:text-base font-extrabold text-slate-700 mt-1 tracking-tight whitespace-nowrap" title="₹{{ number_format($financials['unbilled_contract_value'], 2) }}">
                ₹{{ number_format($financials['unbilled_contract_value'], fmod($financials['unbilled_contract_value'], 1) !== 0.0 ? 2 : 0) }}
            </div>
            <span class="text-[11px] text-slate-500 block truncate">Remaining contract cap</span>
        </div>

        <!-- 6. Approved Budget & Progress -->
        <div class="soft-card p-3 sm:p-3.5 min-w-0">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate">Budget & Progress</span>
                <span class="text-xs font-bold text-blue-600">{{ $project->progress_percent }}%</span>
            </div>
            <div class="text-[14px] sm:text-[15px] xl:text-base font-extrabold text-slate-900 mt-1 tracking-tight whitespace-nowrap" title="₹{{ number_format($financials['approved_budget'], 2) }}">
                ₹{{ number_format($financials['approved_budget'], fmod($financials['approved_budget'], 1) !== 0.0 ? 2 : 0) }}
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mt-1.5">
                <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $project->progress_percent }}%"></div>
            </div>
        </div>
    </div>

    <!-- Verified Cost & Procurement Statement -->
    <div class="soft-card p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Cost Accounting & Procurement Commitments</h2>
                <p class="text-[11px] text-slate-500">Distinct separation between committed purchase orders, certified subcontractor liabilities, and actual cash paid.</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Actual Cash Paid</span>
                <span class="text-lg font-extrabold text-slate-900">₹{{ number_format($financials['total_actual_cash_paid'], 2) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <!-- 1. Direct Site Expenses -->
            <div class="p-3 bg-slate-50/75 rounded-xl border border-slate-100 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800">1. Direct Site Expenses</span>
                    <span class="text-[10px] font-semibold text-slate-500">{{ $expenses->count() }} voucher(s)</span>
                </div>
                <div class="text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Approved & Paid:</span>
                        <strong class="text-slate-900">₹{{ number_format($financials['recorded_direct_expenses_paid'], 2) }}</strong>
                    </div>
                    @if($financials['pending_direct_expenses'] > 0)
                    <div class="flex justify-between text-amber-600 font-medium">
                        <span>Pending Approval:</span>
                        <span>₹{{ number_format($financials['pending_direct_expenses'], 2) }}</span>
                    </div>
                    @endif
                </div>
                <p class="text-[10px] text-slate-400">Direct site vouchers, fuel, and petty cash recorded for this project.</p>
            </div>

            <!-- 2. Material Procurement -->
            <div class="p-3 bg-slate-50/75 rounded-xl border border-slate-100 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800">2. Material Procurement</span>
                    <span class="text-[10px] font-semibold text-slate-500">{{ $purchaseOrders->count() }} PO(s)</span>
                </div>
                <div class="text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Committed PO Total:</span>
                        <strong class="text-slate-900">₹{{ number_format($financials['committed_procurement_po'], 2) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Supplier Invoiced:</span>
                        <span class="text-slate-700 font-semibold">₹{{ number_format($financials['supplier_invoiced'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-emerald-600">
                        <span>Paid to Suppliers:</span>
                        <strong class="font-semibold">₹{{ number_format($financials['supplier_paid'], 2) }}</strong>
                    </div>
                    <div class="flex justify-between text-amber-600 font-medium">
                        <span>Outstanding Payable:</span>
                        <span>₹{{ number_format($financials['supplier_outstanding'], 2) }}</span>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400">Approved purchase orders are committed liabilities; only invoiced/paid amounts constitute actual cash expenses.</p>
            </div>

            <!-- 3. Subcontractor Work Orders & RA Bills -->
            <div class="p-3 bg-slate-50/75 rounded-xl border border-slate-100 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800">3. Subcontractor Liabilities</span>
                    <span class="text-[10px] font-semibold text-slate-500">{{ $subWorkOrders->count() }} WO(s)</span>
                </div>
                <div class="text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Active Work Orders:</span>
                        <strong class="text-slate-900">₹{{ number_format($financials['committed_subcontractor_wo'], 2) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Gross Work Certified:</span>
                        <span class="text-slate-700 font-semibold">₹{{ number_format($financials['sub_gross_certified'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-emerald-600">
                        <span>Paid to Subcontractors:</span>
                        <strong class="font-semibold">₹{{ number_format($financials['sub_paid'], 2) }}</strong>
                    </div>
                    <div class="flex justify-between text-amber-600 font-medium">
                        <span>Outstanding Due:</span>
                        <span>₹{{ number_format($financials['sub_outstanding'], 2) }}</span>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400">Net of retention and TDS deductions certified through digital Measurement Books.</p>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500">
            <strong>Accounting Rule Notice:</strong> Total cash paid (₹{{ number_format($financials['total_actual_cash_paid'], 2) }}) reflects Direct Expenses Paid + Material Supplier Invoices Paid + Subcontractor Bills Paid. Committed Purchase Orders and unbilled Work Orders represent contractual commitments and are not double-counted as cash outflow.
        </div>
    </div>

    <!-- Pending Operational Actions -->
    @php
        $totalPendingCount = $pendingActions['pending_prs']->count()
            + $pendingActions['draft_invoices']->count()
            + $pendingActions['submitted_invoices']->count()
            + $pendingActions['draft_sub_bills']->count()
            + $pendingActions['submitted_sub_bills']->count()
            + $pendingActions['pending_expenses']->count();
    @endphp

    <div class="soft-card p-5 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Pending Operational Actions & Approvals</h2>
            @if($totalPendingCount > 0)
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                    {{ $totalPendingCount }} Action(s) Required
                </span>
            @else
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    All Actions Up to Date
                </span>
            @endif
        </div>

        @if($totalPendingCount > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            @if($pendingActions['pending_prs']->count() > 0)
            <div class="p-3 bg-amber-50/50 rounded-xl border border-amber-200/60">
                <span class="font-bold text-amber-900 block">{{ $pendingActions['pending_prs']->count() }} Material Request(s)</span>
                <span class="text-[11px] text-amber-700 block mt-0.5">Awaiting procurement review</span>
                <a href="{{ route('purchase-requests.index', ['project_id' => $project->id]) }}" class="text-[11px] font-semibold text-blue-600 hover:underline mt-1 inline-block">Review Indents →</a>
            </div>
            @endif

            @if($pendingActions['draft_invoices']->count() > 0 || $pendingActions['submitted_invoices']->count() > 0)
            <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-200/60">
                <span class="font-bold text-blue-900 block">{{ $pendingActions['draft_invoices']->count() + $pendingActions['submitted_invoices']->count() }} Client Bill(s)</span>
                <span class="text-[11px] text-blue-700 block mt-0.5">{{ $pendingActions['submitted_invoices']->count() }} submitted, {{ $pendingActions['draft_invoices']->count() }} draft</span>
                <a href="{{ route('billing.index') }}" class="text-[11px] font-semibold text-blue-600 hover:underline mt-1 inline-block">Billing Register →</a>
            </div>
            @endif

            @if($pendingActions['draft_sub_bills']->count() > 0 || $pendingActions['submitted_sub_bills']->count() > 0)
            <div class="p-3 bg-purple-50/50 rounded-xl border border-purple-200/60">
                <span class="font-bold text-purple-900 block">{{ $pendingActions['draft_sub_bills']->count() + $pendingActions['submitted_sub_bills']->count() }} Subcontractor Bill(s)</span>
                <span class="text-[11px] text-purple-700 block mt-0.5">Awaiting QS certification</span>
                <a href="{{ route('subcontractor-bills.index') }}" class="text-[11px] font-semibold text-blue-600 hover:underline mt-1 inline-block">Review Bills →</a>
            </div>
            @endif

            @if($pendingActions['pending_expenses']->count() > 0)
            <div class="p-3 bg-rose-50/50 rounded-xl border border-rose-200/60">
                <span class="font-bold text-rose-900 block">{{ $pendingActions['pending_expenses']->count() }} Expense Voucher(s)</span>
                <span class="text-[11px] text-rose-700 block mt-0.5">Pending managerial approval</span>
                <a href="{{ route('expenses.index') }}" class="text-[11px] font-semibold text-blue-600 hover:underline mt-1 inline-block">Review Expenses →</a>
            </div>
            @endif
        </div>
        @else
        <p class="text-xs text-slate-500 py-2">No pending indents, uncertified subcontractor bills, or outstanding expense approvals for this project.</p>
        @endif
    </div>

    <!-- Engineering Scope & Associated Sites -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 soft-card p-5 space-y-4">
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Engineering Scope & Deliverables</h2>
            <p class="text-xs text-slate-600 leading-relaxed">{{ $project->description ?? 'No detailed engineering scope notes recorded.' }}</p>

            <div class="pt-4 border-t border-slate-100">
                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">Associated Operational Sites</h3>
                <div class="space-y-2">
                    @forelse ($project->sites as $site)
                        <div class="flex items-center justify-between p-3 bg-slate-50/75 rounded-xl border border-slate-100 text-xs">
                            <div>
                                <span class="font-bold text-slate-900">{{ $site->name }}</span>
                                <span class="block text-[11px] text-slate-400">{{ $site->location ?? 'General Site Boundary' }}</span>
                            </div>
                            <a href="{{ route('sites.show', $site) }}" class="text-blue-600 font-semibold hover:text-blue-800">Site Monitor →</a>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No active work sites defined for this project.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Project Schedule & Dates -->
        <div class="soft-card p-5 space-y-4">
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Timeline & Key Dates</h2>
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Commencement Date</span>
                    <span class="font-semibold text-slate-800">{{ $project->start_date?->format('d M Y') ?? 'Not specified' }}</span>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Target Completion Date</span>
                    <span class="font-semibold text-slate-800">{{ $project->target_completion_date?->format('d M Y') ?? 'Not specified' }}</span>
                </div>
                @if($project->actual_completion_date)
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Actual Completion</span>
                    <span class="font-semibold text-emerald-600">{{ $project->actual_completion_date->format('d M Y') }}</span>
                </div>
                @endif
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Project Type</span>
                    <span class="font-semibold text-slate-800">{{ ucfirst($project->project_type) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Client RA Bills Table -->
    <div class="soft-card p-5 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Client Invoices & RA Bills</h2>
                <p class="text-[11px] text-slate-400">Progress bills raised to {{ $project->client->name ?? 'Client' }}</p>
            </div>
            @if($canBilling)
            <a href="{{ route('billing.create', ['project_id' => $project->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                + Raise RA Bill
            </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-2.5 px-3">Invoice Number</th>
                        <th class="py-2.5 px-3">Issue Date</th>
                        <th class="py-2.5 px-3">Due Date</th>
                        <th class="py-2.5 px-3 text-right">Bill Total</th>
                        <th class="py-2.5 px-3 text-right">Paid</th>
                        <th class="py-2.5 px-3 text-right">Balance Due</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($clientInvoices as $inv)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-2.5 px-3 font-mono font-semibold text-slate-900">{{ $inv->invoice_number }}</td>
                        <td class="py-2.5 px-3 text-[11px]">{{ $inv->issue_date?->format('d M Y') }}</td>
                        <td class="py-2.5 px-3 text-[11px]">{{ $inv->due_date?->format('d M Y') }}</td>
                        <td class="py-2.5 px-3 text-right font-bold text-slate-900">₹{{ number_format($inv->total_amount, 2) }}</td>
                        <td class="py-2.5 px-3 text-right text-emerald-600 font-medium">₹{{ number_format($inv->paid_amount, 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-bold {{ $inv->balanceDue() > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                            ₹{{ number_format($inv->balanceDue(), 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                {{ ucfirst(str_replace('_', ' ', $inv->status)) }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-right">
                            <a href="{{ route('billing.show', $inv) }}" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                View Bill →
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-slate-400">
                            No client bills raised for this project.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Purchase Orders & Subcontractor Obligations side by side -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Purchase Orders -->
        <div class="soft-card p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Purchase Orders (Material Procurement)</h2>
                @if($canProcure)
                <a href="{{ route('procurement.create', ['project_id' => $project->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">+ Issue PO</a>
                @endif
            </div>

            <div class="space-y-2">
                @forelse($purchaseOrders as $po)
                <div class="flex items-center justify-between p-2.5 bg-slate-50/75 rounded-lg border border-slate-100 text-xs">
                    <div>
                        <span class="font-mono font-semibold text-slate-900">{{ $po->po_number }}</span>
                        <span class="text-[11px] text-slate-500 block">{{ $po->supplier->name ?? 'Vendor' }}</span>
                    </div>
                    <div class="text-right">
                        <strong class="text-slate-900">₹{{ number_format($po->total_amount, 2) }}</strong>
                        <span class="text-[10px] text-slate-400 block">{{ ucfirst(str_replace('_', ' ', $po->status)) }}</span>
                    </div>
                </div>
                @empty
                <p class="text-xs text-slate-400 py-3 text-center">No purchase orders issued.</p>
                @endforelse
            </div>
        </div>

        <!-- Subcontractor Work Orders -->
        <div class="soft-card p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Subcontractor Work Orders (Labor & EPC)</h2>
                <a href="{{ route('work-orders.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">View All →</a>
            </div>

            <div class="space-y-2">
                @forelse($subWorkOrders as $wo)
                <div class="flex items-center justify-between p-2.5 bg-slate-50/75 rounded-lg border border-slate-100 text-xs">
                    <div>
                        <span class="font-mono font-semibold text-slate-900">{{ $wo->wo_number }}</span>
                        <span class="text-[11px] text-slate-500 block">{{ $wo->subcontractor->name ?? 'Subcontractor' }}</span>
                    </div>
                    <div class="text-right">
                        <strong class="text-slate-900">₹{{ number_format($wo->effectiveValue(), 2) }}</strong>
                        <span class="text-[10px] text-slate-400 block">{{ ucfirst(str_replace('_', ' ', $wo->status)) }}</span>
                    </div>
                </div>
                @empty
                <p class="text-xs text-slate-400 py-3 text-center">No subcontractor work orders contracted.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Direct Project Expenses Table -->
    <div class="soft-card p-5 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Direct Project Expenses & Site Vouchers</h2>
                <p class="text-[11px] text-slate-400">Petty cash, site consumables, and emergency operational expenses</p>
            </div>
            <a href="{{ route('expenses.create', ['project_id' => $project->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">+ Add Expense</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-2.5 px-3">Voucher #</th>
                        <th class="py-2.5 px-3">Title / Description</th>
                        <th class="py-2.5 px-3">Category</th>
                        <th class="py-2.5 px-3">Date</th>
                        <th class="py-2.5 px-3 text-right">Amount</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($expenses as $exp)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-2.5 px-3 font-mono font-semibold text-slate-900">{{ $exp->expense_number }}</td>
                        <td class="py-2.5 px-3">{{ $exp->title }}</td>
                        <td class="py-2.5 px-3 text-slate-500">{{ $exp->category->name ?? 'General' }}</td>
                        <td class="py-2.5 px-3 text-[11px]">{{ $exp->expense_date?->format('d M Y') }}</td>
                        <td class="py-2.5 px-3 text-right font-bold text-slate-900">₹{{ number_format($exp->amount, 2) }}</td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                {{ ucfirst($exp->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-400">
                            No direct site expenses logged for this project.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Project Documents Vault -->
    <x-document-manager 
        :record="$project" 
        type="project" 
        title="Project Documentation & Engineering Vault"
        :categories="[
            'contract' => 'Client EPC Contract / Work Order',
            'drawing' => 'Engineering Drawing / Blueprints',
            'specification' => 'Technical Specifications / BOQ',
            'permit' => 'Statutory Permit / NOC / Clearance',
            'inspection_report' => 'QA/QC Inspection Report',
            'progress_photo' => 'Site Progress Photograph',
            'general' => 'General Project Correspondence',
        ]"
    />
</div>
@endsection
