# BUILDFLOW — IMPLEMENTATION PLAN & ARCHITECTURE ROADMAP

## Overview
**BuildFlow** is a comprehensive, production-grade Construction Company CRM and ERP engineered specifically for civil infrastructure, interior fit-out contractors, heavy earthmoving operations, and multi-site maintenance contracts.

This document outlines the phased implementation plan, tech stack alignment, architectural boundaries, security policies, and progress tracking.

---

## Technology Stack Alignment
- **Runtime & Language**: PHP 8.3+ (Active Runtime: PHP 8.5.11) with Laravel 13.x (Installed: 13.37.0)
- **Database Engine**: MySQL 8+ with InnoDB, foreign keys, composite indexes, strict transactional isolation
- **Frontend / Templating**: Laravel Blade with Tailwind CSS v3 (matching Stitch design system tokens)
- **Micro-Interactions**: Alpine.js
- **Data Visualization**: Chart.js & dot-matrix progress indicators
- **ORM / Persistence**: Laravel Eloquent ORM with strict parameterization & typed casts
- **Testing Suite**: Pest / PHPUnit test cases covering multi-tenant boundaries, transactions, and calculations

---

## Design System Tokens (Extracted from Stitch Master Screen)
- **Primary Color**: Deep Blue `#0058be` / Action Blue `#2563eb` / `#2170e4`
- **Background Canvas**: Soft Gray-Blue `#e9eef5` / `#f7f9fc`
- **Surface Cards**: `#ffffff` with subtle borders `rgba(226, 232, 240, 0.7)` and soft elevation `0 8px 30px -4px rgba(18, 38, 63, 0.04)`
- **Typography**: Plus Jakarta Sans, Inter
- **Accents**: 
  - Emerald Green `#10b981` (On Track / Completed / Paid)
  - Amber `#f59e0b` (At Risk / Pending Approval)
  - Coral Red `#ef4444` (Delayed / Overdue / Critical)
  - Indian Rupee (`₹ Cr` / `₹ Lakh`) currency localization

---

## Implementation Phases & Progress Checklist

### [x] Phase 0 — Environment & Design Inspection
- [x] Verify Stitch MCP connection and extract project `15768566540196779944` ("NSD CRM")
- [x] Download reference screens: `dashboard-refined.html`, `dashboard-main.html`, `crm-overview.html`, `enterprise-overview.html`
- [x] Extract color tokens, layout hierarchy, and UI component standards
- [x] Prepare environment: PHP 8.5+, Composer 2.10+, MySQL 26+, Node.js
- [x] Setup comprehensive project documentation (`ARCHITECTURE.md`, `DATABASE.md`, `WORKFLOWS.md`, `SECURITY.md`, `IMPLEMENTATION_PLAN.md`)

### [x] Phase 1 — Application Foundation & Auth
- [x] Initialize Laravel project structure in workspace
- [x] Configure `.env` and MySQL database connection (`buildflow_crm`, `buildflow_crm_test`)
- [x] Build multi-tenant schema: `companies`, `users`, `roles`, `permissions`, `company_user`
- [x] Implement Auth module: Login, Forgot Password, Reset Password, Workspace Selection
- [x] Build Master App Shell (sidebar, top navbar, notifications, tenant switcher)
- [x] Implement BuildFlow Dashboard matching Stitch design with database-backed metrics
- [x] Create Database Seeders for development & demo records (`NSD Earth Movers & Construction Ltd`)
- [x] Write automated tests for Auth, Tenant Isolation, and Session Handling

### [x] Phase 2 — Projects & Site Operations
- [x] Schema: `projects`, `project_milestones`, `tasks`, `work_packages`, `sites`, `daily_site_logs`, `site_inspections`
- [x] Projects CRUD, detail view, timeline tracking, and budget utilization
- [x] Site Operations monitor: daily logs, worker muster summary, photos & progress reports
- [x] Tests for Project status transitions, creation, and budget tracking (`ProjectManagementTest.php`)

### [x] Phase 3 — Workforce & Attendance
- [x] Schema: `employees`, `shifts`, `shift_rosters`, `attendance_punches`, `daily_attendance`, `leave_requests`
- [x] Raw punch logging (immutable audit) vs processed daily attendance
- [x] Muster roll bulk entry and biometric punch traceability
- [x] Overtime approval workflow and project cost allocation
- [x] Tests for attendance calculation and duplicate punch handling (`AttendanceAndMusterTest.php`)

### [x] Phase 4 — Materials, Warehouses & Procurement
- [x] Schema: `material_categories`, `materials`, `warehouses`, `stock_ledger_entries`, `stock_transfers`
- [x] Schema: `suppliers`, `purchase_requests`, `purchase_request_items`, `purchase_orders`, `purchase_order_items`, `goods_receipts`, `supplier_invoices`
- [x] Double-entry stock movement ledger with atomic transactions and negative stock prevention
- [x] Site Purchase Requests (Material Indents) UI: interactive line items builder, positive quantity validation, and live warehouse stock context
- [x] Project Manager Approval Workflow: segregation of duties enforcement, mandatory rejection reason with audit logging, and lifecycle timeline
- [x] Procurement PO Conversion: atomic database transactions, partial conversion tracking, GST calculation, and over-order prevention
- [x] Three-way matching: PO -> Goods Receipt (GRN) -> Supplier Invoice
- [x] Stock Availability Checks: ledger-backed live stock at destination warehouse, shortfall calculations, central yard transfer prompts, zero silent reservations/deductions (`StockAvailabilityService.php`)
- [x] Project Budget Validation: approved budget vs posted actuals (expenses + bills) and commitments (unbilled POs + open PRs) without double-counting, overrun warnings, and mandatory override reason (`ProjectBudgetService.php`)
- [x] Configurable Approval Limits: company-level approval tiers, multi-level sequential approvals, segregation of duties, server-side authority enforcement, and conversion safeguards (`ApprovalPolicyService.php`, `ProcurementApprovalPolicyController.php`)
- [x] Automated tests for atomic stock transfers (`InventoryTransferTest.php`), procurement receipt & invoice (`ProcurementAndGoodsReceiptTest.php`), PR workflow (`PurchaseRequestWorkflowTest.php`), and stock & budget approval controls (`StockAndBudgetApprovalControlsTest.php` - 7 tests, 51 assertions)

### [x] Phase 5 — Payroll & Client Billing
- [x] Schema: `payroll_periods`, `salary_structures`, `payroll_records`, `payslips`
- [x] Schema: `clients`, `client_contracts`, `billing_milestones`, `client_invoices`, `invoice_payments`, `retentions`
- [x] Deterministic payroll calculation linked to approved attendance records
- [x] Client Running Account (RA) progress billing with retention and tax handling
- [x] Tests for partial payments, outstanding balances, and overpayment prevention (`BillingAndReceivablesTest.php`)

### [x] Phase 6 — Expenses, Payments & Loan EMI
- [x] Schema: `expense_categories`, `expenses`, `payments`, `loans`, `loan_emi_schedules`
- [x] Multi-level expense submission, review, and reimbursement workflow
- [x] Loan & equipment finance management with amortized EMI schedule calculation
- [x] Tests for EMI schedule tracking and expense allocation

