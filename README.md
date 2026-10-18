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

13. **Real-Time Notifications & Operations Centre (Laravel Reverb & Echo)**:
    - High-performance WebSocket broadcasting with Laravel Reverb and client-side Laravel Echo.
    - Transaction-safe `$afterCommit = true` event broadcasting across procurement, payroll, billing, and site operations.
    - Multi-tenant private channels (`company.{id}`, `App.Models.User.{id}`, `company.{id}.role.{role}`) with zero sensitive financial exposure.
    - Google Stitch UI: top navigation bell with live pulse animation, dynamic unread badge, dropdown feed, and full Notification Centre (`/notifications`).
    - Automatic reconnection recovery, window focus synchronization, and graceful HTTP fallback.

14. **Secure Document & File Upload Management (Private Storage, Versioning & Immutability)**:
    - Multi-tenant polymorphic attachment system embedded across 16 core business modules (Projects, Sites, PRs, POs, GRNs, RA Bills, Retention Claims, Work Orders, Subcontractor Bills, Fixed Assets, Loans, Employees, Payroll, and Journal Vouchers).
    - Private disk storage (`storage/app/private`) with random UUID physical filenames and SHA-256 integrity checksums.
    - Server-side `finfo` magic-byte inspection verifying binary headers against allowlist (`pdf`, `png`, `jpg`, `jpeg`, `dwg`, `dxf`, `xlsx`, `xls`, `csv`, `doc`, `docx`).
    - Pluggable ClamAV anti-malware scanner pipeline (`ConfigurableFileScanner`) with safe development fallback.
    - Statutory immutability locks blocking deletion on posted journals, certified subcontractor bills, approved client invoices, and settled loans.
    - Confidential document vault for HR identity records (Aadhaar/PAN) and payroll NEFT batches restricted to authorized roles (`owner`, `admin`, `hr_officer`, `accountant`).
    - Central Document Centre (`/documents`) with 4 KPI cards, multi-module filter, category filter, search, and inline streaming preview modal.

---

## 💻 Mandatory Technology Stack

- **Backend**: PHP 8.3+ (Runtime: PHP 8.5.11) & Laravel 13.x (Installed: 13.37.0)
- **File Security & Storage**: Private disk storage, UUID storage hashing, `finfo` MIME verification, ClamAV anti-malware pipeline
- **Real-Time WebSockets**: Laravel Reverb (v1.12+) & Laravel Echo with Pusher client
- **Database**: MySQL 8+ (InnoDB, strict foreign keys, indexes, decimal precision)
- **Frontend / Templating**: Laravel Blade
- **Styling**: Tailwind CSS (Stitch tokens, Plus Jakarta Sans, Inter)
- **Interactions**: Alpine.js
- **Charts**: Chart.js
- **Automated Testing**: PHPUnit (175 tests, 1081 assertions, 100% passing)
- **Durable Automated Backups**: Multi-tier backup engine (`buildflow:backup:*`) with AEAD AES-256-GCM authenticated encryption, secondary off-host replication, atomic locks, and bounded retention protection
- **Release Preflight & Gate**: Automated go-live audit command (`php artisan buildflow:preflight {--strict}`)
- **Operational Health**: Real-time `/health` endpoint reporting database latency, storage, cache, Reverb, scanner, secondary replication, and 60m RPO status

---

## 🚀 Installation & Quick Start

### 1. Prerequisites
Ensure you have the following installed on your system:
- **PHP 8.3+** (PHP 8.5.11 recommended) with PDO MySQL, BCMath, cURL, MBString, and OpenSSL extensions
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
# IMPORTANT: Run key:generate ONCE only during initial greenfield installation!
# NEVER run key:generate on an existing deployment containing encrypted data, active sessions, or users.
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

### 9. Start Real-Time WebSocket Server (Laravel Reverb)
In a separate terminal, launch the Reverb WebSocket daemon:
```bash
php artisan reverb:start --debug
```
Reverb listens by default on `0.0.0.0:8080` (or the port defined by `REVERB_PORT` in `.env`). Client connections over `ws://` / `wss://` are authenticated automatically through `/broadcasting/auth`.

---

## 🛡️ Production Operations & Disaster Recovery

### 1. Durable Automated Backups & AEAD Cryptography (`buildflow:backup:*`)
BuildFlow separates backup creation, AEAD cryptographic validation, isolated restore testing, and secondary off-host replication into distinct, non-conflicting operational workflows:

