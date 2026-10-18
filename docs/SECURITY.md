# BUILDFLOW — SECURITY & DATA PROTECTION POLICIES

## 1. Multi-Tenant Isolation
- **Global Scoping**: Every business query is bound to the active `company_id`.
- **Server-Side Enforcement**: All controller endpoints verify that requested entity IDs belong to the user's active tenant session, preventing Insecure Direct Object References (IDOR).
- **Session Protection**: Tenant switches regenerate the session ID and invalidate cached authorization credentials.

---

## 2. Authentication & Authorization
- **Password Security**: Strong hashing with Bcrypt/Argon2.
- **Login Throttling**: Rate-limiting on authentication endpoints (5 attempts per minute per IP/email).
- **Session Invalidation**: Immediate session regeneration on privilege escalation, password change, or logout.
- **Granular RBAC**: Role-based permissions controlling `view`, `create`, `edit`, `approve`, and `delete` across all modules.

---

## 3. Financial & Operational Integrity
- **Double-Entry Balance Verification**: Journal entries with unequal debits and credits throw database transaction rollbacks.
- **Immutable Financial Ledgers**: Posted journals, invoices, and stock movements cannot be hard-deleted.
- **Safe Precision**: All monetary values are computed using exact decimal arithmetic (`bcmath` or string decimals) and stored in `DECIMAL(15,2)` columns.

---

## 5. Role-Based Authorization Matrix & Direct Route Guards

BuildFlow enforces server-side role and permission verification across all controllers. Hiding UI elements is never treated as a security boundary.

| Role | Permitted Areas | Confidential / Prohibited Areas | Direct Route Guards |
|---|---|---|---|
| **Company Owner / Admin** | All modules, approvals, company settings, policies, override decisions, asset registration, periodic depreciation batch posting, subcontractor work order approvals, bill certifications | None | Unrestricted (`*` permission) |
| **Project Manager** | Assigned projects, sites, daily logs, material requests, attendance muster, retention claim drafting & certificates, subcontractor work order creation & approval, MB review & approval | Company bank accounts, financial journals, payroll compensation, approval policies, fixed asset accounting, subcontractor bill certification & payments | 403 on `/payroll`, `/accounting`, `/finance/policies`, `/assets/*`, `/subcontractor-bills/*/certify`, `/subcontractor-bills/*/payments` |
| **Site Supervisor** | Assigned sites, daily site logs, muster roll attendance recording, material requests, site MB entry recording | Financial accounting, client billing, retention claims, company payroll, employee bank details, fixed assets & loans, work order approval, MB approval, bill certification | 403 on `/payroll`, `/accounting/*`, `/billing/*`, `/retention/*`, `/procurement/orders/create`, `/assets/*`, `/loans/*`, `/work-orders/create`, `/work-orders/*/approve`, `/measurement-books/*/approve`, `/subcontractor-bills/*` |
| **HR / Payroll Officer** | Employee profiles, shifts, biometric attendance audit, payroll batch preparation, payslips | Financial accounting, client billing, retention claims, procurement PO approvals, asset register, subcontractor billing | 403 on `/accounting/*`, `/billing/*`, `/retention/*`, `/procurement/policies`, `/assets/*`, `/subcontractor-bills/*`, `/work-orders/*` |
| **Finance Approver / Accountant** | Financial journals, trial balance, client billing & receipts, retention claim approvals, client dispatch, claim receipts, equipment loans, EMI settlement & reversals, fixed assets register & depreciation posting, subcontractor bill certification & payment recording | Direct procurement creation, site master editing, approval policy configuration, raw measurement recording | 403 on `/procurement/policies`, `/procurement/orders/create`, `/measurement-books/create` |
| **Procurement Officer** | Purchase requests review, PO conversion, supplier management, price quotes, subcontractor master maintenance | Financial accounting journals, payroll records, client billing, retention claims, fixed asset registers, bill certification | 403 on `/payroll`, `/accounting/*`, `/billing/*`, `/retention/*`, `/assets/*`, `/subcontractor-bills/*/certify` |
| **Storekeeper** | Goods receipt notes (GRN), quality inspection, warehouse stock transfers, inventory ledgers | Financial journals, payroll, client billing, retention claims, PO issuance, fixed asset ledger, subcontractor contracts | 403 on `/payroll`, `/accounting/*`, `/billing/*`, `/retention/*`, `/procurement/orders/create`, `/assets/*`, `/work-orders/*`, `/subcontractor-bills/*` |

---

## 6. Concurrency Safeguards & Immutable Historical State