### [x] Phase 7 — Double-Entry Accounting & Financial Reports
- [x] Schema: `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `bank_accounts`, `bank_statements`
- [x] Strict balanced debit/credit validation and immutable journal postings
- [x] Financial statements: Trial Balance, General Ledger, Profit & Loss, Balance Sheet, Project Profitability
- [x] Indian GST reporting (CGST, SGST, IGST) configuration
- [x] Tests for journal entry balance and financial report generation (`DoubleEntryAccountingTest.php`)

### [x] Phase 8 — Integration, QA, Role-Based Access Audit & Browser Verification
- [x] Global search across projects, materials, invoices, and workforce (`GlobalSearchController.php`)
- [x] Role-based permission enforcement audit across all 7 organizational roles (`tests/Feature/RoleBasedAccessAndIntegrityAuditTest.php`)
- [x] End-to-end automated test suite execution (68 tests, 456 assertions, 0 errors, 100% passing)
- [x] Real browser E2E test suite with Puppeteer driving macOS Google Chrome (`tests/Browser/e2e_browser_audit.js` - 15 passed, 0 failed)
- [x] Verify UI visual fidelity against Stitch master design tokens, typography, and responsive viewports (Desktop, Tablet, Mobile)
- [x] Complete setup guide in `README.md` and verify `SmokeTest.php` across all 35 main routes
- [x] Defect register remediation (10 security, integrity, and view defects remediated)

### [x] Phase 9 — Client Retention Release & Defect Liability Workflow
- [x] Schema & Migration: `client_contracts`, `client_completion_certificates`, `retention_release_claims`, `retention_claim_invoice_items`, `retention_claim_payments` (`2026_10_18_000003_create_client_retention_and_contracts_tables.php`)
- [x] Eloquent Models: `ClientContract`, `ClientCompletionCertificate`, `RetentionReleaseClaim`, `RetentionClaimInvoiceItem`, `RetentionClaimPayment`, updated `ClientInvoice` and `Role` module wildcard support
- [x] Workflow Engine (`RetentionService.php`):
  - Contract-specific retention terms (custom rate, max cap, 12/24-month DLP, staged vs single release)
  - Prerequisite verification (Practical Completion Certificate verification, DLP maturity countdown, zero-defect clearance)
  - Transactional claim creation with proportional source RA bill allocation and pessimistic row locking (`lockForUpdate()`)
  - Server-side segregation of duties (claim preparers prohibited from self-approving)
  - Mandatory rejection reason logging (min 5 characters) with audit trail
  - Zero revenue duplication: Client billing posts Debit `1020` AR / Credit `1025` Retention Asset without duplicating project revenue
  - Payment remittance handling with overpayment prevention and balanced bank receipt journal postings (Debit `1010` Bank / Credit `1020` AR)
- [x] User Interface (Google Stitch `15768566540196779944` Aligned):
  - Retention Register (`/retention`): 5 dynamic KPI cards, search/filter bar, contracts overview, and active claims table
  - Contract Detail (`/retention/contracts/{id}`): Contract terms, stage cards with ineligibility reasons, source RA bills, certificate audit trail, and claim register
  - Create Claim Form (`/retention/contracts/{id}/claims/create`): Maximum eligible limit enforcement, expected payment due date, source bill allocation preview
  - Claim Detail & Settlement (`/retention/claims/{id}`): 5-step visual lifecycle timeline, approval modal, rejection modal, client dispatch modal, and receipt settlement modal
- [x] Feature Tests (`tests/Feature/ClientRetentionAndDlpWorkflowTest.php` - 9 tests, 43 assertions):
  - Contract retention calculations & cap enforcement
  - Ineligible stage claim blocking (missing PCC or active DLP)
  - Claim creation and source invoice allocation
  - Server-side approval authorization & segregation of duties enforcement
  - Mandatory rejection reason validation
  - Concurrency & double-claiming prevention
  - Partial & full client payment receipt settlement
  - Zero revenue duplication & balanced double-entry accounting journals
  - Cross-company tenant isolation
- [x] Full automated test suite passing: **77 tests, 499 assertions, 0 errors, 100% passing**

### [x] Phase 10 — Automated Equipment EMI & Depreciation Ledger Postings
- [x] Database Schema & Additive Migration (`2026_10_18_000004_create_fixed_assets_and_depreciation_tables.php`):
  - `fixed_assets`: Capital equipment register, categories, capitalization, gross block, residual salvage floor, useful life, SLM/WDV methods, project deployment, linked loan
  - `asset_depreciation_entries`: Periodic depreciation entries with composite unique constraint `['company_id', 'fixed_asset_id', 'period_start_date', 'period_end_date']` (`ade_comp_status_date_idx`), opening/closing carrying values, GL journal voucher link, status (`calculated`, `approved`, `posted`, `reversed`)
  - `loans`: Added `fixed_asset_id`, `liability_account_id` (default `2010`), `interest_account_id` (default `5020`)
  - `loan_emi_schedules`: Added `bank_account_id`, `journal_entry_id`
- [x] Eloquent Models & Service Engine:
  - `FixedAsset` model with methods: `depreciableAmount()`, `monthlyStraightLineDepreciation()`, status badges, and relationships
  - `AssetDepreciationEntry` model with relationships and status badges
  - Updated `Loan` and `LoanEmiSchedule` with account and journal links
  - `EquipmentAccountingService`:
    - `postEmiPayment()`: Pessimistic row locking, validates unposted status, generates balanced double-entry `JournalEntry` (Debit Loan Liability `2010`, Debit Interest Expense `5020`, Credit Bank Account `1010`), updates loan principal and schedule status, writes audit log
    - `reverseEmiPayment()`: Reverses original journal, generates balanced counter-journal, restores loan principal and resets schedule status with mandatory audit reason (min 5 chars)
    - `calculateDepreciation()`: Deterministic SLM & WDV calculation with pro-rata partial-period scaling, caps at residual value floor, unique period prevention
    - `postDepreciationEntry()`: Row locking, generates non-cash journal entry (Debit Depreciation Expense `5030` charged to deployed project cost center, Credit Accumulated Depreciation `1520`), updates asset book value and accumulated depreciation
    - `reverseDepreciationEntry()`: Reverses journal, posts counter-journal, restores asset carrying value
    - `getPeriodicFleetSummary()`: Period metrics across fleet with unposted and posted metrics
- [x] Controller & Web Routing:
  - `LoanController`: Wired `recordEmiPayment()` to `EquipmentAccountingService::postEmiPayment()` and added `reverseEmiPayment()`
  - `FixedAssetController`: 8 RESTful and periodic processing endpoints (`index`, `create`, `store`, `show`, `calculateDepreciation`, `postDepreciation`, `reverseDepreciation`, `periodReview`, `postPeriodBatch`)
  - `routes/web.php`: Registered `loans.emi.reverse` and `assets.*` route group
  - Sidebar navigation updated in `resources/views/layouts/app.blade.php`
- [x] User Interface (Google Stitch `15768566540196779944` Aligned):
  - Loans Detail (`/loans/{id}`): Financed capital asset card, double-entry account mapping card, GL journal voucher links on paid installments, settlement modal, and reversal modal
  - Fixed Asset Register (`/assets`): 5 KPI cards (Gross Block, Accumulated Depr, Net Book Value, Active Fleet, Financed Units), search & filters, assets master table
  - Register Capital Asset Form (`/assets/create`): Registration form with real-time Gross Block calculation and estimated monthly SLM charge, SLM/WDV toggle, project deployment and loan linkage
  - Asset Detail & Depreciation Schedule (`/assets/{id}`): 4 financial KPIs, technical specifications card, financed loan card, depreciation schedule table with GL journal links, "Calculate Depreciation" modal, "Post to GL" action, and reversal modal
  - Fleet Period Review & Batch Posting (`/assets/period-review`): Month/Year period selector, fleet metrics, batch "Post All Journals" button, itemized asset breakdown table
- [x] Automated Feature Tests (`tests/Feature/EquipmentEmiAndDepreciationLedgerTest.php` - 15 tests, 101 assertions):
  - EMI principal and interest split creates balanced double-entry journal (Debit 2010, Debit 5020, Credit 1010)
  - Duplicate settlement and duplicate posting prevention on retry
  - Correct loan principal balance after settlement
  - Reversal of posted EMI settlement with counter-journal and principal restoration
  - Straight-line depreciation calculation and residual salvage value floor
  - Written-down-value (WDV) depreciation calculation
  - Partial-period pro-rata depreciation for mid-period capitalized asset
  - Prevention of duplicate depreciation entries for the same asset in the same period
  - Non-cash depreciation journal posting (Debit 5030, Credit 1520) and General Ledger impact
  - Depreciation reversal creates counter-journal and restores asset carrying value
  - General ledger & Trial balance debit/credit equality after EMI & Depreciation
  - Atomic rollback if settlement or journal posting fails
  - Unauthorized user rejection on fixed assets and depreciation
  - Cross-company tenant isolation on fixed assets and depreciation
  - Batch periodic fleet depreciation calculation and posting
- [x] Real Browser E2E Audit with Puppeteer (`tests/Browser/e2e_equipment_depreciation_audit.js` - 5 screenshots captured and verified):
  - `asset_01_loan_emi_settled.png`: EMI #20 settled, JV-EMI posted, outstanding principal reduced
  - `asset_02_register.png`: Fleet register KPI summary cards and asset master table
  - `asset_03_detail_depreciated.png`: Asset calculated, posted to GL under JV-DEPR, book value updated
  - `asset_04_period_review.png`: Fleet periodic breakdown, unposted/posted metrics, batch action
  - `asset_05_trial_balance.png`: Grand totals exactly balanced (Debit ₹3,264,750.00 == Credit ₹3,264,750.00)
- [x] Full automated test suite passing: **92 tests, 600 assertions, 0 errors, 100% passing**

### [x] Phase 11 — Subcontractor Measurement Book (MB), Work Orders & Bill Certification
- [x] Database Schema & Additive Migration (`2026_10_18_000005_create_subcontractor_work_orders_and_billing_tables.php`):
  - `suppliers`: Extended with `vendor_type`, `subcontractor_code`, `work_categories`, `pan_number`, `msme_number`, bank remittance details, `tds_section` (194C), `tds_rate`, `status`, `notes`.
  - `subcontractor_work_orders`: Unique work order code, company/project/site/subcontractor, contract & revised values, retention % (default 5%), TDS % (1% or 2%), GST % (18%), payment terms, lifecycle status.
  - `subcontractor_work_order_items`: Itemized BOQ lines (code, description, category, unit of measure, approved qty, unit rate, cumulative measured qty, certified qty, billed amount).
  - `subcontractor_work_order_variations`: Controlled revisions (`VAR-YYYY-XXXXX`) updating approved quantities/rates with full audit justification.
  - `measurement_book_entries`: Digital Measurement Book (`MB-YYYY-XXXXX`), $L \times B \times D$ formula or manual override with mandatory reason, chainage/location, site remarks, status (`draft`, `submitted`, `approved`, `rejected`, `certified`).
  - `subcontractor_bills`: Running Account (RA) bills (`SCB-YYYY-XXXXX`), gross certified amount, retention withheld, TDS, input GST, advance recovery, net payable, balance due, linked GL journals (`JV-SCB-...`, `JV-REV-SCB-...`).
  - `subcontractor_bill_items`: Itemized billed lines showing previous, current, and cumulative quantities and amounts.
  - `subcontractor_bill_measurements`: Pivot linking certified MB entries to RA bills with allocated quantities and amounts.
  - `subcontractor_bill_payments`: Remittance tracking with payment mode, bank account, and linked GL settlement journals (`JV-PAY-SCB-...`).
- [x] Eloquent Models & Service Engine:
  - `SubcontractorWorkOrder`, `SubcontractorWorkOrderItem`, `SubcontractorWorkOrderVariation`, `MeasurementBookEntry`, `SubcontractorBill`, `SubcontractorBillItem`, `SubcontractorBillMeasurement`, `SubcontractorBillPayment`, updated `Supplier` and `Project`.
  - `SubcontractorBillingService`:
    - `createWorkOrder()`: BOQ lines and contract value validation.
    - `approveWorkOrder()`: Authorization status progression and audit logging.
    - `addWorkOrderVariation()`: Controlled variation updating revised quantities/rates.
    - `recordMeasurement()`: Deterministic calculation ($L \times B \times D \times \text{multiplier}$ or manual override with mandatory reason), project/site matching validation, cumulative limit verification against BOQ.
    - `approveMeasurement()`, `rejectMeasurement()`, `resubmitMeasurement()`: Multi-step review workflow with mandatory rejection reason (min 5 chars).
    - `createBillFromMeasurements()`: 3-way matching (WO BOQ limit vs approved MB vs billed quantities), anti-duplicate billing, retention/TDS/GST calculations.
    - `certifyBill()`: Atomic transaction with row lock, double-entry journal voucher posting (Debit Subcontractor Work Cost `5040`, Debit Input GST `1030`, Credit Retention Payable `2030`, Credit TDS Payable `2040`, Credit Subcontractor Payable `2020`), balanced via `bccomp()`.
    - `recordBillPayment()`: Bank settlement posting (Debit Subcontractor Payable `2020` / Credit Operating Bank Account `1010`), overpayment prevention.
    - `reverseBill()`: Reverses journal, posts counter-balancing journal (`JV-REV-SCB-...`), unlinks and resets MB entries back to `approved`.
  - `ProjectBudgetService`:
    - Integrated certified bills into actual costs (`getActualCosts()`).
    - Integrated unbilled balance of approved work orders into committed costs (`getCommittedCosts()`).
    - Guarantees **zero double counting** between commitments and actuals.
- [x] Controller & Web Routing:
  - `SubcontractorController`, `SubcontractorWorkOrderController`, `MeasurementBookController`, `SubcontractorBillController`.
  - Registered 24 RESTful routes under `/subcontractors`, `/work-orders`, `/measurement-books`, `/subcontractor-bills`.
  - Sidebar navigation updated with dedicated "SUBCONTRACT & MB" group.
- [x] User Interface (Google Stitch `15768566540196779944` Aligned):
  - Subcontractor Register (`/subcontractors`): KPI cards, vendor type filter, specialties, GSTIN/PAN, and action menus.
  - Create / Show Subcontractor (`/subcontractors/{id}`): Profile, bank remittance details, active work orders, cumulative billed and retention withheld stats.
  - Work Orders Register (`/work-orders`): Status tabs, contract and revised values, project and subcontractor filters.
  - Create Work Order (`/work-orders/create`): Dynamic BOQ builder with live quantity/rate calculations and retention/TDS defaults.
  - Work Order Detail (`/work-orders/{id}`): BOQ table with cumulative progress bars, variations audit trail, "Add Variation" modal, and direct "New Measurement" action.
  - Measurement Book Register (`/measurement-books`): MB status filters, measurement method badges, project and work order filters.
  - Create Measurement Entry (`/measurement-books/create`): Live $L \times B \times D$ formula calculator, remaining BOQ limit helper, manual override toggle with mandatory reason.
  - Measurement Review & Detail (`/measurement-books/{id}`): Dimensional breakdown card, chainage/location specs, "Approve Measurement" modal, "Reject Measurement" modal with mandatory reason.
  - Subcontractor RA Bill Register (`/subcontractor-bills`): 4 KPI cards (Gross Certified, Retention Withheld, TDS Deducted, Net Payable), bill status tabs.
  - Create Subcontractor Bill (`/subcontractor-bills/create`): Work order selector, multi-select approved MB entries, live retention/TDS/GST deduction breakdown.
  - Bill Detail & 3-Way Reconciliation (`/subcontractor-bills/{id}`): Comprehensive 3-way reconciliation table (BOQ Limit vs Approved MB vs Current Bill), dedicated Financial Deductions & Net Payable Audit Reconciliation card, General Ledger journal voucher breakdown card, "Certify Bill" action, "Record Payment" modal with bank selector, and "Reverse Bill" modal with audit justification.
- [x] Automated Feature Tests (`tests/Feature/SubcontractorWorkOrderAndBillingTest.php` - 20 tests, 144 assertions):
  - 1. Authorized creation and approval of subcontractor work orders.
  - 2. Valid measurement calculations across supported units and measurement methods ($L \times B \times D$).
  - 3. Rejection of invalid quantities and mismatched project/site/work-order relationships.
  - 4. Prevention of certified quantities exceeding approved work order limits.
  - 5. Rejected measurement correction and resubmission with preserved history.
  - 6. Only approved measurements can be billed.
  - 7. Previously billed quantities cannot be billed twice.
  - 8. Partial billing preserves the correct remaining quantity.
  - 9. Approved variations support revised quantities only through authorized workflow.
  - 10. Correct work-order–MB–bill 3-way reconciliation.
  - 11. Contractual retention (5%) and configured tax/deduction calculations (194C TDS 1% or 2%, GST 18%).
  - 12. Approval and certification permission checks.
  - 13. Payment allocations, outstanding balances, and overpayment prevention.
  - 14. Balanced accounting journal entries and prevention of duplicate posting.
  - 15. Project budgets do not double-count commitments and certified costs.
  - 16. Cross-company tenant isolation for work orders, measurements, and bills.
  - 17. Concurrent billing attempts cannot claim the same measurement quantities.
  - 18. Controlled bill reversal posts counter-journal and restores MB entry availability.
  - 19. Exact audit verification of reported transaction: Gross ₹195,000, GST ₹35,100, Retention ₹9,750, TDS ₹1,950, Advance Recovery ₹50,000 => Net Payable ₹168,400, UI display, balanced GL posting (Dr 5040, Dr 1030 / Cr 2030, Cr 2040, Cr 1040, Cr 2020), partial payment, and overpayment rejection.
  - 20. Controlled bill reversal restores Account 1040 Advance Asset balance.
- [x] Real Browser E2E Audit with Puppeteer (`tests/Browser/e2e_subcontractor_billing_audit.js` - 6 screenshots captured and verified):
  - `subcon_01_work_order.png`: Work order detail with BOQ progress bars, variation audit trail, and active status.
  - `subcon_02_measurement_book.png`: Measurement Book register with MB entries, status badges, and project context.
  - `subcon_03_mb_detail.png`: MB entry detail with dimensions breakdown ($100 \times 12 \times 0.25 = 300\text{ cum}$), approval modal, and review status.
  - `subcon_04_bill_reconciliation.png`: Certified RA bill with 6-card KPI breakdown, detailed financial deduction audit card, 3-way reconciliation table, balanced GL voucher (including Cr 1040 Advance Recovery), and payment status.
  - `subcon_05_subcontractors_register.png`: Subcontractor register with party classification, TDS sections, and GSTIN/PAN.
  - `subcon_06_trial_balance.png`: Grand totals exactly balanced (Debit ₹4,200,094.00 == Credit ₹4,200,094.00, Diff: ₹0.00) including accounts `1010`, `1020`, `1025`, `1030`, `1040`, `1520`, `2010`, `2020`, `2030`, `2040`, `4010`, `5020`, `5030`, and `5040`.
- [x] Full automated test suite passing: **123 tests, 795 assertions, 0 errors, 100% passing**

---

### Phase 8: Posted Journal Immutability & Financial Correction Audit (Completed & Verified)
- [x] Non-Destructive Environment & Data Audit:
  - Verified `buildflow_crm` environment is `local` containing development fixtures from `DatabaseSeeder.php` and automated test runs.
  - Inspected Journal Entry #6 (`JV-SCB-2026-VJHLG`): Verified header and all 6 lines (Total Debits ₹685,344.00 == Total Credits ₹685,344.00, Diff: ₹0.00). Confirmed that the prior direct insertion of line 19 lacked an `AuditLog` trace.
  - Confirmed no live records were truncated, reset, or overwritten; preserved all financial records.
- [x] Model-Level Immutability Safeguards:
  - `JournalEntry::booted()`: Prohibits editing posted headers (`entry_date`, `total_amount`, `entry_number`, etc.), prohibits deleting posted or reversed entries, prohibits modifying reversed entries, and only permits transition from `posted` to `reversed` with audit trail (`reversed_by`, `reversed_at`, `reversal_reason`).
  - `JournalEntryLine::booted()`: Prohibits modifying or deleting lines belonging to posted or reversed entries.
  - Creation Lock Registry (`JournalEntry::$unlockedEntryIds`): Allows line creation strictly during initial process construction; blocks post-creation line injections on existing posted entries (`DomainException`).
- [x] Traceable Reversal & Adjustment Workflows:
  - `AccountingController::reverseJournal()`: Authorized endpoint (`POST /accounting/journals/{id}/reverse`) with server-side RBAC, mandatory justification (min 5 chars), atomic counter-journal generation (`JV-REV-...`), and permanent `AuditLog` entry.
  - Subsystem routing: Blocks generic reversal of subsystem-managed journals (`subcontractor_bills`, `loan_emi_schedules`, `asset_depreciation_entries`), enforcing operational reversal to prevent desynchronization of operational records.
  - `SubcontractorBillingService::reverseBill()`: Updated to mark original journal entry as `reversed` with `reversed_by`, `reversed_at`, and `reversal_reason`, and log counter-journal in `AuditLog`.
- [x] UI & View Enhancements:
  - `resources/views/accounting/journals.blade.php`: Added `reversed` status styling with red border, audit trail banner with timestamp/reverser/reason, and authorized "Reverse Voucher" modal dialog with mandatory justification.
- [x] Seed Data Integrity:
  - Updated `DatabaseSeeder.php` to generate the complete Subcontractor Work Order, Measurement Book, and RA Bill flow directly through `SubcontractorBillingService::certifyBill`, reproducing balanced vouchers cleanly without manual database editing.
- [x] Automated Feature Tests (`tests/Feature/DoubleEntryAccountingTest.php` - 14 tests, 59 assertions):
  - Model deletion prevention on posted journals.
  - Model modification prevention on posted journal headers.
  - Model modification and deletion prevention on posted lines.
  - Direct line insertion prevention on committed posted entries.
  - Authorized reversal with counter-entry, swapped debits/credits, and `AuditLog`.
  - Unauthorized user rejection (403 Forbidden).
  - Duplicate reversal rejection.
  - Mandatory minimum 5-character justification validation.
  - Subsystem journal reversal routing guards.
  - Full editability and deletion on draft journals prior to posting.
- [x] Real Browser E2E Audit with Puppeteer (`tests/Browser/e2e_journal_immutability_audit.js`):
  - `journal_01_vouchers_register.png`: Verified journal register with all 7 vouchers, balanced lines, and reverse controls.
  - `journal_02_trial_balance_reconciled.png`: Verified Trial Balance showing exact balance across 14 accounts (₹4,200,094.00).
- [x] Full test suite passing: **123 tests, 795 assertions, 0 errors, 100% passing**

---

### Phase 9: Secure Real-Time Notifications with Laravel Reverb & Echo (Completed & Verified)
- [x] Server-Side Broadcasting Infrastructure:
  - Installed `laravel/reverb` (v1.12.0) and configured `config/broadcasting.php`, `config/reverb.php`, and `.env` (`BROADCAST_CONNECTION=reverb`).
  - Added channels definition to `bootstrap/app.php` (`channels: __DIR__.'/../routes/channels.php'`).
  - Implemented channel authorization callbacks in `routes/channels.php` enforcing multi-tenant isolation, user identity matching, and role-based permissions for user, company, role, project, and site private channels.
- [x] Post-Commit Event Dispatching & Service Layer:
  - Created `App\Events\NotificationDispatched` implementing `ShouldBroadcastNow` with `$afterCommit = true`.
  - Created `App\Services\NotificationService` providing `sendToUser()`, `sendToCompany()`, `sendToRole()`, `markAsRead()`, and `markAllAsRead()`.
  - Added transaction-aware broadcasting with `DB::afterCommit(...)` wrapper and fail-safe try-catch handling ensuring standard HTTP requests remain fully functional even when Reverb is offline.
  - Implemented model-level post-commit dispatching via `AppNotification::booted()` hook.
- [x] Business Workflow Event Integration:
  - Material indent submitted / approved / rejected (`PurchaseRequestController`).
  - Purchase order issued / Goods receipt recorded (`ProcurementController`).
  - Material stock shortage alert when available <= reorder level (`InventoryController`).
  - Daily attendance muster roll submitted (`AttendanceController`).
  - Payroll batch submitted for review / approved (`PayrollController`).
  - Client RA invoice generated / client payment recorded (`BillingController`).
  - Subcontractor bill certified / payment recorded (`SubcontractorBillController`).
  - Client retention release claim submitted / approved / settled (`RetentionController`).
  - Project milestone status changes (`ProjectController`).
- [x] Notification Centre UI & Experience (Google Stitch `15768566540196779944`):
  - Top navigation notification bell with unread badge counter (`notification-badge-count`) and live pulse micro-animation.
  - Real-time connection indicator dot (green = connected, amber = connecting, red = offline).
  - Bell dropdown panel with live feed, mark single read, mark all read, and record routing.
  - Full Notification Centre (`/notifications`) with 4 KPI summary cards, filter tabs (All, Unread, Procurement, Muster & Payroll, Billing & Finance, Projects & Sites), and pagination.
  - Sidebar navigation entry with active unread counter badge.
- [x] Client Resilience & Reconnection:
  - Configured Laravel Echo with Pusher client in `resources/js/echo.js` and `resources/js/app.js`.
  - Added connection state tracking and automatic feed reconciliation upon WebSocket reconnection.
  - Graceful fallback: silent HTTP polling every 60s and window focus synchronization.
- [x] Automated Feature Tests (`tests/Feature/RealTimeNotificationTest.php` - 10 tests, 42 assertions):
  - Targeted user private channel authorization and event delivery.
  - Company tenant isolation and cross-company channel subscription rejection (403).
  - Inactive/revoked company user subscription denial.
  - Role, project, and site channel authorization gates.
  - Safe payload verification (strict prohibition of salaries, bank accounts, and rates).
  - Transaction commit timing (`$afterCommit = true`) and rollback isolation.
  - Read/unread state persistence and unread count reconciliation.
  - Cross-tenant notification read denial (global tenant scope 404 / 403).
  - Broadcaster downtime resilience.
- [x] Multi-Session Browser E2E Audit with Puppeteer (`tests/Browser/e2e_realtime_notifications_audit.js` - 4 screenshots verified):
  - `reverb_01_live_notification_received.png`: Session A (Project Manager) receives live WebSocket notification from Session B (Site Supervisor) without page reload; topbar badge updates 2 -> 3; bell pulses; dropdown displays event.
  - `reverb_02_notification_centre.png`: Full Notification Centre loaded with tabs, KPI cards, and unread highlight.
  - `reverb_03_tenant_isolation.png`: Mark All As Read decrements unread counter to 0; cross-company channel subscription strictly denied.
  - `reverb_04_fallback_state.png`: HTTP notification feed endpoint returns valid fallback JSON and reconciles state.
- [x] Full test suite passing: **133 tests, 837 assertions, 0 errors, 100% passing**

---

### Phase 10: Secure, System-Wide Document & File Upload Management (Completed & Verified)
- [x] Database Schema & Additive Migration:
  - Created migration `2026_10_18_000006_create_documents_table.php` (applied without database reset, truncation, or reseed).
  - Table `documents` features: `company_id`, polymorphic parent association (`documentable_type`, `documentable_id`), `category`, `original_name`, `storage_name`, `disk`, `path`, `mime_type`, `file_size`, `checksum` (SHA-256), `version`, `is_confidential`, `scan_status`, `uploaded_by`, `superseded_by_id`, soft deletes (`deleted_at`), and composite indexes `[company_id, documentable_type, documentable_id]` and `[company_id, category]`.
- [x] Document Models & Eloquent Traits:
  - Created `App\Models\Document` with `BelongsToCompany`, `SoftDeletes`, `scopeActive()`, `scopeVisibleTo($user)`, preview helpers (`isPdf()`, `isImage()`, `isPreviewable()`, `formatted_size`), and statutory immutability check (`isParentImmutable()`).
  - Created `App\Models\Traits\HasDocuments` providing polymorphic `documents()` and `activeDocuments()` relationships.
  - Attached `HasDocuments` trait to 17 core business models:
    - `Project`, `Site`, `DailySiteLog`
    - `PurchaseRequest`, `PurchaseOrder`, `GoodsReceipt`
    - `Employee`, `PayrollPeriod`
    - `ClientInvoice`, `ClientContract`, `RetentionReleaseClaim`
    - `Supplier`, `SubcontractorWorkOrder`, `SubcontractorBill`, `MeasurementBookEntry`
    - `FixedAsset`, `Loan`, `JournalEntry`
- [x] Security & Anti-Malware Scanner Architecture:
  - Designed scanner pipeline: `App\Services\Security\ScanResult`, `FileScannerInterface`, and `ConfigurableFileScanner`.
  - Configurable ClamAV daemon integration (`CLAMAV_HOST`, `CLAMAV_PORT`) with safe fallback to `unscanned` in development.
  - Storage security: Private disk storage (`storage/app/private`), non-sequential random UUID physical filenames, zero trust in user path input, safe filename sanitization via `preg_replace`.
  - Server-side `finfo_file` magic-byte inspection verifying actual binary headers against `ALLOWED_MIMES`.
  - Extension allowlist (`pdf`, `png`, `jpg`, `jpeg`, `dwg`, `dxf`, `xlsx`, `xls`, `csv`, `doc`, `docx`) with 15MB file size limit.
  - SHA-256 cryptographic hash calculated on ingest for tamper prevention.
- [x] Policy, Service Layer & Immutability Rules:
  - Created `App\Services\DocumentService` managing upload, metadata extraction, safe private disk storage, non-destructive replacement (`replaceDocument()`), authorized soft-delete (`deleteDocument()`), streaming preview (`streamPreview()`), and file download (`download()`) with defense-in-depth HTTP headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`).
  - Created `App\Policies\DocumentPolicy` registered in `AppServiceProvider`.
  - Enforces tenant isolation, parent record access rights, confidential document restrictions (`owner`, `admin`, `hr_officer`, `accountant`), and statutory immutability locks blocking deletion on posted journals, certified subcontractor bills, approved client invoices, and settled loans.
  - Audit logging via `AuditLog` for upload, replace, and delete events.
