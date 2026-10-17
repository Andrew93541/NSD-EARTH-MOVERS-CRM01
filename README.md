# BuildFlow — Construction Company CRM & ERP

**BuildFlow** is a complete, enterprise-grade Construction Company CRM and ERP built for civil infrastructure contractors, interior contracting firms, heavy earthmoving operations, and multi-site maintenance providers.

Visual design and UI tokens are modeled directly after the **Google Stitch** project (`15768566540196779944` - "NSD CRM").

---

## 🏗️ Core Modules & Features

1. **Multi-Company Workspace & Tenant Isolation**:
   - Secure multi-tenancy with company memberships, granular roles, and server-side global tenant scoping (`BelongsToCompany` trait).
   - Seamless workspace switching and new workspace provisioning.

2. **Dashboard / Operations Overview**:
   - Matches Google Stitch master reference screen with soft canvas frame (`#dbe2ea` / `#f7f9fc`), custom typography, and dynamic metrics derived directly from MySQL.
   - Real-time KPI Cards: Active Projects, Total Receivables, Total Payables, Pending Approvals.
   - Execution Volume Dot-Matrix chart and live site workforce distribution.
   - Project contracts table with localized INR currency formatting (`₹ Cr` / `₹ Lakh`).

3. **Project Management**:
   - Full lifecycle management for Civil, Interior, Earthmoving, and Maintenance projects.
   - Project milestones, work package breakdowns, progress tracking, and budget vs actual utilization.

4. **Site Operations & Daily Logs**:
   - Multi-site support, supervisor daily work summaries, worker headcount tracking, obstacle logs, and photo attachment capability.

5. **Workforce & Labor Muster Roll**:
   - Employee master records, wage classifications (monthly, daily wage, piece rate), and shift definitions.
   - Supervisor site-wise daily muster roll with radio status toggles (Present, Half Day, Absent) and overtime hours.
   - Immutable punch log ledger for biometric device CSV imports (`AttendancePunch`).

6. **Materials, Warehouses & Stock Ledger**:
   - Multi-warehouse inventory tracking (Central Yards, Site Stores).
   - Double-entry stock movement ledger with atomic transfers and negative-stock prevention.

7. **Procurement & 3-Way Matching**:
   - Purchase Requests -> Purchase Orders -> Goods Receipts (GRN) -> Supplier Invoices.
   - Segregation of duties, approval workflows, and payable tracking.

8. **Client Billing & Receivables**:
   - Progress Running Account (RA) bills with certified amounts, Central GST (9%), State GST (9%), and retention deductions.
   - Partial payment collection tracking, bank references, and live balance-due computation.

9. **Heavy Equipment Finance, Loans & EMI**:
   - Loan management for heavy machinery (excavators, dumpers, transit mixers).
   - Amortized EMI schedules detailing principal, interest, due dates, and settlement status.

10. **Expenses & Payments**:
    - Project and site expense categorization, claim submission, and approval workflows.

11. **Double-Entry Accounting & Financial Reports**:
    - Chart of Accounts, strict balanced debit/credit validation (`bccomp`), and immutable posted journals.
    - Real-time Trial Balance report generated from posted journal entries.

12. **Global Search**:
    - Multi-entity search across projects, materials, client invoices, and workforce.

---

## 💻 Mandatory Technology Stack

- **Backend**: PHP 8.3+ (Tested on PHP 8.5.11) & Laravel 11/12
- **Database**: MySQL 8+ (InnoDB, strict foreign keys, indexes, decimal precision)
- **Frontend / Templating**: Laravel Blade
- **Styling**: Tailwind CSS (Stitch tokens, Plus Jakarta Sans, Inter)
- **Interactions**: Alpine.js
- **Charts**: Chart.js
- **Automated Testing**: PHPUnit (27 tests, 151 assertions, 100% passing)

---

## 🚀 Installation & Quick Start

### 1. Prerequisites
Ensure you have the following installed on your system:
- **PHP 8.3+** with PDO MySQL, BCMath, cURL, MBString, and OpenSSL extensions
- **Composer 2.x**
- **MySQL 8.0+**
- **Node.js 18+** and **npm**