1. **Pessimistic Row Locking (`lockForUpdate()`)**:
   - **Subcontractor Bill Certification**: Certification acquires pessimistic row locks on `subcontractor_bills` and associated `measurement_book_entries` using `lockForUpdate()` within an atomic `DB::transaction`. Prevents concurrent duplicate certification or double-claiming of measured quantities.
   - **Subcontractor Bill Payment Remittances**: Payment recording acquires row locks on `subcontractor_bills` with `lockForUpdate()` within `DB::transaction`, guaranteeing that total remittances never exceed the outstanding balance due (`balance_due`).
   - **Loan EMI Repayments**: EMI settlements acquire row locks on `loan_emi_schedules` and parent `loans` using `lockForUpdate()` within an atomic `DB::transaction`. Prevents duplicate settlement on retry (`status === 'paid'` or `journal_entry_id !== null`).
   - **Periodic Depreciation Entries**: Depreciation posting acquires pessimistic row locks on `asset_depreciation_entries` and parent `fixed_assets` using `lockForUpdate()`. Prevents double-posting or race conditions during batch runs.
   - **Client Retention Release Claims**: Claim creation acquires row locks on `client_contracts` and source `client_invoices` with `lockForUpdate()`, strictly preventing duplicate claim allocations across concurrent requests.
   - **Retention Payment Remittances**: Receipt recording acquires row locks on `retention_release_claims` with `lockForUpdate()` within `DB::transaction`, guaranteeing that total receipts never exceed the outstanding balance due.
   - **Client Billing Payments**: Payment recording acquires row-level locks on `client_invoices` within `DB::transaction`, preventing double-receipt and overpayment race conditions.
   - **Purchase Request Conversion**: Conversion acquires row locks on `purchase_requests` and `purchase_request_items` preventing duplicate PO issuance from rapid concurrent submissions.
2. **Segregation of Duties (SoD)**:
   - Measurement preparers are strictly prohibited from approving their own measurements (`mb_entry->recorded_by === auth()->id()`).
   - Subcontractor bill preparers cannot certify their own bills unless holding Company Administrator privileges.
   - Claim preparers are strictly prohibited from approving their own retention claims (`claim->created_by === auth()->id()`).
3. **Historical Attendance Protection**:
   - Daily attendance muster roll records cannot be updated or created for dates falling within an `approved` or `disbursed` `PayrollPeriod`. Attempts throw HTTP 403.
4. **Double-Entry Journal & Financial Ledger Immutability**:
   - **Model-Level Immutability**:
     - `JournalEntry::booted()` strictly blocks any update to posted journal headers (`entry_date`, `total_amount`, `entry_number`, `company_id`, `reference_type`, etc.) throwing a `DomainException`.
     - The only permitted transition on a `posted` entry is to `'reversed'` with `reversed_by`, `reversed_at`, and `reversal_reason`.
     - Deletion of `posted` or `reversed` journal entries is blocked (`DomainException`).
     - Once an entry is marked `reversed`, it is permanently locked and cannot be updated, reversed again, or reverted (`DomainException`).
   - **Journal Line Immutability & Line-Injection Prevention**:
     - `JournalEntryLine::booted()` blocks updating or deleting lines of any posted or reversed journal entry.
     - New lines cannot be added to an existing posted journal entry outside its initial process creation transaction (`JournalEntry::isUnlockedForCreation()`). Unsafe direct line insertion attempts throw `DomainException`.
   - **Authorized Reversals & Audit Trail**:
     - Corrections to posted entries must use authorized reversal workflows:
       - General journals: `POST /accounting/journals/{journal}/reverse` (requires `accounting.*` role and mandatory 5+ character explanation).
       - Subcontractor bills: `SubcontractorBillingService::reverseBill` (marks original voucher reversed and posts `JV-REV-SCB-...`).
       - Equipment EMI: `EquipmentAccountingService::reverseEmiPayment` (marks original voucher reversed and posts `JV-REV-...`).
       - Asset Depreciation: `EquipmentAccountingService::reverseDepreciationEntry` (marks original voucher reversed and posts `JV-REV-...`).
     - Every reversal atomically records an `AuditLog` entry capturing original voucher, counter voucher, user ID, timestamp, and explanation. Duplicate reversals are strictly rejected.

---

## 7. Real-Time WebSocket Security & Channel Authorization

BuildFlow enforces strict operational security across its WebSocket broadcasting layer:

### 1. Zero Public Channels
- Every WebSocket channel is an authenticated **PrivateChannel** (`routes/channels.php`).
- Public channels are strictly prohibited across the entire codebase.

