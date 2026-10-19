@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{ convertModalOpen: false, rejectModalOpen: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('quotations.index') }}" class="text-xs text-blue-600 hover:underline">← Quotations Register</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-xs font-bold text-slate-500">{{ $quotation->quotation_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $quotation->project_title }}</h1>
                @php
                    $badgeClass = match($quotation->status) {
                        'draft' => 'bg-slate-100 text-slate-700 border-slate-300',
                        'sent' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                        'expired' => 'bg-gray-100 text-gray-500 border-gray-300',
                        default => 'bg-slate-50 text-slate-600 border-slate-200',
                    };
                @endphp
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badgeClass }}">
                    {{ ucfirst($quotation->status) }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Client: <span class="font-semibold text-slate-800">{{ $quotation->client->name }}</span> | Created by {{ $quotation->creator->name ?? 'System' }} on {{ $quotation->date->format('d M Y') }}</p>
        </div>

        @php
            $u = auth()->user();
            $canManage = $u && ($u->hasPermission('*') || $u->hasPermission('quotations.*') || in_array($u->currentRole()?->slug, ['owner', 'admin', 'project_manager', 'accountant']));
        @endphp

        <!-- Action Bar -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Print Button -->
            <a href="{{ route('quotations.print', $quotation) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print / PDF View</span>
            </a>

            @if($canManage && ! $quotation->converted_project_id)
                @if(in_array($quotation->status, ['draft', 'rejected']))
                <a href="{{ route('quotations.edit', $quotation) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Edit</span>
                </a>
                @endif

                <!-- Status Transition Buttons -->
                @if($quotation->status === 'draft')
                <form method="POST" action="{{ route('quotations.status', $quotation) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="sent">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <span>Mark as Sent</span>
                    </button>
                </form>
                @elseif($quotation->status === 'sent')
                <form method="POST" action="{{ route('quotations.status', $quotation) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="accepted">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Mark Accepted</span>
                    </button>
                </form>

                <button type="button" @click="rejectModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 rounded-xl text-xs font-semibold transition">
                    <span>Reject</span>
                </button>
                @endif

                <!-- Convert to Project Button (only when accepted and not yet converted) -->
                @if($quotation->status === 'accepted' && ! $quotation->converted_project_id)
                <button type="button" @click="convertModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Award & Convert to Project</span>
                </button>
                @endif
            @endif
        </div>
    </div>

    <!-- Alert Banner: Converted to Project -->
    @if($quotation->convertedProject)
    <div class="p-4 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 rounded-2xl flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-xs font-bold text-indigo-900">Quotation Converted into Active Project</div>
                <div class="text-[11px] text-indigo-700 mt-0.5">
                    Project: <span class="font-bold">{{ $quotation->convertedProject->name }}</span> (Code: {{ $quotation->convertedProject->code }}) |
                    Awarded: {{ $quotation->converted_at?->format('d M Y, h:i A') }} by {{ $quotation->converter->name ?? 'Admin' }}
                </div>
            </div>
        </div>
        <a href="{{ route('projects.show', $quotation->convertedProject) }}"
           class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
            <span>View Live Project</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>
    @endif

    <!-- Alert Banner: Rejected with reason -->
    @if($quotation->status === 'rejected' && $quotation->rejection_reason)
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl">
        <div class="text-xs font-bold text-rose-800">Quotation Rejected by Client</div>
        <div class="text-xs text-rose-700 mt-1 italic">"{{ $quotation->rejection_reason }}"</div>
    </div>
    @endif

    <!-- Information Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Client Card -->
        <div class="soft-card p-5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Client Details</span>
            <div class="font-bold text-slate-900 text-sm">{{ $quotation->client->name }}</div>
            <div class="text-xs text-slate-600 mt-0.5">{{ $quotation->client->company_name ?? 'Client Organization' }}</div>
            <div class="mt-3 pt-3 border-t border-slate-100 space-y-1 text-xs text-slate-500">
                <div><span class="text-slate-400">Contact:</span> {{ $quotation->client->contact_person ?? 'N/A' }}</div>
                <div><span class="text-slate-400">Email:</span> {{ $quotation->client->email ?? 'N/A' }}</div>
                <div><span class="text-slate-400">Phone:</span> {{ $quotation->client->phone ?? 'N/A' }}</div>
                <div><span class="text-slate-400 font-mono">GSTIN:</span> {{ $quotation->client->gstin ?? 'Unregistered' }}</div>
            </div>
        </div>

        <!-- Quotation Metadata Card -->
        <div class="soft-card p-5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Estimate Parameters</span>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Quotation Number:</span>
                    <span class="font-mono font-bold text-slate-900">{{ $quotation->quotation_number }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Date of Quotation:</span>
                    <span class="font-semibold text-slate-800">{{ $quotation->date->format('d M Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Validity Period:</span>
                    <span class="font-semibold text-slate-800">{{ $quotation->valid_until->format('d M Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Tax Regime:</span>
                    <span class="font-semibold text-blue-600 uppercase">{{ str_replace('_', ' ', $quotation->tax_type) }} ({{ $quotation->tax_rate }}%)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Commercial Status:</span>
                    <span class="font-semibold text-slate-800 capitalize">{{ $quotation->status }}</span>
                </div>
            </div>
        </div>

        <!-- Commercial Total Summary Card -->
        <div class="soft-card p-5 bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex flex-col justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-200 block">Total Quotation Value</span>
                <div class="text-3xl font-extrabold font-mono mt-2">₹{{ number_format($quotation->total_amount, 2) }}</div>
                <div class="text-xs text-blue-100 mt-1">Taxable Subtotal: ₹{{ number_format($quotation->subtotal, 2) }}</div>
            </div>
            <div class="text-[11px] text-blue-200 pt-3 border-t border-blue-500/50 mt-4">
                <span>Inclusive of ₹{{ number_format($quotation->tax_amount, 2) }} estimated GST</span>
            </div>
        </div>
    </div>

    <!-- Technical Scope & Description -->
    @if($quotation->project_description)
    <div class="soft-card p-5">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Scope of Work</span>
        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ $quotation->project_description }}</p>
    </div>
    @endif

    <!-- Schedule of Quantities Table -->
    <div class="soft-card overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 tracking-tight">Schedule of Quantities & Line Items</h3>
            <span class="text-xs text-slate-400">{{ $quotation->items->count() }} item(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4">Description of Works</th>
                        <th class="py-3 px-4 text-right w-28">Quantity</th>
                        <th class="py-3 px-4 text-center w-24">Unit</th>
                        <th class="py-3 px-4 text-right w-32">Rate (₹)</th>
                        <th class="py-3 px-4 text-right w-36">Total Amount (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($quotation->items as $index => $item)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="py-3 px-4 font-mono text-slate-400 text-center font-bold">{{ $index + 1 }}</td>
                        <td class="py-3 px-4 text-slate-800 font-medium leading-relaxed">
                            {{ $item->description }}
                            @if($item->notes)
                            <span class="block text-[11px] text-slate-400 mt-0.5">{{ $item->notes }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-slate-700">{{ number_format($item->quantity, 3) }}</td>
                        <td class="py-3 px-4 text-center text-slate-600 font-medium">{{ $item->unit }}</td>
                        <td class="py-3 px-4 text-right font-mono text-slate-700">₹{{ number_format($item->unit_rate, 2) }}</td>
                        <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($item->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50/50 border-t border-slate-200 font-semibold text-slate-700">
                        <td colspan="5" class="py-2.5 px-4 text-right">Subtotal:</td>
                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($quotation->subtotal, 2) }}</td>
                    </tr>
                    @if($quotation->discount_amount > 0)
                    <tr class="text-rose-600">
                        <td colspan="5" class="py-2 px-4 text-right">
                            Discount {{ $quotation->discount_type === 'percentage' ? "({$quotation->discount_rate}%)" : '' }}:
                        </td>
                        <td class="py-2 px-4 text-right font-mono font-bold">-₹{{ number_format($quotation->discount_amount, 2) }}</td>
                    </tr>
                    <tr class="bg-slate-50/25 font-semibold text-slate-700">
                        <td colspan="5" class="py-2 px-4 text-right">Taxable Amount:</td>
                        <td class="py-2 px-4 text-right font-mono font-bold text-slate-900">
                            ₹{{ number_format($quotation->subtotal - $quotation->discount_amount, 2) }}
                        </td>
                    </tr>
                    @endif

                    @if($quotation->tax_type === 'gst_18')
                    <tr class="text-slate-600">
                        <td colspan="5" class="py-1.5 px-4 text-right">CGST (9.0%):</td>
                        <td class="py-1.5 px-4 text-right font-mono">₹{{ number_format($quotation->tax_amount / 2, 2) }}</td>
                    </tr>
                    <tr class="text-slate-600">
                        <td colspan="5" class="py-1.5 px-4 text-right">SGST (9.0%):</td>
                        <td class="py-1.5 px-4 text-right font-mono">₹{{ number_format($quotation->tax_amount / 2, 2) }}</td>
                    </tr>
                    @elseif($quotation->tax_amount > 0)
                    <tr class="text-slate-600">
                        <td colspan="5" class="py-1.5 px-4 text-right">Tax ({{ $quotation->tax_rate }}%):</td>
                        <td class="py-1.5 px-4 text-right font-mono">₹{{ number_format($quotation->tax_amount, 2) }}</td>
                    </tr>
                    @endif

                    <tr class="bg-blue-50/50 border-t-2 border-slate-300 text-sm">
                        <td colspan="5" class="py-3 px-4 text-right font-bold text-slate-900">Total Quotation Value:</td>
                        <td class="py-3 px-4 text-right font-mono font-extrabold text-blue-700 text-base">₹{{ number_format($quotation->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Commercial Terms & Conditions -->
    @if($quotation->terms_conditions)
    <div class="soft-card p-5">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Terms & Commercial Conditions</span>
        <div class="text-xs text-slate-700 leading-relaxed whitespace-pre-line bg-slate-50 p-4 rounded-xl border border-slate-100 font-mono">{{ $quotation->terms_conditions }}</div>
    </div>
    @endif

    <!-- Convert to Project Modal -->
    <div x-show="convertModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="convertModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Award & Convert to Project</h3>
                </div>
                <button @click="convertModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <p class="text-xs text-slate-600">
                You are about to convert quotation <span class="font-bold text-slate-900">{{ $quotation->quotation_number }}</span> into an active construction project for <span class="font-bold text-slate-900">{{ $quotation->client->name }}</span>.
            </p>

            <form method="POST" action="{{ route('quotations.convert', $quotation) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Project Name *</label>
                    <input type="text" name="project_name" value="{{ $quotation->project_title }}" required
                           class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="{{ date('Y-m-d') }}"
                               class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Completion</label>
                        <input type="date" name="target_completion_date" value="{{ date('Y-m-d', strtotime('+6 months')) }}"
                               class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="p-3 bg-indigo-50/75 rounded-xl border border-indigo-100 text-[11px] text-indigo-900 space-y-1">
                    <div class="font-bold">Project Financial Initializer:</div>
                    <div>Contract Amount: <span class="font-mono font-bold">₹{{ number_format($quotation->total_amount, 2) }}</span></div>
                    <div>Approved Budget: <span class="font-mono font-bold">₹{{ number_format($quotation->subtotal, 2) }}</span></div>
                    <div class="text-indigo-600 text-[10px] mt-1">This action cannot be repeated. The quotation will be permanently linked to the new project.</div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="convertModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition">
                        Confirm & Create Project
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div x-show="rejectModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.away="rejectModalOpen = false">
            <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Record Rejection Reason</h3>
            <form method="POST" action="{{ route('quotations.status', $quotation) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="rejected">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Rejection / Lost Bid</label>
                    <textarea name="rejection_reason" rows="3" required placeholder="e.g. Client opted for lower bidder, project shelved, budget constraints..."
                              class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="rejectModalOpen = false" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-medium">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