- [x] Central Document Centre & Reusable Component:
  - Built `<x-document-manager>` Blade/Alpine component with drag-and-drop file dropzone, category selector, file pill list, confidential toggle, inline preview modal, download, and delete confirmation modal.
  - Integrated `<x-document-manager>` into 16 core business record views:
    1. Projects (`projects/show.blade.php`) — Architectural drawings, specifications, EPC contracts
    2. Sites (`sites/show.blade.php`) — Safety permits, statutory licenses, inspection reports
    3. Purchase Requests (`purchase-requests/show.blade.php`) — Technical specs, supplier quotation comparison
    4. Procurement / POs & GRNs (`procurement/show.blade.php`) — Vendor purchase orders, delivery challans, inspection photos
    5. Client RA Billing (`billing/show.blade.php`) — Certified RA bill copies, joint measurement sheets, payment receipts
    6. Retention Claims (`retention/claims/show.blade.php`) — DLC handover certificates, bank guarantees, release claims
    7. Retention Contracts (`retention/contracts/show.blade.php`) — Master client agreements, defect liability terms
    8. Subcontractor Work Orders (`work-orders/show.blade.php`) — Signed work orders, itemized BOQ schedules
    9. Subcontractor Profiles (`subcontractors/show.blade.php`) — GST/PAN certificates, MSME registration, WC insurance
    10. Measurement Books (`measurement-books/show.blade.php`) — Field survey sheets, level calculation books, site photos
    11. Subcontractor Bills (`subcontractor-bills/show.blade.php`) — Subcontractor tax invoices, measurement sign-off sheets
    12. Fixed Assets (`assets/show.blade.php`) — Machine RC books, vehicle fitness, insurance policies, tax invoices
    13. Equipment Loans (`loans/show.blade.php`) — Loan sanction letters, hypothecation deeds, EMI schedules
    14. Employee Profiles (`employees/show.blade.php`) — Confidential Aadhaar cards, PAN cards, employment contracts
    15. Payroll Batches (`payroll/show.blade.php`) — Confidential bank NEFT batch files, PF/ESI challans
    16. Journal Vouchers (`accounting/journals.blade.php`) — Source debit/credit vouchers, bank counterfoils, vendor receipts
  - Built Central Document Centre (`/documents`) with 4 KPI summary cards, module filter, category filter, keyword search, confidential vault toggle, and inline streaming preview modal.
  - Added "Document Centre" link to master sidebar navigation.
