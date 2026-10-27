@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('work-orders-register.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Work Orders Register</a>
                <span class="text-gray-300">/</span>
                <span class="text-xs text-gray-500 font-mono">{{ $workOrder->order_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $workOrder->title }}</h2>
                @if($workOrder->isClientIssued())
                    <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-700 font-semibold rounded-full text-xs border border-indigo-200">
                        Client Issued
                    </span>
                @else
                    <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 font-semibold rounded-full text-xs border border-blue-200">
                        Internal Operation
                    </span>
                @endif
            </div>
        </div>

        <!-- Management Actions -->
        <div class="flex items-center gap-2">
            @if(in_array($workOrder->extraction_status, ['extracted', 'scanned_manual_entry_required']))
                <a href="{{ route('work-orders-register.verify', $workOrder) }}" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    <span>Verify Document Fields →</span>
                </a>
            @endif

            @if($workOrder->status !== 'approved' && $workOrder->status !== 'completed' && $workOrder->status !== 'cancelled')
                <button type="button" onclick="document.getElementById('approveModal').classList.remove('hidden')" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Approve Work Order</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Duplicate Alert Banner -->
    @if($workOrder->is_duplicate_flagged)
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-2xl space-y-2 text-xs text-rose-900">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold text-rose-800 text-sm">
                <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>Safeguard Alert: Non-Destructive Duplicate Flag</span>
            </div>
            <button type="button" onclick="document.getElementById('dismissModal').classList.remove('hidden')" class="px-3 py-1 bg-white hover:bg-rose-100 border border-rose-200 text-rose-700 rounded-lg font-semibold text-[11px] transition">
                Dismiss Flag with Reason
            </button>
        </div>
        <p class="text-rose-700 pl-7">{{ $workOrder->duplicate_reason }}</p>
        @if($workOrder->duplicateMatchedOrder)
            <div class="pl-7">
                <a href="{{ route('work-orders-register.show', $workOrder->duplicateMatchedOrder) }}" class="text-rose-900 font-bold underline hover:text-rose-950">
                    View matching Work Order [{{ $workOrder->duplicateMatchedOrder->order_number }}] →
                </a>
            </div>
        @endif
    </div>
    @endif

    <!-- 4 Key Attributes Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Internal CRM ID -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Internal CRM ID</span>
            <div class="text-lg font-black text-gray-900 mt-1 font-mono">{{ $workOrder->order_number }}</div>
            <span class="text-[11px] text-gray-400">Created by {{ $workOrder->creator?->name }}</span>
        </div>

        <!-- External Reference / Client Ref -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Client External Ref</span>
            <div class="text-lg font-bold text-slate-800 mt-1 font-mono truncate" title="{{ $workOrder->external_reference_number }}">
                {{ $workOrder->external_reference_number ?: 'N/A (Self-Directed)' }}
            </div>
            <span class="text-[11px] text-gray-400">{{ $workOrder->client ? ($workOrder->client->company_name ?: $workOrder->client->name) : 'No client specified' }}</span>
        </div>

        <!-- Order Valuation -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Committed Order Value</span>
            <div class="text-lg font-black text-emerald-600 mt-1 font-mono">
                ₹{{ number_format($workOrder->total_amount, 2) }}
            </div>
            <span class="text-[11px] text-gray-400">{{ $workOrder->items->count() }} BOQ line items</span>
        </div>

        <!-- Management & Verification Status -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Workflow Governance</span>
            <div class="flex items-center gap-2 mt-1">
                @php
                    $statusStyles = [
                        'draft' => 'bg-gray-100 text-gray-700',
                        'pending_approval' => 'bg-amber-100 text-amber-800',
                        'approved' => 'bg-emerald-100 text-emerald-800',
                        'in_progress' => 'bg-blue-100 text-blue-800',
                        'completed' => 'bg-indigo-100 text-indigo-800',
                        'cancelled' => 'bg-rose-100 text-rose-800',
                    ];
                @endphp
                <span class="px-2 py-0.5 font-bold rounded-md text-xs {{ $statusStyles[$workOrder->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
                </span>
                @if($workOrder->isVerified())
                    <span class="px-2 py-0.5 font-bold rounded-md text-xs bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Verified
                    </span>
                @endif
            </div>
            <span class="text-[10px] text-gray-400 block mt-1">
                @if($workOrder->approved_at)
                    Approved by {{ $workOrder->approver?->name }} on {{ $workOrder->approved_at->format('d M Y') }}
                @elseif($workOrder->verified_at)
                    Verified by {{ $workOrder->verifier?->name }} on {{ $workOrder->verified_at->format('d M Y') }}
                @else
                    Pending verification
                @endif
            </span>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Operational Details Card -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Operational Assignment</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-gray-400 block">Project:</span>
                        <span class="font-semibold text-gray-900">{{ $workOrder->project?->name ?? 'General Operations' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Site:</span>
                        <span class="font-semibold text-gray-900">{{ $workOrder->site?->name ?? 'Unassigned' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Supervisor:</span>
                        <span class="font-semibold text-gray-900">{{ $workOrder->assignedSupervisor?->name ?? 'Unassigned' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Issue Date:</span>
                        <span class="font-semibold text-gray-900">{{ $workOrder->issue_date ? $workOrder->issue_date->format('d M Y') : '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Start Date:</span>
                        <span class="font-semibold text-gray-900">{{ $workOrder->start_date ? $workOrder->start_date->format('d M Y') : '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Target Completion:</span>
                        <span class="font-semibold text-gray-900">{{ $workOrder->target_completion_date ? $workOrder->target_completion_date->format('d M Y') : '—' }}</span>
                    </div>
                </div>

                @if($workOrder->description)
                <div class="pt-3 border-t border-gray-100">
                    <span class="text-xs font-bold text-gray-700 block mb-1">Scope of Work & Notes:</span>
                    <p class="text-xs text-gray-600 whitespace-pre-line bg-slate-50 p-3 rounded-xl">{{ $workOrder->description }}</p>
                </div>
                @endif
            </div>

            <!-- BOQ / Work Items Schedule -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <h3 class="text-sm font-bold text-gray-900">BOQ Schedule & Line Items</h3>
                    <span class="text-xs text-gray-400">{{ $workOrder->items->count() }} items listed</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="py-2.5 px-3 w-12 text-center">#</th>
                                <th class="py-2.5 px-3">Description</th>
                                <th class="py-2.5 px-3">Category</th>
                                <th class="py-2.5 px-3 w-20 text-center">Unit</th>
                                <th class="py-2.5 px-3 w-24 text-right">Quantity</th>
                                <th class="py-2.5 px-3 w-28 text-right">Unit Rate (₹)</th>
                                <th class="py-2.5 px-3 w-28 text-right">Total (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($workOrder->items as $item)
                            <tr>
                                <td class="py-2.5 px-3 text-center text-gray-400 font-mono">{{ $item->item_number }}</td>
                                <td class="py-2.5 px-3 font-medium text-gray-900">
                                    {{ $item->description }}
                                    @if($item->notes)
                                        <span class="block text-[11px] text-gray-400">{{ $item->notes }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-gray-600">{{ $item->workCategory?->name ?? 'General' }}</td>
                                <td class="py-2.5 px-3 text-center font-mono">{{ $item->unit_of_measure }}</td>
                                <td class="py-2.5 px-3 text-right font-mono">{{ number_format($item->quantity, 3) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono">₹{{ number_format($item->unit_rate, 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900">₹{{ number_format($item->total_price, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400 italic">
                                    No itemized BOQ lines entered. Total order value recorded as a lump sum: ₹{{ number_format($workOrder->total_amount, 2) }}.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 bg-slate-50/50 font-bold text-xs">
                                <td colspan="6" class="py-3 px-3 text-right text-gray-700">Total Work Order Value:</td>
                                <td class="py-3 px-3 text-right font-mono text-sm text-emerald-700">₹{{ number_format($workOrder->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar: Documents & Governance Audit (1 col) -->
        <div class="space-y-6">
            <!-- Documents Card -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <h3 class="text-sm font-bold text-gray-900">Attached Documents</h3>
                    <span class="text-xs text-gray-400">{{ $workOrder->documents->count() }} file(s)</span>
                </div>

                <div class="space-y-3">
                    @forelse($workOrder->documents as $doc)
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-800 truncate" title="{{ $doc->original_filename }}">
                                📄 {{ $doc->original_filename }}
                            </span>
                            <span class="text-[10px] text-gray-400 font-mono">
                                {{ number_format($doc->file_size / 1024, 1) }} KB
                            </span>
                        </div>
                        <div class="text-[10px] text-gray-400 font-mono truncate">
                            SHA-256: {{ $doc->checksum }}
                        </div>
                        <div class="flex items-center gap-2 pt-1 border-t border-slate-200">
                            @if($doc->isPreviewable())
                            <a href="{{ route('work-orders-register.preview-document', [$workOrder, $doc]) }}" target="_blank"
                               class="px-2.5 py-1 bg-white hover:bg-gray-100 border border-gray-200 rounded-lg text-gray-700 font-semibold text-[11px] transition flex-1 text-center">
                                Preview
                            </a>
                            @endif
                            <a href="{{ route('work-orders-register.download-document', [$workOrder, $doc]) }}"
                               class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg text-indigo-700 font-semibold text-[11px] transition flex-1 text-center">
                                Download
                            </a>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 italic">No original documents attached.</p>
                    @endforelse
                </div>
            </div>

            <!-- Verification & Audit Trail -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-3 text-xs">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Audit & Compliance</h3>
                
                <div class="space-y-2 text-[11px]">
                    <div>
                        <span class="text-gray-400 block">Created by:</span>
                        <span class="font-semibold text-gray-800">{{ $workOrder->creator?->name }}</span>
                        <span class="text-gray-400"> on {{ $workOrder->created_at->format('d M Y, h:i A') }}</span>
                    </div>

                    @if($workOrder->verified_by)
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-gray-400 block">Document Verified by:</span>
                        <span class="font-semibold text-emerald-700">{{ $workOrder->verifier?->name }}</span>
                        <span class="text-gray-400"> on {{ $workOrder->verified_at?->format('d M Y, h:i A') }}</span>
                        @if($workOrder->verification_notes)
                            <p class="text-gray-500 italic mt-0.5">"{{ $workOrder->verification_notes }}"</p>
                        @endif
                    </div>
                    @endif

                    @if($workOrder->approved_by)
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-gray-400 block">Operational Approval:</span>
                        <span class="font-semibold text-blue-700">{{ $workOrder->approver?->name }}</span>
                        <span class="text-gray-400"> on {{ $workOrder->approved_at?->format('d M Y, h:i A') }}</span>
                        @if($workOrder->approval_notes)
                            <p class="text-gray-500 italic mt-0.5">"{{ $workOrder->approval_notes }}"</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Approve Work Order -->
<div id="approveModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <h3 class="text-base font-bold text-gray-900">Approve Work Order for Execution</h3>
        <p class="text-xs text-gray-500">Approving this work order authorizes site supervisors to mobilize resources and issue materials against it. No financial journal entries are created upon approval.</p>

        <form method="POST" action="{{ route('work-orders-register.approve', $workOrder) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Approval Notes (Optional)</label>
                <textarea name="notes" rows="2" class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl" placeholder="e.g. Approved per contract terms signed on 25th Oct..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-xs font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm">Confirm Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Dismiss Duplicate Warning -->
<div id="dismissModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <h3 class="text-base font-bold text-gray-900">Dismiss Duplicate Warning</h3>
        <p class="text-xs text-gray-500">State the business reason why this order is legitimate and not a duplicate (e.g. "Amendment #2 under same client reference", or "Separate sub-package"). This will be recorded in the audit trail.</p>

        <form method="POST" action="{{ route('work-orders-register.dismiss-duplicate', $workOrder) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Justification Reason <span class="text-rose-500">*</span></label>
                <textarea name="reason" rows="3" required class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl" placeholder="e.g. Reviewed with accounts: this is a distinct supplemental order..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('dismissModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-xs font-semibold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-sm">Dismiss Flag</button>
            </div>
        </form>
    </div>
</div>
@endsection
