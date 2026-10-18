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
| **Company Owner / Admin** | All modules, approvals, company settings, policies, override decisions | None | Unrestricted (`*` permission) |
| **Project Manager** | Assigned projects, sites, daily logs, material requests, attendance muster, retention claim drafting & certificates | Company bank accounts, financial journals, payroll compensation, approval policies, approving own claims | 403 on `/payroll`, `/accounting`, `/finance/policies`, 403 on self-approving claims |
| **Site Supervisor** | Assigned sites, daily site logs, muster roll attendance recording, material requests | Financial accounting, client billing, retention claims, company payroll, employee bank details | 403 on `/payroll`, `/accounting/*`, `/billing/*`, `/retention/*`, `/procurement/orders/create` |
| **HR / Payroll Officer** | Employee profiles, shifts, biometric attendance audit, payroll batch preparation, payslips | Financial accounting, client billing, retention claims, procurement PO approvals | 403 on `/accounting/*`, `/billing/*`, `/retention/*`, `/procurement/policies` |
| **Finance Approver / Accountant** | Financial journals, trial balance, client billing & receipts, retention claim approvals, client dispatch, claim receipts | Direct procurement creation, site master editing, approval policy configuration | 403 on `/procurement/policies`, `/procurement/orders/create` |
| **Procurement Officer** | Purchase requests review, PO conversion, supplier management, price quotes | Financial accounting journals, payroll records, client billing, retention claims | 403 on `/payroll`, `/accounting/*`, `/billing/*`, `/retention/*` |
| **Storekeeper** | Goods receipt notes (GRN), quality inspection, warehouse stock transfers, inventory ledgers | Financial journals, payroll, client billing, retention claims, PO issuance | 403 on `/payroll`, `/accounting/*`, `/billing/*`, `/retention/*`, `/procurement/orders/create` |

---

## 6. Concurrency Safeguards & Immutable Historical State

1. **Pessimistic Row Locking (`lockForUpdate()`)**:
   - **Client Retention Release Claims**: Claim creation acquires row locks on `client_contracts` and source `client_invoices` with `lockForUpdate()`, strictly preventing duplicate claim allocations across concurrent requests.
   - **Retention Payment Remittances**: Receipt recording acquires row locks on `retention_release_claims` with `lockForUpdate()` within `DB::transaction`, guaranteeing that total receipts never exceed the outstanding balance due.
   - **Client Billing Payments**: Payment recording acquires row-level locks on `client_invoices` within `DB::transaction`, preventing double-receipt and overpayment race conditions.
   - **Purchase Request Conversion**: Conversion acquires row locks on `purchase_requests` and `purchase_request_items` preventing duplicate PO issuance from rapid concurrent submissions.
   - **Loan EMI Repayments**: EMI repayments lock installment records and verify `status !== 'paid'` inside the transaction.
2. **Segregation of Duties (SoD)**:
   - Claim preparers are strictly prohibited from approving their own retention claims (`claim->created_by === auth()->id()`), enforced at the controller and service level. Only independent review roles or Company Administrators can clear claims for billing.
3. **Historical Attendance Protection**:
   - Daily attendance muster roll records cannot be updated or created for dates falling within an `approved` or `disbursed` `PayrollPeriod`. Attempts throw HTTP 403.
4. **Double-Entry Journal & Stock Ledger Immutability**:
   - Posted general ledger journal lines, retention asset vouchers, and stock ledger movement entries are immutable. Reversals must be executed via distinct counter-balancing transactions.


