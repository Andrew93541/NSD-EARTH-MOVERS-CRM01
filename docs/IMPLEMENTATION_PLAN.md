# BUILDFLOW — IMPLEMENTATION PLAN & ARCHITECTURE ROADMAP

## Overview
**BuildFlow** is a comprehensive, production-grade Construction Company CRM and ERP engineered specifically for civil infrastructure, interior fit-out contractors, heavy earthmoving operations, and multi-site maintenance contracts.

This document outlines the phased implementation plan, tech stack alignment, architectural boundaries, security policies, and progress tracking.

---

## Technology Stack Alignment
- **Runtime & Language**: PHP 8.3+ with Laravel 11/12
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


