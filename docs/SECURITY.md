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



