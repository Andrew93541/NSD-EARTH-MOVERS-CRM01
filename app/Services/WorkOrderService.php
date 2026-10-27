<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    protected DocumentService $documentService;
    protected PdfTextExtractionService $extractionService;

    public function __construct(
        DocumentService $documentService,
        PdfTextExtractionService $extractionService
    ) {
        $this->documentService = $documentService;
        $this->extractionService = $extractionService;
    }

    /**
     * Ingest and parse an incoming Client Work Order PDF.
     */
    public function createClientWorkOrderFromUpload(
        UploadedFile $file,
        int $companyId,
        User $user
    ): WorkOrder {
        // 1. Strict validation of uploaded PDF file
        $this->documentService->validateUploadedFile($file);

        if ($file->getClientOriginalExtension() !== 'pdf' && $file->getMimeType() !== 'application/pdf') {
            throw ValidationException::withMessages([
                'file' => 'Only valid PDF files are accepted for Work Order document intake.',
            ]);
        }

        $realPath = $file->getRealPath();
        $checksum = hash_file('sha256', $realPath);

        return DB::transaction(function () use ($file, $companyId, $user, $checksum, $realPath) {
            $internalOrderNumber = WorkOrder::generateOrderNumber($companyId, 'client_issued');

            // 2. Create base draft WorkOrder record
            $workOrder = WorkOrder::create([
                'company_id' => $companyId,
                'order_number' => $internalOrderNumber,
                'order_type' => 'client_issued',
                'title' => 'Client Work Order: ' . $file->getClientOriginalName(),
                'status' => 'draft',
                'extraction_status' => 'extracted',
                'created_by' => $user->id,
            ]);

            // 3. Store the original document in private storage via DocumentService
            $document = $this->documentService->storeDocument(
                $workOrder,
                $file,
                [
                    'category' => 'work_order_original',
                    'title' => 'Original Client PDF: ' . $file->getClientOriginalName(),
                ],
                $user
            );

            // 4. Extract digital text and candidate fields
            $absolutePath = Storage::disk($document->disk)->path($document->file_path);
            $extractionResult = $this->extractionService->extract($absolutePath, $companyId);

            $candidates = $extractionResult['candidates'] ?? [];
            $extractionStatus = $extractionResult['status'];

            // Pre-fill extracted fields if found
            $title = ! empty($candidates['title'])
                ? $candidates['title']
                : ($candidates['external_reference_number'] ? 'Work Order ' . $candidates['external_reference_number'] : $workOrder->title);

            $workOrder->update([
                'title' => $title,
                'external_reference_number' => $candidates['external_reference_number'] ?? null,
                'client_id' => $candidates['client_id'] ?? null,
                'issue_date' => $candidates['issue_date'] ?? null,
                'target_completion_date' => $candidates['target_completion_date'] ?? null,
                'total_amount' => $candidates['total_amount'] ?? 0.00,
                'extraction_status' => $extractionStatus,
                'extracted_data' => $extractionResult,
            ]);

            // 5. Check for duplicates (Non-destructive duplicate detection)
            $this->evaluateDuplicateFlags($workOrder, $checksum, $user);

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'action' => 'work_order.client_upload',
                'auditable_type' => WorkOrder::class,
                'auditable_id' => $workOrder->id,
                'old_values' => null,
                'new_values' => [
                    'order_number' => $workOrder->order_number,
                    'external_reference' => $workOrder->external_reference_number,
                    'extraction_status' => $workOrder->extraction_status,
                    'is_duplicate_flagged' => $workOrder->is_duplicate_flagged,
                    'document_id' => $document->id,
                ],
            ]);

            return $workOrder->fresh(['client', 'documents', 'creator']);
        });
    }

    /**
     * Confirm human staff verification of extracted / entered work order data.
     */
    public function verifyClientWorkOrder(
        WorkOrder $workOrder,
        array $validatedData,
        User $user
    ): WorkOrder {
        return DB::transaction(function () use ($workOrder, $validatedData, $user) {
            $oldValues = $workOrder->only([
                'title', 'external_reference_number', 'client_id', 'project_id', 'site_id',
                'assigned_supervisor_id', 'issue_date', 'target_completion_date', 'total_amount',
                'extraction_status', 'status'
            ]);

            // Document verification fields
            $workOrder->title = $validatedData['title'];
            $workOrder->external_reference_number = $validatedData['external_reference_number'] ?? null;
            $workOrder->client_id = $validatedData['client_id'] ?? null;
            $workOrder->project_id = $validatedData['project_id'] ?? null;
            $workOrder->site_id = $validatedData['site_id'] ?? null;
            $workOrder->assigned_supervisor_id = $validatedData['assigned_supervisor_id'] ?? null;
            $workOrder->issue_date = $validatedData['issue_date'] ?? null;
            $workOrder->start_date = $validatedData['start_date'] ?? null;
            $workOrder->target_completion_date = $validatedData['target_completion_date'] ?? null;
            $workOrder->description = $validatedData['description'] ?? null;
            $workOrder->notes = $validatedData['notes'] ?? null;

            // Separate document verification from management approval:
            $workOrder->extraction_status = 'verified';
            $workOrder->verified_by = $user->id;
            $workOrder->verified_at = now();
            $workOrder->verification_notes = $validatedData['verification_notes'] ?? 'Verified by staff review against original PDF.';

            // If user explicitly submits for management approval, set status = pending_approval; else retain draft
            if (! empty($validatedData['submit_for_approval'])) {
                $workOrder->status = 'pending_approval';
            }

            // Sync items if provided
            if (isset($validatedData['items']) && is_array($validatedData['items'])) {
                $this->syncWorkOrderItems($workOrder, $validatedData['items']);
            } else {
                $workOrder->total_amount = (float) ($validatedData['total_amount'] ?? $workOrder->total_amount);
            }

            $workOrder->save();

            // Re-evaluate duplicate flags after human verification / edits
            $primaryDoc = $workOrder->primaryDocument();
            $checksum = $primaryDoc?->checksum;
            $this->evaluateDuplicateFlags($workOrder, $checksum, $user);

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $workOrder->company_id,
                'action' => 'work_order.verify',
                'auditable_type' => WorkOrder::class,
                'auditable_id' => $workOrder->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'extraction_status' => 'verified',
                    'status' => $workOrder->status,
                    'verified_by' => $user->id,
                    'total_amount' => $workOrder->total_amount,
                ],
            ]);

            return $workOrder->fresh(['client', 'project', 'site', 'assignedSupervisor', 'items', 'verifier']);
        });
    }

    /**
     * Create an internal work order (no forced client or contract required).
     */
    public function createInternalWorkOrder(
        array $validatedData,
        int $companyId,
        User $user,
        ?UploadedFile $attachment = null
    ): WorkOrder {
        return DB::transaction(function () use ($validatedData, $companyId, $user, $attachment) {
            $internalOrderNumber = WorkOrder::generateOrderNumber($companyId, 'internal');

            $workOrder = WorkOrder::create([
                'company_id' => $companyId,
                'order_number' => $internalOrderNumber,
                'order_type' => 'internal',
                'external_reference_number' => $validatedData['external_reference_number'] ?? null,
                'title' => $validatedData['title'],
                'description' => $validatedData['description'] ?? null,
                'client_id' => null, // Never forced for internal jobs
                'project_id' => $validatedData['project_id'] ?? null,
                'site_id' => $validatedData['site_id'] ?? null,
                'assigned_supervisor_id' => $validatedData['assigned_supervisor_id'] ?? null,
                'issue_date' => $validatedData['issue_date'] ?? now()->toDateString(),
                'start_date' => $validatedData['start_date'] ?? null,
                'target_completion_date' => $validatedData['target_completion_date'] ?? null,
                'total_amount' => 0.00,
                'status' => ! empty($validatedData['submit_for_approval']) ? 'pending_approval' : 'draft',
                'extraction_status' => 'none',
                'created_by' => $user->id,
                'notes' => $validatedData['notes'] ?? null,
            ]);

            // Save line items and compute total
            if (! empty($validatedData['items']) && is_array($validatedData['items'])) {
                $this->syncWorkOrderItems($workOrder, $validatedData['items']);
            }

            // Handle optional attachment
            if ($attachment) {
                $this->documentService->storeDocument(
                    $workOrder,
                    $attachment,
                    [
                        'category' => 'work_order_attachment',
                        'title' => 'Internal WO Attachment: ' . $attachment->getClientOriginalName(),
                    ],
                    $user
                );
            }

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'action' => 'work_order.create_internal',
                'auditable_type' => WorkOrder::class,
                'auditable_id' => $workOrder->id,
                'old_values' => null,
                'new_values' => [
                    'order_number' => $workOrder->order_number,
                    'title' => $workOrder->title,
                    'total_amount' => $workOrder->total_amount,
                ],
            ]);

            return $workOrder->fresh(['project', 'site', 'assignedSupervisor', 'items', 'documents']);
        });
    }

    /**
     * Management approval of a verified or internal work order.
     */
    public function approveWorkOrder(WorkOrder $workOrder, User $user, ?string $notes = null): WorkOrder
    {
        if ($workOrder->status === 'approved') {
            throw new DomainException('Work order is already approved.');
        }

        return DB::transaction(function () use ($workOrder, $user, $notes) {
            $oldStatus = $workOrder->status;

            $workOrder->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $workOrder->company_id,
                'action' => 'work_order.approve',
                'auditable_type' => WorkOrder::class,
                'auditable_id' => $workOrder->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now()->toIso8601String(),
                ],
            ]);

            return $workOrder;
        });
    }

    /**
     * Dismiss duplicate warning after human review.
     */
    public function dismissDuplicate(WorkOrder $workOrder, User $user, string $reason): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $user, $reason) {
            $workOrder->update([
                'is_duplicate_flagged' => false,
                'duplicate_resolved_by' => $user->id,
                'duplicate_resolved_at' => now(),
                'notes' => trim(($workOrder->notes ? $workOrder->notes . "\n" : '') . "Duplicate flag dismissed by {$user->name}: {$reason}"),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $workOrder->company_id,
                'action' => 'work_order.dismiss_duplicate',
                'auditable_type' => WorkOrder::class,
                'auditable_id' => $workOrder->id,
                'old_values' => ['is_duplicate_flagged' => true],
                'new_values' => [
                    'is_duplicate_flagged' => false,
                    'reason' => $reason,
                ],
            ]);

            return $workOrder;
        });
    }

    /**
     * Synchronize work order line items and update total_amount.
     */
    protected function syncWorkOrderItems(WorkOrder $workOrder, array $itemsData): void
    {
        $workOrder->items()->delete();

        $runningTotal = 0.00;
        $itemSeq = 1;

        foreach ($itemsData as $item) {
            if (empty($item['description']) && empty($item['quantity'])) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 1);
            $rate = (float) ($item['unit_rate'] ?? 0);
            $lineTotal = round($qty * $rate, 2);
            $runningTotal += $lineTotal;

            WorkOrderItem::create([
                'work_order_id' => $workOrder->id,
                'item_number' => $itemSeq++,
                'description' => $item['description'] ?? 'Work Item',
                'work_category_id' => ! empty($item['work_category_id']) ? $item['work_category_id'] : null,
                'unit_of_measure' => $item['unit_of_measure'] ?? 'units',
                'quantity' => $qty,
                'unit_rate' => $rate,
                'total_price' => $lineTotal,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $workOrder->update(['total_amount' => $runningTotal]);
    }

    /**
     * Evaluate non-destructive duplicate candidates.
     */
    protected function evaluateDuplicateFlags(WorkOrder $workOrder, ?string $fileChecksum, User $user): void
    {
        $duplicateReason = null;
        $matchedOrderId = null;

        // 1. Check client_id + external_reference_number match
        if ($workOrder->client_id && ! empty($workOrder->external_reference_number)) {
            $existing = WorkOrder::withoutGlobalScopes()
                ->where('company_id', $workOrder->company_id)
                ->where('id', '!=', $workOrder->id)
                ->where('client_id', $workOrder->client_id)
                ->where('external_reference_number', $workOrder->external_reference_number)
                ->first();

            if ($existing) {
                $duplicateReason = "Potential duplicate: Client reference [{$workOrder->external_reference_number}] already exists under Work Order [{$existing->order_number}].";
                $matchedOrderId = $existing->id;
            }
        }

        // 2. Check identical file checksum match
        if (! $duplicateReason && ! empty($fileChecksum)) {
            $matchingDoc = Document::where('company_id', $workOrder->company_id)
                ->where('documentable_type', WorkOrder::class)
                ->where('documentable_id', '!=', $workOrder->id)
                ->where('checksum', $fileChecksum)
                ->where('status', 'active')
                ->first();

            if ($matchingDoc) {
                $matchedOrder = WorkOrder::withoutGlobalScopes()
                    ->where('company_id', $workOrder->company_id)
                    ->find($matchingDoc->documentable_id);
                if ($matchedOrder) {
                    $duplicateReason = "Potential duplicate: Uploaded PDF file has an identical SHA-256 checksum to the document on Work Order [{$matchedOrder->order_number}].";
                    $matchedOrderId = $matchedOrder->id;
                }
            }
        }

        if ($duplicateReason) {
            $workOrder->update([
                'is_duplicate_flagged' => true,
                'duplicate_reason' => $duplicateReason,
                'duplicate_matched_order_id' => $matchedOrderId,
            ]);
        } else {
            $workOrder->update([
                'is_duplicate_flagged' => false,
                'duplicate_reason' => null,
                'duplicate_matched_order_id' => null,
            ]);
        }
    }
}
