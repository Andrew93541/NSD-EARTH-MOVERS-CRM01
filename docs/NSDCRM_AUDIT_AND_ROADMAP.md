# NSDCRM / BUILDFLOW — SYSTEM AUDIT & IMPLEMENTATION ROADMAP

**Document Date:** October 18, 2026  
**Workspace:** `/Users/andrew/Developer/NSD EARTH MOVERS CRM`  
**Reference:** NSDCRM Project Context & Google Stitch Project `15768566540196779944`  

---

## 1. Executive Summary & Environment Assessment

A non-destructive architectural and technical audit was conducted on the existing construction management platform. The application is a monolithic Laravel 11/12 application running on PHP 8.5.11, MySQL 26.7.0 / 8+, and Blade with Tailwind CSS and Alpine.js.

### Verified Environment & Stack:
- **PHP Version**: 8.5.11 (Homebrew cli)
- **Composer**: 2.10.3
- **Database Server**: MySQL 26.7.0 (Homebrew service active on port 3306)
- **Databases**: `buildflow_crm` (Primary), `buildflow_crm_test` (Automated Test Isolation)
- **Asset Pipeline**: Vite 8.3.4 + Tailwind CSS 4 + Alpine.js 3.17.4 + Chart.js 4.5.1 (Production build verified, 90.17 kB CSS / 256.83 kB JS)
- **Test Suite Status**: 54 PHPUnit tests, 343 assertions, 0 errors, 100% passing

---

## 2. Six-Phase Roadmap Status Audit

| Phase | Module Scope | Status | Notes & Verification |
|---|---|:---:|---|
| **Phase 1** | Foundation & Core CRM (Workspaces, Auth, Roles, Clients, Projects, Dashboard, Audit) | **Verified Working** | Multi-tenant scoping via `BelongsToCompany`, Auth throttling, Stitch-matched Dashboard with live DB queries. All 35 main routes return HTTP 200. |
| **Phase 2** | Site Operations & Attendance (Sites, Daily Logs, Worker Count, Muster Roll, Biometric Punch Audit) | **Verified Working** | Multi-site logs with worker counts, daily muster roll with radio status toggles and overtime approval, raw punch log traceability (`attendance_punches`). |
| **Phase 3** | Workforce & Payroll (Salaries, Wages, Overtime, Payroll Batch Generation, Approvals, Payslips) | **Verified Working** | Attendance-backed deterministic payroll calculation, HR/supervisor approval state machine, disbursement locks, printable payslip certificates. |
| **Phase 4** | Suppliers, Purchasing & Inventory (Material Indents, PM Approval, PO Conversion, GRN, Invoices, Stock Ledger) | **Verified Working** | Site material requests, PM approval workflow with segregation of duties, partial/full PO conversion, GRN quality inspection, and 3-way matching. |
| **Phase 5** | Project Finance & Commercials (RA Billing, GST, Retention, Partial Payments, Expenses) | **Verified Working** | Progress RA billing with 9% CGST, 9% SGST, retention deductions, partial payment tracking, overpayment rejection, operational expenses. |
| **Phase 6** | Accounting, Integrations & Reports (Double-Entry Ledger, Trial Balance, Loans/EMI, Reverb) | **Partially Implemented** | Chart of accounts, balanced journal entries (`bccomp`), Trial Balance report, equipment finance with 36-month amortized EMI schedules working. Real-time Reverb evaluation documented below. |

---

## 3. Detailed Audit Findings

### 3.1. Verified Working Functionality
1. **Multi-Tenant Isolation**: Server-side tenant enforcement on every model via `BelongsToCompany` global scope. Switching companies is protected against unauthorized access (HTTP 403), and accessing another company's records returns HTTP 404.
2. **Dashboard UI Fidelity**: Exact reproduction of Google Stitch layout with responsive dot-matrix execution volume chart, site operations workforce deployment, dynamic INR metrics (`₹ Cr` / `₹ Lakh`), and data tables.
3. **Atomic Inventory Ledger**: Inter-warehouse transfers enforce source quantity verification; outflow and inflow are atomically wrapped in database transactions preventing negative stock or orphan transfers.
4. **Balanced Accounting**: Journal entries validate `debit == credit` down to 2 decimal places using `bccomp` and block unbalanced submissions.
5. **Progress RA Billing**: Correctly computes subtotal, taxes (18% GST split into CGST/SGST), retention deduction, tracks partial collections, and updates balance due.

