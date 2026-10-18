<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplierInvoice;

class ProjectBudgetService
{
    /**
     * Get actual posted expenditures for a project (expenses + approved supplier bills).
     */
    public static function getActualCosts(Project $project): float
    {
        // 1. Approved / reimbursed direct operational expenses
        $expenses = (float) Expense::where('project_id', $project->id)
            ->whereIn('status', ['approved', 'reimbursed'])
            ->sum('amount');

        // 2. Approved supplier commercial invoices
        $invoices = (float) SupplierInvoice::whereHas('purchaseOrder', function ($q) use ($project) {
            $q->where('project_id', $project->id);
        })->whereIn('status', ['approved', 'paid', 'partially_paid'])->sum('total_amount');

        $totalActual = $expenses + $invoices;

        // If manual base actual_cost was seeded/set and exceeds transactional records, use the higher
        if ((float) $project->actual_cost > $totalActual && $totalActual === 0.0) {
            return (float) $project->actual_cost;
        }

        return round($totalActual, 2);
    }

    /**
     * Get committed expenditures (open unbilled Purchase Orders + approved unconverted material indents).
     * Prevents double-counting by subtracting already-invoiced portions of POs.
     */
    public static function getCommittedCosts(Project $project, ?int $excludePurchaseOrderId = null, ?int $excludePurchaseRequestId = null): float
    {
        // 1. Open Purchase Orders (total amount minus approved supplier invoices for each PO)
        $poQuery = PurchaseOrder::where('project_id', $project->id)
            ->whereNotIn('status', ['cancelled']);

        if ($excludePurchaseOrderId) {
            $poQuery->where('id', '!=', $excludePurchaseOrderId);
        }

        $openPOs = $poQuery->with('supplierInvoices')->get();
        $unbilledPoCommitments = 0.0;

        foreach ($openPOs as $po) {
            $invoicedAmount = (float) $po->supplierInvoices
                ->whereIn('status', ['approved', 'paid', 'partially_paid'])
                ->sum('total_amount');

            $unbilledRemaining = max(0.0, (float) $po->total_amount - $invoicedAmount);
            $unbilledPoCommitments += $unbilledRemaining;
        }

        // 2. Approved unconverted Purchase Requests (remaining quantities * estimated rate)
        $prQuery = PurchaseRequest::where('project_id', $project->id)
            ->whereIn('status', ['approved', 'partially_converted']);

        if ($excludePurchaseRequestId) {
            $prQuery->where('id', '!=', $excludePurchaseRequestId);
        }

        $approvedPRs = $prQuery->with('items')->get();
        $unconvertedPrCommitments = 0.0;

        foreach ($approvedPRs as $pr) {
            foreach ($pr->items as $prItem) {
                $remQty = $prItem->remainingQuantity();
                $rate = (float) $prItem->estimated_rate;
                if ($remQty > 0 && $rate > 0) {
                    $unconvertedPrCommitments += ($remQty * $rate);
                }
            }
        }

        return round($unbilledPoCommitments + $unconvertedPrCommitments, 2);
    }

    /**
     * Comprehensive evaluation of project budget and the impact of an existing or proposed requisition.
     */
    public static function evaluateProjectBudget(Project $project, float $newRequisitionAmount = 0.00, ?int $currentPrId = null): array
    {
        $approvedBudget = (float) $project->approved_budget;
        $hasBudget = ($approvedBudget > 0.00);

        $actualCosts = self::getActualCosts($project);
        // Exclude current PR from existing commitments so we don't count it twice
        $committedCosts = self::getCommittedCosts($project, null, $currentPrId);

        $totalCommittedAndActual = round($actualCosts + $committedCosts, 2);
        $remainingBefore = round($approvedBudget - $totalCommittedAndActual, 2);

        $remainingAfter = round($remainingBefore - $newRequisitionAmount, 2);
        $isOverBudget = ($hasBudget && $remainingAfter < 0.00);
        $overrunAmount = $isOverBudget ? abs($remainingAfter) : 0.00;

        $utilizationBefore = $hasBudget ? round(($totalCommittedAndActual / $approvedBudget) * 100, 1) : 0.0;
        $utilizationAfter = $hasBudget ? round((($totalCommittedAndActual + $newRequisitionAmount) / $approvedBudget) * 100, 1) : 0.0;

        // Status classification
        if (! $hasBudget) {
            $status = 'unbudgeted';
            $statusLabel = 'Budget Not Set';
            $badgeClass = 'bg-gray-100 text-gray-700 border-gray-300';
        } elseif ($isOverBudget) {
            $status = 'critical_overrun';
            $statusLabel = 'Budget Exceeded';
            $badgeClass = 'bg-red-50 text-red-800 border-red-200';
        } elseif ($utilizationAfter >= 90.0) {
            $status = 'warning_near_limit';
            $statusLabel = 'Near Budget Limit (>90%)';
            $badgeClass = 'bg-amber-50 text-amber-800 border-amber-200';
        } else {
            $status = 'healthy';
            $statusLabel = 'Within Budget';
            $badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
        }

        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'project_code' => $project->code,
            'approved_budget' => $approvedBudget,
            'has_budget' => $hasBudget,
            'actual_costs' => $actualCosts,
            'committed_costs' => $committedCosts,
            'total_committed_and_actual' => $totalCommittedAndActual,
            'newly_requested_cost' => $newRequisitionAmount,
            'remaining_before' => $remainingBefore,
            'remaining_after' => $remainingAfter,
            'is_over_budget' => $isOverBudget,
            'overrun_amount' => $overrunAmount,
            'utilization_percent_before' => $utilizationBefore,
            'utilization_percent_after' => $utilizationAfter,
            'status' => $status,
            'status_label' => $statusLabel,
            'badge_class' => $badgeClass,
        ];
    }
}
