<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_number')->index();
            $table->string('title');
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->string('payment_mode')->default('bank_transfer'); // cash, bank_transfer, company_card, upi
            $table->string('receipt_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, reimbursed
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'expense_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('payment_voucher_no')->index();
            $table->string('payable_type'); // supplier_invoices, payroll_records, loans, expenses
            $table->unsignedBigInteger('payable_id');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_mode')->default('bank_transfer'); // neft_rtgs, cheque, upi, cash
            $table->string('reference_number')->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'payable_type', 'payable_id']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('loan_account_number')->index();
            $table->string('lender_name'); // e.g. HDFC Bank, Tata Capital, SREI Equipment Finance
            $table->string('loan_type')->default('equipment_finance'); // equipment_finance, vehicle_loan, term_loan, working_capital
            $table->string('asset_description')->nullable(); // e.g. "Tata Hitachi EX200 LC Excavator", "JCB 3DX Eco Super"
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('annual_interest_rate', 5, 2); // e.g. 10.50%
            $table->unsignedSmallInteger('tenure_months');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_emi', 15, 2);
            $table->decimal('outstanding_principal', 15, 2);
            $table->string('status')->default('active'); // active, closed, defaulted
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('loan_emi_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_component', 15, 2);
            $table->decimal('interest_component', 15, 2);
            $table->decimal('total_emi', 15, 2);
            $table->decimal('remaining_balance', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, overdue
            $table->date('paid_date')->nullable();
            $table->string('payment_ref')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_emi_schedules');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