### 3.2. Partially Implemented & Missing Functionality
1. **Phase 3 — Payroll Engine**:
   - `payroll_periods` and `payroll_records` tables were created in migration `000005`, but their Eloquent models (`PayrollPeriod`, `PayrollRecord`) were missing.
   - Dedicated UI for creating a payroll period, aggregating attendance to calculate gross pay, overtime pay, and deductions, and an approval action (Draft -> Approved -> Disbursed) are required.
2. **Phase 4 — Procurement UI Extensions**:
   - Purchase Requests (`purchase_requests`) and Goods Receipts (`goods_receipts`) have database schemas and models, but currently share space under Procurement. Dedicated views for PR submission and GRN quality inspections (accepted vs rejected quantities) are planned.
3. **Phase 6 — Real-Time Evaluation (Laravel Reverb & Echo)**:
   - Evaluated below in Section 4.

### 3.3. Security & Database Risk Analysis
- **Tenant Data Isolation**: Implemented via global query scopes and session validation. Foreign keys have `cascadeOnDelete` or `nullOnDelete` appropriately.
- **CSRF & Rate Limiting**: All form submissions enforce CSRF tokens; authentication uses IP-based rate limiting (5 attempts per minute).
- **Data Protection**: Monetary values use `DECIMAL(15, 2)` or `DECIMAL(12, 2)` preventing floating-point rounding errors.

---

## 4. Evaluation of Real-Time Updates (Laravel Reverb & Echo)

As mandated by Section 8 of the project rules:
1. **Compatibility**: Laravel Reverb is a first-party, high-performance WebSocket server for Laravel 11/12. It runs directly as a PHP CLI process and requires no external Node.js server or commercial Pusher account.
2. **Resource & Infrastructure**: Can run as a daemon supervisor process locally or on a standard Linux VPS alongside MySQL.
3. **Event Scoping**:
   - Channels must be private and company-scoped (e.g. `private-company.{companyId}`).
   - Channel authorization must verify user membership in that company.
   - Financial and payroll approvals must never broadcast raw salary or sensitive banking numbers over WebSockets; events should broadcast lightweight entity IDs and notification signals, prompting the client to fetch authorized data via standard authenticated routes.
4. **Fallback Design**: The application gracefully functions via standard HTTP request-response cycles and polling without hard dependencies on WebSocket connectivity.

---

## 5. Verification & Completed Milestones

### Phase 3 — Workforce and Payroll (Completed & Verified)
1. **Models & Schema**:
   - `PayrollPeriod` and `PayrollRecord` Eloquent models connected with `Company`, `Employee`, `User` (approver), and `DailyAttendance`.
2. **Controller & Business Rules (`PayrollController.php`)**:
   - `index`: Displays summary KPI cards (Total Disbursed, Pending Approval, Active Workforce) and historical payroll registers.
   - `create` & `store`: Generates deterministic payroll batches by inspecting actual daily attendance records in the period.
     - Daily wage / machine operators: `days_worked * daily_wage_rate`, overtime hourly rate = `(daily_wage_rate / 8) * 1.5`.
     - Permanent / salaried employees: Base salary pro-rated against 26 standard working days, overtime hourly rate = `(monthly_salary / 208) * 1.5`.
     - Site allowances: Applicable for >10 days active site deployment.
     - Statutory deductions: 5% Provident Fund contribution for salaried staff.
   - `show`: Interactive payroll batch register with worker-by-worker earnings, deductions, and site assignments.
   - `approve`: Enforces supervisor/HR sign-off before wages can be disbursed.
   - `disburse`: Marks records as paid and finalizes disbursement. Re-approval or post-disbursement alterations are strictly blocked.
   - `payslip`: Printable salary slip / certificate with official GSTIN, earnings breakdown, statutory deductions, net transfer amount, and sign-off blocks.
3. **Automated Test Suite**:
   - Created `tests/Feature/PayrollCalculationAndApprovalTest.php` with 6 dedicated test cases covering batch creation from muster, overtime rate calculation, approval state machine, disbursement locks, and payslip rendering.
   - Full test suite: **33 tests, 193 assertions, 0 errors**.
4. **Visual & UI Compliance**:
   - Styled with Stitch design tokens (Plus Jakarta Sans, smooth rounded cards, action blue accents, clean status badges).
   - "Enterprise Construction" banner card removed from all layout templates and verified clean across all rendered views.

---