- [x] Automated Feature Tests (`tests/Feature/DocumentManagementTest.php` - 11 tests, 64 assertions):
  - Authorized upload of valid file to authorized parent record.
  - Server-side MIME validation rejecting invalid or spoofed files.
  - File size cap enforcement (>15MB rejected).
  - Storage path and UUID isolation (zero trust in user path input).
  - Unauthenticated access rejection (redirect to login).
  - Cross-tenant upload and preview rejection (404/403 with zero metadata leakage).
  - Unauthorized user permission denial on protected records.
  - Confidential vault isolation (hides confidential documents from site supervisors).
  - Non-destructive document replacement with version history increment.
  - Statutory immutability lock preventing deletion of documents on posted journal entries.
  - Central Document Centre search, category filter, and tenant scoping.
- [x] Real Browser E2E Audit with Puppeteer (`tests/Browser/e2e_document_management_audit.js` - 5 screenshots verified):
  - `doc_01_project_detail_attached.png`: Project detail view showing uploaded structural drawing with preview and delete controls.
  - `doc_02_preview_modal.png`: Real-time streaming PDF preview modal rendering inline with security headers.
  - `doc_03_po_delivery_challan.png`: Purchase Order detail view with attached vendor delivery challan and category badge.
  - `doc_04_central_document_centre.png`: Central Document Centre repository showing 4 KPI cards, filters, and records table.
  - `doc_05_document_centre_mobile.png`: Mobile viewport (375x812) responsive layout verifying responsive table and action buttons.
