@props([
    'record',
    'type',
    'categories' => [
        'general' => 'General Attachment',
        'contract' => 'Contract / Agreement',
        'drawing' => 'Engineering Drawing',
        'quotation' => 'Quotation / Estimate',
        'delivery_challan' => 'Delivery Challan',
        'supplier_invoice' => 'Supplier / Vendor Invoice',
        'inspection_photo' => 'Site Inspection Photo',
        'measurement_sheet' => 'Measurement Sheet',
        'completion_certificate' => 'Completion Certificate',
        'voucher_support' => 'Voucher Support Evidence',
        'insurance_policy' => 'Insurance / RC Document',
    ],
    'title' => 'Supporting Documents & Attachments',
    'canUpload' => true,
    'isRequired' => false,
    'requiredMessage' => 'A supporting document is required for compliance/audit sign-off.',
])

@php
    $documents = $record->activeDocuments;
    $user = auth()->user();
    $roleSlug = $user?->currentRole()?->slug;
    $isPrivileged = in_array($roleSlug, ['owner', 'admin', 'hr_officer', 'accountant'], true);
    $isImmutable = method_exists($record, 'isParentImmutable') ? false : (
        ($record instanceof \App\Models\JournalEntry && in_array($record->status, ['posted', 'reversed'], true)) ||
        ($record instanceof \App\Models\SubcontractorBill && in_array($record->status, ['certified', 'paid'], true)) ||
        ($record instanceof \App\Models\Loan && $record->status === 'settled')
    );
@endphp

<div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-5"
     x-data="{
         previewOpen: false,
         previewUrl: '',
         previewTitle: '',
         previewType: '',
         uploadOpen: false,
         isDragging: false,
         openPreview(url, title, isPdf) {
             this.previewUrl = url;
             this.previewTitle = title;
             this.previewType = isPdf ? 'pdf' : 'image';
             this.previewOpen = true;
         }
     }">
    <!-- Header -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">{{ $title }}</h3>
                <span class="text-[11px] text-slate-500 font-medium">
                    {{ $documents->count() }} {{ Str::plural('file', $documents->count()) }} attached
                </span>
            </div>
        </div>

        @if($canUpload)
            <button type="button"
                    @click="uploadOpen = !uploadOpen"
                    id="toggle-upload-btn-{{ $type }}-{{ $record->id }}"
                    class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span x-text="uploadOpen ? 'Cancel' : '+ Attach Document'"></span>
            </button>
        @endif
    </div>

    @if($isRequired && $documents->count() === 0)
        <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5 text-xs text-amber-800">
            <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <strong class="font-bold block">Document Required:</strong>
                <span>{{ $requiredMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Upload Form Panel (Collapsible) -->
    <div x-show="uploadOpen" x-transition class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="hidden" name="documentable_type" value="{{ $type }}"/>
            <input type="hidden" name="documentable_id" value="{{ $record->id }}"/>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="doc-category-{{ $type }}-{{ $record->id }}" class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Category *
                    </label>
                    <select id="doc-category-{{ $type }}-{{ $record->id }}" name="category" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-blue-600 focus:outline-none">
                        @foreach($categories as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="doc-title-{{ $type }}-{{ $record->id }}" class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Document Title (Optional)
                    </label>
                    <input type="text" id="doc-title-{{ $type }}-{{ $record->id }}" name="title" placeholder="e.g. Signed Delivery Challan 12 Oct"
                           class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-600 focus:outline-none"/>
                </div>
            </div>

            <!-- Drag & Drop Area -->
            <div class="border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-blue-500 bg-white transition cursor-pointer relative"
                 :class="{ 'border-blue-500 bg-blue-50/30': isDragging }"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files">
                <input type="file" name="file" x-ref="fileInput" required id="doc-file-input-{{ $type }}-{{ $record->id }}"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"/>
                <svg class="w-8 h-8 text-slate-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <div class="text-xs font-bold text-slate-700 mt-1">
                    Click to browse or drag file here
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                    Permitted: PDF, PNG, JPG, WEBP, DOCX, XLSX (Max 15MB)
                </div>
            </div>

            @if($isPrivileged)
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="doc-confidential-{{ $type }}-{{ $record->id }}" name="is_confidential" value="1"
                           class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500"/>
                    <label for="doc-confidential-{{ $type }}-{{ $record->id }}" class="text-xs text-slate-700 font-medium">
                        Mark as Confidential (Restricted to HR / Finance Officers)
                    </label>
                </div>
            @endif

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" @click="uploadOpen = false" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-800 transition">
                    Cancel
                </button>
                <button type="submit" id="submit-upload-btn-{{ $type }}-{{ $record->id }}" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-xs transition cursor-pointer">
                    Upload & Attach
                </button>
            </div>
        </form>
    </div>

    <!-- Attached Documents List -->
    <div class="divide-y divide-slate-100">
        @forelse($documents as $doc)
            <div class="py-3 flex items-center justify-between gap-3 text-xs document-item">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center border {{ $doc->isPdf() ? 'bg-red-50 text-red-600 border-red-200' : ($doc->isImage() ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-slate-100 text-slate-600 border-slate-200') }}">
                        @if($doc->isPdf())
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        @elseif($doc->isImage())
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 truncate">
                                {{ $doc->title ?: $doc->original_filename }}
                            </span>
                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-100 text-slate-600">
                                {{ $doc->category_label }}
                            </span>
                            @if($doc->is_confidential)
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800">
                                    Confidential
                                </span>
                            @endif
                            @if($doc->version > 1)
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-purple-100 text-purple-700 font-mono">
                                    v{{ $doc->version }}
                                </span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                            <span>{{ $doc->original_filename }}</span>
                            <span>•</span>
                            <span class="tabular-nums font-mono">{{ $doc->formatted_size }}</span>
                            <span>•</span>
                            <span>{{ $doc->uploadedBy->name ?? 'User' }}</span>
                            <span>•</span>
                            <span>{{ $doc->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 flex-shrink-0">
                    @if($doc->isPreviewable())
                        <button type="button"
                                id="preview-btn-{{ $doc->id }}"
                                @click="openPreview('{{ route('documents.preview', $doc) }}', '{{ addslashes($doc->title ?: $doc->original_filename) }}', {{ $doc->isPdf() ? 'true' : 'false' }})"
                                class="px-2.5 py-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg text-xs font-semibold flex items-center gap-1 transition cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>Preview</span>
                        </button>
                    @endif

                    <a href="{{ route('documents.download', $doc) }}"
                       class="px-2.5 py-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg text-xs font-semibold flex items-center gap-1 transition">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download</span>
                    </a>

                    @if(! $doc->isParentImmutable() && (auth()->user()->hasPermission('*') || $doc->uploaded_by === auth()->id()))
                        <form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('Remove document [{{ addslashes($doc->original_filename) }}]?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer" title="Remove Document">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="py-6 text-center text-xs text-slate-400">
                No supporting documents attached yet. Click "+ Attach Document" above to upload files.
            </div>
        @endforelse
    </div>

    <!-- Preview Modal -->
    <div x-show="previewOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         @keydown.escape.window="previewOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl h-[85vh] flex flex-col overflow-hidden"
             @click.away="previewOpen = false">
            <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h4 class="text-xs font-bold text-slate-900 truncate" x-text="previewTitle"></h4>
                </div>
                <button type="button" @click="previewOpen = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
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
