<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Client Contracts
        Schema::create('client_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number');
            $table->string('title');
            $table->decimal('contract_value', 15, 2)->default(0.00);
            $table->date('start_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->boolean('retention_applicable')->default(true);
            $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
            $table->decimal('max_retention_cap', 15, 2)->nullable();
            $table->unsignedInteger('dlp_duration_months')->default(12);
            $table->string('dlp_start_date_basis')->default('practical_completion'); // practical_completion, handover, final_bill
            $table->string('release_terms_type')->default('staged_50_50'); // staged_50_50, single_full_release, custom_stages
            $table->json('custom_stages_config')->nullable();
            $table->string('status')->default('active'); // active, completed, dlp_active, closed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'contract_number'], 'uniq_cnt_comp_num');
            $table->index(['company_id', 'client_id'], 'idx_cnt_comp_client');
            $table->index(['company_id', 'project_id'], 'idx_cnt_comp_proj');
            $table->index(['company_id', 'status'], 'idx_cnt_comp_stat');
        });

        // 2. Add client_contract_id to client_invoices
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->foreignId('client_contract_id')->nullable()->after('project_id')->constrained('client_contracts')->nullOnDelete();
        });

        // 3. Client Completion & Defect Liability Certificates
        Schema::create('client_completion_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contract_id')->constrained('client_contracts')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_type'); // practical_completion, handover_note, dlp_clearance, defect_free_certificate, final_measurement_clearance
            $table->string('certificate_number');
            $table->string('issuing_authority');
            $table->date('issue_date');
            $table->date('effective_date');
            $table->date('dlp_expiry_date')->nullable();
            $table->string('status')->default('verified'); // draft, submitted, verified, rejected
            $table->unsignedInteger('defect_count_reported')->default(0);
            $table->unsignedInteger('defect_count_resolved')->default(0);
            $table->boolean('all_defects_rectified')->default(true);
            $table->text('inspection_remarks')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['company_id', 'client_contract_id', 'certificate_type'], 'idx_cert_comp_cnt_type');
            $table->index(['company_id', 'status'], 'idx_cert_comp_stat');
        });

        // 4. Retention Release Claims
        Schema::create('retention_release_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contract_id')->constrained('client_contracts')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completion_certificate_id')->nullable()->constrained('client_completion_certificates')->nullOnDelete();
            $table->string('claim_number');
            $table->string('stage_key'); // stage_1_completion, stage_2_dlp, full_release, custom_stage
            $table->string('stage_name');
            $table->decimal('eligible_amount', 15, 2)->default(0.00);
            $table->decimal('claimed_amount', 15, 2)->default(0.00);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->default(0.00);
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected, client_submitted, partially_received, received, closed
            $table->date('submission_date')->nullable();
            $table->string('client_reference')->nullable();
            $table->timestamp('client_submitted_at')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['company_id', 'claim_number'], 'uniq_ret_comp_claim');
            $table->index(['company_id', 'client_contract_id'], 'idx_ret_comp_cnt');
            $table->index(['company_id', 'status'], 'idx_ret_comp_stat');
        });

        // 5. Junction: Retention Claim items drawn from specific RA bills
        Schema::create('retention_claim_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retention_release_claim_id')->constrained('retention_release_claims')->cascadeOnDelete();
            $table->foreignId('client_invoice_id')->constrained('client_invoices')->cascadeOnDelete();
            $table->decimal('source_retention_amount', 15, 2);
            $table->decimal('previously_claimed_amount', 15, 2)->default(0.00);
            $table->decimal('claim_allocated_amount', 15, 2);
            $table->timestamps();

            $table->index(['retention_release_claim_id', 'client_invoice_id'], 'idx_ret_item_claim_inv');
        });

        // 6. Client Receipts recorded against Retention Claims
        Schema::create('retention_claim_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retention_release_claim_id')->constrained('retention_release_claims')->cascadeOnDelete();
            $table->string('receipt_number');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_mode')->default('bank_transfer'); // bank_transfer, cheque, rtgs_neft, cash
            $table->string('reference_number')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'receipt_number'], 'uniq_ret_pay_comp_num');
            $table->index(['company_id', 'payment_date'], 'idx_ret_pay_comp_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_claim_payments');
        Schema::dropIfExists('retention_claim_invoice_items');
        Schema::dropIfExists('retention_release_claims');
        Schema::dropIfExists('client_completion_certificates');

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropForeign(['client_contract_id']);
            $table->dropColumn('client_contract_id');
        });

        Schema::dropIfExists('client_contracts');
    }
};
