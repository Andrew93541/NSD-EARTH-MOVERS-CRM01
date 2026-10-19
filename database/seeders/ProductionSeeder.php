<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\ConstructionWorkCategory;
use App\Models\ProcurementApprovalPolicy;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Seed genuine NSD Earth Movers foundational configuration.
     * Contains ZERO fake projects, fake clients, or demo records.
     */
    public function run(): void
    {
        // 1. Primary Company: NSD Earth Movers & Construction Ltd
        $company = Company::firstOrCreate(
            ['slug' => 'nsd-earth-movers'],
            [
                'name' => 'NSD Earth Movers & Construction Ltd',
                'legal_name' => 'NSD Earth Movers & Infrastructure Private Limited',
                'email' => 'contact@nsdinfra.com',
                'phone' => '+91 80 4123 5678',
                'gstin' => '29AAACN1234F1Z5',
                'pan' => 'AAACN1234F',
                'address' => 'Plot 42, Peenya Industrial Area 2nd Phase, Bengaluru, Karnataka 560058',
                'currency' => 'INR',
                'currency_symbol' => '₹',
                'is_active' => true,
            ]
        );

        session(['current_company_id' => $company->id]);

        // 2. Standard System Roles
        $ownerRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'owner'],
            [
                'name' => 'Company Owner',
                'permissions' => ['*'],
                'is_system' => true,
            ]
        );

        $pmRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'project_manager'],
            [
                'name' => 'Project Manager',
                'permissions' => ['projects.*', 'sites.*', 'materials.request', 'attendance.view', 'attendance.muster'],
                'is_system' => true,
            ]
        );

        $siteSupervisorRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'site_supervisor'],
            [
                'name' => 'Site Supervisor',
                'permissions' => ['sites.view', 'daily_logs.*', 'attendance.record', 'materials.request'],
                'is_system' => true,
            ]
        );

        $accountantRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'accountant'],
            [
                'name' => 'Accountant',
                'permissions' => ['finance.*', 'billing.*', 'accounting.*', 'expenses.*', 'loans.*', 'assets.*', 'retention.*', 'procurement.invoices'],
                'is_system' => true,
            ]
        );

        $hrRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'hr_officer'],
            [
                'name' => 'HR / Payroll Officer',
                'permissions' => ['employees.*', 'attendance.*', 'payroll.*'],
                'is_system' => true,
            ]
        );

        $procurementRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'procurement_officer'],
            [
                'name' => 'Procurement Officer',
                'permissions' => ['procurement.*', 'materials.*', 'purchase-requests.view', 'purchase-requests.convert'],
                'is_system' => true,
            ]
        );

        $storekeeperRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'storekeeper'],
            [
                'name' => 'Storekeeper',
                'permissions' => ['inventory.*', 'procurement.receive', 'materials.view'],
                'is_system' => true,
            ]
        );

        // 3. Primary Administrator & Operational Role Users
        $defaultPassword = Hash::make('Password123!');

        $users = [
            ['name' => 'Rajesh Varma', 'email' => 'admin@nsdinfra.com', 'role' => $ownerRole, 'primary' => true],
            ['name' => 'Amit Sharma', 'email' => 'pm@nsdinfra.com', 'role' => $pmRole, 'primary' => false],
            ['name' => 'Sneha Roy', 'email' => 'accounts@nsdinfra.com', 'role' => $accountantRole, 'primary' => false],
            ['name' => 'Vikram Malhotra', 'email' => 'supervisor@nsdinfra.com', 'role' => $siteSupervisorRole, 'primary' => false],
            ['name' => 'Anand Joshi', 'email' => 'procurement@nsdinfra.com', 'role' => $procurementRole, 'primary' => false],
            ['name' => 'Gopal Das', 'email' => 'storekeeper@nsdinfra.com', 'role' => $storekeeperRole, 'primary' => false],
            ['name' => 'Meera Nair', 'email' => 'hr@nsdinfra.com', 'role' => $hrRole, 'primary' => false],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $defaultPassword,
                ]
            );

            $company->users()->syncWithoutDetaching([
                $user->id => [
                    'role_id' => $userData['role']->id,
                    'status' => 'active',
                    'is_primary' => $userData['primary'],
                ],
            ]);
        }

        // 4. Baseline Chart of Accounts
        $accounts = [
            ['code' => '1010', 'name' => 'HDFC Current Bank Account', 'account_type' => 'asset', 'sub_type' => 'current_asset'],
            ['code' => '1020', 'name' => 'Accounts Receivable (Clients)', 'account_type' => 'asset', 'sub_type' => 'current_asset'],
            ['code' => '1025', 'name' => 'Retention Money Withheld by Clients', 'account_type' => 'asset', 'sub_type' => 'current_asset'],
            ['code' => '2020', 'name' => 'Accounts Payable (Suppliers)', 'account_type' => 'liability', 'sub_type' => 'current_liability'],
            ['code' => '4010', 'name' => 'Civil Contracting Revenue', 'account_type' => 'revenue', 'sub_type' => 'operating_revenue'],
            ['code' => '1510', 'name' => 'Plant, Machinery & Heavy Earthmoving Equipment', 'account_type' => 'asset', 'sub_type' => 'fixed_asset'],
            ['code' => '1520', 'name' => 'Accumulated Depreciation - Plant & Machinery', 'account_type' => 'asset', 'sub_type' => 'fixed_asset'],
            ['code' => '2010', 'name' => 'Equipment Financing Loans & Borrowings', 'account_type' => 'liability', 'sub_type' => 'non_current_liability'],
            ['code' => '5010', 'name' => 'Direct Site Labor & Staff Wages', 'account_type' => 'expense', 'sub_type' => 'direct_cost'],
            ['code' => '5020', 'name' => 'Loan Interest & Financing Charges', 'account_type' => 'expense', 'sub_type' => 'finance_cost'],
            ['code' => '5030', 'name' => 'Depreciation Expense - Heavy Equipment & Fleet', 'account_type' => 'expense', 'sub_type' => 'depreciation'],
            ['code' => '5040', 'name' => 'Material Procurement & Consumables', 'account_type' => 'expense', 'sub_type' => 'direct_cost'],
            ['code' => '5050', 'name' => 'Site Operating & Subcontractor Expenses', 'account_type' => 'expense', 'sub_type' => 'direct_cost'],
        ];

        foreach ($accounts as $acc) {
            Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => $acc['code']],
                [
                    'name' => $acc['name'],
                    'account_type' => $acc['account_type'],
                    'sub_type' => $acc['sub_type'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }

        // 5. Default Shift
        Shift::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'General Day Shift (8:00 AM - 5:00 PM)'],
            [
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'grace_minutes' => 15,
                'standard_hours' => 8.00,
            ]
        );

        // 6. Construction Work Categories
        $categories = [
            ['code' => 'CWC-EARTH', 'name' => 'Earth Moving & Excavation', 'unit_of_measure' => 'cum', 'description' => 'Heavy earth moving, site grading, trenching, and soil transport'],
            ['code' => 'CWC-CIVIL', 'name' => 'Civil & Structural Works', 'unit_of_measure' => 'sqm', 'description' => 'Foundation, PCC, RCC, reinforcement, and masonry'],
            ['code' => 'CWC-PIPE', 'name' => 'Pipelaying & Infrastructure', 'unit_of_measure' => 'm', 'description' => 'Utility trenching, storm water, and culvert execution'],
            ['code' => 'CWC-ROAD', 'name' => 'Roads & Pavement', 'unit_of_measure' => 'sqm', 'description' => 'Sub-base, WMM, asphalt paving, and kerb casting'],
        ];

        foreach ($categories as $cat) {
            ConstructionWorkCategory::firstOrCreate(
                ['company_id' => $company->id, 'code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'unit_of_measure' => $cat['unit_of_measure'],
                    'description' => $cat['description'],
                    'is_active' => true,
                ]
            );
        }

        // 7. Procurement Approval Policies
        $policies = [
            ['approval_level' => 1, 'name' => 'Tier 1: Standard Site Material Indent (Up to ₹50,000)', 'min_amount' => 0.00, 'max_amount' => 50000.00, 'approver_role_slug' => 'project_manager'],
            ['approval_level' => 2, 'name' => 'Tier 2: Mid-value Procurement (₹50,000 to ₹5,00,000)', 'min_amount' => 50000.00, 'max_amount' => 500000.00, 'approver_role_slug' => 'accountant'],
            ['approval_level' => 3, 'name' => 'Tier 3: High-value Major Procurement (> ₹5,00,000)', 'min_amount' => 500000.00, 'max_amount' => null, 'approver_role_slug' => 'owner'],
        ];

        foreach ($policies as $pol) {
            ProcurementApprovalPolicy::firstOrCreate(
                ['company_id' => $company->id, 'approval_level' => $pol['approval_level']],
                [
                    'name' => $pol['name'],
                    'min_amount' => $pol['min_amount'],
                    'max_amount' => $pol['max_amount'],
                    'approver_role_slug' => $pol['approver_role_slug'],
                    'is_active' => true,
                ]
            );
        }
    }
}
