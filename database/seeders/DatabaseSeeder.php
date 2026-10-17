<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\DailySiteLog;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Loan;
use App\Models\LoanEmiSchedule;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Site;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Companies
        $company = Company::create([
            'name' => 'NSD Earth Movers & Construction Ltd',
            'legal_name' => 'NSD Earth Movers & Infrastructure Private Limited',
            'slug' => 'nsd-earth-movers',
            'email' => 'contact@nsdinfra.com',
            'phone' => '+91 80 4123 5678',
            'gstin' => '29AAACN1234F1Z5',
            'pan' => 'AAACN1234F',
            'address' => 'Plot 42, Peenya Industrial Area 2nd Phase, Bengaluru, Karnataka 560058',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        $secondCompany = Company::create([
            'name' => 'Apex Infra Developers Pvt Ltd',
            'legal_name' => 'Apex Infrastructure & Realty Developers Pvt Ltd',
            'slug' => 'apex-infra',
            'email' => 'info@apexinfra.com',
            'phone' => '+91 22 2876 5432',
            'gstin' => '27AABCA5678E1Z2',
            'pan' => 'AABCA5678E',
            'address' => 'Andheri East, Mumbai, Maharashtra 400069',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_active' => true,
        ]);

        session(['current_company_id' => $company->id]);

        // 2. Roles
        $ownerRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Company Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $pmRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Project Manager',
            'slug' => 'project_manager',
            'permissions' => ['projects.view', 'projects.edit', 'sites.view', 'materials.request', 'attendance.view'],
            'is_system' => true,
        ]);

        $siteSupervisorRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['sites.view', 'daily_logs.create', 'attendance.record'],
            'is_system' => true,
        ]);

        $accountantRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => ['finance.*', 'billing.*', 'accounting.*'],
            'is_system' => true,
        ]);

        // 3. Users
        $admin = User::create([
            'name' => 'Rajesh Varma',
            'email' => 'admin@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $pmUser = User::create([
            'name' => 'Amit Sharma',
            'email' => 'pm@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $siteUser = User::create([
            'name' => 'Vikram Malhotra',
            'email' => 'site@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $accountant = User::create([
            'name' => 'Sneha Roy',
            'email' => 'accounts@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        $karan = User::create([
            'name' => 'Karan Patel',
            'email' => 'karan@buildflow.local',
            'password' => Hash::make('Password123!'),
        ]);

        // Associate Users to Primary Company
        $company->users()->attach($admin->id, ['role_id' => $ownerRole->id, 'status' => 'active', 'is_primary' => true]);
        $company->users()->attach($pmUser->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);
        $company->users()->attach($siteUser->id, ['role_id' => $siteSupervisorRole->id, 'status' => 'active', 'is_primary' => true]);
        $company->users()->attach($accountant->id, ['role_id' => $accountantRole->id, 'status' => 'active', 'is_primary' => true]);
        $company->users()->attach($karan->id, ['role_id' => $pmRole->id, 'status' => 'active', 'is_primary' => true]);

        // Also give admin access to second company for testing tenant switcher
        $secondCompany->users()->attach($admin->id, ['role_id' => null, 'status' => 'active', 'is_primary' => false]);

        // 4. Clients
        $client1 = Client::create([
            'company_id' => $company->id,
            'name' => 'Prestige Estates Projects Ltd',
            'contact_person' => 'Sunil Alva',
            'email' => 'contracts@prestigeestates.com',
            'phone' => '+91 80 2559 1080',
            'gstin' => '29AAACP9876C1Z4',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
        ]);

        $client2 = Client::create([
            'company_id' => $company->id,
            'name' => 'L&T Realty Infrastructure',
            'contact_person' => 'Deepak Nambiar',
            'email' => 'infra.procure@lnt.com',
            'phone' => '+91 22 6752 5656',
            'gstin' => '27AAACL1234D1Z8',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ]);

        $client3 = Client::create([
            'company_id' => $company->id,
            'name' => 'Karnataka State Highways Authority',
            'contact_person' => 'Executive Engineer S. Murthy',
            'email' => 'ee.roads@kset.gov.in',
            'phone' => '+91 80 2221 4455',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
        ]);

        $client4 = Client::create([
            'company_id' => $company->id,
            'name' => 'Embassy Commercial Spaces',
            'contact_person' => 'Rohan Mehta',
            'email' => 'fitouts@embassy.com',
            'phone' => '+91 80 4039 9999',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
        ]);

        // 5. Projects (Matching Stitch Reference Exactly)
        $project1 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client1->id,
            'code' => 'PRJ-2026-001',
            'name' => 'Riverside Residency',
            'project_type' => 'civil',
            'description' => '14th floor slab casting & reinforcement inspection for premium twin towers.',
            'location' => 'Whitefield, Bengaluru',
            'start_date' => '2026-01-15',
            'target_completion_date' => '2026-11-30',
            'contract_value' => 148000000.00, // ₹14.80 Cr
            'approved_budget' => 135000000.00,
            'actual_cost' => 97200000.00,
            'status' => 'on_track',
            'progress_percent' => 72,
            'manager_id' => $pmUser->id,
        ]);

        $project2 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client2->id,
            'code' => 'PRJ-2026-002',
            'name' => 'Industrial Park Phase 2',
            'project_type' => 'civil',
            'description' => 'Structural steel erection & heavy crane mobilization on north bay.',
            'location' => 'Peenya 3rd Stage, Bengaluru',
            'start_date' => '2026-03-01',
            'target_completion_date' => '2026-12-15',
            'contract_value' => 84000000.00, // ₹8.40 Cr
            'approved_budget' => 78000000.00,
            'actual_cost' => 40320000.00,
            'status' => 'at_risk',
            'progress_percent' => 48,
            'manager_id' => $siteUser->id,
        ]);

        $project3 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client3->id,
            'code' => 'PRJ-2026-003',
            'name' => 'City Road Maintenance',
            'project_type' => 'maintenance',
            'description' => 'Asphalt overlay, stormwater drain rebuilding, and lane marking.',
            'location' => 'Outer Ring Road, Bengaluru',
            'start_date' => '2026-05-10',
            'target_completion_date' => '2026-10-20',
            'contract_value' => 32000000.00, // ₹3.20 Cr
            'approved_budget' => 29000000.00,
            'actual_cost' => 24650000.00,
            'status' => 'on_track',
            'progress_percent' => 85,
            'manager_id' => $accountant->id,
        ]);

        $project4 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client4->id,
            'code' => 'PRJ-2026-004',
            'name' => 'Corporate Fit-out',
            'project_type' => 'interior',
            'description' => 'Acoustic ceilings, HVAC ducting, and glass partitioning.',
            'location' => 'Bellandur Tech Park, Bengaluru',
            'start_date' => '2026-08-01',
            'target_completion_date' => '2026-11-20',
            'contract_value' => 19000000.00, // ₹1.90 Cr
            'approved_budget' => 17500000.00,
            'actual_cost' => 6100000.00,
            'status' => 'delayed',
            'progress_percent' => 32,
            'manager_id' => $karan->id,
        ]);

        // 6. Sites (Matching Stitch Reference Exactly)
        $site1 = Site::create([
            'company_id' => $company->id,
            'project_id' => $project1->id,
            'name' => 'Main High-Rise Tower',
            'location' => 'Whitefield Tower A',
            'supervisor_id' => $pmUser->id,
            'status' => 'active',
        ]);

        $site2 = Site::create([
            'company_id' => $company->id,
            'project_id' => $project1->id,
            'name' => 'Riverside Site B',
            'location' => 'Whitefield Tower B',
            'supervisor_id' => $siteUser->id,
            'status' => 'active',
        ]);

        $site3 = Site::create([
            'company_id' => $company->id,
            'project_id' => $project3->id,
            'name' => 'City Expressway Pkg 3',
            'location' => 'Hebbal - Silkboard Stretch',
            'supervisor_id' => $siteUser->id,
            'status' => 'active',
        ]);

        $site4 = Site::create([
            'company_id' => $company->id,
            'project_id' => $project4->id,
            'name' => 'Corporate Fit-out',
            'location' => 'Embassy Tech Village Tower 4',
            'supervisor_id' => $karan->id,
            'status' => 'active',
        ]);

        // 7. Daily Site Logs (Worker counts matching Stitch: 48, 36, 29, 18)
        DailySiteLog::create([
            'company_id' => $company->id,
            'project_id' => $project1->id,
            'site_id' => $site1->id,
            'log_date' => Carbon::today(),
            'work_summary' => '14th floor slab reinforcement bar tying completed. Ready Mix concrete pouring scheduled for tonight.',
            'active_workers_count' => 48,
            'created_by' => $pmUser->id,
        ]);

        DailySiteLog::create([
            'company_id' => $company->id,
            'project_id' => $project1->id,
            'site_id' => $site2->id,
            'log_date' => Carbon::today(),
            'work_summary' => 'Substructure column shuttering and waterproof coating application.',
            'active_workers_count' => 36,
            'created_by' => $siteUser->id,
        ]);

        DailySiteLog::create([
            'company_id' => $company->id,
            'project_id' => $project3->id,
            'site_id' => $site3->id,
            'log_date' => Carbon::today(),
            'work_summary' => 'Milling of damaged bituminous layer on lane 2. Soil stabilization along median.',
            'active_workers_count' => 29,
            'created_by' => $siteUser->id,
        ]);

        DailySiteLog::create([
            'company_id' => $company->id,
            'project_id' => $project4->id,
            'site_id' => $site4->id,
            'log_date' => Carbon::today(),
            'work_summary' => 'Drywall framing and electrical conduit laying across floor 3.',
            'active_workers_count' => 18,
            'created_by' => $karan->id,
        ]);

        // 8. Shifts & Employees
        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'General Day Shift (8:00 AM - 5:00 PM)',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_minutes' => 15,
            'standard_hours' => 8.00,
        ]);

        $emp1 = Employee::create([
            'company_id' => $company->id,
            'code' => 'EMP-001',
            'first_name' => 'Ramesh',
            'last_name' => 'Yadav',
            'designation' => 'Senior Excavator Operator',
            'department' => 'Heavy Machinery',
            'employment_type' => 'machine_operator',
            'joining_date' => '2024-02-01',
            'assigned_site_id' => $site1->id,
            'shift_id' => $shift->id,
            'daily_wage_rate' => 1200.00,
            'monthly_salary' => 36000.00,
            'bank_name' => 'State Bank of India',
            'bank_account_number' => '30291823741',
            'ifsc_code' => 'SBIN0004123',
        ]);

        $emp2 = Employee::create([
            'company_id' => $company->id,
            'code' => 'EMP-002',
            'first_name' => 'Suresh',
            'last_name' => 'Kumar',
            'designation' => 'Bar Bending Master',
            'department' => 'Civil Works',
            'employment_type' => 'daily_wage',
            'joining_date' => '2025-01-10',
            'assigned_site_id' => $site1->id,
            'shift_id' => $shift->id,
            'daily_wage_rate' => 950.00,
            'monthly_salary' => 28500.00,
        ]);

        DailyAttendance::create([
            'company_id' => $company->id,
            'employee_id' => $emp1->id,
            'site_id' => $site1->id,
            'project_id' => $project1->id,
            'attendance_date' => Carbon::today(),
            'first_in' => '07:55:00',
            'status' => 'present',
            'worked_hours' => 8.00,
            'overtime_hours' => 1.50,
            'is_overtime_approved' => true,
        ]);

        // 9. Materials & Stock
        $cat1 = MaterialCategory::create([
            'company_id' => $company->id,
            'name' => 'Structural & Cementitious',
            'code' => 'CAT-STR',
        ]);

        $cat2 = MaterialCategory::create([
            'company_id' => $company->id,
            'name' => 'Fuel & Heavy Equipment Spares',
            'code' => 'CAT-FLEET',
        ]);

        $cement = Material::create([
            'company_id' => $company->id,
            'category_id' => $cat1->id,
            'code' => 'MAT-CEM-53',
            'name' => 'OPC 53 Grade Cement',
            'unit_of_measure' => 'Bags',
            'standard_cost' => 380.00,
            'reorder_level' => 500.000,
            'hsn_code' => '25232910',
        ]);

        $steel = Material::create([
            'company_id' => $company->id,
            'category_id' => $cat1->id,
            'code' => 'MAT-STL-TMT',
            'name' => 'Fe550D TMT Rebars 16mm',
            'unit_of_measure' => 'MT',
            'standard_cost' => 58500.00,
            'reorder_level' => 15.000,
            'hsn_code' => '72142090',
        ]);

        $diesel = Material::create([
            'company_id' => $company->id,
            'category_id' => $cat2->id,
            'code' => 'MAT-FUEL-DSL',
            'name' => 'High Speed Diesel (HSD) for Excavators',
            'unit_of_measure' => 'Ltrs',
            'standard_cost' => 92.50,
            'reorder_level' => 2000.000,
            'hsn_code' => '27101930',
        ]);

        $centralYard = Warehouse::create([
            'company_id' => $company->id,
            'name' => 'Central Yard & Logistics Hub',
            'type' => 'central_yard',
            'location' => 'Peenya Industrial Area',
            'manager_id' => $admin->id,
        ]);

        $siteStore1 = Warehouse::create([
            'company_id' => $company->id,
            'site_id' => $site1->id,
            'name' => 'Riverside Tower A On-Site Store',
            'type' => 'site_store',
            'location' => 'Whitefield Site Yard',
            'manager_id' => $pmUser->id,
        ]);

        // Stock ledger opening entries
        StockLedgerEntry::create([
            'company_id' => $company->id,
            'material_id' => $cement->id,
            'warehouse_id' => $centralYard->id,
            'entry_date' => Carbon::now()->subDays(5),
            'transaction_type' => 'opening_balance',
            'quantity' => 1200.000,
            'direction' => 'in',
            'unit_rate' => 380.00,
            'total_amount' => 456000.00,
            'balance_quantity' => 1200.000,
            'created_by' => $admin->id,
        ]);

        StockLedgerEntry::create([
            'company_id' => $company->id,
            'material_id' => $steel->id,
            'warehouse_id' => $centralYard->id,
            'entry_date' => Carbon::now()->subDays(5),
            'transaction_type' => 'opening_balance',
            'quantity' => 45.000,
            'direction' => 'in',
            'unit_rate' => 58500.00,
            'total_amount' => 2632500.00,
            'balance_quantity' => 45.000,
            'created_by' => $admin->id,
        ]);

        // 10. Suppliers & Purchase Orders
        $supplier1 = Supplier::create([
            'company_id' => $company->id,
            'name' => 'UltraTech Cement Limited',
            'contact_person' => 'Anil Deshmukh',
            'phone' => '+91 80 2299 1100',
            'email' => 'institutional.sales@ultratech.com',
            'gstin' => '27AAACU1234A1Z1',
            'payment_terms' => '30_days',
        ]);

        $supplier2 = Supplier::create([
            'company_id' => $company->id,
            'name' => 'Tata Steel Infrastructure Solutions',
            'contact_person' => 'Girish Joshi',
            'phone' => '+91 80 6655 4433',
            'email' => 'tmt.orders@tatasteel.com',
            'gstin' => '20AAACT1234B1Z9',
            'payment_terms' => '45_days',
        ]);

        $po = PurchaseOrder::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier1->id,
            'project_id' => $project1->id,
            'po_number' => 'PO-2026-089',
            'po_date' => Carbon::now()->subDays(7),
            'delivery_due_date' => Carbon::now()->addDays(3),
            'subtotal' => 380000.00,
            'tax_amount' => 106400.00,
            'total_amount' => 486400.00,
            'status' => 'approved',
            'created_by' => $pmUser->id,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::now()->subDays(6),
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'material_id' => $cement->id,
            'quantity_ordered' => 1000.000,
            'unit_price' => 380.00,
            'tax_percent' => 28.00,
            'total_price' => 486400.00,
            'quantity_received' => 0.000,
        ]);

        // 11. Invoices & Receivables (Matching Stitch: Receivables = ₹45.2 Lakhs)
        ClientInvoice::create([
            'company_id' => $company->id,
            'client_id' => $client1->id,
            'project_id' => $project1->id,
            'invoice_number' => 'INV-RA-2026-012',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::now()->subDays(12),
            'due_date' => Carbon::now()->addDays(18),
            'subtotal' => 2600000.00,
            'cgst_amount' => 234000.00,
            'sgst_amount' => 234000.00,
            'retention_amount' => 130000.00,
            'total_amount' => 2938000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $accountant->id,
        ]);

        ClientInvoice::create([
            'company_id' => $company->id,
            'client_id' => $client2->id,
            'project_id' => $project2->id,
            'invoice_number' => 'INV-RA-2026-013',
            'bill_type' => 'progress_ra',
            'issue_date' => Carbon::now()->subDays(8),
            'due_date' => Carbon::now()->addDays(12),
            'subtotal' => 1400000.00,
            'cgst_amount' => 126000.00,
            'sgst_amount' => 126000.00,
            'retention_amount' => 70000.00,
            'total_amount' => 1582000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
            'created_by' => $accountant->id,
        ]);
        // Total Receivables = 29.38L + 15.82L = ₹45.20 Lakhs! Exactly matches the Stitch metric!

        // Supplier Invoices (Payables)
        SupplierInvoice::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier2->id,
            'invoice_number' => 'TATA-INV-9902',
            'invoice_date' => Carbon::now()->subDays(15),
            'due_date' => Carbon::now()->addDays(5),
            'subtotal' => 2200000.00,
            'tax_amount' => 396000.00,
            'total_amount' => 2596000.00,
            'paid_amount' => 0.00,
            'status' => 'approved',
        ]);

        // 12. Loans & Equipment Finance
        $excavatorLoan = Loan::create([
            'company_id' => $company->id,
            'loan_account_number' => 'TC-EQ-2024-8841',
            'lender_name' => 'Tata Capital Financial Services Ltd',
            'loan_type' => 'equipment_finance',
            'asset_description' => 'Tata Hitachi EX200 LC Hydraulic Excavator (Chassis: TH-9981)',
            'principal_amount' => 6500000.00,
            'annual_interest_rate' => 9.75,
            'tenure_months' => 48,
            'start_date' => '2024-03-15',
            'end_date' => '2028-03-15',
            'monthly_emi' => 164250.00,
            'outstanding_principal' => 3950000.00,
            'status' => 'active',
        ]);

        LoanEmiSchedule::create([
            'loan_id' => $excavatorLoan->id,
            'installment_number' => 20,
            'due_date' => Carbon::now()->addDays(8),
            'principal_component' => 132150.00,
            'interest_component' => 32100.00,
            'total_emi' => 164250.00,
            'remaining_balance' => 3817850.00,
            'status' => 'pending',
        ]);

        // 13. Chart of Accounts & Balanced Double Entry
        $cashAcc = Account::create([
            'company_id' => $company->id,
            'code' => '1010',
            'name' => 'HDFC Current Bank Account',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_system' => true,
        ]);

        $arAcc = Account::create([
            'company_id' => $company->id,
            'code' => '1020',
            'name' => 'Accounts Receivable (Clients)',
            'account_type' => 'asset',
            'sub_type' => 'current_asset',
            'is_system' => true,
        ]);

        $revAcc = Account::create([
            'company_id' => $company->id,
            'code' => '4010',
            'name' => 'Civil Contracting Revenue',
            'account_type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'is_system' => true,
        ]);

        // Balanced journal entry for initial billing
        $je = JournalEntry::create([
            'company_id' => $company->id,
            'entry_number' => 'JV-2026-001',
            'entry_date' => Carbon::now()->subDays(8),
            'narration' => 'Initial billing recognition for Riverside Residency RA Bill 12',
            'total_amount' => 2938000.00,
            'status' => 'posted',
            'created_by' => $accountant->id,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $arAcc->id,
            'project_id' => $project1->id,
            'debit' => 2938000.00,
            'credit' => 0.00,
            'description' => 'Accounts receivable debit for invoice INV-RA-2026-012',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $revAcc->id,
            'project_id' => $project1->id,
            'debit' => 0.00,
            'credit' => 2938000.00,
            'description' => 'Contract revenue credit for invoice INV-RA-2026-012',
        ]);
    }
}