- [x] Full test suite passing: **144 tests, 901 assertions, 0 errors, 100% passing**

---

### Phase 11: Production Readiness, Security Hardening & Go-Live Audit (Completed & Verified)
- [x] Baseline Establishment & Read-Only Audit:
  - PHP 8.5.11 (cli), Composer 2.10.3, Laravel Framework 13.37.0, MySQL 26.7.0.
  - Verified 16/16 migrations applied with zero pending.
  - Zero vulnerability advisories across both Composer (`composer audit`) and NPM (`npm audit`).
- [x] Database Integrity & Disaster Recovery Verification:
  - Reconciled Trial Balance across all posted journal entries: Debits ₹3,494,664.07 == Credits ₹3,494,664.07 (Diff: ₹0.00).
  - Verified zero unbalanced posted journals (0/7), zero orphaned users, zero orphaned projects, zero orphaned documents.
  - Built repeatable Artisan command: `php artisan buildflow:verify-recovery`.
  - Executed automated disaster recovery test against isolated disposable database (`buildflow_restore_test`):
    - Table count exact match (72 source == 72 restored).
    - Row count exact 100% parity across all 72 tables.
    - Migration count exact match (16 migrations).
    - Financial balance exact parity: Dr ₹3,494,664.07 == Cr ₹3,494,664.07 (Diff: ₹0.00).
    - Document metadata and SHA-256 binary checksums verified down to the byte on all physical files.
    - Automated feature test created: `tests/Feature/DisasterRecoveryRestoreTest.php`.
