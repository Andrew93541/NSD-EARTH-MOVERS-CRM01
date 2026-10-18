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
- [x] Real Browser E2E Audit with Puppeteer (`tests/Browser/e2e_retention_audit.js` - All 6 screenshots captured and verified):
  - Complete lifecycle: Draft -> Review -> Approved -> Billed to Client -> Received -> Settled
- [x] Full automated test suite passing: **77 tests, 499 assertions, 0 errors, 100% passing**

