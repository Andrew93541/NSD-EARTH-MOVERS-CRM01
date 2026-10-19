<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\MeasurementBookEntry;
use App\Models\Project;
use App\Models\SubcontractorBill;
use App\Models\SubcontractorBillItem;
use App\Models\SubcontractorBillMeasurement;
use App\Models\SubcontractorBillPayment;
use App\Models\SubcontractorWorkOrder;
use App\Models\SubcontractorWorkOrderItem;
use App\Models\SubcontractorWorkOrderVariation;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubcontractorBillingService
{
    /**
     * Create a new Subcontractor Work Order with itemized BOQ lines.
     */
    public static function createWorkOrder(array $data, User $user): SubcontractorWorkOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $companyId = $data['company_id']
                ?? $user->currentCompany()?->id
                ?? session('current_company_id')
                ?? $user->companies()->first()?->id;

            // Validate Subcontractor
            $subcontractor = Supplier::where('id', $data['subcontractor_id'])
                ->where('company_id', $companyId)
                ->firstOrFail();

            if (! $subcontractor->isSubcontractor()) {
                // If existing supplier is being assigned a work order, update vendor_type to include subcontractor
                $subcontractor->update([
                    'vendor_type' => $subcontractor->vendor_type === 'supplier' ? 'both' : 'subcontractor',
                ]);
            }

            // Generate unique Work Order Number if not provided
            $woNumber = $data['wo_number'] ?? null;
            if (! $woNumber) {
                $count = SubcontractorWorkOrder::where('company_id', $companyId)->count() + 1;
                $woNumber = 'WO-' . date('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            }

            $workOrder = SubcontractorWorkOrder::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'],
                'site_id' => $data['site_id'] ?? null,
                'subcontractor_id' => $subcontractor->id,
                'wo_number' => $woNumber,
                'title' => $data['title'],
                'work_category' => $data['work_category'] ?? 'earthwork',
                'work_package' => $data['work_package'] ?? null,
                'scope_of_work' => $data['scope_of_work'] ?? null,
                'start_date' => $data['start_date'] ?? $data['commencement_date'] ?? Carbon::now(),
                'completion_date' => $data['completion_date'] ?? $data['completion_due_date'] ?? Carbon::now()->addDays(30),
                'contract_value' => 0.00,
                'revised_value' => 0.00,
                'retention_percent' => $data['retention_percent'] ?? 5.00,
                'retention_cap_amount' => $data['retention_cap_amount'] ?? null,
                'tds_rate_percent' => $data['tds_rate_percent'] ?? $subcontractor->defaultTdsRate(),
                'gst_rate_percent' => $data['gst_rate_percent'] ?? 18.00,
                'payment_terms' => $data['payment_terms'] ?? '30_days',
                'status' => 'draft',
                'created_by' => $user->id,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalContractValue = 0.0;
            $items = $data['items'] ?? [];

            foreach ($items as $idx => $itemData) {
                $qty = (float) $itemData['approved_quantity'];
                $rate = (float) $itemData['unit_rate'];
                $lineTotal = round($qty * $rate, 2);

                SubcontractorWorkOrderItem::create([
                    'work_order_id' => $workOrder->id,
                    'item_code' => $itemData['item_code'] ?? ('BOQ-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT)),
                    'description' => $itemData['description'],
                    'category' => $itemData['category'] ?? $workOrder->work_category,
                    'unit_of_measure' => $itemData['unit_of_measure'] ?? 'cum',
                    'approved_quantity' => $qty,
                    'unit_rate' => $rate,
                    'total_amount' => $lineTotal,
                ]);

                $totalContractValue += $lineTotal;
            }

            $workOrder->update([
                'contract_value' => round($totalContractValue, 2),
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'create_subcontractor_work_order',
                'auditable_type' => SubcontractorWorkOrder::class,
                'auditable_id' => $workOrder->id,
                'new_values' => [
                    'wo_number' => $workOrder->wo_number,
                    'contract_value' => $workOrder->contract_value,
                    'subcontractor' => $subcontractor->name,
                ],
            ]);

            return $workOrder->fresh(['items', 'subcontractor', 'project']);
        });
    }

    /**
     * Approve a Subcontractor Work Order.
     */
    public static function approveWorkOrder(SubcontractorWorkOrder $workOrder, User $user): SubcontractorWorkOrder
    {
        if (! in_array($workOrder->status, ['draft', 'submitted'])) {
            throw new \InvalidArgumentException("Work Order {$workOrder->wo_number} is already {$workOrder->status} and cannot be re-approved.");
        }

        $workOrder->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ]);

        AuditLog::create([
            'company_id' => $workOrder->company_id,
            'user_id' => $user->id,
            'action' => 'approve_subcontractor_work_order',
            'auditable_type' => SubcontractorWorkOrder::class,
            'auditable_id' => $workOrder->id,
            'new_values' => [
                'status' => 'approved',
                'approved_by' => $user->name,
                'approved_at' => Carbon::now()->toDateTimeString(),
            ],
        ]);

        return $workOrder->fresh();
    }

    /**
     * Add and approve a controlled variation to a Work Order.
     */
    public static function addWorkOrderVariation(SubcontractorWorkOrder $workOrder, array $data, User $user): SubcontractorWorkOrderVariation
    {
        return DB::transaction(function () use ($workOrder, $data, $user) {
            $count = $workOrder->variations()->count() + 1;
            $varNumber = 'VAR-' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);

            $itemId = $data['work_order_item_id'] ?? null;
            $item = $itemId ? SubcontractorWorkOrderItem::where('id', $itemId)->where('work_order_id', $workOrder->id)->firstOrFail() : null;

            $prevQty = $item ? (float) $item->approved_quantity : null;
            $revQty = isset($data['revised_quantity']) ? (float) $data['revised_quantity'] : $prevQty;
            $prevRate = $item ? (float) $item->unit_rate : null;
            $revRate = isset($data['revised_rate']) ? (float) $data['revised_rate'] : $prevRate;

            $prevTotal = $item ? (float) $item->total_amount : 0.0;
            $revTotal = ($revQty !== null && $revRate !== null) ? round($revQty * $revRate, 2) : $prevTotal;
            $valueImpact = round($revTotal - $prevTotal, 2);

            if (isset($data['value_impact'])) {
                $valueImpact = (float) $data['value_impact'];
            }

            $variation = SubcontractorWorkOrderVariation::create([
                'work_order_id' => $workOrder->id,
                'variation_number' => $varNumber,
                'variation_type' => $data['variation_type'] ?? 'quantity_increase',
                'work_order_item_id' => $item?->id,
                'previous_quantity' => $prevQty,
                'revised_quantity' => $revQty,
                'previous_rate' => $prevRate,
                'revised_rate' => $revRate,
                'value_impact' => $valueImpact,
                'reason' => $data['reason'],
                'status' => 'approved',
                'requested_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
            ]);

            // Apply modification to item if present
            if ($item) {
                $item->update([
                    'approved_quantity' => $revQty,
                    'unit_rate' => $revRate,
                    'total_amount' => $revTotal,
                ]);
            }

            // Update Work Order revised value
            $currentEffective = $workOrder->effectiveValue();
            $newRevisedValue = round($currentEffective + $valueImpact, 2);
            $workOrder->update([
                'revised_value' => $newRevisedValue,
            ]);

            AuditLog::create([
                'company_id' => $workOrder->company_id,
                'user_id' => $user->id,
                'action' => 'approved_work_order_variation',
                'auditable_type' => SubcontractorWorkOrderVariation::class,
                'auditable_id' => $variation->id,
                'new_values' => [
                    'variation_number' => $varNumber,
                    'value_impact' => $valueImpact,
                    'new_work_order_value' => $newRevisedValue,
                ],
            ]);

            return $variation;
        });
    }

    /**
     * Record a site measurement entry in the digital Measurement Book (MB).
     */
    public static function recordMeasurement(array $data, User $user): MeasurementBookEntry
    {
        return DB::transaction(function () use ($data, $user) {
            // Resolve Work Order and Project if not explicitly provided
            if (! isset($data['work_order_id']) && isset($data['work_order_item_id'])) {
                $item = SubcontractorWorkOrderItem::with('workOrder')->find($data['work_order_item_id']);
                if ($item && $item->workOrder) {
                    $data['work_order_id'] = $item->work_order_id;
                    $data['project_id'] = $data['project_id'] ?? $item->workOrder->project_id;
                }
            }

            // Lock Work Order and Item
            $workOrder = SubcontractorWorkOrder::where('id', $data['work_order_id'])->firstOrFail();
            $companyId = $workOrder->company_id;

            if (! in_array($workOrder->status, ['approved', 'active'])) {
                throw new \InvalidArgumentException("Measurements cannot be recorded for Work Order {$workOrder->wo_number} because its status is '{$workOrder->status}'. Work Order must be approved first.");
            }

            $item = SubcontractorWorkOrderItem::where('id', $data['work_order_item_id'])
                ->where('work_order_id', $workOrder->id)
                ->firstOrFail();

            // Mismatched project/site validation
            $projectId = $data['project_id'] ?? $workOrder->project_id;
            if ((int) $projectId !== (int) $workOrder->project_id) {
                throw new \InvalidArgumentException("Measurement project #{$projectId} does not match Work Order project #{$workOrder->project_id}.");
            }

            // Calculate measured quantity
            $formula = $data['measurement_formula'] ?? 'dimensions_l_b_h';
            $length = isset($data['length']) ? (float) $data['length'] : null;
            $breadth = isset($data['breadth']) ? (float) $data['breadth'] : null;
            $depth = isset($data['depth_height']) ? (float) $data['depth_height'] : null;
            $multiplier = isset($data['multiplier_units']) ? (float) $data['multiplier_units'] : 1.00;
            $manualOverride = isset($data['manual_quantity_override']) && $data['manual_quantity_override'] !== '' ? (float) $data['manual_quantity_override'] : null;

            $overrideReason = $data['override_reason'] ?? $data['manual_override_reason'] ?? null;
            if ($manualOverride !== null && empty($overrideReason)) {
                throw new \InvalidArgumentException("An authorized override reason is required whenever a manual quantity override is specified.");
            }

            $measuredQty = MeasurementBookEntry::computeFormulaQuantity(
                $formula,
                $length,
                $breadth,
                $depth,
                $multiplier,
                $manualOverride
            );

            if ($measuredQty <= 0) {
                throw new \InvalidArgumentException("Measured quantity must be greater than zero.");
            }

            // Calculate cumulative quantities
            $previouslyCertified = (float) $item->certified_quantity;
            $priorApprovedUncertified = (float) MeasurementBookEntry::where('work_order_item_id', $item->id)
                ->where('status', 'approved')
                ->whereNull('certified_in_bill_id')
                ->sum('measured_quantity');

            $cumulativeQty = round($previouslyCertified + $priorApprovedUncertified + $measuredQty, 3);
            $approvedLimit = (float) $item->approved_quantity;

            if ($cumulativeQty > $approvedLimit) {
                throw new \InvalidArgumentException("Cumulative measured quantity ({$cumulativeQty} {$item->unit_of_measure}) exceeds the approved work order limit ({$approvedLimit} {$item->unit_of_measure}) for BOQ item {$item->item_code}. Please request an authorized Work Order Variation first.");
            }

            // MB numbering
            $count = MeasurementBookEntry::where('company_id', $companyId)->count() + 1;
            $mbNumber = 'MB-' . date('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

            $status = $data['status'] ?? 'submitted';

            $mb = MeasurementBookEntry::create([
                'company_id' => $companyId,
                'work_order_id' => $workOrder->id,
                'work_order_item_id' => $item->id,
                'project_id' => $workOrder->project_id,
                'site_id' => $data['site_id'] ?? $workOrder->site_id,
                'subcontractor_id' => $workOrder->subcontractor_id,
                'mb_number' => $mbNumber,
                'measurement_date' => $data['measurement_date'],
                'period_start_date' => $data['period_start_date'] ?? $data['measurement_date'],
                'period_end_date' => $data['period_end_date'] ?? $data['measurement_date'],
                'location_chainage' => $data['location_chainage'] ?? null,
                'description' => $data['description'] ?? $data['work_description'] ?? 'Site measurement',
                'measurement_formula' => $formula,
                'length' => $length,
                'breadth' => $breadth,
                'depth_height' => $depth,
                'multiplier_units' => $multiplier,
                'manual_quantity_override' => $manualOverride,
                'override_reason' => $data['override_reason'] ?? $data['manual_override_reason'] ?? null,
                'measured_quantity' => $measuredQty,
                'previously_certified_quantity' => $previouslyCertified,
                'cumulative_quantity' => $cumulativeQty,
                'status' => $status,
                'measured_by' => $user->id,
                'submitted_at' => Carbon::now(),
                'site_remarks' => $data['site_remarks'] ?? null,
                'document_path' => $data['document_path'] ?? null,
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'record_measurement_book_entry',
                'auditable_type' => MeasurementBookEntry::class,
                'auditable_id' => $mb->id,
                'new_values' => [
                    'mb_number' => $mb->mb_number,
                    'measured_quantity' => $mb->measured_quantity,
                    'cumulative_quantity' => $mb->cumulative_quantity,
                ],
            ]);

            return $mb;
        });
    }

    /**
     * Review and approve a measurement book entry.
     */
    public static function approveMeasurement(MeasurementBookEntry $mb, User $user): MeasurementBookEntry
    {
        return DB::transaction(function () use ($mb, $user) {
            $lockedMb = MeasurementBookEntry::where('id', $mb->id)->lockForUpdate()->firstOrFail();

            if ($lockedMb->status === 'certified') {
                throw new \InvalidArgumentException("MB entry {$lockedMb->mb_number} is already certified in a bill and cannot be re-approved.");
            }

            if ($lockedMb->status === 'approved') {
                return $lockedMb;
            }

            // Verify cumulative limit again under lock
            $item = $lockedMb->workOrderItem;
            $previouslyCertified = (float) $item->certified_quantity;
            $otherApproved = (float) MeasurementBookEntry::where('work_order_item_id', $item->id)
                ->where('id', '!=', $lockedMb->id)
                ->where('status', 'approved')
                ->whereNull('certified_in_bill_id')
                ->sum('measured_quantity');

            $cumulative = round($previouslyCertified + $otherApproved + (float) $lockedMb->measured_quantity, 3);
            if ($cumulative > (float) $item->approved_quantity) {
                throw new \InvalidArgumentException("Approving MB entry {$lockedMb->mb_number} would cause cumulative quantity ({$cumulative}) to exceed approved limit ({$item->approved_quantity}).");
            }

            $lockedMb->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => Carbon::now(),
                'cumulative_quantity' => $cumulative,
                'rejection_reason' => null,
            ]);

            AuditLog::create([
                'company_id' => $lockedMb->company_id,
                'user_id' => $user->id,
                'action' => 'approve_measurement_entry',
                'auditable_type' => MeasurementBookEntry::class,
                'auditable_id' => $lockedMb->id,
                'new_values' => [
                    'mb_number' => $lockedMb->mb_number,
                    'status' => 'approved',
                    'reviewed_by' => $user->name,
                ],
            ]);

            return $lockedMb;
        });
    }

    /**
     * Reject a measurement book entry with mandatory reason.
     */
    public static function rejectMeasurement(MeasurementBookEntry $mb, string $reason, User $user): MeasurementBookEntry
    {
        if (trim($reason) === '' || strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException("A detailed rejection reason of at least 5 characters is mandatory.");
        }

        return DB::transaction(function () use ($mb, $reason, $user) {
            $lockedMb = MeasurementBookEntry::where('id', $mb->id)->lockForUpdate()->firstOrFail();

            if ($lockedMb->status === 'certified') {
                throw new \InvalidArgumentException("MB entry {$lockedMb->mb_number} has already been certified in a bill and cannot be rejected.");
            }

            $lockedMb->update([
                'status' => 'rejected',
                'rejection_reason' => trim($reason),
                'reviewed_by' => $user->id,
                'reviewed_at' => Carbon::now(),
            ]);

            AuditLog::create([
                'company_id' => $lockedMb->company_id,
                'user_id' => $user->id,
                'action' => 'reject_measurement_entry',
                'auditable_type' => MeasurementBookEntry::class,
                'auditable_id' => $lockedMb->id,
                'new_values' => [
                    'mb_number' => $lockedMb->mb_number,
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                ],
            ]);

            return $lockedMb;
        });
    }

    /**
     * Correct and resubmit a rejected measurement entry.
     */
    public static function resubmitMeasurement(MeasurementBookEntry $mb, array $data, User $user): MeasurementBookEntry
    {
        return DB::transaction(function () use ($mb, $data, $user) {
            $lockedMb = MeasurementBookEntry::where('id', $mb->id)->lockForUpdate()->firstOrFail();

            if ($lockedMb->status !== 'rejected') {
                throw new \InvalidArgumentException("Only rejected measurements can be corrected and resubmitted.");
            }

            $formula = $data['measurement_formula'] ?? $lockedMb->measurement_formula;
            $length = isset($data['length']) ? (float) $data['length'] : (float) $lockedMb->length;
            $breadth = isset($data['breadth']) ? (float) $data['breadth'] : (float) $lockedMb->breadth;
            $depth = isset($data['depth_height']) ? (float) $data['depth_height'] : (float) $lockedMb->depth_height;
            $multiplier = isset($data['multiplier_units']) ? (float) $data['multiplier_units'] : (float) $lockedMb->multiplier_units;
            $manualOverride = array_key_exists('manual_quantity_override', $data)
                ? ($data['manual_quantity_override'] !== null && $data['manual_quantity_override'] !== '' ? (float) $data['manual_quantity_override'] : null)
                : $lockedMb->manual_quantity_override;

            $measuredQty = MeasurementBookEntry::computeFormulaQuantity(
                $formula,
                $length,
                $breadth,
                $depth,
                $multiplier,
                $manualOverride
            );

            $item = $lockedMb->workOrderItem;
            $previouslyCertified = (float) $item->certified_quantity;
            $otherApproved = (float) MeasurementBookEntry::where('work_order_item_id', $item->id)
                ->where('id', '!=', $lockedMb->id)
                ->where('status', 'approved')
                ->whereNull('certified_in_bill_id')
                ->sum('measured_quantity');

            $cumulative = round($previouslyCertified + $otherApproved + $measuredQty, 3);
            if ($cumulative > (float) $item->approved_quantity) {
                throw new \InvalidArgumentException("Resubmitted quantity ({$cumulative}) exceeds approved limit ({$item->approved_quantity}).");
            }

            $lockedMb->update([
                'description' => $data['description'] ?? $lockedMb->description,
                'measurement_formula' => $formula,
                'length' => $length,
                'breadth' => $breadth,
                'depth_height' => $depth,
                'multiplier_units' => $multiplier,
                'manual_quantity_override' => $manualOverride,
                'override_reason' => $data['override_reason'] ?? $lockedMb->override_reason,
                'measured_quantity' => $measuredQty,
                'cumulative_quantity' => $cumulative,
                'status' => 'submitted',
                'site_remarks' => ($lockedMb->site_remarks ? $lockedMb->site_remarks . " | " : '') . "Resubmitted on " . date('Y-m-d H:i') . " after rejection: " . $lockedMb->rejection_reason,
                'submitted_at' => Carbon::now(),
                'rejection_reason' => null,
            ]);

            AuditLog::create([
                'company_id' => $lockedMb->company_id,
                'user_id' => $user->id,
                'action' => 'resubmit_measurement_entry',
                'auditable_type' => MeasurementBookEntry::class,
                'auditable_id' => $lockedMb->id,
                'new_values' => [
                    'mb_number' => $lockedMb->mb_number,
                    'measured_quantity' => $measuredQty,
                    'status' => 'submitted',
                ],
            ]);

            return $lockedMb;
        });
    }

    /**
     * Create a Subcontractor Running Account (RA) Bill from approved Measurement Book entries.
     */
    public static function createBillFromMeasurements(
        SubcontractorWorkOrder|array $workOrder,
        array|User $mbEntryIds = [],
        array $billData = [],
        ?User $user = null
    ): SubcontractorBill {
        if (is_array($workOrder)) {
            $data = $workOrder;
            $user = $mbEntryIds instanceof User ? $mbEntryIds : ($user ?? auth()->user());
            $woId = $data['subcontractor_work_order_id'] ?? $data['work_order_id'] ?? null;
            $workOrder = SubcontractorWorkOrder::findOrFail($woId);
            $mbEntryIds = $data['measurement_book_entry_ids'] ?? [];
            $billData = $data;
        }

        return DB::transaction(function () use ($workOrder, $mbEntryIds, $billData, $user) {
            $companyId = $workOrder->company_id;

            if (empty($mbEntryIds)) {
                throw new \InvalidArgumentException("At least one approved Measurement Book entry must be selected to generate an RA Bill.");
            }

            // Lock and validate all MB entries
            $mbEntries = MeasurementBookEntry::whereIn('id', $mbEntryIds)
                ->where('company_id', $companyId)
                ->where('work_order_id', $workOrder->id)
                ->lockForUpdate()
                ->get();

            if ($mbEntries->count() !== count($mbEntryIds)) {
                throw new \InvalidArgumentException("One or more selected measurement entries do not exist or belong to a different work order.");
            }

            foreach ($mbEntries as $mb) {
                if ($mb->certified_in_bill_id !== null || $mb->status === 'certified') {
                    throw new \InvalidArgumentException("Measurement {$mb->mb_number} has already been billed under Bill #{$mb->certified_in_bill_id}. Duplicate billing is strictly prohibited.");
                }
                if ($mb->status !== 'approved') {
                    throw new \InvalidArgumentException("Measurement {$mb->mb_number} cannot be billed because its status is '{$mb->status}'. Only 'approved' measurements can be billed.");
                }
            }

            // Group MB quantities by work order item
            $groupedByItem = [];
            foreach ($mbEntries as $mb) {
                $itemId = $mb->work_order_item_id;
                if (! isset($groupedByItem[$itemId])) {
                    $groupedByItem[$itemId] = [
                        'item' => $mb->workOrderItem,
                        'current_billed_quantity' => 0.0,
                        'entries' => [],
                    ];
                }
                $groupedByItem[$itemId]['current_billed_quantity'] += (float) $mb->measured_quantity;
                $groupedByItem[$itemId]['entries'][] = $mb;
            }

            // Verify work order limits and compute line values
            $totalGrossWorkValue = 0.0;
            $billItemsToCreate = [];

            foreach ($groupedByItem as $itemId => $group) {
                $item = $group['item'];
                $prevBilledQty = (float) $item->billed_quantity;
                $currentBilledQty = round($group['current_billed_quantity'], 3);
                $cumulativeBilledQty = round($prevBilledQty + $currentBilledQty, 3);
                $approvedLimit = (float) $item->approved_quantity;

                if ($cumulativeBilledQty > $approvedLimit) {
                    throw new \InvalidArgumentException("Cumulative billed quantity ({$cumulativeBilledQty} {$item->unit_of_measure}) exceeds approved limit ({$approvedLimit} {$item->unit_of_measure}) for BOQ item {$item->item_code}.");
                }

                $unitRate = (float) $item->unit_rate;
                $lineGross = round($currentBilledQty * $unitRate, 2);
                $totalGrossWorkValue += $lineGross;

                $billItemsToCreate[] = [
                    'work_order_item_id' => $item->id,
                    'previously_billed_quantity' => $prevBilledQty,
                    'current_billed_quantity' => $currentBilledQty,
                    'cumulative_billed_quantity' => $cumulativeBilledQty,
                    'unit_rate' => $unitRate,
                    'gross_amount' => $lineGross,
                ];
            }

            $totalGrossWorkValue = round($totalGrossWorkValue, 2);

            // Compute deductions & taxes
            $retentionRate = isset($billData['retention_rate_percent']) ? (float) $billData['retention_rate_percent'] : (float) $workOrder->retention_percent;
            $retentionAmount = round($totalGrossWorkValue * ($retentionRate / 100), 2);

            $tdsRate = isset($billData['tds_rate_percent']) ? (float) $billData['tds_rate_percent'] : (float) $workOrder->tds_rate_percent;
            $tdsAmount = round($totalGrossWorkValue * ($tdsRate / 100), 2);

            $gstRate = isset($billData['gst_rate_percent']) ? (float) $billData['gst_rate_percent'] : (float) $workOrder->gst_rate_percent;
            $gstAmount = round($totalGrossWorkValue * ($gstRate / 100), 2);

            $advanceRecovery = isset($billData['advance_recovery_amount']) ? (float) $billData['advance_recovery_amount'] : 0.00;
            $otherDeductions = isset($billData['other_deductions_amount']) ? (float) $billData['other_deductions_amount'] : 0.00;

            // Net Payable = Gross Work Value + GST - Retention - TDS - Advance Recovery - Other Deductions
            $netPayable = round($totalGrossWorkValue + $gstAmount - $retentionAmount - $tdsAmount - $advanceRecovery - $otherDeductions, 2);

            // Previous certified value
            $prevCertifiedValue = (float) SubcontractorBill::where('work_order_id', $workOrder->id)
                ->whereIn('status', ['certified', 'approved_for_payment', 'partially_paid', 'paid'])
                ->sum('gross_work_value');

            $cumulativeGrossValue = round($prevCertifiedValue + $totalGrossWorkValue, 2);

            // Bill number & sequence
            $sequence = SubcontractorBill::where('work_order_id', $workOrder->id)->count() + 1;
            $billNumber = $billData['bill_number'] ?? ('RA-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT) . '-' . $workOrder->wo_number);

            $bill = SubcontractorBill::create([
                'company_id' => $companyId,
                'project_id' => $workOrder->project_id,
                'subcontractor_id' => $workOrder->subcontractor_id,
                'work_order_id' => $workOrder->id,
                'bill_number' => $billNumber,
                'bill_sequence' => $sequence,
                'bill_date' => $billData['bill_date'] ?? $billData['invoice_date'] ?? Carbon::today(),
                'period_start_date' => $billData['period_start_date'] ?? $billData['bill_period_start'] ?? $mbEntries->min('measurement_date') ?? Carbon::today(),
                'period_end_date' => $billData['period_end_date'] ?? $billData['bill_period_end'] ?? $mbEntries->max('measurement_date') ?? Carbon::today(),
                'gross_work_value' => $totalGrossWorkValue,
                'cumulative_gross_value' => $cumulativeGrossValue,
                'previously_certified_value' => $prevCertifiedValue,
                'retention_rate_percent' => $retentionRate,
                'retention_amount' => $retentionAmount,
                'tds_rate_percent' => $tdsRate,
                'tds_amount' => $tdsAmount,
                'gst_rate_percent' => $gstRate,
                'gst_amount' => $gstAmount,
                'advance_recovery_amount' => $advanceRecovery,
                'other_deductions_amount' => $otherDeductions,
                'net_payable_amount' => $netPayable,
                'paid_amount' => 0.00,
                'status' => 'draft',
                'created_by' => $user->id,
                'notes' => $billData['notes'] ?? null,
            ]);

            // Create bill items
            foreach ($billItemsToCreate as $itemRow) {
                $itemRow['subcontractor_bill_id'] = $bill->id;
                SubcontractorBillItem::create($itemRow);
            }

            // Create measurement pivot allocations
            foreach ($mbEntries as $mb) {
                SubcontractorBillMeasurement::create([
                    'subcontractor_bill_id' => $bill->id,
                    'measurement_book_entry_id' => $mb->id,
                    'allocated_quantity' => $mb->measured_quantity,
                ]);
            }

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'create_subcontractor_bill',
                'auditable_type' => SubcontractorBill::class,
                'auditable_id' => $bill->id,
                'new_values' => [
                    'bill_number' => $bill->bill_number,
                    'gross_work_value' => $totalGrossWorkValue,
                    'net_payable' => $netPayable,
                ],
            ]);

            return $bill->fresh(['items', 'measurementsPivot', 'workOrder', 'subcontractor']);
        });
    }

    /**
     * Certify Subcontractor Bill and post balanced double-entry journal entries.
     */
    public static function certifyBill(SubcontractorBill $bill, User $user): SubcontractorBill
    {
        return DB::transaction(function () use ($bill, $user) {
            $lockedBill = SubcontractorBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedBill->status, ['certified', 'approved_for_payment', 'partially_paid', 'paid'])) {
                throw new \InvalidArgumentException("Bill {$lockedBill->bill_number} is already {$lockedBill->status} and cannot be certified again.");
            }

            $companyId = $lockedBill->company_id;
            $workOrder = SubcontractorWorkOrder::where('id', $lockedBill->work_order_id)->lockForUpdate()->firstOrFail();

            // 1. Validate and mark Measurement Book entries as certified
            $mbPivots = SubcontractorBillMeasurement::where('subcontractor_bill_id', $lockedBill->id)->get();
            foreach ($mbPivots as $pivot) {
                $mbEntry = MeasurementBookEntry::where('id', $pivot->measurement_book_entry_id)->lockForUpdate()->firstOrFail();
                if ($mbEntry->status !== 'approved') {
                    throw new \InvalidArgumentException("MB entry {$mbEntry->mb_number} is in '{$mbEntry->status}' state and cannot be certified.");
                }
                $mbEntry->update([
                    'status' => 'certified',
                    'certified_in_bill_id' => $lockedBill->id,
                ]);
            }

            // 2. Update Work Order Items with certified & billed quantities
            $billItems = SubcontractorBillItem::where('subcontractor_bill_id', $lockedBill->id)->get();
            foreach ($billItems as $bItem) {
                $woItem = SubcontractorWorkOrderItem::where('id', $bItem->work_order_item_id)->lockForUpdate()->firstOrFail();
                $newBilledQty = round((float) $woItem->billed_quantity + (float) $bItem->current_billed_quantity, 3);
                $newCertifiedQty = round((float) $woItem->certified_quantity + (float) $bItem->current_billed_quantity, 3);

                if ($newBilledQty > (float) $woItem->approved_quantity) {
                    throw new \InvalidArgumentException("Certified quantity for item {$woItem->item_code} exceeds approved limit.");
                }

                $woItem->update([
                    'billed_quantity' => $newBilledQty,
                    'certified_quantity' => $newCertifiedQty,
                ]);
            }

            // 3. Post Double-Entry Journal Entry
            $grossWorkValue = (float) $lockedBill->gross_work_value;
            $gstAmount = (float) $lockedBill->gst_amount;
            $retentionAmount = (float) $lockedBill->retention_amount;
            $tdsAmount = (float) $lockedBill->tds_amount;
            $advanceRecovery = (float) $lockedBill->advance_recovery_amount;
            $otherDeductions = (float) $lockedBill->other_deductions_amount;
            $netPayable = (float) $lockedBill->net_payable_amount;

            // Debits: Gross Work Value (Dr 5040) + GST Input Tax Credit (Dr 1030)
            $totalDebits = round($grossWorkValue + $gstAmount, 2);

            // Credits: Retention (Cr 2030) + TDS (Cr 2040) + Advance Recovery + Net Payable (Cr 2020)
            $totalCredits = round($retentionAmount + $tdsAmount + $advanceRecovery + $otherDeductions + $netPayable, 2);

            if (bccomp((string) $totalDebits, (string) $totalCredits, 2) !== 0) {
                throw new \InvalidArgumentException("Journal entry does not balance: Debits ({$totalDebits}) != Credits ({$totalCredits}).");
            }

            // Resolve Standard Accounts
            $costAccount = self::getOrCreateAccount($companyId, '5040', 'Subcontracting & Labour Contract Works', 'expense', 'direct_expense');
            $gstInputAccount = self::getOrCreateAccount($companyId, '1030', 'GST Input Tax Credit / Input GST Receivable', 'asset', 'current_asset');
            $subconPayableAccount = self::getOrCreateAccount($companyId, '2020', 'Subcontractor & Labour Payables', 'liability', 'current_liability');
            $retentionPayableAccount = self::getOrCreateAccount($companyId, '2030', 'Subcontractor Retention Money Withheld', 'liability', 'current_liability');
            $tdsPayableAccount = self::getOrCreateAccount($companyId, '2040', 'TDS Payable u/s 194C', 'liability', 'current_liability');
            $advanceRecoveryAccount = self::getOrCreateAccount($companyId, '1040', 'Mobilization & Material Advances to Subcontractors', 'asset', 'current_asset');
            $otherDeductionsAccount = self::getOrCreateAccount($companyId, '1050', 'Other Contractor Deductions & Recoveries', 'asset', 'current_asset');

            $journalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_number' => 'JV-SCB-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => $lockedBill->bill_date,
                'reference_type' => 'subcontractor_bills',
                'reference_id' => $lockedBill->id,
                'narration' => "Subcontractor RA Bill Certification: {$lockedBill->bill_number} for {$lockedBill->subcontractor->name} (WO: {$workOrder->wo_number})",
                'total_amount' => $totalDebits,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // Line 1: Debit Subcontractor Cost (tagged with project)
            if ($grossWorkValue > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $costAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => $grossWorkValue,
                    'credit' => 0.00,
                    'description' => "Certified subcontractor work value for {$lockedBill->bill_number}",
                ]);
            }

            // Line 2: Debit Input GST (Input Tax Credit)
            if ($gstAmount > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $gstInputAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => $gstAmount,
                    'credit' => 0.00,
                    'description' => "Input GST on subcontractor bill {$lockedBill->bill_number}",
                ]);
            }

            // Line 3: Credit Subcontractor Retention Withheld (Liability 2030)
            if ($retentionAmount > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $retentionPayableAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => 0.00,
                    'credit' => $retentionAmount,
                    'description' => "Retention withheld from subcontractor for {$lockedBill->bill_number}",
                ]);
            }

            // Line 4: Credit TDS Payable u/s 194C (Liability 2040)
            if ($tdsAmount > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $tdsPayableAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => 0.00,
                    'credit' => $tdsAmount,
                    'description' => "TDS deducted u/s 194C on {$lockedBill->bill_number}",
                ]);
            }

            // Line 5: Credit Advance Recovery (Asset 1040 recoupment)
            if ($advanceRecovery > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $advanceRecoveryAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => 0.00,
                    'credit' => $advanceRecovery,
                    'description' => "Mobilization advance recovery against {$lockedBill->bill_number}",
                ]);
            }

            // Line 6: Credit Other Deductions & Recoveries (Asset 1050)
            if ($otherDeductions > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $otherDeductionsAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => 0.00,
                    'credit' => $otherDeductions,
                    'description' => "Other authorized contract deductions on {$lockedBill->bill_number}",
                ]);
            }

            // Line 7: Credit Net Subcontractor Payable (Liability 2020)
            if ($netPayable > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $subconPayableAccount->id,
                    'project_id' => $lockedBill->project_id,
                    'debit' => 0.00,
                    'credit' => $netPayable,
                    'description' => "Net amount payable to {$lockedBill->subcontractor->name} for {$lockedBill->bill_number}",
                ]);
            }

            // Invariant Verification: Ensure actual posted journal lines are balanced down to 2 decimal places
            $actualDebits = (float) $journalEntry->lines()->sum('debit');
            $actualCredits = (float) $journalEntry->lines()->sum('credit');
            if (bccomp((string) $actualDebits, (string) $actualCredits, 2) !== 0) {
                throw new \LogicException("CRITICAL: Posted journal lines do not balance: Debits ({$actualDebits}) != Credits ({$actualCredits}).");
            }

            // Update Bill record
            $lockedBill->update([
                'status' => 'certified',
                'certified_by' => $user->id,
                'certified_at' => Carbon::now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'certify_subcontractor_bill',
                'auditable_type' => SubcontractorBill::class,
                'auditable_id' => $lockedBill->id,
                'new_values' => [
                    'bill_number' => $lockedBill->bill_number,
                    'status' => 'certified',
                    'journal_entry' => $journalEntry->entry_number,
                    'net_payable' => $netPayable,
                ],
            ]);

            return $lockedBill->fresh();
        });
    }

    /**
     * Record a payment remittance against a certified subcontractor bill.
     */
    public static function recordBillPayment(SubcontractorBill $bill, array $data, User $user): SubcontractorBillPayment
    {
        return DB::transaction(function () use ($bill, $data, $user) {
            $lockedBill = SubcontractorBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedBill->status, ['certified', 'approved_for_payment', 'partially_paid'])) {
                throw new \InvalidArgumentException("Payments can only be recorded against certified or approved bills. Current status: {$lockedBill->status}.");
            }

            $paymentAmount = round((float) $data['amount'], 2);
            if ($paymentAmount <= 0) {
                throw new \InvalidArgumentException("Payment amount must be greater than zero.");
            }

            $outstanding = $lockedBill->outstandingBalance();
            if ($paymentAmount > $outstanding) {
                throw new \InvalidArgumentException("Payment amount (₹" . number_format($paymentAmount, 2) . ") exceeds outstanding balance (₹" . number_format($outstanding, 2) . "). Overpayment is strictly prevented.");
            }

            $companyId = $lockedBill->company_id;
            $paymentDate = ! empty($data['payment_date']) ? Carbon::parse($data['payment_date']) : Carbon::today();
            $paymentRef = $data['transaction_reference'] ?? ('NEFT-' . strtoupper(Str::random(6)));

            // Resolve Bank Account
            $bankAccount = null;
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::where('id', $data['bank_account_id'])->where('company_id', $companyId)->first();
            }

            $bankLedgerAccount = null;
            if ($bankAccount && $bankAccount->account_id) {
                $bankLedgerAccount = Account::find($bankAccount->account_id);
            }
            if (! $bankLedgerAccount) {
                $bankLedgerAccount = self::getOrCreateAccount($companyId, '1010', 'HDFC Current Bank Account', 'asset', 'current_asset');
            }

            $subconPayableAccount = self::getOrCreateAccount($companyId, '2020', 'Subcontractor & Labour Payables', 'liability', 'current_liability');

            // Journal Entry for Payment: Debit Subcontractor Payable 2020 / Credit Bank Account 1010
            $journalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_number' => 'JV-SCPAY-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'entry_date' => $paymentDate,
                'reference_type' => 'subcontractor_bill_payments',
                'reference_id' => $lockedBill->id,
                'narration' => "Payment against Subcontractor Bill {$lockedBill->bill_number} to {$lockedBill->subcontractor->name} - Ref: {$paymentRef}",
                'total_amount' => $paymentAmount,
                'status' => 'posted',
                'created_by' => $user->id,
            ]);

            // Debit Payable
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $subconPayableAccount->id,
                'project_id' => $lockedBill->project_id,
                'debit' => $paymentAmount,
                'credit' => 0.00,
                'description' => "Settlement of Subcontractor Payable for {$lockedBill->bill_number}",
            ]);

            // Credit Bank
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $bankLedgerAccount->id,
                'project_id' => null,
                'debit' => 0.00,
                'credit' => $paymentAmount,
                'description' => "Bank payment for {$lockedBill->bill_number} via {$paymentRef}",
            ]);

            // Create Payment Record
            $payment = SubcontractorBillPayment::create([
                'company_id' => $companyId,
                'subcontractor_bill_id' => $lockedBill->id,
                'payment_date' => $paymentDate,
                'amount' => $paymentAmount,
                'payment_mode' => $data['payment_mode'] ?? 'bank_transfer',
                'transaction_reference' => $paymentRef,
                'bank_account_id' => $bankAccount?->id,
                'journal_entry_id' => $journalEntry->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // Update Bill paid amount & status
            $newPaidAmount = round((float) $lockedBill->paid_amount + $paymentAmount, 2);
            $newStatus = ($newPaidAmount >= (float) $lockedBill->net_payable_amount) ? 'paid' : 'partially_paid';

            $lockedBill->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'record_subcontractor_bill_payment',
                'auditable_type' => SubcontractorBillPayment::class,
                'auditable_id' => $payment->id,
                'new_values' => [
                    'bill_number' => $lockedBill->bill_number,
                    'payment_amount' => $paymentAmount,
                    'transaction_reference' => $paymentRef,
                    'new_bill_status' => $newStatus,
                ],
            ]);

            return $payment;
        });
    }

    /**
     * Alias for recordBillPayment.
     */
    public static function recordPayment(SubcontractorBill $bill, array $data, User $user): SubcontractorBillPayment
    {
        return self::recordBillPayment($bill, $data, $user);
    }

    /**
     * Reverse a certified subcontractor bill through controlled counter-journal.
     */
    public static function reverseBill(SubcontractorBill $bill, string $reason, User $user): SubcontractorBill
    {
        if (trim($reason) === '' || strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException("An authorized reversal explanation of at least 5 characters is mandatory.");
        }

        return DB::transaction(function () use ($bill, $reason, $user) {
            $lockedBill = SubcontractorBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedBill->status, ['certified', 'approved_for_payment'])) {
                throw new \InvalidArgumentException("Bill cannot be reversed because its current status is '{$lockedBill->status}'. (Bills with payments must reverse payments first).");
            }

            if ((float) $lockedBill->paid_amount > 0) {
                throw new \InvalidArgumentException("Cannot reverse bill {$lockedBill->bill_number} because payments of ₹" . number_format($lockedBill->paid_amount, 2) . " have already been posted.");
            }

            $companyId = $lockedBill->company_id;

            // 1. Post Reversing Counter-Journal
            $originalJournal = $lockedBill->journalEntry;
            $reversalJournal = null;
            if ($originalJournal) {
                // Mark original journal entry as reversed with audit trace
                $originalJournal->update([
                    'status' => 'reversed',
                    'reversed_by' => $user->id,
                    'reversed_at' => Carbon::now(),
                    'reversal_reason' => $reason,
                ]);

                $reversalJournal = JournalEntry::create([
                    'company_id' => $companyId,
                    'entry_number' => 'JV-REV-SCB-' . date('Y') . '-' . strtoupper(Str::random(5)),
                    'entry_date' => Carbon::today(),
                    'reference_type' => 'subcontractor_bills_reversal',
                    'reference_id' => $lockedBill->id,
                    'narration' => "REVERSAL of Subcontractor Bill {$lockedBill->bill_number} - Reason: {$reason}",
                    'total_amount' => $originalJournal->total_amount,
                    'status' => 'posted',
                    'created_by' => $user->id,
                ]);

                // Swap debits and credits
                foreach ($originalJournal->lines as $line) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $reversalJournal->id,
                        'account_id' => $line->account_id,
                        'project_id' => $line->project_id,
                        'debit' => $line->credit,
                        'credit' => $line->debit,
                        'description' => "Reversal of Line: " . $line->description,
                    ]);
                }

                JournalEntry::lockAfterCreation($reversalJournal->id);
            }

            // 2. Revert Work Order Item certified/billed quantities
            foreach ($lockedBill->items as $bItem) {
                $woItem = SubcontractorWorkOrderItem::where('id', $bItem->work_order_item_id)->lockForUpdate()->firstOrFail();
                $woItem->update([
                    'billed_quantity' => max(0.0, round((float) $woItem->billed_quantity - (float) $bItem->current_billed_quantity, 3)),
                    'certified_quantity' => max(0.0, round((float) $woItem->certified_quantity - (float) $bItem->current_billed_quantity, 3)),
                ]);
            }

            // 3. Reset Measurement Book entries back to approved state
            $mbPivots = SubcontractorBillMeasurement::where('subcontractor_bill_id', $lockedBill->id)->get();
            foreach ($mbPivots as $pivot) {
                $mbEntry = MeasurementBookEntry::where('id', $pivot->measurement_book_entry_id)->lockForUpdate()->first();
                if ($mbEntry) {
                    $mbEntry->update([
                        'status' => 'approved',
                        'certified_in_bill_id' => null,
                    ]);
                }
            }

            // 4. Update Bill status
            $lockedBill->update([
                'status' => 'reversed',
                'rejection_reason' => $reason,
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'action' => 'reverse_subcontractor_bill',
                'auditable_type' => SubcontractorBill::class,
                'auditable_id' => $lockedBill->id,
                'old_values' => [
                    'bill_number' => $lockedBill->bill_number,
                    'status' => 'certified',
                    'original_journal_id' => $originalJournal?->id,
                ],
                'new_values' => [
                    'bill_number' => $lockedBill->bill_number,
                    'status' => 'reversed',
                    'reason' => $reason,
                    'original_journal_id' => $originalJournal?->id,
                    'reversal_journal_id' => $reversalJournal?->id,
                    'reversal_entry_number' => $reversalJournal?->entry_number,
                ],
            ]);

            return $lockedBill->fresh();
        });
    }

    /**
     * Resolve or create a system account in Chart of Accounts.
     */
    protected static function getOrCreateAccount(
        int $companyId,
        string $code,
        string $name,
        string $type,
        string $subType
    ): Account {
        return Account::firstOrCreate([
            'company_id' => $companyId,
            'code' => $code,
        ], [
            'name' => $name,
            'account_type' => $type,
            'sub_type' => $subType,
            'is_system' => true,
        ]);
    }
}
