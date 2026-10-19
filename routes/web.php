<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MeasurementBookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProcurementApprovalPolicyController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\SiteOperationController;
use App\Http\Controllers\SubcontractorBillController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\SubcontractorController;
use App\Http\Controllers\SubcontractorWorkOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\MaterialIssueController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ConstructionWorkCategoryController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\Webhook\GitHubDeployWebhookController;
use Illuminate\Support\Facades\Route;

// Production Health Check (Infrastructure & Monitoring)
Route::get('/health', [HealthCheckController::class, 'check'])->name('health')->middleware('throttle:60,1');

// Automated GitHub Deployment Webhook (Authenticated via HMAC SHA-256)
Route::post('/api/webhooks/github-deploy', [GitHubDeployWebhookController::class, 'handle'])
    ->name('webhooks.github.deploy')
    ->middleware('throttle:30,1');

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:3,1');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('throttle:5,1');
});

// Logout
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Workspace Management
Route::middleware('auth')->prefix('workspace')->name('workspace.')->group(function () {
    Route::get('/select', [WorkspaceController::class, 'select'])->name('select');
    Route::post('/switch/{company}', [WorkspaceController::class, 'switch'])->name('switch');
    Route::get('/create', [WorkspaceController::class, 'create'])->name('create');
    Route::post('/create', [WorkspaceController::class, 'store'])->name('store');
});

