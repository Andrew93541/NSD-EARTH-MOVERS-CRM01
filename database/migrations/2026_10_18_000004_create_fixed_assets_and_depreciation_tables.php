<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Fixed Assets Register
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code')->index(); // e.g. AST-EX200-01
            $table->string('name'); // e.g. Tata Hitachi EX200 LC Excavator
            $table->string('category')->default('excavator'); // excavator, backhoe_loader, dumper_tipper, crane, compactor, concrete_machinery, generator, other
            $table->string('serial_number')->nullable(); // Chassis / Engine / Serial No.
            $table->date('purchase_date');
            $table->date('capitalization_date');
            $table->decimal('acquisition_cost', 15, 2);
            $table->decimal('eligible_capitalized_costs', 15, 2)->default(0.00); // Freight, transit insurance, commissioning
            $table->decimal('total_capitalized_cost', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0.00); // Scrap / Salvage value
            $table->unsignedSmallInteger('useful_life_years')->default(8);
            $table->unsignedSmallInteger('useful_life_months')->default(96);
            $table->string('depreciation_method')->default('straight_line'); // straight_line, written_down_value
            $table->decimal('depreciation_rate_percent', 5, 2)->nullable(); // Required for WDV method
            $table->date('depreciation_start_date');
            $table->string('depreciation_frequency')->default('monthly'); // monthly, quarterly, annual
            $table->decimal('accumulated_depreciation', 15, 2)->default(0.00);
            $table->decimal('current_book_value', 15, 2);
            $table->string('status')->default('active'); // active, under_maintenance, idle, disposed, retired
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // 1510 Plant & Machinery
            $table->foreignId('accumulated_depr_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // 1520 Accumulated Depr
            $table->foreignId('depr_expense_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // 5030 Depreciation Expense
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->string('location')->nullable(); // e.g. "Riverside Residency Site", "Central Yard Nagpur"
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->nullable();
            $table->text('disposal_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'asset_code']);
            $table->index(['company_id', 'status', 'category']);
        });

        // 2. Periodic Asset Depreciation Entries
        Schema::create('asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->string('financial_year', 20)->nullable(); // e.g. 2026-2027
            $table->string('depreciation_method')->default('straight_line');
            $table->decimal('opening_book_value', 15, 2);
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('closing_book_value', 15, 2);
            $table->decimal('accumulated_depreciation_after', 15, 2);
            $table->boolean('is_partial_period')->default(false);
            $table->unsignedSmallInteger('days_in_period')->nullable();
            $table->string('status')->default('calculated'); // calculated, approved, posted, reversed
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fixed_asset_id', 'period_start_date', 'period_end_date'], 'unique_asset_depr_period');
            $table->index(['company_id', 'status', 'period_end_date'], 'ade_comp_status_date_idx');
        });

        // 3. Add link columns to loans table
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('fixed_asset_id')->nullable()->after('asset_description')->constrained('fixed_assets')->nullOnDelete();
            $table->foreignId('liability_account_id')->nullable()->after('outstanding_principal')->constrained('accounts')->nullOnDelete();
            $table->foreignId('interest_account_id')->nullable()->after('liability_account_id')->constrained('accounts')->nullOnDelete();
        });

        // 4. Add accounting columns to loan_emi_schedules table
        Schema::table('loan_emi_schedules', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('payment_ref')->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->after('bank_account_id')->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_emi_schedules', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['journal_entry_id', 'bank_account_id']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->dropForeign(['liability_account_id']);
            $table->dropForeign(['interest_account_id']);
            $table->dropColumn(['fixed_asset_id', 'liability_account_id', 'interest_account_id']);
        });

        Schema::dropIfExists('asset_depreciation_entries');
        Schema::dropIfExists('fixed_assets');
    }
};