```bash
# 1. Create a durable atomic backup (database + private document storage)
php artisan buildflow:backup:create --frequency=hourly

# Optional: Create an authenticated encrypted snapshot using AEAD AES-256-GCM
php artisan buildflow:backup:create --frequency=daily --encrypt

# 2. Validate checksums, AEAD authentication tags, and archive readability
php artisan buildflow:backup:validate

# 3. Replicate verified backup to secondary off-host destination
php artisan buildflow:backup:replicate

# 4. Perform an isolated disaster recovery restore test (never overwrites live DB)
php artisan buildflow:verify-recovery
```

### 2. Automated Scheduling (Laravel Scheduler)
The system defines explicit schedules in `routes/console.php`:
- **Hourly**: `buildflow:backup:create --frequency=hourly` (retains last 24 snapshots)
- **Hourly at :15**: `buildflow:backup:replicate` (syncs verified backup to secondary destination)
- **Daily at 02:00**: `buildflow:backup:validate` (validates checksums, AEAD tags, and archive readability)
- **Weekly on Sunday at 03:00**: `buildflow:verify-recovery` (proves isolated restore into `buildflow_restore_test` with bounded protected recovery baselines)

To activate on your server, ensure the standard Laravel cron entry is installed:
```cron
* * * * * cd /var/www/buildflow && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Release Preflight & Go-Live Audit Gate (`buildflow:preflight`)
Before promoting a build to production or opening live traffic, run the automated release preflight audit:

```bash
# Advisory / Development Mode (reports actionable warnings)
php artisan buildflow:preflight

# Strict Production Gate (returns exit code 1 if any mandatory requirement fails)
php artisan buildflow:preflight --strict
```

### 4. Operational Health Check Endpoint (`GET /health`)
A rate-limited operational health check endpoint is provided at `/health` for load balancers and uptime monitoring tools:
```json
{
  "status": "healthy",
  "timestamp": "2026-10-18T12:11:45+00:00",
  "environment": "local",
  "total_latency_ms": 3.71,
  "checks": {
    "database": {
      "status": "ok",
      "latency_ms": 0.01
    },
    "storage": {
      "status": "ok",
      "free_space_gb": 363.79,
      "latency_ms": 0.43
    },
    "cache": {
      "status": "ok"
    },
    "broadcasting": {
      "status": "ok",
      "driver": "reverb"
    },
    "malware_scanner": {
      "status": "dev_unscanned_active",
      "enabled": true,
      "fail_closed": false,
      "effective_fail_closed": false,
      "allow_unscanned_dev": true,
      "message": "ClamAV daemon unreachable; explicit development unscanned mode active"
    },
    "durable_backups": {
      "status": "ok",
      "latest_backup_id": "backup_20261018_121120_hourly",
      "age_minutes": 0,
      "rpo_target_minutes": 60,
      "rpo_status": "satisfied",
      "validation_status": "valid",
      "restore_test_status": "passed",
      "creation": { "status": "ok", "frequency": "hourly" },
      "validation": { "status": "valid" },
      "restore_verification": { "status": "passed", "is_protected": true },
      "encryption": { "enabled": true, "cipher": "aes-256-gcm", "authenticated": true, "key_configured": true }
    },
    "secondary_replication": {
      "status": "active",
      "enabled": false,
      "latest_replicated_id": "backup_20261018_121120_hourly",
      "total_replicas": 1
    }
  }
}
```
*Note: The health check returns HTTP 200 for healthy states and HTTP 503 Service Unavailable if critical services (database, storage, or fail-closed scanner in production) fail. Secrets, API keys, and internal system paths are strictly excluded.*

### 5. Fail-Closed Anti-Malware Scanning
In production environments, configure anti-malware fail-closed behavior in `.env`:
```env
SCANNER_ENABLED=true
SCANNER_FAIL_CLOSED=true
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310
```
When `SCANNER_FAIL_CLOSED=true`, file uploads will be rejected with an error if the scanning daemon is unreachable, offline, or returns an ambiguous scan result, ensuring malicious payloads cannot bypass security controls under failure conditions.

---

## 📡 Real-Time Broadcasting & Production Deployment

### 1. Reverb Configuration (`.env`)
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID="your-reverb-app-id"
REVERB_APP_KEY="your-reverb-app-key"
REVERB_APP_SECRET="your-reverb-app-secret"
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS="localhost,127.0.0.1,buildflow.local"

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```
*Note: In production, configure `REVERB_SCHEME=https` and `VITE_REVERB_SCHEME=https` with your domain hostname (`wss://`). Restrict `REVERB_ALLOWED_ORIGINS` to your production domain.*