### 2. Multi-Tenant Channel Authorization
- Channel subscription requests to `/broadcasting/auth` are authenticated using standard web session cookies and verified via server-side authorization callbacks:
  - **Company Channels** (`company.{companyId}`): Verifies the user has an active membership record in the target company (`company_user.status === 'active'`). Cross-company subscription attempts are rejected with 403 Forbidden.
  - **User Channels** (`App.Models.User.{id}`): Verifies the authenticated user's ID matches the requested channel ID (`(int) $user->id === (int) $id`).
  - **Role Channels** (`company.{companyId}.role.{roleSlug}`): Verifies tenant membership AND validates that the user's role slug matches the channel parameter.
  - **Project/Site Channels** (`project.{projectId}`, `site.{siteId}`): Verifies assignment or company administrative permissions.
- Inactive or terminated users (`status !== 'active'`) are rejected from all company channels immediately.

### 3. Sanitized Broadcast Payloads (No Sensitive Data Exposure)
- In accordance with the Principle of Least Privilege, broadcast event payloads (`NotificationDispatched::broadcastWith()`) contain **only minimal operational metadata**:
  - `id`: Notification database identifier.
  - `company_id`: Tenant identifier for client routing.
  - `user_id`: Target user identifier (or `null` for company-wide events).
  - `title`: High-level operational subject (e.g. "PR-2026-108 Submitted").
  - `message`: High-level summary (e.g. "Site Supervisor submitted PR for review").
  - `type`: Severity classification (`info`, `warning`, `success`, `danger`).
  - `action_url`: Relative authorized link to the record.
  - `is_read`, `created_at`, `created_at_human`.
- **Strictly Prohibited from Payloads**:
  - Employee salary / compensation amounts, Net pay figures, and payroll batch totals.
  - Bank account numbers, IFSC codes, and payment transaction references.
  - Confidential supplier rates, line item unit prices, and subcontractor profit margins.
  - Full financial invoice schedules.
- Full details must always be retrieved by the authenticated browser via authorized HTTP routes protected by domain policies and middleware.

### 4. Post-Commit Transaction Broadcasting (`afterCommit = true`)
- Broadcast events implement `ShouldBroadcastNow` with public property `$afterCommit = true`.
- `NotificationService::broadcastNotification()` inspects `DB::transactionLevel()`: if an active transaction is underway, dispatch is deferred via `DB::afterCommit(...)`.
- Failed or rolled-back database transactions never trigger WebSocket events, preventing ghost notifications and race conditions.

### 5. Transport Security & Origin Restrictions
- In production, Reverb runs behind TLS termination (`wss://`) over port 443/8080 with encrypted frames.
- Allowed WebSocket origins are strictly restricted in `config/reverb.php` (`allowed_origins = [env('APP_URL')]`).

---

## 8. Document & File Upload Security Architecture (Phase 10)

BuildFlow treats all client-uploaded files, filenames, and headers as untrusted input. The system implements defense-in-depth protections across ingestion, storage, retrieval, and retention.

