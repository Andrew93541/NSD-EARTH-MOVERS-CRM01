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
- **Test Suite Status**: 27 PHPUnit tests, 151 assertions, 0 errors, 100% passing

---

## 2. Six-Phase Roadmap Status Audit

| Phase | Module Scope | Status | Notes & Verification |
|---|---|:---:|---|
| **Phase 1** | Foundation & Core CRM (Workspaces, Auth, Roles, Clients, Projects, Dashboard, Audit) | **Verified Working** | Multi-tenant scoping via `BelongsToCompany`, Auth throttling, Stitch-matched Dashboard with live DB queries. All 35 main routes return HTTP 200. |
| **Phase 2** | Site Operations & Attendance (Sites, Daily Logs, Worker Count, Muster Roll, Biometric Punch Audit) | **Verified Working** | Multi-site logs with worker counts, daily muster roll with radio status toggles and overtime approval, raw punch log traceability (`attendance_punches`). |
| **Phase 3** | Workforce & Payroll (Salaries, Wages, Overtime, Payroll Batch Generation, Approvals, Payslips) | **Partially Implemented** | `employees`, `shifts`, `payroll_periods`, `payroll_records` tables exist. Attendance calculation engine, payroll batch generation, approval workflow, and payslip views need implementation. |
| **Phase 4** | Suppliers, Purchasing & Inventory (Suppliers, POs, GRN, Invoices, Warehouses, Stock Ledger) | **Partially Implemented** | Stock ledger with atomic transfers and PO-invoice matching are implemented. Dedicated UI for Purchase Requests and Goods Receipt Notes (GRN) to be added. |
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

## 5. Immediate Action Plan

In accordance with Section 10 ("select the earliest incomplete or failing item in the six-phase roadmap"):
- **Target**: **Phase 3 — Workforce and Payroll**
- **Action**:
  1. Create `PayrollPeriod` and `PayrollRecord` Eloquent models with company and employee relationships.
  2. Implement `PayrollController` with:
     - Index view: List payroll periods with status indicators and totals.
     - Create/Generate action: Deterministically calculate basic pay, days worked, overtime hours, and overtime pay derived directly from `daily_attendance`.
     - Detail view: Itemized employee payroll breakdown with payslip inspection modal.
     - Approval action: Workflow transition from `draft` to `approved` to `disbursed`.
  3. Create Blade templates matching Stitch tokens: `resources/views/payroll/index.blade.php`, `resources/views/payroll/show.blade.php`, `resources/views/payroll/create.blade.php`.
  4. Register routes under `/payroll` and link in the main navigation under `PEOPLE`.
  5. Write automated tests (`PayrollCalculationAndApprovalTest.php`) verifying deterministic calculation from attendance and state machine enforcement.
