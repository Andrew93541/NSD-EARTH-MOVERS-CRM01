<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Material;
use App\Models\PayrollPeriod;
use App\Models\PurchaseRequest;
use App\Models\StockLedgerEntry;
use App\Models\SupplierInvoice;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendOperationalRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'buildflow:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Scan and dispatch real-time reminders for low-stock materials, overdue purchase requests, and pending approvals';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning for operational reminders and deadline alerts...');

        $company = Company::configuredCompany();
        if (! $company) {
            $this->warn('No configured company found.');
            return self::SUCCESS;
        }

        $companyId = $company->id;
        $today = Carbon::today()->toDateString();
        $dispatchedCount = 0;

        // 1. Scan for Low Stock Materials
        $lowStockMaterials = Material::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('min_stock_level', '>', 0)
            ->get();

        foreach ($lowStockMaterials as $mat) {
            $inStock = StockLedgerEntry::where('material_id', $mat->id)->where('company_id', $companyId)->where('direction', 'in')->sum('quantity');
            $outStock = StockLedgerEntry::where('material_id', $mat->id)->where('company_id', $companyId)->where('direction', 'out')->sum('quantity');
            $currentBalance = max(0.0, (float) ($inStock - $outStock));

            if ($currentBalance <= (float) $mat->min_stock_level) {
                $notifs = NotificationService::sendToRole(
                    $companyId,
                    'storekeeper',
                    "Low Stock Alert: {$mat->name}",
                    "Available stock is {$currentBalance} {$mat->unit_of_measure} (Minimum threshold: {$mat->min_stock_level}). Please initiate replenishment.",
                    'warning',
                    route('inventory.index')
                );
                foreach ($notifs as $n) {
                    NotificationService::broadcastNotification($n);
                    $dispatchedCount++;
                }
            }
        }

        // 2. Scan for Overdue Pending Purchase Requests
        $overduePRs = PurchaseRequest::where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereNotNull('required_by_date')
            ->whereDate('required_by_date', '<', $today)
            ->get();

        foreach ($overduePRs as $pr) {
            if ($pr->requested_by) {
                $n = NotificationService::sendToUser(
                    $companyId,
                    $pr->requested_by,
                    "Overdue Purchase Request: {$pr->pr_number}",
                    "Purchase Request {$pr->pr_number} required by " . Carbon::parse($pr->required_by_date)->format('d M Y') . " remains pending authorization.",
                    'danger',
                    route('purchase-requests.show', $pr)
                );
                NotificationService::broadcastNotification($n);
                $dispatchedCount++;
            }
        }

        // 3. Scan for Pending Overdue Supplier Invoices
        $overdueInvoices = SupplierInvoice::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'approved', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->get();

        foreach ($overdueInvoices as $inv) {
            $notifs = NotificationService::sendToRole(
                $companyId,
                'accountant',
                "Overdue Supplier Invoice: {$inv->invoice_number}",
                "Invoice {$inv->invoice_number} from {$inv->supplier?->name} is overdue for payment.",
                'danger',
                route('procurement.invoices')
            );
            foreach ($notifs as $n) {
                NotificationService::broadcastNotification($n);
                $dispatchedCount++;
            }
        }

        $this->info("Completed reminder scan. Dispatched {$dispatchedCount} alerts.");

        return self::SUCCESS;
    }
}
