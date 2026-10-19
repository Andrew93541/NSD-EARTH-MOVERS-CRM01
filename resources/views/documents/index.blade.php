@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{
    previewOpen: false,
    previewUrl: '',
    previewTitle: '',
    previewType: '',
    openPreview(url, title, isPdf) {
        this.previewUrl = url;
        this.previewTitle = title;
        this.previewType = isPdf ? 'pdf' : 'image';
        this.previewOpen = true;
    }
}">
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                    DIGITAL REPOSITORY
                </span>
                <span class="text-xs text-slate-500 font-medium">NSD Precision Archive</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">Central Document Centre</h1>
            <p class="text-xs text-slate-500 mt-1">
                Unified audit-ready repository for contracts, engineering drawings, delivery challans, bills, and statutory compliance files.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards matching Stitch Reference -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">TOTAL DOCUMENTS</span>
            <div class="text-2xl font-bold text-slate-900 mt-1 tabular-nums">{{ number_format($stats['total_count']) }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                <span>Active company attachments</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">STORAGE VOLUME</span>
            <div class="text-2xl font-bold text-emerald-700 mt-1 tabular-nums">{{ $stats['total_size_formatted'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Encrypted private disk</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider block">CONFIDENTIAL VAULT</span>
            <div class="text-2xl font-bold text-amber-700 mt-1 tabular-nums">{{ number_format($stats['confidential_count']) }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                <span>Restricted HR & Finance files</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider block">RECENTLY INGESTED</span>
            <div class="text-2xl font-bold text-blue-700 mt-1 tabular-nums">{{ number_format($stats['recent_count']) }}</div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                <span>Uploaded past 7 days</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('documents.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <!-- Search Text -->
            <div class="md:col-span-2">
                <label for="search" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Search Filename or Title</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="e.g. Agreement, Drawing, Challan, Invoice..."
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <!-- Module Filter -->
            <div>
                <label for="module" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Module</label>
                <select id="module" name="module" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="">All Business Modules</option>
                    <option value="project" {{ request('module') === 'project' ? 'selected' : '' }}>Projects</option>
                    <option value="purchase_request" {{ request('module') === 'purchase_request' ? 'selected' : '' }}>Material Indents (PR)</option>
                    <option value="purchase_order" {{ request('module') === 'purchase_order' ? 'selected' : '' }}>Purchase Orders</option>
                    <option value="goods_receipt" {{ request('module') === 'goods_receipt' ? 'selected' : '' }}>Goods Receipts (GRN)</option>
                    <option value="client_invoice" {{ request('module') === 'client_invoice' ? 'selected' : '' }}>Client Invoices</option>
                    <option value="retention_claim" {{ request('module') === 'retention_claim' ? 'selected' : '' }}>Retention Claims</option>
                    <option value="subcontractor" {{ request('module') === 'subcontractor' ? 'selected' : '' }}>Subcontractors</option>
                    <option value="work_order" {{ request('module') === 'work_order' ? 'selected' : '' }}>Subcontract Work Orders</option>
                    <option value="subcontractor_bill" {{ request('module') === 'subcontractor_bill' ? 'selected' : '' }}>Subcontractor RA Bills</option>
                    <option value="fixed_asset" {{ request('module') === 'fixed_asset' ? 'selected' : '' }}>Fixed Assets</option>
                    <option value="loan" {{ request('module') === 'loan' ? 'selected' : '' }}>Equipment Loans</option>
                    <option value="journal_entry" {{ request('module') === 'journal_entry' ? 'selected' : '' }}>Journal Vouchers</option>
                    <option value="employee" {{ request('module') === 'employee' ? 'selected' : '' }}>Staff & Workers</option>
                    <option value="payroll_period" {{ request('module') === 'payroll_period' ? 'selected' : '' }}>Payroll Batches</option>
                </select>
            </div>

            <!-- Category Filter -->
            <div>
                <label for="category" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Document Category</label>
                <select id="category" name="category" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    <option value="">All Categories</option>
                    <option value="contract" {{ request('category') === 'contract' ? 'selected' : '' }}>Contracts & Agreements</option>
                    <option value="drawing" {{ request('category') === 'drawing' ? 'selected' : '' }}>Engineering Drawings</option>
                    <option value="quotation" {{ request('category') === 'quotation' ? 'selected' : '' }}>Price Quotations</option>
                    <option value="delivery_challan" {{ request('category') === 'delivery_challan' ? 'selected' : '' }}>Delivery Challans</option>
                    <option value="supplier_invoice" {{ request('category') === 'supplier_invoice' ? 'selected' : '' }}>Vendor Invoices</option>
                    <option value="measurement_sheet" {{ request('category') === 'measurement_sheet' ? 'selected' : '' }}>Measurement Sheets</option>
                    <option value="inspection_photo" {{ request('category') === 'inspection_photo' ? 'selected' : '' }}>Inspection & Site Photos</option>
                    <option value="completion_certificate" {{ request('category') === 'completion_certificate' ? 'selected' : '' }}>Completion Certificates</option>
                    <option value="insurance_policy" {{ request('category') === 'insurance_policy' ? 'selected' : '' }}>Insurance & RC</option>
                    <option value="voucher_support" {{ request('category') === 'voucher_support' ? 'selected' : '' }}>Voucher Support</option>
                    <option value="identity_proof" {{ request('category') === 'identity_proof' ? 'selected' : '' }}>Identity Proof</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" id="filter-documents-btn" class="flex-1 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition cursor-pointer shadow-xs">
                    Apply Filter
                </button>
                <a href="{{ route('documents.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-semibold transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Documents List Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Repository Index</span>
            <span class="text-xs text-slate-500 font-medium">
                Showing {{ $documents->firstItem() ?? 0 }} - {{ $documents->lastItem() ?? 0 }} of {{ $documents->total() }} documents
            </span>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($documents as $doc)
                <div class="p-4 hover:bg-slate-50/70 transition flex flex-col md:flex-row md:items-center justify-between gap-4 document-row">
                    <div class="flex items-start gap-3.5 min-w-0">
                        <!-- Icon -->
                        <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center border {{ $doc->isPdf() ? 'bg-red-50 text-red-600 border-red-200' : ($doc->isImage() ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-slate-100 text-slate-600 border-slate-200') }}">
                            @if($doc->isPdf())
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            @elseif($doc->isImage())
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @endif
                        </div>

                        <!-- Metadata -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-xs font-bold text-slate-900 truncate">
                                    {{ $doc->title ?: $doc->original_filename }}
                                </h3>

                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                    {{ $doc->category_label }}
                                </span>

                                @if($doc->is_confidential)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                        Confidential
                                    </span>
                                @endif

                                @if($doc->version > 1)
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-purple-100 text-purple-700 font-mono">
                                        v{{ $doc->version }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-[11px] text-slate-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span>{{ $doc->original_filename }}</span>
                                <span>•</span>
                                <span class="tabular-nums font-mono">{{ $doc->formatted_size }}</span>
                                <span>•</span>
                                <span>Uploaded by {{ $doc->uploadedBy->name ?? 'System' }}</span>
                                <span>•</span>
                                <span>{{ $doc->created_at->format('d M Y, h:i A') }}</span>
                            </p>

                            <!-- Linked Business Record -->
                            @if($doc->documentable)
                                <div class="mt-1.5 flex items-center gap-1.5 text-[11px]">
                                    <span class="text-slate-400">Attached to:</span>
                                    <span class="font-semibold text-blue-600">
                                        {{ class_basename($doc->documentable_type) }} #{{ $doc->documentable_id }}
                                        @if(isset($doc->documentable->name))
                                            — {{ $doc->documentable->name }}
                                        @elseif(isset($doc->documentable->pr_number))
                                            — {{ $doc->documentable->pr_number }}
                                        @elseif(isset($doc->documentable->po_number))
                                            — {{ $doc->documentable->po_number }}
                                        @elseif(isset($doc->documentable->grn_number))
                                            — {{ $doc->documentable->grn_number }}
                                        @elseif(isset($doc->documentable->bill_number))
                                            — {{ $doc->documentable->bill_number }}
                                        @elseif(isset($doc->documentable->invoice_number))
                                            — {{ $doc->documentable->invoice_number }}
                                        @elseif(isset($doc->documentable->entry_number))
                                            — {{ $doc->documentable->entry_number }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <div class="flex items-center gap-2 flex-shrink-0 self-end md:self-center">
                        @if($doc->isPreviewable())
                            <button type="button"
                                    @click="openPreview('{{ route('documents.preview', $doc) }}', '{{ addslashes($doc->title ?: $doc->original_filename) }}', {{ $doc->isPdf() ? 'true' : 'false' }})"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Preview</span>
                            </button>
                        @endif

                        <a href="{{ route('documents.download', $doc) }}"
                           class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition shadow-xs">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Download</span>
                        </a>

                        @if(! $doc->isParentImmutable() && (auth()->user()->hasPermission('*') || $doc->uploaded_by === auth()->id()))
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('Archive document [{{ addslashes($doc->original_filename) }}]?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 hover:bg-red-50 text-slate-400 hover:text-red-600 rounded-lg transition cursor-pointer" title="Remove Document">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        @else
                            <span class="p-1.5 text-slate-300 cursor-not-allowed" title="Statutory/Audit locked document cannot be deleted">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <h4 class="text-sm font-bold text-slate-800 mt-2">No documents found</h4>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        No files match your search criteria. Supporting documents attached to projects, procurement orders, and financial records appear here automatically.
                    </p>
                </div>
            @endforelse
        </div>

        @if($documents->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    <!-- Secure Preview Modal Component -->
    <div x-show="previewOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         @keydown.escape.window="previewOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl h-[85vh] flex flex-col overflow-hidden"
             @click.away="previewOpen = false">
            <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h3 class="text-xs font-bold text-slate-900 truncate" x-text="previewTitle"></h3>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="previewUrl + '/../download'"
                       class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download</span>
                    </a>
                    <button type="button" @click="previewOpen = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 bg-slate-100 p-2 overflow-auto flex items-center justify-center">
                <template x-if="previewType === 'pdf'">
                    <iframe :src="previewUrl" class="w-full h-full rounded-xl bg-white border border-slate-200" title="Document Preview"></iframe>
                </template>
                <template x-if="previewType === 'image'">
                    <img :src="previewUrl" alt="Preview Image" class="max-w-full max-h-full object-contain rounded-xl shadow-md border border-slate-200"/>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
