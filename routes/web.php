<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SiteOperationController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
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

    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::get('/', [ProcurementController::class, 'index'])->name('index');
        Route::get('/create-po', [ProcurementController::class, 'createPO'])->name('create');
        Route::post('/create-po', [ProcurementController::class, 'storePO'])->name('store');
        Route::get('/orders/{order}', [ProcurementController::class, 'show'])->name('show');
        Route::get('/supplier-invoices', [ProcurementController::class, 'supplierInvoices'])->name('invoices');
    });

    // Billing & Receivables
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('/create', [BillingController::class, 'createInvoice'])->name('create');
        Route::post('/', [BillingController::class, 'storeInvoice'])->name('store');
        Route::get('/{invoice}', [BillingController::class, 'show'])->name('show');
        Route::post('/{invoice}/payments', [BillingController::class, 'recordPayment'])->name('payments.store');
    });

    // Expenses & Loans
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
    });

    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/create', [LoanController::class, 'create'])->name('create');
        Route::post('/', [LoanController::class, 'store'])->name('store');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show');
        Route::post('/emi/{schedule}/pay', [LoanController::class, 'recordEmiPayment'])->name('emi.pay');
    });

    // Accounting & Reports
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])->name('index');
        Route::get('/journals', [AccountingController::class, 'journalEntries'])->name('journals');
        Route::get('/journals/create', [AccountingController::class, 'createJournal'])->name('journals.create');
        Route::post('/journals', [AccountingController::class, 'storeJournal'])->name('journals.store');
        Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])->name('trial-balance');
    });

    // Global Search
    Route::get('/search', [GlobalSearchController::class, 'search'])->name('search');
});
