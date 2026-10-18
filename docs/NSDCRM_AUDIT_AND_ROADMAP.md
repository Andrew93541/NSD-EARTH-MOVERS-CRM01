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
- **Asset Pipeline**: Vite 8.3.4 + Tailwind CSS 4 + Alpine.js 3.17.4 + Chart.js 4.5.1 (Production build verified, 98.62 kB CSS / 256.83 kB JS)
- **Test Suite Status**: 123 PHPUnit tests, 795 assertions, 0 errors, 100% passing

---

## 2. Six-Phase Roadmap Status Audit

| Phase | Module Scope | Status | Notes & Verification |
|---|---|:---:|---|
| **Phase 1** | Foundation & Core CRM (Workspaces, Auth, Roles, Clients, Projects, Dashboard, Audit) | **Verified Working** | Multi-tenant scoping via `BelongsToCompany`, Auth throttling, Stitch-matched Dashboard with live DB queries. All 35 main routes return HTTP 200. |
| **Phase 2** | Site Operations & Attendance (Sites, Daily Logs, Worker Count, Muster Roll, Biometric Punch Audit) | **Verified Working** | Multi-site logs with worker counts, daily muster roll with radio status toggles and overtime approval, raw punch log traceability (`attendance_punches`). |
| **Phase 3** | Workforce & Payroll (Salaries, Wages, Overtime, Payroll Batch Generation, Approvals, Payslips) | **Verified Working** | Attendance-backed deterministic payroll calculation, HR/supervisor approval state machine, disbursement locks, printable payslip certificates. |
| **Phase 4** | Suppliers, Purchasing & Inventory (Material Indents, PM Approval, PO Conversion, GRN, Invoices, Stock Ledger) | **Verified Working** | Site material requests, PM approval workflow with segregation of duties, partial/full PO conversion, GRN quality inspection, and 3-way matching. |
| **Phase 5** | Project Finance & Commercials (RA Billing, GST, Retention, Partial Payments, Expenses) | **Verified Working** | Progress RA billing with 9% CGST, 9% SGST, retention deductions, partial payment tracking, overpayment rejection, operational expenses. |
| **Phase 6** | Accounting, Integrations & Reports (Double-Entry Ledger, Trial Balance, Loans/EMI, Asset Depreciation, Reverb) | **Verified Working** | Chart of accounts, balanced journal entries (`bccomp`), Trial Balance report, equipment finance with amortized EMI schedules, automatic double-entry journal postings (Debit 2010 / Debit 5020 / Credit 1010), fixed asset register, SLM/WDV depreciation engine with pro-rata scaling, and non-cash GL depreciation postings (Debit 5030 / Credit 1520). |
| **Phase 7** | Subcontractor Work Orders, Measurement Book & Bill Certification (Vendor Master, WOs, MB LBD Calculations, 3-Way Match RA Bills, GL Postings) | **Verified Working** | Itemized BOQ work orders, digital MB ($L \times B \times D$ formula, review/approval/rejection), 3-way matching RA billing, retention (2030) and TDS (2040) deductions, balanced GL vouchers (Dr 5040, Dr 1030 / Cr 2030, Cr 2040, Cr 2020), controlled reversals, and zero double counting in project budget actuals vs commitments. |

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

## 8. Client Retention Release & Defect Liability Workflow (Completed & Verified)

### 8.1. Implementation Scope & Architecture
1. **Contractual Terms Configuration**:
   - `client_contracts` table captures project-specific retention terms: custom percentage (e.g. 5% - 10%), maximum cap limits, release schedules (`staged_50_50`, `single_full_release`, custom), Defect Liability Period (DLP) duration (e.g. 12 or 24 months), and certificate prerequisites.
2. **Engineering Certifications & Eligibility**:
   - `client_completion_certificates` stores Practical Completion Certificates (PCC), DLP Clearance Certificates (DLCC), Handover notes, and snag-clearance records with defect counts (`defect_count_reported` vs `defect_count_resolved`, `all_defects_rectified`).
   - Strict server-side eligibility: Stage 1 requires verified PCC; Stage 2 requires DLP maturity countdown + approved zero-defect clearance certificate. Ineligible stages display contractually accurate blockers.
3. **Release Claim Lifecycle**:
   - State machine: `Draft -> Submitted (Under Review) -> Approved / Rejected -> Client Submitted (Billed) -> Partially Received / Received -> Closed`.
   - Proportional allocation across source client RA bills (`retention_claim_invoice_items`) ensuring full backward auditability.
   - Pessimistic row locking (`lockForUpdate()`) prevents concurrent duplicate claims.
