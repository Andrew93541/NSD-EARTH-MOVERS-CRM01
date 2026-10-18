<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Services\Security\ConfigurableFileScanner;
use App\Services\Security\FileScannerInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentService
{
    /**
     * Permitted MIME types allowlist.
     */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
    ];

    /**
     * Permitted file extensions allowlist.
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
    ];

    /**
     * Maximum file size in bytes (15 MB default).
     */
    public const MAX_FILE_SIZE = 15728640;

    protected FileScannerInterface $scanner;

    public function __construct(?FileScannerInterface $scanner = null)
    {
        $this->scanner = $scanner ?? new ConfigurableFileScanner();
    }

    /**
     * Store and associate an uploaded file with a parent record.
     *
     * @param Model $record The parent business record (Project, PR, PO, etc.)
     * @param UploadedFile $file The uploaded file
     * @param array $attributes Additional metadata (category, title, is_confidential)
     * @param \App\Models\User|null $user The authenticated uploader
     */
    public function storeDocument(
        Model $record,
        UploadedFile $file,
        array $attributes = [],
        ?\App\Models\User $user = null
    ): Document {
        $this->validateUploadedFile($file);

        $companyId = $record->company_id ?? session('current_company_id') ?? $user?->currentCompany()?->id;
        if (! $companyId) {
            throw new DomainException('Cannot attach document: Parent record lacks company ownership.');
        }

        $realPath = $file->getRealPath();
        $checksum = hash_file('sha256', $realPath);
        $fileSize = $file->getSize();
        $clientOriginalName = $file->getClientOriginalName();
        $sanitizedOriginalName = $this->sanitizeFilename($clientOriginalName);
        $extension = strtolower($file->getClientOriginalExtension());
        $verifiedMime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath);

        // Extra guard on verified MIME
        if (! in_array($verifiedMime, self::ALLOWED_MIMES, true)) {
            if ($file->getMimeType() && in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
                $verifiedMime = $file->getMimeType();
            } else {
                throw ValidationException::withMessages([
                    'file' => "Security error: Verified file type [{$verifiedMime}] is not permitted.",
                ]);
            }
        }

        // Run malware/antivirus scan
        $scanResult = $this->scanner->scan($realPath);
        if (! $scanResult->isClean) {
            throw ValidationException::withMessages([
                'file' => "Security check failed: {$scanResult->message}",
            ]);
        }

        // Construct storage path: companies/{company_id}/{entity_type}/{record_id}/{uuid}.{ext}
        $entityFolder = Str::kebab(class_basename($record));
        $storageFilename = Str::uuid()->toString() . '.' . $extension;
        $relativeDir = "companies/{$companyId}/{$entityFolder}/{$record->getKey()}";
        $disk = 'local'; // private storage

        // Store file onto private disk
        $storedPath = $file->storeAs($relativeDir, $storageFilename, $disk);

        if (! $storedPath) {
            throw new DomainException('Failed to write file to private storage.');
        }

        return DB::transaction(function () use (
            $record,
            $companyId,
            $attributes,
            $sanitizedOriginalName,
            $storageFilename,
            $disk,
            $storedPath,
            $verifiedMime,
            $fileSize,
            $checksum,
            $user,
            $scanResult
        ) {
            $isConfidential = (bool) ($attributes['is_confidential'] ?? false);
            // Default confidentiality for sensitive entities
            if ($record instanceof \App\Models\PayrollPeriod || $record instanceof \App\Models\PayrollRecord) {
                $isConfidential = true;
            }

            $document = Document::create([
                'company_id' => $companyId,
                'documentable_type' => get_class($record),
                'documentable_id' => $record->getKey(),
                'category' => $attributes['category'] ?? 'general',
                'title' => $attributes['title'] ?? null,
                'original_filename' => $sanitizedOriginalName,
                'storage_filename' => $storageFilename,
                'disk' => $disk,
                'file_path' => $storedPath,
                'mime_type' => $verifiedMime,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'uploaded_by' => $user?->id ?? auth()->id(),
                'is_confidential' => $isConfidential,
                'version' => 1,
                'status' => 'active',
                'scan_status' => $scanResult->status,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            AuditLog::create([
                'user_id' => $user?->id ?? auth()->id(),
                'company_id' => $companyId,
                'action' => 'document.upload',
                'auditable_type' => Document::class,
                'auditable_id' => $document->id,
                'old_values' => null,
                'new_values' => [
                    'original_filename' => $sanitizedOriginalName,
                    'category' => $document->category,
                    'parent_type' => get_class($record),
                    'parent_id' => $record->getKey(),
                    'file_size' => $fileSize,
                    'checksum' => $checksum,
                ],
            ]);

            return $document;
        });
    }

    /**
     * Replace an existing document with a new version.
     */
    public function replaceDocument(
        Document $oldDoc,
        UploadedFile $newFile,
        array $attributes = [],
        ?\App\Models\User $user = null
    ): Document {
        $parent = $oldDoc->documentable;
        if (! $parent) {
            throw new DomainException('Cannot replace document: Associated parent record no longer exists.');
        }

        if ($oldDoc->isParentImmutable()) {
            throw new DomainException('Cannot replace documents on posted, certified, or settled immutable records.');
        }

        $newDoc = $this->storeDocument($parent, $newFile, array_merge([
            'category' => $oldDoc->category,
            'title' => $attributes['title'] ?? $oldDoc->title,
            'is_confidential' => $oldDoc->is_confidential,
        ], $attributes), $user);

        DB::transaction(function () use ($oldDoc, $newDoc, $user) {
            $newDoc->update([
                'version' => $oldDoc->version + 1,
            ]);

            $oldDoc->update([
                'status' => 'superseded',
                'superseded_by_id' => $newDoc->id,
            ]);

            AuditLog::create([
                'user_id' => $user?->id ?? auth()->id(),
                'company_id' => $oldDoc->company_id,
                'action' => 'document.replace',
                'auditable_type' => Document::class,
                'auditable_id' => $newDoc->id,
                'old_values' => ['superseded_document_id' => $oldDoc->id, 'old_version' => $oldDoc->version],
                'new_values' => ['new_document_id' => $newDoc->id, 'new_version' => $newDoc->version],
            ]);
        });

        return $newDoc;
    }

    /**
     * Delete / Archive a document.
     */
    public function deleteDocument(
        Document $document,
        ?\App\Models\User $user = null,
        string $reason = 'User requested removal'
    ): bool {
        if ($document->isParentImmutable()) {
            throw new DomainException('Statutory / Audit Protection: Documents attached to posted journals, certified bills, or settled loans cannot be deleted.');
        }

        return DB::transaction(function () use ($document, $user, $reason) {
            AuditLog::create([
                'user_id' => $user?->id ?? auth()->id(),
                'company_id' => $document->company_id,
                'action' => 'document.delete',
                'auditable_type' => Document::class,
                'auditable_id' => $document->id,
                'old_values' => [
                    'original_filename' => $document->original_filename,
                    'category' => $document->category,
                    'reason' => $reason,
                ],
                'new_values' => ['status' => 'archived'],
            ]);

            $document->update(['status' => 'archived']);
            return $document->delete(); // Soft delete preserves file for statutory audit
        });
    }

    /**
     * Stream an authorized preview response for PDF and Images.
     */
    public function streamPreview(Document $document): BinaryFileResponse
    {
        // Security check: Verify file has passed anti-malware verification
        if (! $document->isCleanForAccess()) {
            $message = match ($document->scan_status) {
                'infected' => 'Security Protection: This file has been flagged as infected and cannot be accessed.',
                'quarantined' => 'Security Protection: This file has been quarantined by security checks and cannot be accessed.',
                'error' => 'Security Protection: This file encountered scanner errors and cannot be accessed under fail-closed security.',
                'pending' => 'Security Protection: This file is pending malware scanning and is not yet available.',
                'unscanned' => 'Security Protection: Unscanned files cannot be accessed in production or fail-closed mode.',
                default => 'Security Protection: This file has not been verified clean by anti-malware inspection.',
            };
            abort(423, $message);
        }

        $disk = Storage::disk($document->disk);
        if (! $disk->exists($document->file_path)) {
            abort(404, 'File not found on storage server.');
        }

        if (! $document->isPreviewable()) {
            abort(415, 'Inline preview is not supported for this file type. Please use download.');
        }

        $absolutePath = $disk->path($document->file_path);

        return response()->file($absolutePath, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Download the document with attachment headers.
     */
    public function download(Document $document): BinaryFileResponse
    {
        // Security check: Verify file has passed anti-malware verification
        if (! $document->isCleanForAccess()) {
            $message = match ($document->scan_status) {
                'infected' => 'Security Protection: This file has been flagged as infected and cannot be accessed.',
                'quarantined' => 'Security Protection: This file has been quarantined by security checks and cannot be accessed.',
                'error' => 'Security Protection: This file encountered scanner errors and cannot be accessed under fail-closed security.',
                'pending' => 'Security Protection: This file is pending malware scanning and is not yet available.',
                'unscanned' => 'Security Protection: Unscanned files cannot be accessed in production or fail-closed mode.',
                default => 'Security Protection: This file has not been verified clean by anti-malware inspection.',
            };
            abort(423, $message);
        }

        $disk = Storage::disk($document->disk);
        if (! $disk->exists($document->file_path)) {
            abort(404, 'File not found on storage server.');
        }

        $absolutePath = $disk->path($document->file_path);

        return response()->download($absolutePath, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Validate an uploaded file strictly on the server.
     */
    public function validateUploadedFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is corrupt or exceeded the server post size limit.',
            ]);
        }

        if ($file->getSize() <= 0) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is empty (0 bytes).',
            ]);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $maxMb = self::MAX_FILE_SIZE / 1048576;
            throw ValidationException::withMessages([
                'file' => "The uploaded file exceeds the maximum allowed size of {$maxMb} MB.",
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => "Files with extension [.{$extension}] are prohibited for security.",
            ]);
        }

        $realPath = $file->getRealPath();
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath);

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            if ($file->getMimeType() && in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
                $mime = $file->getMimeType();
            } else {
                throw ValidationException::withMessages([
                    'file' => "Security check failed: Detected MIME type [{$mime}] is not permitted.",
                ]);
            }
        }
    }

    /**
     * Sanitize user-provided filename to prevent path traversal or unsafe characters.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Strip directory separators to prevent path traversal
        $basename = basename($filename);
        // Replace dangerous characters with underscores
        $clean = preg_replace('/[^A-Za-z0-9_\-\.\s\(\)]/', '_', $basename);
        // Collapse consecutive dots
        $clean = preg_replace('/\.+/', '.', $clean);

        return trim($clean, '. ');
    }
}
