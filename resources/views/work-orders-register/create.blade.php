@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('work-orders-register.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">← Work Orders Register</a>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight mt-1">Create Work Order</h2>
            <p class="text-xs text-gray-500">Choose between importing an incoming Client PDF Work Order or authoring an Internal operational order.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-800 space-y-1">
        <span class="font-bold block">Please correct the following errors:</span>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Mode Selector Tabs -->
    @php
        $activeTab = request('tab') === 'internal' ? 'internal' : 'client';
    @endphp
    <div class="flex border-b border-gray-200 text-xs font-semibold">
        <button type="button" onclick="switchTab('client')" id="tabBtnClient"
                class="px-5 py-3 border-b-2 transition flex items-center gap-2 {{ $activeTab === 'client' ? 'border-indigo-600 text-indigo-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Client-Issued Work Order (PDF Upload & Extraction)</span>
        </button>
        <button type="button" onclick="switchTab('internal')" id="tabBtnInternal"
                class="px-5 py-3 border-b-2 transition flex items-center gap-2 {{ $activeTab === 'internal' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span>Internal Work Order (Self-Directed / Site BOQ)</span>
        </button>
    </div>

    <!-- Mode A: Client-Issued Work Order PDF Upload -->
    <div id="clientTabContent" class="{{ $activeTab === 'client' ? '' : 'hidden' }} space-y-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6">
            <div class="border-b border-gray-100 pb-4">
                <h3 class="text-sm font-bold text-gray-900">Upload Client Work Order PDF</h3>
                <p class="text-xs text-gray-500 mt-1">Upload the original purchase order or work order received from the client. The system will store the file securely, attempt automated text extraction, check for duplicates, and guide you to verification.</p>
            </div>

            <form method="POST" action="{{ route('work-orders-register.upload-client') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- File Drop Zone -->
                <div class="border-2 border-dashed border-gray-200 hover:border-indigo-400 rounded-2xl p-8 text-center bg-slate-50/50 hover:bg-indigo-50/30 transition cursor-pointer" onclick="document.getElementById('pdfInput').click()">
                    <input type="file" name="file" id="pdfInput" accept="application/pdf" class="hidden" onchange="handleFileSelected(this)">
                    <div class="space-y-3">
                        <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-indigo-700 hover:underline">Click to browse</span>
                            <span class="text-xs text-gray-500"> or drag and drop your Client Work Order PDF</span>
                        </div>
                        <p class="text-[11px] text-gray-400">PDF documents only (up to 15 MB). Scanned documents are supported with manual entry fallback.</p>
                        <div id="fileSelectedBadge" class="hidden inline-flex items-center gap-2 px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-lg mt-2">
                            📄 <span id="fileNameDisplay"></span>
                        </div>
                    </div>
                </div>

                <!-- Document Safeguard & Audit Notice -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs space-y-2">
                    <span class="font-bold text-slate-700 flex items-center gap-1.5">
                        🛡️ Document Safeguard & Financial Controls
                    </span>
                    <ul class="text-slate-600 space-y-1 list-disc pl-5 text-[11px]">
                        <li>Original documents are stored in private storage with SHA-256 checksum audit logging.</li>
                        <li>Extracted values are treated as drafts until confirmed by authorized staff in the verification step.</li>
                        <li>Duplicate submissions are flagged non-destructively; no records are automatically merged or deleted.</li>
                        <li>Uploading a work order creates an operational commitment only; zero financial journal postings occur.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('work-orders-register.index') }}" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Cancel</a>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-2">
                        <span>Upload & Proceed to Verification →</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mode B: Internal Work Order Form -->
    <div id="internalTabContent" class="{{ $activeTab === 'internal' ? '' : 'hidden' }} space-y-6">
        <form method="POST" action="{{ route('work-orders-register.store-internal') }}" enctype="multipart/form-data" class="space-y-6" id="internalOrderForm">
            @csrf

            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h3 class="text-sm font-bold text-gray-900">Internal Work Order Details</h3>
                    <p class="text-xs text-gray-500 mt-1">For company-directed jobs, site maintenance, or internal operational directives. No fake client or contract required.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                    <!-- Title -->
                    <div class="md:col-span-2">
                        <label class="block font-semibold text-gray-700 mb-1">Work Order Title / Directive <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" required
                               placeholder="e.g. Site Levelling & Compaction Phase 2"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Internal Code / Ref -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Internal Reference / Tracking #</label>
                        <input type="text" name="external_reference_number" value="{{ old('external_reference_number') }}"
                               placeholder="e.g. INT-EARTH-01"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500 font-mono">
                    </div>

                    <!-- Project -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Project Linkage</label>
                        <select name="project_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- No Specific Project (General Ops) --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ old('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Site -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Site</label>
                        <select name="site_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Unassigned Site --</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}" {{ old('site_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Assigned Supervisor -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Assigned Supervisor / Engineer</label>
                        <select name="assigned_supervisor_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Unassigned --</option>
                            @foreach($supervisors as $sup)
                                <option value="{{ $sup->id }}" {{ old('assigned_supervisor_id') == $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Issue Date -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Issue Date</label>
                        <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="{{ old('start_date') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Target Completion Date -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Target Completion Date</label>
                        <input type="date" name="target_completion_date" value="{{ old('target_completion_date') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Scope / Description -->
                    <div class="col-span-full">
                        <label class="block font-semibold text-gray-700 mb-1">Scope of Work & Technical Directives</label>
                        <textarea name="description" rows="3"
                                  placeholder="Describe the operational scope, machine assignments, or performance guidelines..."
                                  class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-blue-500">{{ old('description') }}</textarea>
                    </div>

                    <!-- Optional Attachment -->
                    <div class="col-span-full">
                        <label class="block font-semibold text-gray-700 mb-1">Optional Scope Document / Technical Drawing</label>
                        <input type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg,.docx,.xlsx"
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>
            </div>

            <!-- Line Items / Work Items Table -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Work Items / BOQ Schedule</h3>
                        <p class="text-xs text-gray-500">Itemize the scope with estimated quantities, measurement units, and optional internal rates.</p>
                    </div>
                    <button type="button" onclick="addLineItem()" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold rounded-xl text-xs transition flex items-center gap-1.5">
                        <span>+ Add Item</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs" id="itemsTable">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="py-2.5 px-3">Description</th>
                                <th class="py-2.5 px-3 w-40">Category</th>
                                <th class="py-2.5 px-3 w-28">Unit</th>
                                <th class="py-2.5 px-3 w-28 text-right">Qty</th>
                                <th class="py-2.5 px-3 w-32 text-right">Rate (₹)</th>
                                <th class="py-2.5 px-3 w-32 text-right">Total (₹)</th>
                                <th class="py-2.5 px-3 w-12 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody" class="divide-y divide-slate-100">
                            <!-- Row 1 -->
                            <tr class="item-row">
                                <td class="py-2 px-3">
                                    <input type="text" name="items[0][description]" placeholder="e.g. Earth excavation in hard rock"
                                           class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs" required>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="items[0][work_category_id]" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs">
                                        <option value="">-- Category --</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2 px-3">
                                    <input type="text" name="items[0][unit_of_measure]" value="CuM" placeholder="CuM"
                                           class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" step="0.001" min="0" name="items[0][quantity]" value="1.000"
                                           class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-right qty-input" oninput="calculateRowTotal(this)">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" step="0.01" min="0" name="items[0][unit_rate]" value="0.00"
                                           class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-right rate-input" oninput="calculateRowTotal(this)">
                                </td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-gray-900 row-total">
                                    ₹0.00
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <button type="button" onclick="removeLineItem(this)" class="text-gray-400 hover:text-rose-600 font-bold text-sm">×</button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 bg-slate-50/50 font-bold text-xs">
                                <td colspan="5" class="py-3 px-3 text-right text-gray-700">Estimated Total Order Value:</td>
                                <td class="py-3 px-3 text-right font-mono text-sm text-blue-700" id="grandTotalDisplay">₹0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Submission Actions -->
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('work-orders-register.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Cancel</a>
                <div class="flex items-center gap-3">
                    <button type="submit" name="submit_for_approval" value="0" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        Save as Draft
                    </button>
                    <button type="submit" name="submit_for_approval" value="1" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        Save & Submit for Approval
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        const clientContent = document.getElementById('clientTabContent');
        const internalContent = document.getElementById('internalTabContent');
        const tabBtnClient = document.getElementById('tabBtnClient');
        const tabBtnInternal = document.getElementById('tabBtnInternal');

        if (tab === 'client') {
            clientContent.classList.remove('hidden');
            internalContent.classList.add('hidden');
            tabBtnClient.className = 'px-5 py-3 border-b-2 border-indigo-600 text-indigo-700 font-bold transition flex items-center gap-2';
            tabBtnInternal.className = 'px-5 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition flex items-center gap-2';
        } else {
            clientContent.classList.add('hidden');
            internalContent.classList.remove('hidden');
            tabBtnClient.className = 'px-5 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition flex items-center gap-2';
            tabBtnInternal.className = 'px-5 py-3 border-b-2 border-blue-600 text-blue-700 font-bold transition flex items-center gap-2';
        }
    }

    function handleFileSelected(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById('fileNameDisplay').textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            document.getElementById('fileSelectedBadge').classList.remove('hidden');
        }
    }

    let itemIndex = 1;
    function addLineItem() {
        const tbody = document.getElementById('itemsTableBody');
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td class="py-2 px-3">
                <input type="text" name="items[${itemIndex}][description]" placeholder="e.g. Structural concrete M25"
                       class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs" required>
            </td>
            <td class="py-2 px-3">
                <select name="items[${itemIndex}][work_category_id]" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs">
                    <option value="">-- Category --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </td>
            <td class="py-2 px-3">
                <input type="text" name="items[${itemIndex}][unit_of_measure]" value="CuM" placeholder="CuM"
                       class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs">
            </td>
            <td class="py-2 px-3">
                <input type="number" step="0.001" min="0" name="items[${itemIndex}][quantity]" value="1.000"
                       class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-right qty-input" oninput="calculateRowTotal(this)">
            </td>
            <td class="py-2 px-3">
                <input type="number" step="0.01" min="0" name="items[${itemIndex}][unit_rate]" value="0.00"
                       class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-right rate-input" oninput="calculateRowTotal(this)">
            </td>
            <td class="py-2 px-3 text-right font-mono font-bold text-gray-900 row-total">
                ₹0.00
            </td>
            <td class="py-2 px-3 text-center">
                <button type="button" onclick="removeLineItem(this)" class="text-gray-400 hover:text-rose-600 font-bold text-sm">×</button>
            </td>
        `;
        tbody.appendChild(tr);
        itemIndex++;
    }

    function removeLineItem(btn) {
        const row = btn.closest('tr');
        const tbody = document.getElementById('itemsTableBody');
        if (tbody.querySelectorAll('tr').length > 1) {
            row.remove();
            recalcGrandTotal();
        }
    }

    function calculateRowTotal(input) {
        const row = input.closest('tr');
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const rate = parseFloat(row.querySelector('.rate-input').value) || 0;
        const total = (qty * rate).toFixed(2);
        row.querySelector('.row-total').textContent = '₹' + Number(total).toLocaleString('en-IN', {minimumFractionDigits: 2});
        recalcGrandTotal();
    }

    function recalcGrandTotal() {
        let sum = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const rate = parseFloat(row.querySelector('.rate-input').value) || 0;
            sum += qty * rate;
        });
        document.getElementById('grandTotalDisplay').textContent = '₹' + sum.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
</script>
@endsection
