@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('work-orders-register.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Work Orders Register</a>
                <span class="text-gray-300">/</span>
                <span class="text-xs text-gray-500 font-mono">{{ $workOrder->order_number }}</span>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">Document Verification & Intake Review</h2>
            <p class="text-xs text-gray-500">Cross-reference the uploaded original PDF against extracted fields. Confirm verification before operational release.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-xl text-xs font-semibold">
                Status: Pending Human Verification
            </span>
        </div>
    </div>

    <!-- Warnings: Duplicate or Scanned Document -->
    @if($workOrder->is_duplicate_flagged)
    <div class="p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-2xl space-y-1 text-xs text-rose-900">
        <div class="flex items-center gap-2 font-bold text-rose-800 text-sm">
            <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <span>Potential Duplicate Detected</span>
        </div>
        <p class="text-rose-700 pl-7">{{ $workOrder->duplicate_reason }}</p>
        @if($workOrder->duplicateMatchedOrder)
            <div class="pl-7 pt-1">
                <a href="{{ route('work-orders-register.show', $workOrder->duplicateMatchedOrder) }}" target="_blank" class="text-rose-900 font-bold underline hover:text-rose-950">
                    Inspect existing Work Order [{{ $workOrder->duplicateMatchedOrder->order_number }}] in new tab →
                </a>
            </div>
        @endif
    </div>
    @endif

    @if($workOrder->extraction_status === 'scanned_manual_entry_required')
    <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-2xl space-y-1 text-xs text-amber-900">
        <div class="flex items-center gap-2 font-bold text-amber-800 text-sm">
            <span>📷 Scanned Document / Non-Selectable Text</span>
        </div>
        <p class="text-amber-700">This document does not contain selectable digital text stream operators. An OCR engine was not available or produced insufficient text. Please inspect the PDF in the viewer on the left and enter all work order fields manually.</p>
    </div>
    @elseif($workOrder->extraction_status === 'extracted')
    <div class="p-4 bg-blue-50 border-l-4 border-blue-500 rounded-r-2xl space-y-1 text-xs text-blue-900">
        <div class="flex items-center gap-2 font-bold text-blue-800 text-sm">
            <span>✨ Automated Digital Text Extraction</span>
        </div>
        <p class="text-blue-700">Digital text was decompressed and parsed from the uploaded PDF. Candidate fields (dates, reference number, client, and total value) have been pre-filled below for your verification. Please verify them against the original document before confirming.</p>
    </div>
    @endif

    <!-- Dual-Pane Verification Interface -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left Pane: PDF Viewer (lg:col-span-6) -->
        <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col h-[750px]">
            <div class="p-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 truncate">
                    <span class="font-bold text-gray-700 truncate">📄 {{ $primaryDoc?->original_filename ?? 'Original Document' }}</span>
                    @if($primaryDoc)
                        <span class="text-[10px] text-gray-400 font-mono">({{ number_format($primaryDoc->file_size / 1024, 1) }} KB)</span>
                    @endif
                </div>
                @if($primaryDoc)
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('work-orders-register.preview-document', [$workOrder, $primaryDoc]) }}" target="_blank" class="px-2.5 py-1 bg-white hover:bg-gray-100 border border-gray-200 rounded-lg text-gray-700 font-semibold text-[11px] transition">
                        Full Tab ↗
                    </a>
                    <a href="{{ route('work-orders-register.download-document', [$workOrder, $primaryDoc]) }}" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg text-indigo-700 font-semibold text-[11px] transition">
                        Download
                    </a>
                </div>
                @endif
            </div>

            <!-- PDF Viewer Frame -->
            <div class="flex-1 bg-slate-100 relative">
                @if($primaryDoc)
                    <iframe src="{{ route('work-orders-register.preview-document', [$workOrder, $primaryDoc]) }}#toolbar=1"
                            class="w-full h-full border-0"
                            title="Work Order PDF Preview">
                        <p class="p-4 text-xs text-gray-500">Your browser does not support inline PDF viewing. <a href="{{ route('work-orders-register.download-document', [$workOrder, $primaryDoc]) }}" class="text-blue-600 underline">Download the file</a> to view it.</p>
                    </iframe>
                @else
                    <div class="h-full flex items-center justify-center p-6 text-center text-xs text-gray-400">
                        No PDF document attached to this record.
                    </div>
                @endif
            </div>

            @if($primaryDoc)
            <div class="p-2 bg-slate-50 border-t border-slate-100 text-[10px] text-gray-400 font-mono truncate px-3">
                SHA-256: {{ $primaryDoc->checksum }}
            </div>
            @endif
        </div>

        <!-- Right Pane: Verification Form (lg:col-span-6) -->
        <div class="lg:col-span-6 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Work Order Confirmation</h3>
                    <p class="text-xs text-gray-500">Edit and verify values before confirming.</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-gray-400 block">Internal CRM ID</span>
                    <span class="font-mono font-bold text-gray-900 text-xs">{{ $workOrder->order_number }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('work-orders-register.confirm-verification', $workOrder) }}" class="space-y-4 text-xs">
                @csrf

                <!-- Title -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Work Order Title <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $workOrder->title) }}" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500 font-medium">
                </div>

                <!-- External Ref & Client Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Client Work Order / PO Ref #</label>
                        <input type="text" name="external_reference_number" value="{{ old('external_reference_number', $workOrder->external_reference_number) }}"
                               placeholder="e.g. L&T/WO/2026/891"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500 font-mono">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Client</label>
                        <select name="client_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Select Client --</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" {{ old('client_id', $workOrder->client_id) == $c->id ? 'selected' : '' }}>
                                    {{ $c->company_name ?: $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Project, Site, Supervisor Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Project</label>
                        <select name="project_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Unassigned --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ old('project_id', $workOrder->project_id) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Site</label>
                        <select name="site_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Unassigned --</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}" {{ old('site_id', $workOrder->site_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Supervisor</label>
                        <select name="assigned_supervisor_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Unassigned --</option>
                            @foreach($supervisors as $sup)
                                <option value="{{ $sup->id }}" {{ old('assigned_supervisor_id', $workOrder->assigned_supervisor_id) == $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Dates & Value Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Issue Date</label>
                        <input type="date" name="issue_date" value="{{ old('issue_date', $workOrder->issue_date?->format('Y-m-d')) }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Target Completion</label>
                        <input type="date" name="target_completion_date" value="{{ old('target_completion_date', $workOrder->target_completion_date?->format('Y-m-d')) }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Total Value (INR)</label>
                        <input type="number" step="0.01" min="0" name="total_amount" value="{{ old('total_amount', $workOrder->total_amount) }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500 font-mono font-bold text-gray-900">
                    </div>
                </div>

                <!-- Description / Scope -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Scope Summary & Extracted Notes</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">{{ old('description', $workOrder->description) }}</textarea>
                </div>

                <!-- Verification Audit Note -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Staff Verification Note <span class="text-gray-400 font-normal">(Audit Trail)</span></label>
                    <input type="text" name="verification_notes" value="{{ old('verification_notes', 'Verified against original client PDF.') }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                </div>

                <!-- Submission Controls -->
                <div class="pt-2 border-t border-gray-100 space-y-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="submit_for_approval" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-gray-700 font-medium">Forward to Management for operational approval immediately</span>
                    </label>

                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('work-orders-register.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition">
                            Back to Register
                        </a>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>Confirm Document Verification</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