- [x] Application-Wide Security Hardening:
  - **Storage Hardening**: Disabled Laravel's unauthenticated `serve => false` route on the private `local` disk (`storage/app/private`), eliminating any accidental bypass vector.
  - **Scanner Fail-Closed Mode**: Enforced strict fail-closed security in `ConfigurableFileScanner.php` and `DocumentService.php`. When fail-closed is active, unverified or errored files are blocked at upload and streaming/download access is rejected (HTTP 423 Locked). Quarantined status added to `ScanResult.php`.
  - **Active Membership Enforcement**: Updated `User::currentCompany()`, `WorkspaceController::switch()`, and `workspace.select` to enforce `wherePivot('status', 'active')`, immediately denying workspace access to suspended or revoked accounts.
  - **Multi-Tenant Global Search Fix**: Resolved SQL operator precedence vulnerability in `GlobalSearchController.php` by wrapping `orWhere` clauses inside parameter-grouped closures `where(function ($query) { ... })`, and added role permission gates hiding employees and billing invoices from unauthorized users.
  - **Confidential Compensation Masking**: Added `authorizeEmployeeView()` in `EmployeeController.php` and masked daily wage and monthly salary from non-HR/finance roles in `employees/show.blade.php`.
  - **Honest Dashboard Calculation**: Replaced hardcoded fallback amounts (`4520000.00` and `2596000.00`) in `DashboardController.php` with honest database-backed `0.00` sums.
  - **Rate Limiting (Throttle)**: Added rate limiters on login (`throttle:5,1`), password reset (`throttle:3,1`), document uploads/replace (`throttle:30,1`), document preview/download (`throttle:60,1`), and health checks (`throttle:60,1`).
  - **Trusted Proxies**: Configured `$middleware->trustProxies(at: '*')` in `bootstrap/app.php` for Nginx reverse proxy / Cloudflare compatibility.
  - **Custom Error Pages**: Created Stitch-aligned branded error views: `errors/403.blade.php`, `errors/404.blade.php`, `errors/500.blade.php`, `errors/503.blade.php`.
  - **Production Health Monitoring**: Built `/health` JSON endpoint in `HealthCheckController.php` monitoring database latency, storage disk free space, cache, Reverb WebSocket, and ClamAV status without leaking credentials.
