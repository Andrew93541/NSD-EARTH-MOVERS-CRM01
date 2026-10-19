# NSD Earth Movers CRM — cPanel Database Setup & Migration Guide

**Project:** NSD Earth Movers & Construction Ltd. — CRM / ERP  
**Target Environment:** cPanel Shared / Reseller / Cloud Hosting  
**Database Engines:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB Engine)  
**Document Version:** 1.0 (Production Release)

---

## 1. Database Architecture & Engine Requirements

The NSD Earth Movers CRM uses standard relational database features:
- **Engine:** `InnoDB` is strictly required for all 20 database tables to ensure:
  - Foreign key constraints with cascading rules where designated.
  - Row-level locking during high-concurrency operations (preventing duplicate stock issues or negative inventory).
  - Atomic transactions (`DB::transaction()`) across materials, ledger entries, purchase orders, journal vouchers, and audit logs.
- **Character Set & Collation:**
  - `charset`: `utf8mb4`
  - `collation`: `utf8mb4_unicode_ci` (or `utf8mb4_0900_ai_ci` on MySQL 8.0+)
  - Supports international characters, multilingual project names, Indian Rupee currency symbols (`₹`), and emoji/Unicode inputs.
- **Precision Conventions:**
  - Material quantities: `DECIMAL(12, 3)` (supports millimeter/gram precision).
  - Monetary values: `DECIMAL(14, 2)` (contract sums, retention amounts, payroll disbursements).

---

## 2. cPanel MySQL Setup Procedure

Follow these steps in the cPanel management dashboard:

### Step 2.1: Create the MySQL Database
1. Log into your cPanel account.
2. In the **Databases** section, click **MySQL® Databases** (or **MySQL Database Wizard**).
3. Under **Create New Database**, enter a name (e.g., `nsdcrm`).
   > *Note:* cPanel prefixes the database name with your account username.  
   > Example: If your cPanel username is `nsdearth`, the database name will be `nsdearth_nsdcrm`.
4. Click **Create Database**.

### Step 2.2: Create the Dedicated Database User
1. In the same **MySQL® Databases** interface, scroll to **MySQL Users — Add New User**.
2. Enter a username (e.g., `crmusr`).  
   > *Example full username:* `nsdearth_crmusr`.
3. Generate a strong, random password (minimum 16 characters including uppercase, lowercase, numbers, and symbols) using the cPanel Password Generator.
4. Copy this password safely for the `.env` configuration.
5. Click **Create User**.