### Phase 4 — Suppliers, Purchasing, Goods Receipts & Inventory (Completed & Verified)
1. **Goods Receipt Note (GRN) & Quality Inspection Workflow**:
   - Implemented `ProcurementController::createGRN` and `ProcurementController::storeGRN` connected to `GoodsReceipt` and `GoodsReceiptItem`.
   - Gate entry metadata capturing delivery challan number, vehicle/truck registration, and inspection notes.
   - Itemized quality inspection supporting received, accepted, and rejected quantities, with mandatory rejection reason tracking.
   - Enforces mathematical integrity: `accepted + rejected == received`, with checks preventing over-receipt beyond PO balances.
2. **Double-Entry Stock Ledger Credit (`StockLedgerEntry`)**:
   - Inward transaction automatically generated (`transaction_type: 'goods_receipt'`, `direction: 'in'`) directly crediting the designated site store or central yard.
   - Strictly isolates rejected materials so they do not artificially inflate warehouse valuation or stock balances.
3. **Purchase Order State Machine & 3-Way Match**:
   - PO line item `quantity_received` incremented atomically.
   - Status updates automatically to `partially_received` or `completed`.
   - Integrated commercial supplier invoice recording (`ProcurementController::storeSupplierInvoice`) establishing 3-way matching between PO, Goods Receipt, and vendor billing.
4. **Automated Test Suite**:
   - Created `tests/Feature/ProcurementAndGoodsReceiptTest.php` with 6 feature tests covering PO creation, GRN form view, quality inspection stock crediting, rejection handling, over-receipt rejection, and supplier invoice linking.
   - Full test suite: **39 tests, 232 assertions, 0 errors**.

5. **Site Purchase Requests (Material Indents), PM Approval & PO Conversion (Completed & Verified)**:
   - Additive migration `2026_10_18_000001_enhance_purchase_requests_and_order_items.php`:
     - `purchase_requests`: added `priority`, `warehouse_id`, `rejection_reason`, `submitted_at`.
     - `purchase_request_items`: added `specifications`, `quantity_ordered`.
     - `purchase_order_items`: added `purchase_request_item_id` foreign key.
   - Interactive Blade Views (Stitch design matched):
     - `purchase-requests/index.blade.php`: KPI summary cards, filter bar (status, priority, project, site), and requisition register.
     - `purchase-requests/create.blade.php`: Alpine.js reactive line items builder, positive quantity validation, auto-rate filling, and Draft vs Submit actions.
     - `purchase-requests/edit.blade.php`: Revision form for draft or rejected indents with pre-filled items.
     - `purchase-requests/show.blade.php`: Requisition detail with 4-stage visual timeline, contextual site yard stock levels, approval action, and rejection modal with mandatory audit reason.
     - `purchase-requests/convert.blade.php`: Procurement PO conversion interface with partial quantity support, live tax computation, and supplier selection.
   - Controller & Business Logic (`PurchaseRequestController.php`):
     - Enforces site supervisor scoping to authorized sites.
     - Strict segregation of duties: requesters cannot self-approve unless holding owner/admin privileges.
     - Atomic DB transaction for PO conversion: links `PurchaseOrder` and `PurchaseOrderItem` back to originating PR and PR line items, increments `quantity_ordered`, and updates status to `partially_converted` or `po_created`.
   - Comprehensive Automated Feature Suite (`tests/Feature/PurchaseRequestWorkflowTest.php`):
     - 15 dedicated tests with 111 assertions covering draft creation, submission timestamping, PM notifications, supervisor authorization, segregation of duties, mandatory rejection reason, unapproved conversion blocking, partial ordering, remaining quantity tracking, over-order prevention, cross-company isolation, and GRN/supplier invoice integration.
