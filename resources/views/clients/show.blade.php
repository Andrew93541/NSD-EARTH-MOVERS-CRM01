@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('clients.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    ← Client Directory
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $client->name }}</h1>
                @if($client->status === 'active')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Active Client
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        Inactive
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-1">
                {{ $client->company_name ?? 'Direct Employer' }} • Registered {{ $client->created_at?->format('d M Y') }}
            </p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('clients.manage') || $u->hasPermission('clients.*') || $u->hasPermission('projects.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
            $canBilling = $u && ($u->hasPermission('*') || $u->hasPermission('billing.*') || $u->hasPermission('finance.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'accountant']));
        @endphp

        <div class="flex items-center gap-2">
            @if($canBilling)
            <a href="{{ route('billing.create', ['client_id' => $client->id]) }}" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                + Raise RA Bill
            </a>
            @endif
            @if($canManage)
            <a href="{{ route('projects.create', ['client_id' => $client->id]) }}" class="px-3.5 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                + New Project
            </a>
            <a href="{{ route('clients.edit', $client) }}" class="px-3.5 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                Edit Details
            </a>
            @endif
        </div>
    </div>

    <!-- 5 Financial Stat Cards for Client Portfolio -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Agreed Contract Value</span>
            <div class="text-xl font-extrabold text-slate-900 mt-1">₹{{ number_format($financials['contract_value'], 2) }}</div>
            <span class="text-[11px] text-slate-500">{{ $projects->count() }} total contract(s)</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Client Bills Raised</span>
            <div class="text-xl font-extrabold text-blue-600 mt-1">₹{{ number_format($financials['bills_raised'], 2) }}</div>
            <span class="text-[11px] text-slate-500">{{ $invoices->count() }} invoice(s) submitted</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Money Received</span>
            <div class="text-xl font-extrabold text-emerald-600 mt-1">₹{{ number_format($financials['money_received'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Receipts credited</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Outstanding Balance</span>
            <div class="text-xl font-extrabold {{ $financials['outstanding_balance'] > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">
                ₹{{ number_format($financials['outstanding_balance'], 2) }}
            </div>
            <span class="text-[11px] text-slate-500">Receivable from client</span>
        </div>

        <div class="soft-card p-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Unbilled Contract Balance</span>
            <div class="text-xl font-extrabold text-slate-700 mt-1">₹{{ number_format($financials['unbilled_contract_value'], 2) }}</div>
            <span class="text-[11px] text-slate-500">Remaining contract cap</span>
        </div>
    </div>

    <!-- Client Profile Details & Contacts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Contact & Identification Card -->
        <div class="soft-card p-5 space-y-4">
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Key Information & Statutory IDs</h2>
            
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Key Contact Person</span>
                    <span class="font-semibold text-slate-800">{{ $client->contact_person ?? 'Not designated' }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Phone Number</span>
                        <span class="font-mono text-slate-700">{{ $client->phone ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Email Address</span>
                        <span class="text-slate-700">{{ $client->email ?? '—' }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">GSTIN</span>
                        <span class="font-mono font-semibold text-slate-900">{{ $client->gstin ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">PAN</span>
                        <span class="font-mono font-semibold text-slate-700">{{ $client->pan ?? '—' }}</span>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">City & State</span>
                    <span class="text-slate-700">{{ collect([$client->city, $client->state])->filter()->join(', ') ?: '—' }}</span>
                </div>

                @if($client->billing_address)
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Billing Address</span>
                    <p class="text-slate-600 text-xs mt-0.5 leading-relaxed">{{ $client->billing_address }}</p>
                </div>
                @endif

                @if($client->shipping_address)
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Shipping / Correspondence Address</span>
                    <p class="text-slate-600 text-xs mt-0.5 leading-relaxed">{{ $client->shipping_address }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Associated Projects Card -->
        <div class="lg:col-span-2 soft-card p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Associated Construction Projects</h2>
                    <p class="text-[11px] text-slate-400">Projects awarded or contracted by {{ $client->name }}</p>
                </div>
                @if($canManage)
                <a href="{{ route('projects.create', ['client_id' => $client->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    + Add Project
                </a>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($projects as $p)
                <div class="p-3 bg-slate-50/75 rounded-xl border border-slate-100 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900">{{ $p->name }}</span>
                            <span class="font-mono text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-semibold border border-blue-200">
                                {{ $p->code }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span>Contract: <strong class="text-slate-800">₹{{ number_format($p->contract_value, 2) }}</strong></span>
                            <span>Manager: {{ $p->manager->name ?? 'Unassigned' }}</span>
                            <span>Sites: {{ $p->sites->count() }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <span class="text-[11px] font-bold text-blue-600">{{ $p->progress_percent }}%</span>
                            <div class="w-16 bg-slate-200 h-1.5 rounded-full overflow-hidden mt-0.5">
                                <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $p->progress_percent }}%"></div>
                            </div>
                        </div>
                        <a href="{{ route('projects.show', $p) }}" class="px-2.5 py-1.5 bg-white border border-slate-200 text-slate-700 hover:text-blue-600 rounded-lg text-xs font-semibold shadow-2xs transition">
                            Project View →
                        </a>
                    </div>
                </div>
                @empty
                <div class="text-center py-6 text-slate-400">
                    <p class="text-xs">No projects associated with this client yet.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Client RA Bills / Invoices History -->
    <div class="soft-card p-5 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Client Billing & RA Invoices</h2>
                <p class="text-[11px] text-slate-400">Certified running account bills, payments, and receipts</p>
            </div>
            @if($canBilling)
            <a href="{{ route('billing.create', ['client_id' => $client->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                + Raise RA Bill
            </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-2.5 px-3">Invoice Number</th>
                        <th class="py-2.5 px-3">Project</th>
                        <th class="py-2.5 px-3">Dates</th>
                        <th class="py-2.5 px-3 text-right">Bill Amount</th>
                        <th class="py-2.5 px-3 text-right">Paid</th>
                        <th class="py-2.5 px-3 text-right">Balance Due</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-600 font-medium">
                    @forelse($invoices as $inv)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-2.5 px-3 font-mono font-semibold text-slate-900">
                            {{ $inv->invoice_number }}
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="font-semibold text-slate-800">{{ $inv->project->name ?? '—' }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-[11px]">
                            <span>{{ $inv->issue_date?->format('d M Y') }}</span>
                            <span class="text-slate-400 block text-[10px]">Due: {{ $inv->due_date?->format('d M Y') }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold text-slate-900">
                            ₹{{ number_format($inv->total_amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right text-emerald-600 font-medium">
                            ₹{{ number_format($inv->paid_amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold {{ $inv->balanceDue() > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                            ₹{{ number_format($inv->balanceDue(), 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            @php
                                $statusClasses = match($inv->status) {
                                    'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'partially_paid' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'approved' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                    'submitted' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'overdue' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusClasses }}">
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
                            No billing records raised for this client.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Client Documentation Vault -->
    <x-document-manager 
        :record="$client" 
        type="client" 
        title="Client Documentation & Master Contracts"
        :categories="[
            'contract' => 'Client EPC Master Agreement / LOI',
            'tax' => 'Client GST / PAN Registration Certificate',
            'correspondence' => 'Official Client Notice / Letter',
            'general' => 'General Client Document',
        ]"
    />
</div>
@endsection