- [x] Automated Feature Tests (`tests/Feature/ProductionSecurityHardeningTest.php` + `DisasterRecoveryRestoreTest.php`):
  - Health check endpoint JSON response and secrets masking.
  - Revoked/suspended company user access denial.
  - Global search multi-tenant operator precedence protection.
  - Employee compensation masking from unauthorized roles.
  - Scanner fail-closed mode and quarantined file access rejection.
  - Full suite: **151 tests, 943 assertions, 0 errors, 100% passing**.
- [x] Full-Workflow Browser E2E Audit (`tests/Browser/e2e_production_readiness_audit.js` - 10 screenshots verified):
  - `prod_01_auth_role_dashboard.png`: Super Admin / Owner dashboard navigation with live INR metrics.
  - `prod_02_tenant_isolation_denied.png`: Cross-company IDOR access test returning 404 Not Found without metadata leakage.
  - `prod_03_pr_po_attached_flow.png`: Procurement Purchase Order with attached supplier challan and delivery evidence.
  - `prod_04_workforce_payroll_security.png`: Site supervisor muster roll vs confidential payroll gate (HTTP 403).
  - `prod_05_billing_retention_subcontractor.png`: Client billing & retention release register with tax breakdown.
  - `prod_06_journal_immutability_trial_balance.png`: Reconciled Trial Balance showing exact Dr == Cr balance (₹3,494,664.07).
  - `prod_07_document_security_preview.png`: Document Centre inline streaming preview modal with security headers.
  - `prod_08_reverb_live_notifications.png`: Notification Centre with live WebSocket connection indicator.
  - `prod_09_health_check_operational.png`: Operational `/health` JSON monitor verifying infrastructure health.
  - `prod_10_mobile_responsive_views.png`: Mobile viewport (375x812) responsive view of dashboard and critical actions.

---

### Phase 12: Go-Live Gate Closure & Production Handover (Completed & Verified)
- [x] Production Fail-Closed Scanner Hardening:
  - Hardened `config/filesystems.php` and `ConfigurableFileScanner.php`: `fail_closed` forced `true` and `allow_unscanned_dev` forced `false` under `APP_ENV=production`.
  - Added `Document::isCleanForAccess()` and gated `DocumentService::streamPreview()` & `download()` (HTTP 423 Locked on unscanned/flagged files).
  - Updated `HealthCheckController.php` with dynamic scanner status reflection.
- [x] Production Demo Credential Suppression:
  - Hardened `resources/views/auth/login.blade.php` to restrict demo pre-fill and credentials card strictly to `local` / `testing` environments.
- [x] Production Reverse Proxy Trust Restriction:
  - Hardened `bootstrap/app.php` to govern trusted proxies via `TRUSTED_PROXIES` environment variable rather than indiscriminate wildcard (`*`).
- [x] Automated Feature Tests (`tests/Feature/GoLiveGateClosureTest.php`):
  - 6 tests, 32 assertions, 0 errors. Full suite: **157 passed, 975 assertions**.

---

### Phase 13: Release Engineering, Automated Backups & Production Acceptance (Completed & Verified)
- [x] Runtime & Framework Reconciliation:
  - Reconciled documentation against actual runtime: PHP 8.5.11 (cli), Composer 2.10.3, Laravel 13.37.0, MySQL 26.7.0.
  - Eliminated stale "Laravel 11/12" and "PHP 8.3" references across all documentation.
- [x] Durable Automated Backup Engine:
  - Implemented `config/backup.php` with configurable destination (`storage/app/backups`), AES-256-CBC encryption (`BACKUP_ENCRYPT`), retention tiers (24 hourly, 30 daily, 12 monthly), and RPO threshold (120m).
  - Developed `BackupManager.php`: Atomic database dump (`.tmp` to `.sql`), private document tarball (`storage.tar.gz`), OpenSSL AES-256-CBC envelope encryption, SHA-256 binary checksums, and atomic `manifest.json`.
  - Built Artisan commands:
    - `php artisan buildflow:backup:create {--frequency=hourly|daily|monthly} {--encrypt}`
    - `php artisan buildflow:backup:validate {backup_id?}`
    - `php artisan buildflow:verify-recovery {--backup=latest}`
  - Implemented overlapping job prevention via atomic cache lock (`buildflow_backup_running_lock`).
  - Implemented verified recovery copy retention protection (`is_protected = true`).
- [x] RPO (1 Hour) & RTO (15 Min) SLA Enforcement & Scheduling:
  - Configured `routes/console.php`: Hourly backup creation (`hourly()`), daily backup validation (`dailyAt('02:00')`), and weekly isolated restore test (`weeklyOn(0, '03:00')`).
  - Added backup freshness and RPO status monitoring to `HealthCheckController.php` (`durable_backups`).
- [x] Isolated Restore & Safe Disaster Recovery Verification:
  - Verified restore from durable backups into disposable schema `buildflow_restore_test` and temporary storage.
  - Verified 100% table parity (72 tables), row count parity, 16 migrations, balanced Trial Balance (Dr == Cr ₹3,494,664.07), and SHA-256 binary hash digest matches for all document attachments.
  - Zero destructive operations on the live application database.
- [x] Release Preflight & Go-Live Gate (`php artisan buildflow:preflight {--strict}`):
  - Audits 13 critical operational domains: Environment/Debug, APP_KEY, DB & Migrations (0 pending), General Ledger Trial Balance invariant (balanced), Storage privacy (`serve => false`), ClamAV fail-closed reachability, HTTPS & secure cookies, Trusted Proxies, Reverb daemon & origins, Durable Backups (age, validation, restore test), Frontend assets (`manifest.json`), Demo accounts, and Host Infrastructure.
  - Supports dual modes: advisory local development (exit 0) and strict production (exit 1 on missing production infra).
- [x] Automated Feature Tests (`tests/Feature/AutomatedBackupAndReleaseAcceptanceTest.php`):
  - 7 comprehensive tests verifying creation, tampering detection, AES-256 encryption, retention protection of verified recovery copy, overlap locking, health check reporting, and dual-mode preflight audit.
  - Full PHPUnit Suite: **164 tests, 1007 assertions, 0 errors, 100% passing**.
  - Frontend production build (`npm run build`): Vite v8.3.4 (286ms, 110.97 kB CSS / 352.54 kB JS).

---

