<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance suppliers table with subcontractor party classification
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('subcontractor_code')->nullable()->after('company_id');
            $table->string('vendor_type')->default('supplier')->after('subcontractor_code'); // supplier, subcontractor, both
            $table->json('work_categories')->nullable()->after('vendor_type'); // earthwork, civil, road_works, concreting, etc.
            $table->string('tds_section')->default('194C_company_2%')->after('pan'); // 194C_individual_1%, 194C_company_2%
            $table->string('trade_license_no')->nullable()->after('tds_section');
        });

        // 2. Subcontractor Work Orders
        Schema::create('subcontractor_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcontractor_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('wo_number');
            $table->string('title');
            $table->string('work_category'); // earthwork, civil, concreting, road_works, drainage, structural, finishing, other
            $table->string('work_package')->nullable();
            $table->text('scope_of_work')->nullable();
            $table->date('start_date');
            $table->date('completion_date');
            $table->decimal('contract_value', 15, 2)->default(0.00);
            $table->decimal('revised_value', 15, 2)->default(0.00);
            $table->decimal('retention_percent', 5, 2)->default(5.00);
            $table->decimal('retention_cap_amount', 15, 2)->nullable();
            $table->decimal('tds_rate_percent', 5, 2)->default(2.00);
            $table->decimal('gst_rate_percent', 5, 2)->default(18.00);
            $table->string('payment_terms')->default('30_days'); // 15_days, 30_days, against_certified_bill
            $table->string('status')->default('draft'); // draft, submitted, approved, active, rejected, completed, cancelled
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'wo_number']);
            $table->index(['company_id', 'status', 'project_id'], 'swo_comp_status_proj_idx');
        });

        // 3. Subcontractor Work Order Items (BOQ lines)
        Schema::create('subcontractor_work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id');
            $table->foreign('work_order_id', 'swoi_wo_fk')->references('id')->on('subcontractor_work_orders')->cascadeOnDelete();
            $table->string('item_code'); // BOQ-01, BOQ-02
            $table->text('description');
            $table->string('category');
            $table->string('unit_of_measure'); // cum, sqm, rmt, MT, nos, brass
            $table->decimal('approved_quantity', 12, 3);
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('certified_quantity', 12, 3)->default(0.000);
            $table->decimal('billed_quantity', 12, 3)->default(0.000);
            $table->timestamps();
        });

        // 4. Subcontractor Work Order Variations / Controlled Revisions
        Schema::create('subcontractor_work_order_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id');
            $table->foreign('work_order_id', 'swov_wo_fk')->references('id')->on('subcontractor_work_orders')->cascadeOnDelete();
            $table->string('variation_number'); // VAR-01, VAR-02
            $table->string('variation_type'); // quantity_increase, rate_revision, scope_addition, new_item
            $table->foreignId('work_order_item_id')->nullable();
            $table->foreign('work_order_item_id', 'swov_wo_item_fk')->references('id')->on('subcontractor_work_order_items')->nullOnDelete();
            $table->decimal('previous_quantity', 12, 3)->nullable();
            $table->decimal('revised_quantity', 12, 3)->nullable();
            $table->decimal('previous_rate', 12, 2)->nullable();
            $table->decimal('revised_rate', 12, 2)->nullable();
            $table->decimal('value_impact', 15, 2)->default(0.00);
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        // 5. Digital Measurement Book Entries (MB)
        Schema::create('measurement_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id');
            $table->foreign('work_order_id', 'mbe_wo_fk')->references('id')->on('subcontractor_work_orders')->cascadeOnDelete();
            $table->foreignId('work_order_item_id');
            $table->foreign('work_order_item_id', 'mbe_wo_item_fk')->references('id')->on('subcontractor_work_order_items')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcontractor_id');
            $table->foreign('subcontractor_id', 'mbe_subcon_fk')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->string('mb_number'); // MB-2026-0001
            $table->date('measurement_date');
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->string('location_chainage')->nullable();
            $table->text('description');
            $table->string('measurement_formula')->default('dimensions_l_b_h'); // dimensions_l_b_h, area_depth_a_d, unit_count_nos, direct_manual
            $table->decimal('length', 10, 3)->nullable();
            $table->decimal('breadth', 10, 3)->nullable();
            $table->decimal('depth_height', 10, 3)->nullable();
            $table->decimal('multiplier_units', 8, 2)->default(1.00);
            $table->decimal('manual_quantity_override', 12, 3)->nullable();
            $table->text('override_reason')->nullable();
            $table->decimal('measured_quantity', 12, 3);
            $table->decimal('previously_certified_quantity', 12, 3)->default(0.000);
            $table->decimal('cumulative_quantity', 12, 3);
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected, certified
            $table->foreignId('measured_by')->constrained('users');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('certified_in_bill_id')->nullable();
            $table->text('site_remarks')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'work_order_id', 'status'], 'mbe_comp_wo_status_idx');
            $table->index(['company_id', 'project_id', 'measurement_date'], 'mbe_comp_proj_date_idx');
        });

        // 6. Subcontractor Running Account Bills (RA Bills)
        Schema::create('subcontractor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id');
            $table->foreign('subcontractor_id', 'scb_subcon_fk')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreignId('work_order_id');
            $table->foreign('work_order_id', 'scb_wo_fk')->references('id')->on('subcontractor_work_orders')->cascadeOnDelete();
            $table->string('bill_number'); // SCB-2026-001, RA-01
            $table->unsignedInteger('bill_sequence')->default(1);
            $table->date('bill_date');
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->decimal('gross_work_value', 15, 2);
            $table->decimal('cumulative_gross_value', 15, 2);
            $table->decimal('previously_certified_value', 15, 2)->default(0.00);
            $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
            $table->decimal('retention_amount', 15, 2)->default(0.00);
            $table->decimal('tds_rate_percent', 5, 2)->default(2.00);
            $table->decimal('tds_amount', 15, 2)->default(0.00);
            $table->decimal('gst_rate_percent', 5, 2)->default(18.00);
            $table->decimal('gst_amount', 15, 2)->default(0.00);
            $table->decimal('advance_recovery_amount', 15, 2)->default(0.00);
            $table->decimal('other_deductions_amount', 15, 2)->default(0.00);
            $table->decimal('net_payable_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->string('status')->default('draft'); // draft, submitted, certified, approved_for_payment, partially_paid, paid, rejected, reversed
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('certified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'bill_number']);
            $table->index(['company_id', 'work_order_id', 'status'], 'scb_comp_wo_status_idx');
        });

        // 7. Subcontractor Bill Items (Reconciled with BOQ & MB)
        Schema::create('subcontractor_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontractor_bill_id');
            $table->foreign('subcontractor_bill_id', 'scbi_bill_fk')->references('id')->on('subcontractor_bills')->cascadeOnDelete();
            $table->foreignId('work_order_item_id');
            $table->foreign('work_order_item_id', 'scbi_wo_item_fk')->references('id')->on('subcontractor_work_order_items')->cascadeOnDelete();
            $table->decimal('previously_billed_quantity', 12, 3)->default(0.000);
            $table->decimal('current_billed_quantity', 12, 3);
            $table->decimal('cumulative_billed_quantity', 12, 3);
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('gross_amount', 15, 2);
            $table->timestamps();
        });

        // 8. Subcontractor Bill Measurement Allocations Pivot
        Schema::create('subcontractor_bill_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontractor_bill_id');
            $table->foreign('subcontractor_bill_id', 'scbm_bill_fk')->references('id')->on('subcontractor_bills')->cascadeOnDelete();
            $table->foreignId('measurement_book_entry_id');
            $table->foreign('measurement_book_entry_id', 'scbm_entry_fk')->references('id')->on('measurement_book_entries')->cascadeOnDelete();
            $table->decimal('allocated_quantity', 12, 3);
            $table->timestamps();
        });

        // 9. Subcontractor Bill Payments
        Schema::create('subcontractor_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_bill_id');
            $table->foreign('subcontractor_bill_id', 'scbp_bill_fk')->references('id')->on('subcontractor_bills')->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_mode')->default('bank_transfer'); // bank_transfer, cheque, rtgs_neft, adjustment
            $table->string('transaction_reference');
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcontractor_bill_payments');
        Schema::dropIfExists('subcontractor_bill_measurements');
        Schema::dropIfExists('subcontractor_bill_items');
        Schema::dropIfExists('subcontractor_bills');
        Schema::dropIfExists('measurement_book_entries');
        Schema::dropIfExists('subcontractor_work_order_variations');
        Schema::dropIfExists('subcontractor_work_order_items');
        Schema::dropIfExists('subcontractor_work_orders');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['subcontractor_code', 'vendor_type', 'work_categories', 'tds_section', 'trade_license_no']);
        });
    }
};
