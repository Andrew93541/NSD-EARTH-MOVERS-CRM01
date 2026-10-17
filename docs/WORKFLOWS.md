# BUILDFLOW — KEY BUSINESS WORKFLOWS & STATE MACHINES

## 1. Procurement & Payables Workflow (Three-Way Matching)

```mermaid
stateDiagram-v2
    [*] --> SiteMaterialRequest
    SiteMaterialRequest --> PurchaseOrderCreated: Approved by Project Manager
    PurchaseOrderCreated --> GoodsReceived: Site Store/Warehouse Inspection
    GoodsReceived --> SupplierInvoiceRecorded: Vendor Bill Received
    SupplierInvoiceRecorded --> PaymentApproved: 3-Way Match Verified (PO == GRN == Bill)
    PaymentApproved --> PaymentDisbursed: Finance Processing
    PaymentDisbursed --> [*]
```

### Steps & Invariants
1. **Material Request**: A site engineer submits an indent specifying materials, quantity, target site, and need date.
2. **Purchase Order**: Procurement generates a PO against an authorized supplier. PO status moves: `Draft -> Submitted -> Approved -> Sent`.
3. **Goods Receipt Note (GRN)**: Site stores check delivered goods, noting accepted vs rejected quantities. Accepted quantities immediately credit the stock ledger.
4. **Three-Way Matching**: The system validates that the billed unit prices and quantities do not exceed PO rates and GRN received quantities without authorized variances.
5. **Payment Settlement**: Accounts team releases partial or full payment via recorded bank/cheque transaction.

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