// Authenticated Application Modules
Route::middleware(['auth'])->group(function () {
    // Dashboard (Overview)
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // User Profile & Account Settings
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        Route::put('/preferences', [ProfileController::class, 'updatePreferences'])->name('preferences.update');
    });

    // Single Company Profile Module (NSD Earth Movers & Construction Ltd)
    Route::get('/company/profile', [CompanyProfileController::class, 'show'])->name('company.profile');
    Route::put('/company/profile', [CompanyProfileController::class, 'update'])->name('company.profile.update');

    // Clients Module
    Route::prefix('clients')->name('clients.')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('/create', [ClientController::class, 'create'])->name('create');
        Route::post('/', [ClientController::class, 'store'])->name('store');
        Route::get('/{client}', [ClientController::class, 'show'])->name('show');
        Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('edit');
        Route::put('/{client}', [ClientController::class, 'update'])->name('update');
    });

    // Quotations and Estimates Module
    Route::prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->name('index');
        Route::get('/create', [QuotationController::class, 'create'])->name('create');
        Route::post('/', [QuotationController::class, 'store'])->name('store');
        Route::get('/{quotation}', [QuotationController::class, 'show'])->name('show');
        Route::get('/{quotation}/edit', [QuotationController::class, 'edit'])->name('edit');
        Route::put('/{quotation}', [QuotationController::class, 'update'])->name('update');
        Route::patch('/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('status');
        Route::post('/{quotation}/convert-project', [QuotationController::class, 'convertToProject'])->name('convert');
        Route::get('/{quotation}/print', [QuotationController::class, 'printView'])->name('print');
    });

    // Projects Module
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
    });

    // Site Operations Module
    Route::prefix('sites')->name('sites.')->group(function () {
        Route::get('/', [SiteOperationController::class, 'index'])->name('index');
        Route::get('/{site}', [SiteOperationController::class, 'show'])->name('show');
        Route::get('/{site}/logs/create', [SiteOperationController::class, 'createLog'])->name('logs.create');
        Route::post('/{site}/logs', [SiteOperationController::class, 'storeLog'])->name('logs.store');
    });

    // Workforce & Attendance Module
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('/muster-roll', [AttendanceController::class, 'musterRoll'])->name('muster');
        Route::post('/muster-roll', [AttendanceController::class, 'storeMuster'])->name('muster.store');
        Route::post('/approve', [AttendanceController::class, 'approve'])->name('approve');
        Route::post('/reject', [AttendanceController::class, 'reject'])->name('reject');
        Route::post('/corrections', [AttendanceController::class, 'requestCorrection'])->name('corrections.store');
        Route::post('/corrections/{correction}/approve', [AttendanceController::class, 'approveCorrection'])->name('corrections.approve');
        Route::post('/corrections/{correction}/reject', [AttendanceController::class, 'rejectCorrection'])->name('corrections.reject');
    });

    // Construction Work Categories & Scopes
    Route::prefix('construction-categories')->name('construction-categories.')->group(function () {
        Route::get('/', [ConstructionWorkCategoryController::class, 'index'])->name('index');
        Route::post('/', [ConstructionWorkCategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [ConstructionWorkCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [ConstructionWorkCategoryController::class, 'destroy'])->name('destroy');
    });

    // Team Access, Roles & User Management
    Route::prefix('team')->name('team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::get('/roles', [TeamController::class, 'roles'])->name('roles');
        Route::post('/roles', [TeamController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [TeamController::class, 'updateRole'])->name('roles.update');
        Route::post('/users/{targetUser}/role', [TeamController::class, 'assignUserRole'])->name('users.role');
    });

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
    });

    // Payroll & Wage Processing Module
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/create', [PayrollController::class, 'create'])->name('create');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::get('/{period}', [PayrollController::class, 'show'])->name('show');
        Route::post('/{period}/approve', [PayrollController::class, 'approve'])->name('approve');
        Route::post('/{period}/disburse', [PayrollController::class, 'disburse'])->name('disburse');
        Route::get('/{period}/payslips/{record}', [PayrollController::class, 'payslip'])->name('payslip');
    });

    // Materials & Procurement
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/ledger', [InventoryController::class, 'ledger'])->name('ledger');
        Route::get('/transfer', [InventoryController::class, 'createTransfer'])->name('transfer');
        Route::post('/transfer', [InventoryController::class, 'storeTransfer'])->name('transfer.store');
    });

    // Material Issue to Site & Consumption Module
    Route::prefix('material-issues')->name('material-issues.')->group(function () {
        Route::get('/', [MaterialIssueController::class, 'index'])->name('index');
        Route::get('/create', [MaterialIssueController::class, 'create'])->name('create');
        Route::post('/', [MaterialIssueController::class, 'store'])->name('store');
        Route::get('/{materialIssue}', [MaterialIssueController::class, 'show'])->name('show');
        Route::get('/{materialIssue}/print', [MaterialIssueController::class, 'printSlip'])->name('print');
        Route::post('/{materialIssue}/return', [MaterialIssueController::class, 'returnStock'])->name('return');
    });

    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::get('/', [ProcurementController::class, 'index'])->name('index');
        Route::get('/create-po', [ProcurementController::class, 'createPO'])->name('create');
        Route::post('/create-po', [ProcurementController::class, 'storePO'])->name('store');
        Route::get('/orders/{order}', [ProcurementController::class, 'show'])->name('show');
        Route::get('/orders/{order}/receive', [ProcurementController::class, 'createGRN'])->name('receive');
        Route::post('/orders/{order}/receive', [ProcurementController::class, 'storeGRN'])->name('receive.store');
        Route::post('/orders/{order}/invoice', [ProcurementController::class, 'storeSupplierInvoice'])->name('invoice.store');
        Route::get('/supplier-invoices', [ProcurementController::class, 'supplierInvoices'])->name('invoices');
        Route::post('/invoices/{invoice}/approve', [ProcurementController::class, 'approveSupplierInvoice'])->name('invoice.approve');
        Route::post('/invoices/{invoice}/payments', [ProcurementController::class, 'recordPayment'])->name('invoice.payment');

        // Approval Policies configuration
        Route::prefix('approval-policies')->name('policies.')->group(function () {
            Route::get('/', [ProcurementApprovalPolicyController::class, 'index'])->name('index');
            Route::post('/', [ProcurementApprovalPolicyController::class, 'store'])->name('store');
            Route::put('/{policy}', [ProcurementApprovalPolicyController::class, 'update'])->name('update');
            Route::delete('/{policy}', [ProcurementApprovalPolicyController::class, 'destroy'])->name('destroy');
        });
    });

    // Material Suppliers Module
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::get('/create', [SupplierController::class, 'create'])->name('create');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
    });

    // Site Purchase Requests (Material Indents) & PO Conversion
    Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseRequestController::class, 'create'])->name('create');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
        Route::get('/{request}', [PurchaseRequestController::class, 'show'])->name('show');
        Route::get('/{request}/edit', [PurchaseRequestController::class, 'edit'])->name('edit');
        Route::put('/{request}', [PurchaseRequestController::class, 'update'])->name('update');
        Route::post('/{request}/submit', [PurchaseRequestController::class, 'submit'])->name('submit');
        Route::post('/{request}/approve', [PurchaseRequestController::class, 'approve'])->name('approve');
        Route::post('/{request}/reject', [PurchaseRequestController::class, 'reject'])->name('reject');
        Route::post('/{request}/cancel', [PurchaseRequestController::class, 'cancel'])->name('cancel');
        Route::get('/{request}/convert-po', [PurchaseRequestController::class, 'createPOConversion'])->name('convert');
        Route::post('/{request}/convert-po', [PurchaseRequestController::class, 'storePOConversion'])->name('convert.store');
    });

    // Billing & Receivables
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('/create', [BillingController::class, 'createInvoice'])->name('create');
        Route::post('/', [BillingController::class, 'storeInvoice'])->name('store');
        Route::get('/{invoice}', [BillingController::class, 'show'])->name('show');
        Route::post('/{invoice}/approve', [BillingController::class, 'approveInvoice'])->name('approve');
        Route::post('/{invoice}/payments', [BillingController::class, 'recordPayment'])->name('payments.store');
        Route::post('/payments/{payment}/allocate', [BillingController::class, 'allocateReceipt'])->name('payments.allocate');
    });

    // Client Retention & Defect Liability Workflow
    Route::prefix('retention')->name('retention.')->group(function () {
        Route::get('/', [RetentionController::class, 'index'])->name('index');
        Route::get('/contracts/create', [RetentionController::class, 'createContract'])->name('contracts.create');
        Route::post('/contracts', [RetentionController::class, 'storeContract'])->name('contracts.store');
        Route::get('/contracts/{contract}', [RetentionController::class, 'showContract'])->name('contracts.show');
        Route::post('/contracts/{contract}/certificates', [RetentionController::class, 'storeCertificate'])->name('contracts.certificates.store');
        Route::get('/contracts/{contract}/claims/create', [RetentionController::class, 'createClaim'])->name('claims.create');
        Route::post('/contracts/{contract}/claims', [RetentionController::class, 'storeClaim'])->name('claims.store');
        Route::get('/claims/{claim}', [RetentionController::class, 'showClaim'])->name('claims.show');
        Route::post('/claims/{claim}/submit', [RetentionController::class, 'submitClaim'])->name('claims.submit');
        Route::post('/claims/{claim}/approve', [RetentionController::class, 'approveClaim'])->name('claims.approve');
        Route::post('/claims/{claim}/reject', [RetentionController::class, 'rejectClaim'])->name('claims.reject');
        Route::post('/claims/{claim}/client-submit', [RetentionController::class, 'markClientSubmitted'])->name('claims.client-submit');
        Route::post('/claims/{claim}/receipts', [RetentionController::class, 'recordReceipt'])->name('claims.receipts.store');
    });

    // Subcontractors Master
    Route::prefix('subcontractors')->name('subcontractors.')->group(function () {
        Route::get('/', [SubcontractorController::class, 'index'])->name('index');
        Route::get('/create', [SubcontractorController::class, 'create'])->name('create');
        Route::post('/', [SubcontractorController::class, 'store'])->name('store');
        Route::get('/{subcontractor}', [SubcontractorController::class, 'show'])->name('show');
    });

    // Subcontractor Work Orders & BOQ
    Route::prefix('work-orders')->name('work-orders.')->group(function () {
        Route::get('/', [SubcontractorWorkOrderController::class, 'index'])->name('index');
        Route::get('/create', [SubcontractorWorkOrderController::class, 'create'])->name('create');
        Route::post('/', [SubcontractorWorkOrderController::class, 'store'])->name('store');
        Route::get('/{workOrder}', [SubcontractorWorkOrderController::class, 'show'])->name('show');
        Route::post('/{workOrder}/approve', [SubcontractorWorkOrderController::class, 'approve'])->name('approve');
        Route::post('/{workOrder}/variations', [SubcontractorWorkOrderController::class, 'storeVariation'])->name('variations.store');
    });

    // Digital Measurement Book (MB)
    Route::prefix('measurement-books')->name('measurement-books.')->group(function () {
        Route::get('/', [MeasurementBookController::class, 'index'])->name('index');
        Route::get('/create', [MeasurementBookController::class, 'create'])->name('create');
        Route::post('/', [MeasurementBookController::class, 'store'])->name('store');
        Route::get('/{measurementBook}', [MeasurementBookController::class, 'show'])->name('show');
        Route::post('/{measurementBook}/approve', [MeasurementBookController::class, 'approve'])->name('approve');
        Route::post('/{measurementBook}/reject', [MeasurementBookController::class, 'reject'])->name('reject');
        Route::post('/{measurementBook}/resubmit', [MeasurementBookController::class, 'resubmit'])->name('resubmit');
    });

    // Subcontractor RA Bills, Reconciliation & Certification
    Route::prefix('subcontractor-bills')->name('subcontractor-bills.')->group(function () {
        Route::get('/', [SubcontractorBillController::class, 'index'])->name('index');
        Route::get('/create', [SubcontractorBillController::class, 'create'])->name('create');
        Route::post('/', [SubcontractorBillController::class, 'store'])->name('store');
        Route::get('/{subcontractorBill}', [SubcontractorBillController::class, 'show'])->name('show');
        Route::post('/{subcontractorBill}/certify', [SubcontractorBillController::class, 'certify'])->name('certify');
        Route::post('/{subcontractorBill}/payments', [SubcontractorBillController::class, 'recordPayment'])->name('payments.store');
        Route::post('/{subcontractorBill}/reverse', [SubcontractorBillController::class, 'reverse'])->name('reverse');
    });

    // Expenses & Loans
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
    });

    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/create', [LoanController::class, 'create'])->name('create');
        Route::post('/', [LoanController::class, 'store'])->name('store');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show');
        Route::post('/emi/{schedule}/pay', [LoanController::class, 'recordEmiPayment'])->name('emi.pay');
        Route::post('/emi/{schedule}/reverse', [LoanController::class, 'reverseEmiPayment'])->name('emi.reverse');
    });

    // Fixed Assets & Fleet Register
    Route::prefix('assets')->name('assets.')->group(function () {
        Route::get('/', [FixedAssetController::class, 'index'])->name('index');
        Route::get('/create', [FixedAssetController::class, 'create'])->name('create');
        Route::post('/', [FixedAssetController::class, 'store'])->name('store');
        Route::get('/period-review', [FixedAssetController::class, 'periodReview'])->name('period-review');
        Route::post('/period-review/batch-post', [FixedAssetController::class, 'postPeriodBatch'])->name('period-review.post');
        Route::get('/{asset}', [FixedAssetController::class, 'show'])->name('show');
        Route::post('/{asset}/depreciation/calculate', [FixedAssetController::class, 'calculateDepreciation'])->name('depreciation.calculate');
        Route::post('/depreciation/{entry}/post', [FixedAssetController::class, 'postDepreciation'])->name('depreciation.post');
        Route::post('/depreciation/{entry}/reverse', [FixedAssetController::class, 'reverseDepreciation'])->name('depreciation.reverse');
    });

    // Accounting & Reports
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])->name('index');
        Route::get('/journals', [AccountingController::class, 'journalEntries'])->name('journals');
        Route::get('/journals/create', [AccountingController::class, 'createJournal'])->name('journals.create');
        Route::post('/journals', [AccountingController::class, 'storeJournal'])->name('journals.store');
        Route::post('/journals/{journal}/reverse', [AccountingController::class, 'reverseJournal'])->name('journals.reverse');
        Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [AccountingController::class, 'profitAndLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [AccountingController::class, 'balanceSheet'])->name('balance-sheet');
    });

    // Global Search
    Route::get('/search', [GlobalSearchController::class, 'search'])->name('search');

    // Notification Centre & Real-Time Sync Endpoints
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/feed', [NotificationController::class, 'feed'])->name('feed');
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read.post');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    });

    // Document Centre & File Management
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::post('/upload', [DocumentController::class, 'store'])->name('store')->middleware('throttle:30,1');
        Route::get('/{document}/preview', [DocumentController::class, 'preview'])->name('preview')->middleware('throttle:60,1');
        Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download')->middleware('throttle:60,1');
        Route::post('/{document}/replace', [DocumentController::class, 'replace'])->name('replace')->middleware('throttle:30,1');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    });

    Route::get('/api/notifications/unread-count', [NotificationController::class, 'feed'])->name('api.notifications.unread-count');
});
