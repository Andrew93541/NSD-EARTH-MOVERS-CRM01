# BUILDFLOW — KEY BUSINESS WORKFLOWS & STATE MACHINES

## 1. Purchase Request (Material Indent), PM Approval & Procurement Conversion

```mermaid
stateDiagram-v2
    [*] --> Draft: Site Supervisor creates request
    Draft --> PendingApproval: Submitted by Requester (submitted_at logged)
    PendingApproval --> Approved: Authorized Project Manager / Admin Approval
    PendingApproval --> Rejected: Project Manager Rejection (Mandatory Reason)
    Rejected --> Draft: Requester edits & revises
    Approved --> PartiallyConverted: Partial Purchase Order issued
    PartiallyConverted --> POCreated: Remaining quantities fully ordered
    Approved --> POCreated: Direct full Purchase Order issued
    POCreated --> GoodsReceived: Site Store/Warehouse Inspection (GRN)
    GoodsReceived --> SupplierInvoiceRecorded: Vendor Commercial Invoice Logged
    SupplierInvoiceRecorded --> PaymentApproved: 3-Way Match Verified (PO == GRN == Bill)
    PaymentApproved --> PaymentDisbursed: Finance Bank Settlement
    PaymentDisbursed --> [*]
```

### Steps & Invariants
1. **Material Indent Creation (Site Supervisor)**:
   - Site Supervisor generates a human-readable request reference (`PR-YYYY-XXXXX`).
   - Validates that site belongs to the selected project and user is authorized to manage the site.
   - Itemized lines contain material, positive requested quantity, unit of measurement, specifications, and estimated rate.
   - Shows live contextual yard stock for the selected delivery destination without modifying inventory balances.
2. **Submission & Notifications**:
   - Requisition status transitions `Draft -> Pending Approval` with recorded `submitted_at` timestamp.
   - Automatically notifies the assigned Project Manager via `AppNotification`.
   - Immutability: Once submitted, indents cannot be directly edited without PM review or formal withdrawal.
3. **Project Manager Review & Segregation of Duties**:
   - Authorized PM or company admin reviews line items, need date, and justification.
   - **Segregation of Duties**: A user cannot approve their own purchase request unless holding company owner/admin privileges.
   - **Rejection**: Requires a mandatory, recorded reason (min 5 characters) for audit trail compliance. Notifies requester and unlocks request for revision and resubmission.
   - **Approval**: Sets status to `approved`, stamps `approved_by` and `approved_at`, and notifies the requester.
4. **Procurement Conversion to Purchase Order**:
   - Visible and actionable only by authorized procurement users.
   - Enables selecting an approved supplier, partial or full quantities, unit prices, and GST rates (CGST/SGST/IGST).
   - Atomic Database Transaction: Generates `PurchaseOrder` and `PurchaseOrderItem` with foreign key links back to `purchase_requests` and `purchase_request_items`.
   - Concurrency & Quantity Protection: Line-by-line verification ensures `quantity_to_order <= remaining_quantity`. Increments `quantity_ordered` on the PR item.
   - Status updates atomically: transitions to `partially_converted` if requested items remain, or `po_created` once all items are ordered.
5. **Downstream Integration (GRN & 3-Way Match)**:
   - Converted PO feeds directly into Goods Receipt (GRN) inspection where accepted quantities credit the stock ledger.
   - Supplier commercial invoices match against the PO and GRN for financial accounts settlement.

---

## 2. Inventory & Multi-Site Stock Movement Workflow

```mermaid
stateDiagram-v2
    [*] --> TransferDraft
    TransferDraft --> Dispatched: Approved & Dispatched from Central Yard
    Dispatched --> InTransit: Vehicle / Consignment Details Logged
    InTransit --> ReceivedAtSite: Site Store Manager Inspects & Accepts
    ReceivedAtSite --> [*]: Stock Added to Site Store
```

### Invariants
- **Atomic Operations**: A transfer decreases source warehouse stock and increases destination warehouse stock within a single database transaction.
- **Strict Ledger**: No direct modification of `current_stock` fields without a corresponding `stock_ledger_entries` record.
- **Negative Stock Protection**: Dispatches exceeding available unallocated quantity are rejected unless company override is explicitly configured.

---

## 3. Attendance, Overtime & Payroll Generation

```mermaid
stateDiagram-v2
    [*] --> BiometricPunchesImported
    BiometricPunchesImported --> DailyMusterSynthesized: Roster & Shift Hours Applied
    DailyMusterSynthesized --> AttendanceCorrectionsApproved: Supervisor Justification
    AttendanceCorrectionsApproved --> MonthlyPayrollBatchPrepared: Period Cutoff
    MonthlyPayrollBatchPrepared --> PayrollApproved: HR & Finance Review
    PayrollApproved --> BankDisbursementFileGenerated: Disbursed
    BankDisbursementFileGenerated --> [*]
```

### Invariants
- Raw punches are never deleted or updated.
- Overtime must be verified and approved by the site supervisor before contributing to wage calculations.
- Finalized payroll records are locked against retrospective modification; discrepancies are addressed via retroactive adjustment vouchers in subsequent pay periods.

---

## 4. Client Progress Billing (RA Bills) & Retention

```mermaid
stateDiagram-v2
    [*] --> MeasurementBookPrepared
    MeasurementBookPrepared --> RABillDrafted: Joint Measurement with Client
    RABillDrafted --> InvoiceSubmitted: Client Certification
    InvoiceSubmitted --> PaymentReceived: Client Remittance
    PaymentReceived --> RetentionRelease: Defect Liability Period Matured
    RetentionRelease --> [*]
```

### Calculations
- `Subtotal` = Cumulative Work Done - Previous RA Bills
- `GST` = Applicable CGST + SGST or IGST (e.g. 18%)
- `Retention Amount` = Subtotal * Retention Rate (e.g. 5%)
- `Net Payable` = Subtotal + GST - Retention - TDS Deductions
