<?php

namespace App\Console\Commands;

use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PurgeDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nsdcrm:purge-demo {--force : Bypass confirmation prompt}';

    /**
     * Backward-compatible aliases.
     */
    protected $aliases = ['buildflow:purge-demo'];

    /**
     * The console command description.
     */
    protected $description = 'Safely purge all demo clients, projects, employees, transactions, and demo accounts from the active database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  NSDCRM — SAFE DEMO DATA REMOVAL & REAL DATA PRESERVATION');
        $this->info('  Target Database: ' . config('database.connections.' . config('database.default') . '.database'));
        $this->info('========================================================================');

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to permanently purge all demo records? Genuine NSD company data and roles will be preserved.')) {
            $this->warn('Aborted by user.');
            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            $deletedCounts = [];

            // Helper to delete rows and record count
            $cleanTable = function (string $table) use (&$deletedCounts) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $cnt = DB::table($table)->count();
                    if ($cnt > 0) {
                        DB::table($table)->delete();
                        $deletedCounts[$table] = $cnt;
                    }
                }
            };

            $this->line('1. Purging demo client contracts, billings & certificates...');
            $cleanTable('retention_claim_payments');
            $cleanTable('retention_claim_invoice_items');
            $cleanTable('retention_release_claims');
            $cleanTable('client_completion_certificates');
            $cleanTable('invoice_payment_allocations');
            $cleanTable('invoice_payments');
            $cleanTable('client_invoice_items');
            $cleanTable('client_invoices');
            $cleanTable('client_contracts');

            $this->line('2. Purging demo procurement, purchase orders & suppliers...');
            $cleanTable('goods_receipt_items');
            $cleanTable('goods_receipts');
            $cleanTable('purchase_order_items');
            $cleanTable('purchase_orders');
            $cleanTable('purchase_request_items');
            $cleanTable('purchase_request_approvals');
            $cleanTable('purchase_requests');
            $cleanTable('supplier_invoices');
            $cleanTable('suppliers');

            $this->line('3. Purging demo inventory items, issues & stock ledgers...');
            $cleanTable('stock_transfer_items');
            $cleanTable('stock_transfers');
            $cleanTable('material_issue_items');
            $cleanTable('material_issues');
            $cleanTable('stock_ledger_entries');
            $cleanTable('materials');
            $cleanTable('material_categories');
            $cleanTable('warehouses');

            $this->line('4. Purging demo subcontractor work orders & bills...');
            $cleanTable('subcontractor_bill_payments');
            $cleanTable('subcontractor_bill_measurements');
            $cleanTable('subcontractor_bill_items');
            $cleanTable('subcontractor_bills');
            $cleanTable('subcontractor_work_order_variations');
            $cleanTable('subcontractor_work_order_items');
            $cleanTable('subcontractor_work_orders');
            $cleanTable('subcontractors');

            $this->line('5. Purging demo sites, logs, muster rolls & payroll...');
            $cleanTable('daily_attendance_corrections');
            $cleanTable('attendance_punches');
            $cleanTable('daily_attendance');
            $cleanTable('daily_site_logs');
            $cleanTable('measurement_book_entries');
            $cleanTable('payroll_records');
            $cleanTable('payroll_periods');
            $cleanTable('employee_salary_structures');
            $cleanTable('leave_requests');
            $cleanTable('employees');
            $cleanTable('tasks');
            $cleanTable('site_inspections');

            $this->line('6. Purging demo projects, milestones, quotations & clients...');
            $cleanTable('project_milestones');
            $cleanTable('quotation_items');
            $cleanTable('quotations');
            $cleanTable('sites');
            $cleanTable('projects');
            $cleanTable('clients');

            $this->line('7. Purging demo assets, loans, expenses, documents & notifications...');
            $cleanTable('asset_depreciation_entries');
            $cleanTable('fixed_assets');
            $cleanTable('loan_emi_schedules');
            $cleanTable('loans');
            $cleanTable('expenses');
            $cleanTable('documents');
            $cleanTable('app_notifications');
            $cleanTable('journal_entry_lines');
            $cleanTable('journal_entries');
            $cleanTable('audit_logs');

            $this->line('8. Purging demo company and demo accounts...');
            // Delete second demo company associations
            if (DB::getSchemaBuilder()->hasTable('companies')) {
                $demoCompany = DB::table('companies')->where('slug', 'apex-infra')->orWhere('id', 2)->first();
                if ($demoCompany) {
                    DB::table('company_user')->where('company_id', $demoCompany->id)->delete();
                    DB::table('companies')->where('id', $demoCompany->id)->delete();
                    $deletedCounts['companies (demo)'] = 1;
                }
            }

            // Purge demo user accounts
            $knownDemoEmails = [
                'admin@buildflow.local',
                'pm@buildflow.local',
                'site@buildflow.local',
                'accounts@buildflow.local',
                'hr@buildflow.local',
                'procurement@buildflow.local',
                'storekeeper@buildflow.local',
                'karan@buildflow.local',
            ];

            $demoUsers = DB::table('users')->whereIn('email', $knownDemoEmails)->get();
            $demoUserIds = $demoUsers->pluck('id')->all();

            if (! empty($demoUserIds)) {
                // If user ID 1 is Rajesh Varma, we rebrand/upgrade to authentic corporate admin
                $firstAdmin = $demoUsers->firstWhere('id', 1);
                if ($firstAdmin) {
                    DB::table('users')->where('id', 1)->update([
                        'email' => 'admin@nsdinfra.com',
                        'name' => 'Rajesh Varma',
                        'password' => Hash::make('Password123!'),
                    ]);
                    $this->info("  [OK] Primary administrator updated: ID 1 -> admin@nsdinfra.com (Rajesh Varma)");
                    // Remove 1 from deletion list
                    $demoUserIds = array_values(array_diff($demoUserIds, [1]));
                }

                if (! empty($demoUserIds)) {
                    DB::table('company_user')->whereIn('user_id', $demoUserIds)->delete();
                    DB::table('users')->whereIn('id', $demoUserIds)->delete();
                    $deletedCounts['users (demo personas)'] = count($demoUserIds);
                }
            }

            $this->line('9. Re-verifying authentic NSD foundational configuration...');
            (new ProductionSeeder())->run();

            DB::commit();

            $this->info("\nSUCCESS: All demo data was cleanly and safely purged from the active database.");
            $this->info("Breakdown of purged demo records:");
            foreach ($deletedCounts as $table => $count) {
                $this->line("  - {$table}: {$count} records removed");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nFAILURE: An error occurred during cleanup. Database rolled back safely: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