4. **Segregation of Duties & Rejection Audit**:
   - Rejection requires mandatory recorded reason (min 5 characters) and preserves historical revisions.
   - Requesters/preparers cannot approve their own claims unless holding Company Administrator privileges.
5. **Zero Revenue Duplication & Accounting Ledger**:
   - Client submission journal entry: Debit `1020` Accounts Receivable, Credit `1025` Retention Money Withheld by Clients.
   - Client payment receipt journal entry: Debit `1010` Operating Bank Account, Credit `1020` Accounts Receivable.
   - Revenue is recognized solely at RA billing, preventing double-counting on retention release or collection.
6. **Client Settlement & Receipts**:
   - Tracks cash remittances, bank transfers, cheques, and UTR references against claims.
   - Prevents overpayment exceeding outstanding balance due.

### 8.2. Verification & Automated Quality Evidence
- **Automated Feature Test Suite**:
  - `tests/Feature/ClientRetentionAndDlpWorkflowTest.php`: 9 comprehensive test cases (43 assertions) covering calculations, caps, certificate prerequisites, approval roles, segregation of duties, concurrency locks, partial receipts, double-entry balanced accounting, and cross-company isolation.
  - **Full PHPUnit Suite**: **77 tests, 499 assertions, 0 errors, 100% green**.
- **Real Browser E2E Test Suite (`tests/Browser/e2e_retention_audit.js`)**:
  - Automated via Puppeteer driving macOS Google Chrome.
  - Complete lifecycle execution from Admin login -> Register -> Contract detail -> Claim draft -> Review submission -> Approval modal -> Client dispatch modal -> Receipt settlement -> Live register metric updates.
  - All 6 screenshots captured and verified:
    1. `retention_01_register.png`: 5 KPI cards, contract list with DLP badges, empty active claims.
    2. `retention_02_contract_detail.png`: Terms, Stage 1 eligible card, Stage 2 DLP countdown blocker, source RA bill, certificate audit trail.
    3. `retention_03_claim_create.png`: Stage prerequisite banner, max permissible limit helper, source RA bill allocation preview.
    4. `retention_04_claim_detail.png`: Under Review status badge, 5-step lifecycle timeline, action buttons, itemized source bill drawdowns.
    5. `retention_05_claim_settled.png`: Fully Received badge, 100% completed lifecycle timeline, settled receipt table with UTR reference.
    6. `retention_06_register_updated.png`: Live register showing eligible amount decreased by ₹50,000, received increased to ₹50,000, and settled claim in active claims table.
- **Frontend Build**: Vite production compilation verified clean in 188ms.

---

## 9. Equipment Finance & Fixed Asset Depreciation Implementation & Verification

### 9.1. Architectural Highlights
1. **Automated EMI Double-Entry Accounting**:
   - Installment settlement automatically creates immutable balanced Journal Entry (`JV-EMI-...`):
     - **Debit**: Account `2010` (Equipment Financing Loans & Borrowings) — Principal repayment reduces liability.
     - **Debit**: Account `5020` (Loan Interest & Financing Charges) — Interest expense recognized.
     - **Credit**: Account `1010` (Bank Account) — Total payment cash outflow.
   - Exact decimal balance verified via `bccomp` (`debits == credits`).
   - Atomic rollback and duplicate settlement prevention on retry.
   - Controlled counter-balancing reversal process with mandatory audit explanation (min 5 chars).
2. **Fixed Asset Register**:
   - Dedicated capital asset register (`fixed_assets`) for heavy machinery (excavators, backhoes, dozers, cranes, graders, compactors).
   - Distinct from consumable inventory items. Tracks capitalization date, purchase date, acquisition cost, eligible additions, gross block, residual salvage floor, useful life (years/months), and deployed project cost center.
   - Associated with financing loan without confusing gross asset cost with outstanding loan liability.
3. **Deterministic Depreciation Engine**:
   - Supports Straight-Line Method (SLM) and Written-Down Value (WDV) methods.
   - Pro-rata partial-period scaling for assets capitalized mid-period.
   - Salvage value floor strictly prevents depreciation below residual value.
   - Non-cash journal entries (`JV-DEPR-...`): Debit Depreciation Expense `5030` (charged to deployed project cost center) / Credit Accumulated Depreciation `1520` (contra-asset).
   - Bank accounts and loan debt balances are untouched.
   - Fleet-wide period review screen (`/assets/period-review`) with batch "Post All Journals" functionality.