### Step 2.3: Assign User to Database with Necessary Privileges
1. Scroll to **Add User to Database**.
2. Select User: `nsdearth_crmusr`.
3. Select Database: `nsdearth_nsdcrm`.
4. Click **Add**.
5. In the **Manage User Privileges** screen, check **ALL PRIVILEGES** (this includes `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `DROP`, `REFERENCES`, `LOCK TABLES`, etc.).
6. Click **Make Changes**.

---

## 3. Production Environment Configuration (`.env`)

In your server's `.env` file (located outside the public web root, e.g. `/home/username/crm_app/.env`), configure the database parameters:

```env
# -------------------------------------------------------------
# DATABASE CONNECTION (cPanel Production)
# -------------------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nsdearth_nsdcrm
DB_USERNAME=nsdearth_crmusr
DB_PASSWORD="your-securely-generated-password"

# Set strictly to true in production to enforce relational integrity
DB_FOREIGN_KEYS=true
```

> **Hosting Note:** On some shared cPanel servers, MySQL sockets or `localhost` can be faster than `127.0.0.1`. If your host specifies `localhost`, use `DB_HOST=localhost`.

---

## 4. Migration Safety & Codebase Audit

All 20 database migrations in `database/migrations/` have been audited for MySQL production readiness:

| Migration File | Primary Tables / Changes | Engine Safety |
| :--- | :--- | :--- |
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | Standard Laravel schema. Verified. |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | Standard cache tables. Verified. |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | Standard queue tables. Verified. |
| `2026_10_17_000001_create_tenant_and_access_control_tables.php` | `companies`, `roles`, `company_user`, `audit_logs` | Named foreign keys, single-company indexing. Verified. |
| `2026_10_17_000002_create_projects_and_sites_tables.php` | `clients`, `projects`, `sites`, `project_notes`, `project_documents` | Scoped to `company_id`. Verified. |
| `2026_10_17_000003_create_workforce_and_attendance_tables.php` | `shifts`, `employees`, `attendance_punches`, `daily_attendance` | Verified column names and `DECIMAL` rates. |
| `2026_10_17_000004_create_materials_and_procurement_tables.php` | `warehouses`, `material_categories`, `materials`, `stock_ledger_entries`, `suppliers`, `purchase_requests`, `purchase_orders`, `goods_receipts` | Authoritative stock ledger with running balance checks. Verified. |
| `2026_10_17_000005_create_billing_and_payroll_tables.php` | `client_invoices`, `payroll_periods`, `payroll_records`, `employee_advances`, `employee_deductions` | Strict financial rounding and period state tracking. Verified. |
| `2026_10_17_000006_create_expenses_and_loans_tables.php` | `expense_categories`, `expenses`, `loans`, `loan_emi_schedules` | Verified. |
| `2026_10_17_000007_create_accounting_and_audit_tables.php` | `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `app_notifications` | Double-entry balanced verification. Verified. |
| `2026_10_18_000001_enhance_purchase_requests_and_order_items.php` | `purchase_request_items`, `purchase_order_items` | Non-destructive column additions. Verified. |
| `2026_10_18_000002_create_approval_policies_and_budget_controls.php` | `approval_policies`, `purchase_request_approvals` | Multi-tier approval structure. Verified. |
| `2026_10_18_000003_create_client_retention_and_contracts_tables.php` | `contracts`, `retention_claims`, `retention_release_certificates` | Verified. |
| `2026_10_18_000004_create_fixed_assets_and_depreciation_tables.php` | `fixed_assets`, `depreciation_logs` | Straight-line / WDV depreciation tracking. Verified. |
| `2026_10_18_000005_create_subcontractor_work_orders_and_billing_tables.php` | `subcontractors`, `subcontractor_work_orders`, `measurement_books`, `subcontractor_bills` | Subcontractor lifecycle tracking. Verified. |
| `2026_10_18_000006_create_documents_table.php` | `documents` | Secure metadata with SHA-256 integrity hash. Verified. |
| `2026_10_19_000001_create_quotations_tables.php` | `quotations`, `quotation_items` | Non-destructive client bidding. Verified. |
| `2026_10_19_000002_create_material_issues_tables.php` | `material_issues`, `material_issue_items` | Warehouse-to-site issues and returns. Verified. |
| `2026_10_19_000003_add_unique_constraints_and_returned_quantity.php` | Indexes and `returned_quantity` tracking | Prevents cumulative over-returns. Verified. |
| `2026_10_19_164531_add_profile_fields_to_users_table.php` | `phone`, `avatar`, `preferences` on `users` | User profile fields. Verified. |

**Audit Findings:**
- Zero SQLite-only assumptions or raw dialects found.
- No destructive dropping of existing data columns.
- All migrations support transactional DDL.

---

## 5. Safe Migration Execution Procedure

When deploying to production, follow this strict checklist:

### Step 5.1: Enable Application Maintenance Mode
Prevent users from submitting transactions during migration:
```bash
php artisan down --secret="deployment-token-nsd-2026" --render="errors::503"
```
*(You can access the site during maintenance by appending `?deployment-token-nsd-2026` to your URL).*

### Step 5.2: Take a Verified Database Backup
Before running any migration on a database with existing data, generate a complete SQL dump:
```bash
# Using SSH / Terminal:
mysqldump -u nsdearth_crmusr -p nsdearth_nsdcrm > ~/backups/pre_migration_backup_$(date +%F_%H%M%S).sql

# Or using cPanel Interface:
# Go to cPanel -> Backup Wizard -> Partial Backup -> Download a MySQL Database Backup.
```
Verify that the dump file is non-empty (`ls -lh ~/backups`).

### Step 5.3: Execute Migrations
Run the reviewed migrations using `--force` (required in `APP_ENV=production`):
```bash
php artisan migrate --force
```

### Step 5.4: Verify Migration Status
Check that all batches have completed cleanly:
```bash
php artisan migrate:status
```
Every migration must show `[Ran]`.

### Step 5.5: Seed Initial Reference Data (First-Time Deployments Only)
On a brand new installation with an empty database:
```bash
# Create the initial NSD Earth Movers company record and admin account:
php artisan db:seed --class=InitialCompanySeeder --force
```
> **CRITICAL RULE:** NEVER run `db:wipe`, `migrate:fresh`, or test seeders on an existing production database.

### Step 5.6: Bring the Application Online
```bash
php artisan up
```

---

## 6. Disaster Recovery & Rollback Plan

If an unexpected error occurs during migration:

1. **Do Not Panic:** Do not run `migrate:reset` or `migrate:fresh`.
2. Inspect the exact error message in the terminal or `storage/logs/laravel.log`.
3. If an individual migration failed midway:
   - Identify the specific table or index that failed.
   - If rolling back via Artisan:
     ```bash
     php artisan migrate:rollback --step=1 --force
     ```
4. If schema inconsistencies or partial execution require a full restore to pre-deployment state:
   ```bash
   # Restore the verified pre-migration backup:
   mysql -u nsdearth_crmusr -p nsdearth_nsdcrm < ~/backups/pre_migration_backup_YYYY-MM-DD_HHMMSS.sql
   ```
   *(Or in cPanel, go to **phpMyAdmin**, select `nsdearth_nsdcrm`, drop any newly added tables, and import the SQL backup file).*
5. Re-verify table structures and bring the site back online with `php artisan up`.