6. **Stock Availability, Project Budget Validation & Configurable Approval Limits (Completed & Verified)**:
   - **Additive Schema & Models** (`2026_10_18_000002_create_approval_policies_and_budget_controls.php`):
     - `procurement_approval_policies`: Company-scoped policy tiers with minimum/maximum expenditure thresholds, role slugs, finance review triggers, and owner approval flags.
     - `purchase_request_approvals`: Complete audit log of every approval decision recording user, timestamp, tier, action (`approved`, `rejected`, `override_approved`), comments, request valuation, and overrun status.
     - `purchase_requests`: Added tracking fields: `estimated_total`, `is_pricing_complete`, `is_over_budget`, `budget_overrun_amount`, `current_approval_level`, `required_approval_levels`, `budget_override_approved`, and `budget_override_reason`.
   - **Services Layer Architecture**:
     - `StockAvailabilityService`: Double-entry stock ledger as sole source of truth. Evaluates line-item quantities, intended site warehouse balances, ordered quantities, remaining procurement quantities, and shortfalls. Recommends internal stock transfers from central yards when stock exists elsewhere. Enforces strictly non-destructive checks (zero silent reservations or deductions).
     - `ProjectBudgetService`: Exact decimal arithmetic (`DECIMAL(15, 2)`) evaluating approved project budgets against posted actuals (expenses + supplier bills) and existing commitments (unbilled POs + unconverted PRs) without double counting. Flags incomplete pricing honestly. Calculates overrun amount and budget consumption percentage.
     - `ApprovalPolicyService`: Dynamically evaluates required approval levels based on request valuation, priority, and budget state. Enforces multi-tier sequential progression (e.g. Tier 1: PM, Tier 2: Finance Review, Tier 3: Owner sign-off). Enforces strict segregation of duties (self-approval prohibited) and company owner override authority.
   - **Procurement Conversion Concurrency Safeguards**:
     - Pre-conversion validation with pessimistic database row locking (`lockForUpdate()`).
     - Refreshes stock availability, verifies all required approval levels have been completed, validates budget override compliance, and prevents duplicate conversions or over-ordering.
   - **UI & UX Extensions (Google Stitch `15768566540196779944`)**:
     - `procurement/policies/index.blade.php`: Configurable approval thresholds, role matrix, and segregation-of-duties governance cards.
     - `purchase-requests/show.blade.php`: Project budget summary cards (approved budget, posted actuals, commitments, net remaining), stock availability table with fulfillment badges & yard transfer prompts, and multi-tier approval timeline with decision logs and override modal.
     - `purchase-requests/convert.blade.php`: Refreshed stock shortfall alerts and budget impact indicators before generating purchase orders.
     - `purchase-requests/index.blade.php`: Over-budget badges, pricing completeness flags, and multi-tier approval progress badges (`Pending L1/2`).
   - **Automated Feature Suite** (`tests/Feature/StockAndBudgetApprovalControlsTest.php`):
     - 7 comprehensive tests with 51 assertions covering ledger-backed stock checks without silent deductions, budget calculations without double-counting commitments, over-budget detection and mandatory override reasons, server-side multi-tier approval enforcement, policy access control, conversion blocking on missing approvals, and cross-company isolation.
   - **Full Test Suite**: **61 tests, 394 assertions, 0 errors, 100% passing**.

---

### Phase 7 & 8 — Role-Based End-to-End Quality Audit, Real Browser Verification & Security Hardening (Completed & Verified)
1. **Role-Based Permission Matrix & Test Accounts**:
   - Seeded and verified dedicated accounts for all 7 organizational roles (`Company 1` - NSD Earth Movers & Construction Ltd):
     - **Owner / Administrator**: `admin@buildflow.local` (`*` wildcards, company-wide management & override authority)
     - **Project Manager**: `pm@buildflow.local` (`projects.*`, `sites.*`, `materials.request`, `attendance.view`, `attendance.muster`)
     - **Site Supervisor**: `site@buildflow.local` (`sites.view`, `daily_logs.*`, `attendance.record`, `materials.request` — restricted from confidential financial data)
     - **HR / Payroll Officer**: `hr@buildflow.local` (`employees.*`, `attendance.*`, `payroll.*`)
     - **Finance Approver / Accountant**: `accounts@buildflow.local` (`finance.*`, `billing.*`, `accounting.*`, `expenses.*`, `loans.*`, `procurement.invoices`)
     - **Procurement Officer**: `procurement@buildflow.local` (`procurement.*`, `materials.*`, `purchase-requests.view`, `purchase-requests.convert`)
     - **Storekeeper**: `storekeeper@buildflow.local` (`inventory.*`, `procurement.receive`, `materials.view`)