### 2. Clone and Setup Environment
```bash
git clone <repository-url> buildflow-crm
cd buildflow-crm

# Copy environment template
cp .env.example .env
```

### 3. Configure MySQL Database
Edit `.env` to match your local MySQL server credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=buildflow_crm
DB_USERNAME=root
DB_PASSWORD=
```

Create the application and testing databases:
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS buildflow_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS buildflow_crm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. Install Dependencies
```bash
composer install
npm install
```

### 5. Generate Application Key & Run Migrations
```bash
php artisan key:generate
php artisan migrate
```

### 6. Seed Demo Data (Stitch Reference Data)
```bash
php artisan db:seed
```

This seeds realistic data for:
- **Primary Company**: `NSD Earth Movers & Construction Ltd`
- **Secondary Company**: `Apex Infra Developers Pvt Ltd`
- **Projects**: Riverside Residency (₹14.8 Cr), Industrial Park Phase 2 (₹8.4 Cr), City Road Maintenance (₹3.2 Cr), Corporate Fit-Out (₹1.9 Cr)
- **Central Yards & Site Stores**: Cement, TMT 16mm Rebars, Crushed Aggregate, River Sand
- **Equipment Loan**: Tata Hitachi EX200 LC with complete 36-month amortized EMI schedule
- **Balanced Chart of Accounts & Journals**

### 7. Compile Frontend Assets
```bash
npm run build
```

### 8. Start Local Development Server
```bash
php artisan serve
```
Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

---

## 🔑 Default Credentials

| Role | Email | Password |
|---|---|---|
| **Super Admin / Owner** | `admin@buildflow.local` | `Password123!` |
| **Project Manager** | `pm@buildflow.local` | `Password123!` |
| **Site Engineer** | `site@buildflow.local` | `Password123!` |
| **Accountant / Finance** | `accounts@buildflow.local` | `Password123!` |

*(Pre-filled on the login screen for rapid evaluation)*

---

## 🧪 Running Automated Tests

Run the full PHPUnit test suite:
```bash
php artisan test
```

### Test Coverage Highlights:
- `AuthenticationTest.php`: Login, password throttling, session regeneration, logout, and guest protection.
- `MultiTenantIsolationTest.php`: Server-side tenant scoping, preventing cross-company access, and workspace switching authorization.
- `ProjectManagementTest.php`: Project creation, validation, milestone tracking, and progress percentage updates.
- `InventoryTransferTest.php`: Atomic double-entry stock transfers, stock availability checks, and insufficient quantity rejection.
- `BillingAndReceivablesTest.php`: Progress RA bill generation (with 9% CGST, 9% SGST, 5% retention), partial remittance tracking, and overpayment prevention.
- `DoubleEntryAccountingTest.php`: Balanced debit/credit validation, rejecting unbalanced journals, and Trial Balance generation.
- `AttendanceAndMusterTest.php`: Multi-worker muster roll submission, overtime calculation, and raw punch auditability.
- `DashboardTest.php`: Dynamic database-backed metric aggregation and tenant-specific project summaries.
- `SmokeTest.php`: End-to-end HTTP 200 verification across all 35 primary application routes.

---

## 📚 Technical Documentation

Comprehensive architectural and design documents are located in [`docs/`](docs/):
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — System architecture, tenant scoping, and domain boundaries.
- [`docs/DATABASE.md`](docs/DATABASE.md) — Schema entity-relationship breakdown, decimal precisions, and indexes.
- [`docs/WORKFLOWS.md`](docs/WORKFLOWS.md) — State machine workflows for procurement, stock transfers, billing, and accounting.
- [`docs/SECURITY.md`](docs/SECURITY.md) — Tenant isolation guarantees, CSRF/XSS mitigations, password policies, and audit trails.
- [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) — Progress checklist across all 8 phases.

---

## 🛡️ License
Proprietary software developed for NSD Earth Movers & Construction Ltd. All rights reserved.
