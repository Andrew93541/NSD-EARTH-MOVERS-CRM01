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
- [/] Prepare environment: PHP 8.3+, Composer 2.x, MySQL 8+, Node.js
- [x] Setup comprehensive project documentation (`ARCHITECTURE.md`, `DATABASE.md`, `WORKFLOWS.md`, `SECURITY.md`, `IMPLEMENTATION_PLAN.md`)

### [ ] Phase 1 — Application Foundation & Auth
- [ ] Initialize Laravel project structure in workspace
- [ ] Configure `.env` and MySQL database connection
- [ ] Build multi-tenant schema: `companies`, `users`, `roles`, `permissions`, `company_user`
- [ ] Implement Auth module: Login, Forgot Password, Reset Password, Workspace Selection
- [ ] Build Master App Shell (sidebar, top navbar, notifications, tenant switcher)
- [ ] Implement BuildFlow Dashboard matching Stitch design with database-backed metrics
- [ ] Create Database Seeders for development & demo records
- [ ] Write automated tests for Auth, Tenant Isolation, and Session Handling

### [ ] Phase 2 — Projects & Site Operations
- [ ] Schema: `projects`, `project_milestones`, `tasks`, `work_packages`, `sites`, `daily_site_logs`, `site_inspections`
- [ ] Projects CRUD, detail view, timeline tracking, and budget utilization
- [ ] Site Operations monitor: daily logs, worker muster summary, photos & progress reports
- [ ] Tests for Project status transitions and budget tracking

### [ ] Phase 3 — Workforce & Attendance
- [ ] Schema: `employees`, `shifts`, `shift_rosters`, `attendance_punches`, `daily_attendance`, `leave_requests`
- [ ] Raw punch logging (immutable audit) vs processed daily attendance
- [ ] Muster roll bulk entry and CSV biometric import adapter
- [ ] Overtime approval workflow and project cost allocation
- [ ] Tests for attendance calculation and duplicate punch handling

### [ ] Phase 4 — Materials, Warehouses & Procurement
- [ ] Schema: `material_categories`, `materials`, `warehouses`, `stock_ledger_entries`, `stock_transfers`
- [ ] Schema: `suppliers`, `purchase_requests`, `quotations`, `purchase_orders`, `goods_receipts`, `supplier_invoices`
- [ ] Double-entry stock movement ledger with atomic transactions and negative stock prevention
- [ ] Three-way matching: PO -> Goods Receipt -> Supplier Invoice
- [ ] Tests for atomic stock transfers and procurement approval workflow

### [ ] Phase 5 — Payroll & Client Billing
- [ ] Schema: `payroll_periods`, `salary_structures`, `payroll_records`, `payslips`
- [ ] Schema: `clients`, `client_contracts`, `billing_milestones`, `client_invoices`, `invoice_payments`, `retentions`
- [ ] Deterministic payroll calculation linked to approved attendance records
- [ ] Client Running Account (RA) progress billing with retention and tax handling
- [ ] Tests for payroll computation and receivables aging

### [ ] Phase 6 — Expenses, Payments & Loan EMI
- [ ] Schema: `expense_categories`, `expenses`, `payments`, `loans`, `loan_emi_schedules`
- [ ] Multi-level expense submission, review, and reimbursement workflow
- [ ] Loan & equipment finance management with amortized EMI schedule calculation
- [ ] Tests for EMI schedule generation and payment reconciliation

### [ ] Phase 7 — Double-Entry Accounting & Financial Reports
- [ ] Schema: `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `bank_accounts`, `bank_statements`
- [ ] Strict balanced debit/credit validation and immutable journal postings
- [ ] Financial statements: Trial Balance, General Ledger, Profit & Loss, Balance Sheet, Project Profitability
- [ ] Indian GST reporting (CGST, SGST, IGST) configuration
- [ ] Tests for journal entry balance and financial report generation

### [ ] Phase 8 — Integration, QA & Production Hardening
- [ ] Global search across projects, materials, invoices, and workforce
- [ ] Role-based permission enforcement audit across all routes and controllers
- [ ] End-to-end automated test suite execution
- [ ] Verify UI visual fidelity against Stitch master design
- [ ] Complete setup guide in `README.md`