### 9.2. Verification & Automated Quality Evidence
- **Automated Feature Test Suite**:
  - `tests/Feature/EquipmentEmiAndDepreciationLedgerTest.php`: 15 comprehensive test cases (101 assertions) covering principal/interest split, balanced postings, duplicate prevention, loan balance reduction, reversals, straight-line calculation, residual floor, WDV calculation, partial-period pro-rata logic, duplicate period prevention, non-cash postings, trial balance equality, atomic rollback, unauthorized rejection, cross-company isolation, and fleet batch posting.
  - **Full PHPUnit Suite**: **92 tests, 600 assertions, 0 errors, 100% green**.
- **Real Browser E2E Test Suite (`tests/Browser/e2e_equipment_depreciation_audit.js`)**:
  - Automated via Puppeteer driving macOS Google Chrome against local dev server (`http://127.0.0.1:8000`).
  - Captured and verified 5 visual artifacts:
    1. `asset_01_loan_emi_settled.png`: EMI #20 settled, JV-EMI-2026-NMSOA posted, outstanding principal reduced, reversal action enabled.
    2. `asset_02_register.png`: Capital Asset Register with 5 KPI cards, search/filter bar, and master asset table.
    3. `asset_03_detail_depreciated.png`: Asset detail, technical specs, linked loan card, and posted depreciation schedule under JV-DEPR-2026-MILMZ.
    4. `asset_04_period_review.png`: Fleet periodic breakdown, unposted/posted metrics, and batch "Post All Journals" action.
    5. `asset_05_trial_balance.png`: Grand totals exactly balanced (Debit ₹3,264,750.00 == Credit ₹3,264,750.00) including accounts `1520`, `2010`, `5020`, and `5030`.
- **Frontend Build**: Vite production compilation verified clean in 193ms.

---

---

## 10. Subcontractor Work Orders, Measurement Book (MB) & Bill Certification (Completed & Verified)

### 10.1. Architectural Highlights
1. **Vendor Master Party Classification**:
   - Subcontractors are identifiable business parties sharing the vendor foundation (`suppliers` table) via `vendor_type` (`subcontractor`, `both`), `subcontractor_code`, `work_categories` (JSON array of specialties: earthmoving, excavation, rcc, roadworks, drainage), `pan_number`, `msme_number`, bank remittance details, and `tds_section` (Section 194C individual 1% or company 2%).
2. **Subcontractor Work Orders & Itemized BOQ**:
   - Work orders contract construction services and earthwork activities, distinctly separated from material purchase orders.
   - Itemized Bill of Quantities (BOQ): Item code, description, work category, unit of measure (`cum`, `sqm`, `rmt`, `mt`, `nos`, `trips`, `hrs`), approved quantity, unit rate, and total line amount.
   - Controlled variations (`VAR-YYYY-XXXXX`) permit authorized revisions to quantities and rates with full audit justifications.
3. **Digital Measurement Book (MB)**:
   - Captures physical site work executed against a specific approved work order line.
   - Deterministic calculation: $\text{Multiplier} \times \text{Length} \times \text{Breadth} \times \text{Height/Depth}$.
   - Supports unit count, trip logging, hourly machine deployment, and manual overrides with mandatory justification.
   - Cumulative quantity limits: Validates that cumulative measured quantities do not exceed the approved work order BOQ limits.
   - Review and approval lifecycle: `Draft -> Submitted -> Approved / Rejected -> Certified`. Rejections require a mandatory recorded reason (min 5 characters).
4. **Three-Way Matching & Running Account (RA) Billing**:
   - 3-Way reconciliation enforces:
     1. Work Order is active and approved.
     2. Measurement Book entries are approved and belong to the selected work order.
     3. Previously billed quantities are excluded ($\text{Eligible Qty} = \text{Measured Qty} - \text{Billed Qty} > 0$).
     4. Applied unit rates match approved work order BOQ rates.
   - Deductions: Contractual retention withheld (default 5%), statutory Section 194C TDS (1% or 2%), Input GST (18%), Mobilization Advance Recovery, Other authorized deductions, and net payable calculation.