> [!CAUTION]
> **Credential Rotation Advisory**: If the development Reverb credentials or secrets published in earlier commit history or documentation were ever configured in any non-local or staging environment, they must be rotated immediately using your environment's secret manager or deployment procedure. Never reuse published development secrets in production.

### 2. Process Supervision (Supervisor)
To ensure the Reverb WebSocket daemon remains active and automatically restarts upon system reboots or failures, use Supervisor:

Create `/etc/supervisor/conf.d/reverb.conf`:
```ini
[program:buildflow-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/buildflow/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/reverb.log
stopwaitsecs=60
```

Start the supervised worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start buildflow-reverb:*
```

### 3. Nginx Reverse Proxy & TLS Termination (WSS)
Route WebSocket upgrade requests through Nginx to terminate SSL on port 443:
```nginx
location /reverb/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60;
    proxy_connect_timeout 60;
}
```

---

## 🔑 Development Demo Accounts & Credentials

> [!IMPORTANT]
> Demo accounts are created strictly for local development and automated testing via `DatabaseSeeder.php`. They are prohibited in production environments and `DatabaseSeeder` will fail closed if invoked under `APP_ENV=production`. In production, strict preflight audit (`php artisan buildflow:preflight --strict`) will block release if any known demo account remains active.

| Role | Email | Password Reference |
|---|---|---|
| **Super Admin / Owner** | `admin@buildflow.local` | Managed via local `.env` / seeder fixture |
| **Project Manager** | `pm@buildflow.local` | Managed via local `.env` / seeder fixture |
| **Site Engineer** | `site@buildflow.local` | Managed via local `.env` / seeder fixture |
| **Accountant / Finance** | `accounts@buildflow.local` | Managed via local `.env` / seeder fixture |
| **HR / Payroll Officer** | `hr@buildflow.local` | Managed via local `.env` / seeder fixture |
| **Procurement Officer** | `procurement@buildflow.local` | Managed via local `.env` / seeder fixture |
| **Storekeeper** | `storekeeper@buildflow.local` | Managed via local `.env` / seeder fixture |
| **Project Manager 2** | `karan@buildflow.local` | Managed via local `.env` / seeder fixture |

*(Pre-filled on login screen strictly under `local` and `testing` environments; suppressed in production)*

---

## 🧪 Running Automated Tests

Run the full PHPUnit test suite:
```bash
php artisan test
```

### Test Coverage Highlights:
- `DisasterRecoveryRestoreTest.php`: Verifies that disaster recovery backup and isolated restore commands execute with zero downtime and preserve database integrity, migration status, and financial GL balance (2 tests, 12 assertions).
- `ProductionSecurityHardeningTest.php`: Health check endpoint availability and secret masking, user company membership revocation immediate session termination, global search tenant isolation and operator precedence grouping, HR wage/salary masking, scanner fail-closed enforcement on upload and stream access denial on infected/quarantined files (5 tests, 30 assertions).
- `DocumentManagementTest.php`: Authorized multi-tenant uploads, MIME magic-byte verification, 15MB file size limit, private storage UUID isolation, unauthenticated access denial, cross-tenant isolation (404/403 with zero metadata leakage), confidential HR vault restrictions, non-destructive replacement versioning, statutory immutability lock on posted journals, and Document Centre keyword/category filtering (11 tests, 64 assertions).
- `RealTimeNotificationTest.php`: Private user channels, company tenant isolation, role/site permissions, safe non-sensitive payloads, `$afterCommit = true` transaction rollback isolation, and broadcaster downtime resilience (10 tests, 42 assertions).
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
- [`docs/NSDCRM_AUDIT_AND_ROADMAP.md`](docs/NSDCRM_AUDIT_AND_ROADMAP.md) — Full phase-by-phase implementation audit and go-live assessment report.
- [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) — Progress checklist across all development phases.

---

## 🛡️ License
Proprietary software developed for NSD Earth Movers & Construction Ltd. All rights reserved.