### Phase 14: Backup Cryptography Audit, Recovery SLA Enforcement & Final Release Safety (Completed & Verified)
- [x] Cryptographic Authenticated Encryption (AEAD AES-256-GCM):
  - Replaced unauthenticated AES-256-CBC with standard OpenSSL AEAD AES-256-GCM (`cipher => AES-256-GCM`, `format_version => 2`).
  - Binary envelope (`BFENC\x02\x01\x0C\x10`): 12-byte IV + 16-byte Poly1305/GMAC tag + ciphertext.
  - Associated Authenticated Data (AAD): Context-bound to `BuildFlow-AEAD-v2|{backup_id}|{filename}` to prevent ciphertext-swap attacks between files or generations.
  - Cryptographically detects ciphertext modification, altered IVs, truncated files, mismatched tags, and wrong keys, raising fatal `RuntimeException` without leaking secrets.
  - Transparent non-destructive backward compatibility for Version 1 (AES-256-CBC) backups with deprecation logging.
  - Key separation: `BACKUP_ENCRYPTION_KEY` distinct from `APP_KEY`; non-sensitive SHA-256 key fingerprints in `manifest.json`.
- [x] Process Table Credential Concealment:
  - Rewrote database dump and restore execution in `BackupManager` and `VerifyDisasterRecoveryCommand` to use `proc_open` with environment-isolated `MYSQL_PWD`.
  - Zero database passwords appear in `ps aux` command-line process arguments.
- [x] RPO Policy & Critical SLA Breach Enforcement:
  - Reconciled discrepancy between 60m RPO and 120m threshold: Defined 60m target RPO (`rpo_minutes`), 15m execution grace (`execution_grace_minutes`), and 75m critical SLA breach (`critical_breach_minutes`).
  - Enforced 75m critical threshold across `/health` (HTTP 503) and `buildflow:preflight --strict` (Exit 1).
- [x] Secondary Off-Host Replication Pipeline:
  - Designed `BackupReplicationInterface` and implemented `LocalSecondaryReplicationProvider`.
  - Created `php artisan buildflow:backup:replicate` with 3x retry backoff, verifying remote SHA-256 checksums and transfer parity against primary files before emitting `replication_receipt.json`.
  - Scheduled hourly replication at `:15` past the hour in `routes/console.php`.
- [x] Bounded Protected-Copy Lifecycle Policy:
  - Resolved unbounded storage growth by implementing `max_protected_restore_copies = 2`.
  - Older restore-tested copies roll over to `is_protected = false`, enabling tier retention pruning.
  - Guaranteed retention invariants: The single newest valid backup overall and the single newest restore-tested baseline are never purged.
- [x] Disaster Recovery Safety Hardening:
  - Hardened `VerifyDisasterRecoveryCommand` with `assertSafeRestoreTarget` strictly refusing to target active databases (`buildflow_crm`, `production`, `live`, or `DB_DATABASE`).
- [x] Preflight Gate Expansion:
  - Expanded `buildflow:preflight` from 13 to **16 operational domains**, adding backup AEAD encryption verification, restore baseline verification, and secondary replication checks.
- [x] Automated Feature Tests (`tests/Feature/BackupCryptographyAndRecoverySlaTest.php`):
  - 11 comprehensive tests: AEAD encryption/decryption round-trip, tampered ciphertext rejection, tampered tag rejection, wrong key rejection, truncated file rejection, legacy CBC backward compatibility, RPO 60m/75m thresholds, secondary replication, bounded retention rollover, destructive restore prevention, and `/health` reporting.
  - Full PHPUnit Suite: **175 tests, 1081 assertions, 0 errors, 100% passing** (14.4s).
  - Frontend production build (`npm run build`): Vite v8.3.4 (242ms, 110.97 kB CSS / 352.54 kB JS).

---

### Phase 15: Critical Security Remediation, Backup Enforcement & CI Verification (Completed & Verified)
- [x] Priority 1 — Password-Reset Broker Security:
  - Integrated Laravel Password Broker (`Password::broker()->reset()`), ensuring token hashing, single-use token consumption, and 60-minute expiration.
  - Implemented missing `resources/views/auth/reset-password.blade.php` with Stitch tokens.
  - Enforced rate limiting (`throttle:3,1` on email dispatch, `throttle:5,1` on reset update).
  - Preserved uniform generic responses preventing account existence enumeration.
  - Recorded security `AuditLog` events without logging tokens or passwords.
  - Added `tests/Feature/PasswordResetSecurityTest.php` (8 tests, 32 assertions) verifying valid, invalid, expired, reused, and wrong-email tokens.
- [x] Priority 2 — Deny-by-Default Document Authorization:
  - Denied access to orphaned documents (`documentable === null`) by default across `view`, `download`, `replace`, and `delete`.
  - Removed permissive fallbacks; implemented explicit authorization rules for all 18 `MODEL_MAP` types (`Project`, `Site`, `DailySiteLog`, `PurchaseRequest`, `PurchaseOrder`, `GoodsReceipt`, `Employee`, `PayrollPeriod`, `ClientInvoice`, `ClientContract`, `RetentionReleaseClaim`, `Supplier`, `SubcontractorWorkOrder`, `MeasurementBookEntry`, `SubcontractorBill`, `FixedAsset`, `Loan`, `JournalEntry`).
  - Scoped Document Centre queries (`scopeVisibleTo()`) to exclude documents whose parent models the user cannot access, preventing metadata, filename, or KPI count leakage.
  - Maintained immutability locks on posted journals, certified subcontractor bills, and settled loans.
  - Added `tests/Feature/DocumentAuthorizationDenyByDefaultTest.php` (7 tests, 82 assertions) verifying cross-tenant isolation, role checks, and deny-by-default behavior.
- [x] Priority 3 — Secret Sanitization & Demo Account Hygiene:
  - Removed literal `REVERB_APP_SECRET` and demo password table from `README.md`.
  - Audited tracked files for credentials and documented Reverb rotation requirements.
  - Added fail-closed guard to `DatabaseSeeder.php` throwing `\RuntimeException` in production.
  - Enumerated all 8 demo accounts in `buildflow:preflight`. In strict mode, preflight fails and reports accounts for operator deactivation without destructive auto-deletion.
- [x] Priority 4 — Encrypted Production Backups & Strict AEAD Context Binding:
  - Enforced dedicated `BACKUP_ENCRYPTION_KEY` in production; removed silent fallback to `APP_KEY`.
  - Enforced exact AAD context binding (`BuildFlow-AEAD-v2|{backup_id}|{filename}`) with zero empty-AAD fallbacks.
  - Validated envelope header magic (`BFENC`), version (2), cipher (1), IV length (12), and tag length (16) with `random_bytes(12)` nonces.
  - Implemented atomic `.tmp` writes preventing partial unauthenticated file recovery.
  - Preserved legacy AES-256-CBC decryption compatibility.
  - Documented single-pass memory limitations (512MB default limit).
- [x] Priority 5 — Operational Readiness Reporting:
  - Enforced unified 60m RPO target and 75m critical breach policy across config, `/health`, and preflight.
  - Health check reports HTTP 503 in production if backup is unencrypted, key is missing, or RPO breached.
  - Demarcated `LocalSecondaryReplicationProvider` as same-host (`is_off_host = false`, `off_host_status = unverified_same_host`). In strict mode, preflight fails if off-host remote boundary is unverified.
  - Expanded preflight to **18 operational domains**.
- [x] Priority 6 — CI Workflow & Full Regression Verification:
  - Created `.github/workflows/ci.yml` running PHP 8.5, MySQL 8, Node 20, migrations, PHPUnit, and preflight audit.
  - Full PHPUnit test suite: **204 passed, 1,226 assertions, 0 errors, 100% passing** (15.5s).
  - Frontend production build (`npm run build`): Vite v8.3.4 (237ms, 111.00 kB CSS / 352.54 kB JS).