5. **Double-Entry General Ledger Integration**:
   - On Certification (`SubcontractorBillingService::certifyBill`):
     - **Debit**: Account `5040` (Subcontractor & Labour Cost - Direct Project Cost) — with `project_id` cost center.
     - **Debit**: Account `1030` (Input GST Receivable / ITC Asset).
     - **Credit**: Account `2030` (Subcontractor Retention Payable - Current Liability).
     - **Credit**: Account `2040` (TDS Payable under Sec 194C - Current Liability).
     - **Credit**: Account `1040` (Mobilization & Material Advances to Subcontractors - Current Asset).
     - **Credit**: Account `1050` (Other Recoveries / Deductions - Current Asset).
     - **Credit**: Account `2020` (Subcontractor Payable - Trade Payables).
     - Balances verified down to 2 decimal places using `bccomp()` ($\text{Debits} == \text{Credits}$).
   - On Payment Remittance:
     - **Debit**: Account `2020` (Subcontractor Payable).
     - **Credit**: Account `1010` (Operating Bank Account).
   - Reversal generates counter-journal entry (`JV-REV-SCB-...`), restores Account `1040` advance asset balance, and unlinks MB entries for future re-billing.
6. **Project Budget Integration (Zero Double-Counting)**:
   - `ProjectBudgetService` integrates unbilled work order balances as commitments and certified subcontractor bills as actuals.
   - When a bill is certified, committed cost decreases by the certified amount while actual cost increases by the identical amount. The total project cost obligation ($\text{Committed} + \text{Actual}$) remains exact with **zero double counting**.

### 10.2. Verification & Automated Quality Evidence
- **Automated Feature Test Suites**:
  - `tests/Feature/SubcontractorWorkOrderAndBillingTest.php`: 20 comprehensive test cases (149 assertions) covering work order lifecycle, $L \times B \times D$ calculations, quantity limit enforcement, rejected measurement resubmission, 3-way reconciliation, partial billing, variation revisions, retention and TDS calculations, server-side permission checks, payment settlement, balanced GL journals, advance recovery exact reconciliation, bill reversal with advance recovery restoration, zero budget double counting, cross-company isolation, concurrency locks, and controlled reversals.
  - `tests/Feature/DoubleEntryAccountingTest.php`: 14 comprehensive test cases (59 assertions) covering double-entry balance validation, trial balance rendering, posted journal header immutability, posted line deletion/modification prevention, line-injection locks, authorized reversals, duplicate reversal blocking, minimum reason length validation, subsystem reversal guards, and RBAC server-side protection.
  - **Full PHPUnit Suite**: **123 tests, 795 assertions, 0 errors, 100% green**.
- **Real Browser E2E Test Suite**:
  - Automated via Puppeteer driving macOS Google Chrome against local dev server (`http://127.0.0.1:8000`).
  - Captured and verified 8 visual artifacts:
    1. `subcon_01_work_order.png`: Work order detail with BOQ progress bars, variation audit trail, and active status.
    2. `subcon_02_measurement_book.png`: Measurement Book register with MB entries, status badges, and project context.
    3. `subcon_03_mb_detail.png`: MB entry detail with dimensions breakdown ($100 \times 12 \times 0.25 = 300\text{ cum}$), approval modal, and review status.
    4. `subcon_04_bill_reconciliation.png`: Certified RA bill with 6 KPI cards (Gross, GST, Retention, TDS, Advance Recovery, Net Payable), detailed financial deductions breakdown audit table, balanced GL journal voucher with Account 1040, and payment remittance status.
    5. `subcon_05_subcontractors_register.png`: Subcontractor register with party classification, TDS sections, and GSTIN/PAN.
    6. `subcon_06_trial_balance.png`: Grand totals exactly balanced (Debit ₹4,200,094.00 == Credit ₹4,200,094.00, Diff: ₹0.00) across all 14 ledger accounts including accounts `1030`, `1040`, `2020`, `2030`, `2040`, and `5040`.
    7. `journal_01_vouchers_register.png`: General journal vouchers register displaying all 7 vouchers with debits, credits, reference badges, and authorized reverse voucher controls.
    8. `journal_02_trial_balance_reconciled.png`: Reconciled Trial Balance view showing zero difference across all active accounts.
- **Frontend Build**: Vite production compilation verified clean in 243ms.