2. **Defect Register & Remediations**:
   - **SEC-01 (High)**: *Confidential Payroll Direct Route Access* — Added `authorizePayrollView()`, `authorizePayrollManage()`, `authorizePayrollApprove()`, and `authorizePayrollDisburse()` in `PayrollController.php`. Blocks unauthorized roles (e.g. Site Supervisors, Storekeepers) with HTTP 403.
   - **SEC-02 (High)**: *Accounting Ledger Direct Route Access* — Added `authorizeAccounting()` in `AccountingController.php` for journals, create, store, and trial-balance. Returns HTTP 403 for non-finance users.
   - **SEC-03 (High)**: *Client Billing Payment Concurrency & Race Condition* — Added `authorizeBilling()` and wrapped `BillingController::recordPayment` in `DB::transaction` with pessimistic row locking (`lockForUpdate()`), preventing duplicate payment recording and balance over-collection.
   - **SEC-04 (High)**: *Loan Double-Deduction & Unauthorized Access* — Added `authorizeLoans()` and transactional verification in `LoanController::recordEmiPayment` preventing duplicate payments on already-settled installments.
   - **DAT-05 (Medium)**: *Historical Attendance Tampering on Disbursed Payroll* — Updated `AttendanceController::storeMuster` to check against finalized/disbursed `PayrollPeriod` records and strictly block modifying attendance for locked payroll dates. Added site supervisor site-scoping check.
   - **SEC-06 (Medium)**: *Unapproved Direct Purchase Order Creation* — Added `authorizeDirectPO()`, `authorizeGRN()`, and `authorizeSupplierInvoice()` in `ProcurementController.php` ensuring purchase orders cannot bypass the authorized procurement role.
   - **SEC-07 (Medium)**: *Confidential Employee & Bank Details Protection* — Added `authorizeEmployeeManage()` in `EmployeeController.php`.
   - **DAT-08 (Medium)**: *Inventory Stock Transfer Authorization* — Added `authorizeStockTransfer()` in `InventoryController.php` preventing unauthorized inventory reallocations.
   - **SEC-09 (Low)**: *Expense Self-Approval Prevention* — Restricted automatic expense approval in `ExpenseController.php` to finance and admin roles.
   - **UI-10 (Low)**: *Role-Gated Sidebar UI & Stitch Alignment* — Gated sidebar items in `resources/views/layouts/app.blade.php` with permission checks while preserving Stitch active styling, typography, and responsive hamburger navigation. Fixed undefined key in `purchase-requests/show.blade.php` and enhanced `StockAvailabilityService.php`.
3. **Automated Regression Suite (`tests/Feature/RoleBasedAccessAndIntegrityAuditTest.php`)**:
   - 7 test cases with 62 assertions covering:
     - Unauthorized role direct access to `/payroll` returning HTTP 403.
     - Unauthorized role direct access to `/accounting/trial-balance` returning HTTP 403.
     - Accountant authorized access to trial balance and journals returning HTTP 200.
     - Disbursed payroll historical muster roll modification lock (HTTP 403).
     - Client billing overpayment and concurrent double-receipt prevention via row locking.
     - Loan EMI double-payment prevention.
     - Cross-company isolation on client billing records returning HTTP 404.
   - **Total Automated Test Suite**: **68 tests, 456 assertions, 0 errors, 100% passing**.
4. **Real Browser Automation (`tests/Browser/e2e_browser_audit.js`)**:
   - Automated via `puppeteer-core` driving local Chrome (`/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`).
   - Executed 11 end-to-end user workflows:
     - Workflow 1: Authentication failure validation (invalid password rejected).
     - Workflow 2: Admin login & Dashboard live metrics inspection.
     - Workflow 3: Projects register & Site detail rendering.
     - Workflow 4: Attendance muster roll rendering.
     - Workflow 5: Purchase requests register & PR detail with live stock and budget impact cards.
     - Workflow 6: Procurement PO detail & 3-way match.
     - Workflow 7: Client billing RA bill detail & retention deduction tracking.
     - Workflow 8: Accounting double-entry general ledger & trial balance.
     - Workflow 9: Role-gated navigation verification for Site Supervisor & HTTP 403 verification on direct URL access to `/payroll` and `/accounting/trial-balance`.
     - Workflow 10: Accountant login, access to financial reports, and HTTP 403 verification on approval policy modification.
     - Workflow 11: Multi-device responsive verification at Desktop (1280x800), Tablet (768x1024), and Mobile (375x812).
   - **Browser Audit Outcome**: **15 passed, 0 failed, 17 screenshots captured**.
   - **Frontend Build**: Vite production compilation verified clean in 164ms.

---

## 8. Next Recommended Implementation Target

With core operational workflows, multi-tier approvals, role-based access control, and browser validation complete:
1. **Client Retention Release Workflow**: Mechanism to bill and release accumulated defect liability retentions upon project completion certificate issuance.
2. **Automated Depreciation & EMI Ledger Postings**: Automatic journal posting from equipment EMI payments directly into the general ledger.
3. **Subcontractor Measurement Sheets & Work Order Matching**: Subcontractor bill certification against approved site measurements.




