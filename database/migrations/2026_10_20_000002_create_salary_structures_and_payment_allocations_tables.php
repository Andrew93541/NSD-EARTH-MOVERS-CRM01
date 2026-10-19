<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Effective-dated employee salary & wage structures
        if (! Schema::hasTable('employee_salary_structures')) {
            Schema::create('employee_salary_structures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('employment_type')->default('daily_wage'); // daily_wage, machine_operator, permanent, contract
                $table->decimal('daily_wage_rate', 10, 2)->default(0.00);
                $table->decimal('monthly_salary', 12, 2)->default(0.00);
                $table->decimal('hourly_overtime_rate', 10, 2)->default(0.00);
                $table->decimal('standard_allowance', 10, 2)->default(0.00);
                $table->decimal('statutory_deduction_percent', 5, 2)->default(0.00); // e.g. 5.00 for 5% PF
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'effective_from'], 'emp_sal_struct_emp_date_idx');
                $table->index(['company_id', 'employment_type'], 'emp_sal_struct_comp_type_idx');
            });
        }

        // 2. Multi-bill receipt allocation ledger
        if (! Schema::hasTable('invoice_payment_allocations')) {
            Schema::create('invoice_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->cascadeOnDelete();
                $table->foreignId('client_invoice_id')->constrained('client_invoices')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['client_invoice_id', 'invoice_payment_id'], 'inv_alloc_inv_pay_idx');
                $table->index(['company_id', 'invoice_payment_id'], 'inv_alloc_comp_pay_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_allocations');
        Schema::dropIfExists('employee_salary_structures');
    }
};