### 10.3. Subcontractor Billing Arithmetic & Accounting Reconciliation Audit
An in-depth audit of the subcontractor billing arithmetic and double-entry ledger postings resolved the reported discrepancy:
1. **Arithmetic Verification**:
   - In `SubcontractorBillingService::createBillFromMeasurements`, net payable is computed as:
     $$\text{Net Payable} = \text{Gross Work} + \text{GST} - \text{Retention} - \text{TDS} - \text{Advance Recovery} - \text{Other Deductions}$$
   - For the benchmark scenario: Gross ₹195,000 + GST (18%) ₹35,100 − Retention (5%) ₹9,750 − TDS (1%) ₹1,950 − Advance Recovery ₹50,000 = **₹168,400.00**.
2. **Double-Entry Journal Defect & Fix**:
   - While `certifyBill` properly reduced the Subcontractor Payable liability (Account 2020) by the ₹50,000 advance recovery, the service omitted inserting a Credit journal line to Account `1040` (Mobilization Advances Asset).
   - This caused debits to exceed credits by ₹50,000 on certified bills with advance recovery.
   - **Resolution**: `SubcontractorBillingService::certifyBill` now inserts Credit lines to Account `1040` for advance recovery and Account `1050` for other deductions, followed by a strict post-insertion database verification assertion. Live Journal Entry #6 was reconciled, bringing the system Trial Balance to an exact zero difference (₹4,200,094.00 balanced).
3. **UI Transparency**:
   - `subcontractor-bills/show.blade.php` previously omitted an Advance Recovery card in its KPI summary, creating the appearance that advance recovery was excluded.
   - **Resolution**: Redesigned KPI card grid to display Advance Recovery and Other Deductions cards dynamically, and added a dedicated **Certified Bill Financial Breakdown & Deductions Audit** panel reconciling additions, subtractions, and net remittance.
4. **Statutory TDS Alignment**:
   - Subcontractor party classification distinguishes individual contractors (1% TDS under Sec 194C) from corporate contractors (2% TDS under Sec 194C) via `Supplier::$tds_section` and `Supplier::defaultTdsRate()`.

### 10.4. Posted Journal Immutability & Financial Correction Audit
1. **Environment & Data Audit**:
   - Audited the `buildflow_crm` database. Confirmed it operates in the `local` environment containing development and demo fixtures generated via `DatabaseSeeder.php` and automated test runs.
   - No real commercial, banking, or production business records exist.
2. **Journal Entry #6 Inspection**:
   - Journal voucher `JV-SCB-2026-VJHLG` (Subcontractor RA Bill Certification RA-01-WO-2026-0001) has 6 lines with balanced totals: Debits ₹685,344.00 == Credits ₹685,344.00 (Diff: ₹0.00).
   - Line 19 (Credit ₹50,000.00 to Account 1040) was inserted during the previous audit to balance the ledger. However, inspection revealed this direct line insertion was executed without an `AuditLog` entry, and occurred on a voucher marked `posted`.
3. **Immutability Architecture Implemented**:
   - **Model Lifecycle Event Locks**: `JournalEntry` and `JournalEntryLine` strictly prohibit updating or deleting posted records.
   - **Process Creation Windows**: `JournalEntry::$unlockedEntryIds` permits line insertion solely during initial process creation; post-creation line additions are blocked with `DomainException`.
   - **Authorized Traceable Reversals**: Implemented `POST /accounting/journals/{journal}/reverse` requiring mandatory justification (min 5 chars), server-side RBAC, and creating an immutable counter-voucher (`JV-REV-...`) and `AuditLog`.
   - **Subsystem Isolation**: Reversals for subsystem-managed transactions (subcontractor bills, loan EMIs, asset depreciation) are guarded and routed to preserve operational synchronization.
   - **Seeder Integrity**: Updated `DatabaseSeeder.php` to generate the complete subcontractor billing workflow through `SubcontractorBillingService::certifyBill`, reproducing balanced vouchers cleanly without manual database intervention.

---

## 11. Next Recommended Implementation Target

With Subcontractor Work Orders, Measurement Book, and Bill Certification fully operational:
1. **GST Tax Return Filing Vouchers**: Automated GSTR-1 and GSTR-3B reconciliation exports from client invoices and supplier/subcontractor input tax credits.
2. **Equipment Fuel & Maintenance Cost Allocation**: Hourly machine deployment and diesel consumption tracking allocated against project cost centers.
3. **Subcontractor Retention Release & Final Settlement**: Final bill certification and settlement of retention withheld (Account `2030`) post-defect liability inspection.