### 1. Server-Side File Inspection & MIME Verification
- **Magic-Byte Inspection**: File MIME types are verified using PHP `finfo` inspecting the file binary header (`finfo_open(FILEINFO_MIME_TYPE)`), never trusting browser-supplied headers alone.
- **Strict Allowlist**: Only approved construction and enterprise formats are permitted:
  - Documents: `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
  - Spreadsheets: `application/vnd.ms-excel`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `text/csv`
  - Images: `image/jpeg`, `image/png`, `image/webp`
- **Prohibited Formats**: Executable binaries (`.exe`, `.sh`, `.bat`, `.cmd`), web scripts (`.php`, `.phtml`, `.js`), and vector formats with embedded script potential (`.svg`) are unconditionally blocked.
- **Size & Integrity Bounds**: Configurable ceiling of 15 MB per file (`MAX_FILE_SIZE = 15728640`). 0-byte or empty files are rejected.

### 2. Path Traversal Defense & Randomized Storage
- **Filename Sanitization**: User filenames undergo `sanitizeFilename()`, stripping directory separators (`/`, `\`), collapsing consecutive periods (`..`), and removing non-alphanumeric characters.
- **UUID Physical Naming**: The file is stored using a generated UUID v4 string (`{uuid}.{extension}`). The user's original filename is preserved strictly as database metadata.
- **Private Partitioning**: Documents are stored outside the public document root on the private disk:
  `storage/app/private/companies/{company_id}/{entity_type}/{record_id}/{uuid}.{ext}`
  No public symlinks or static URL routes exist.

### 3. Cryptographic Hashing & Anti-Malware Interface
- **SHA-256 Checksums**: Every uploaded file is hashed at ingestion (`hash_file('sha256', $path)`), storing a 64-character digest in `documents.checksum` to detect tampering or corruption.
- **Pluggable Scanner Pipeline**: Uploads pass through `FileScannerInterface`. The default `ConfigurableFileScanner` supports ClamAV daemon scanning over local UNIX sockets (`/var/run/clamav/clamd.ctl`) or TCP (`127.0.0.1:3310`). If a scanner is enabled and detects a threat, upload is aborted, quarantined, and recorded in audit logs.

### 4. Tenant Isolation & IDOR Protection
- **Multi-Tenant Boundary**: All database queries are filtered by `BelongsToCompany`. A user in Company A cannot view, download, preview, replace, or delete documents owned by Company B. Direct URL tampering returns `404 Not Found` via route-model binding.
- **Parent Record Verification**: `DocumentPolicy` validates that the user possesses active domain rights to the owning business record before permitting upload or retrieval.

### 5. Confidential Personnel Vault
- **Sensitive Categories**: Personnel identity documents (Aadhaar, PAN), payroll banking files, and statutory contribution receipts are flagged `is_confidential = true`.
- **Restricted Access**: Confidential files are restricted exclusively to `owner`, `admin`, `hr_officer`, and `accountant` roles. Non-privileged project personnel (site supervisors, storekeepers, engineers) receive HTTP `403 Forbidden`.

### 6. Statutory Audit Immutability & Lifecycle Protection
- **Immutable Financial Evidence**: Files supporting posted or reversed general ledger journals (`JournalEntry`), certified or paid subcontractor bills (`SubcontractorBill`), and settled loans (`Loan`) are locked from deletion or direct overwrite (`isParentImmutable()` check).
- **Controlled Supersession**: For active records, updates increment the version counter (`version = v+1`), mark previous documents as `superseded`, record `superseded_by_id`, and retain previous files for statutory historical review.
- **Audit Logging**: Upload, version replacement, and archival deletion actions are logged in `audit_logs` without storing raw file bytes or credentials.
- **Zero Financial Mutation**: Uploading, replacing, or archiving supporting documents never mutates ledger balances, journal lines, debit/credit totals, or payment allocations.

---

## 7. Production Security Hardening & Operational Safeguards (Phase 11)

### 1. Fail-Closed Anti-Malware Protection
- **Configurable Scanner Pipeline**: Governed by `ConfigurableFileScanner`, supporting local TCP or Unix-socket ClamAV instances.
- **Fail-Closed Mode (`SCANNER_FAIL_CLOSED=true`)**: In production, if the scanner daemon is unreachable, times out, or produces an ambiguous scan result, the upload is rejected with `ScanResult::error()` rather than silently treated as clean.
- **Stream & Preview Enforcement**: In `DocumentService::streamPreview()` and `download()`, any file with `scan_status` in `infected`, `error`, or `quarantined` (or any non-clean file when `fail_closed` is active) is rejected with `HTTP 423 Locked`.

### 2. Private Storage Bypass Mitigation
- **Local Disk Route Suppression**: In Laravel, the `'local'` filesystem disk can register a public route `/storage/{path}` if `'serve' => true`.
- **Hardened Configuration**: `config/filesystems.php` explicitly sets `'serve' => false` on the `'local'` disk (`storage/app/private`), ensuring raw private storage files cannot be accessed or enumerated without passing through authenticated, policy-guarded controller endpoints.

### 3. Immediate Revocation of Workspace Access
- **Active Membership Guard**: The `company_user` pivot table tracks membership states (`active`, `suspended`, `inactive`).
- **Enforcement Points**: `User::currentCompany()` and `WorkspaceController::switch()` require `wherePivot('status', 'active')`. If an administrator deactivates a user's company membership, their active session is immediately blocked from accessing tenant resources without waiting for session expiration.

### 4. SQL Operator Precedence Hardening
- **Vulnerability Mitigated**: Calling `orWhere` at the top level builder on models with global tenant scopes can generate SQL where the boolean `OR` binds looser than `AND` (`WHERE company_id = ? AND col1 LIKE ? OR col2 LIKE ?`), leaking cross-company records matching the second condition.
- **Resolution**: In `GlobalSearchController`, all search predicates are wrapped within parameter-grouped subquery closures:
  ```php
  $query->where(function ($sub) use ($search) {
      $sub->where('title', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%");
  });
  ```
  This guarantees that global tenant scopes strictly bind all search criteria: `WHERE (company_id = ?) AND (col1 LIKE ? OR col2 LIKE ?)`.

### 5. Compensation & Wage Access Boundaries
- **Field & Role Isolation**: Site engineers and general supervisors require access to employee master data to record daily muster rolls, but must not access confidential commercial wages or monthly salary figures.
- **Enforcement**:
  - `EmployeeController::authorizeEmployeeView()` verifies HR / Finance role permissions.
  - Blade templates (`resources/views/employees/show.blade.php`) mask compensation amounts behind role checks, displaying `[Restricted to HR / Finance]` to unauthorized roles.
  - Global search excludes employee records from search results when queried by non-HR/finance roles.

### 6. Layered Rate Limiting & Throttles
- Strict rate limits prevent brute force, denial of service, and rapid automated enumeration:
  - `POST /login`: `throttle:5,1` (5 attempts per minute)
  - `POST /forgot-password`: `throttle:3,1` (3 attempts per minute)
  - `POST /documents`: `throttle:30,1` (30 uploads/replacements per minute)
  - `GET /documents/{id}/stream`: `throttle:60,1` (60 stream/downloads per minute)
  - `GET /health`: `throttle:60,1` (60 requests per minute)

### 7. Information Leak Prevention & Custom Error Pages
- Standard debug stack traces and internal directory layouts are suppressed in production.
- Custom branded error views (`resources/views/errors/403.blade.php`, `404.blade.php`, `500.blade.php`, `503.blade.php`) match the Google Stitch visual palette (`#dbe2ea` / `#f7f9fc`), providing safe guidance to users while revealing zero internal system paths, table names, or SQL statements.

---

## 8. Automated Backup Security & Encryption at Rest (Phase 13)

### 1. Cryptographic Protection at Rest (OpenSSL AES-256-CBC)
- **Envelope Encryption**: When `BACKUP_ENCRYPT=true` is enabled, database dumps (`database.sql`) and file archives (`storage.tar.gz`) are encrypted using standard OpenSSL AES-256-CBC.
- **Key Isolation**: Encryption keys are supplied via `BACKUP_ENCRYPTION_KEY` in environment variables and are never stored in the database, filesystem code, or backup manifests.
- **Initialization Vector (IV)**: Each encrypted archive prepends a cryptographically secure 16-byte random IV (`openssl_random_pseudo_bytes(16)`), ensuring ciphertext unpredictability even across identical source files.
- **Decryption Verification**: The `buildflow:backup:validate` command verifies decryptability with the configured key before flagging a backup as structurally sound.

### 2. Filesystem Access Restrictions & Least Privilege
- **Directory Permissions**: Backup destination directories (`storage/app/backups/`) and individual snapshot folders are created with restrictive `0700` permissions (read/write/execute restricted strictly to the application owner process).
- **File Permissions**: Database dumps, tarballs, and `manifest.json` files are created with `0600` permissions (read/write restricted strictly to the application owner process).
- **Web Root Exclusion**: The backup store is situated inside `storage/app/backups/`, completely outside public web document root (`public/`), precluding direct HTTP access.

### 3. Secret Redaction & Zero Credentials Disclosure
- **Connection Parameter Masking**: Database passwords, connection strings, encryption passphrases, and full filesystem path structures are strictly omitted from console outputs, logs, health checks, and JSON diagnostic endpoints.
- **Sanitized Manifests**: `manifest.json` records metadata (sizes, SHA-256 hashes, validation timestamps, schema counts) but contains zero access keys, database passwords, or cleartext table contents.

### 4. Retention Security & Immutability Protection
- **Retention Pruning Rule**: Automated retention pruning (`pruneOldBackups`) preserves 24 hourly, 30 daily, and 12 monthly recovery points.
- **Protected Recovery Point**: Any backup that has passed isolated disaster recovery verification (`buildflow:verify-recovery`) is tagged with `is_protected: true` in its manifest. The retention engine strictly skips protected backups and the latest valid backup overall, preventing automated pruning from destroying verified recovery baselines.
- **Concurrency Locking**: The backup creation pipeline acquires an atomic lock (`buildflow_backup_running_lock`) to prevent race conditions, corrupted partial dumps, or overlapping backup tasks.

### 5. Production Reverse Proxy & Network Boundary Security
- **Explicit Proxy Boundaries**: In production, `bootstrap/app.php` reads `TRUSTED_PROXIES` to define exact reverse proxy IPs or CIDR blocks. Indiscriminate wildcard proxy trust is prevented, defeating spoofed `X-Forwarded-For` or `X-Forwarded-Proto` client tampering.
- **Secure Cookie Guard**: Production enforcement requires `SESSION_SECURE_COOKIE=true` and HTTPS delivery for session cookies and CSRF tokens.







