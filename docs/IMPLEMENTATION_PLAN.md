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
- [x] Schema: `suppliers`, `purchase_requests`, `quotations`, `purchase_orders`, `goods_receipts`, `supplier_invoices`
- [x] Double-entry stock movement ledger with atomic transactions and negative stock prevention
- [x] Three-way matching: PO -> Goods Receipt -> Supplier Invoice
- [x] Tests for atomic stock transfers and procurement approval workflow (`InventoryTransferTest.php`)

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

### [x] Phase 8 — Integration, QA & Production Hardening
- [x] Global search across projects, materials, invoices, and workforce (`GlobalSearchController.php`)
- [x] Role-based permission enforcement audit across all routes and controllers
- [x] End-to-end automated test suite execution (27 tests, 151 assertions, 0 errors)
- [x] Verify UI visual fidelity against Stitch master design
- [x] Complete setup guide in `README.md` and verify `SmokeTest.php` across all 35 main routes
