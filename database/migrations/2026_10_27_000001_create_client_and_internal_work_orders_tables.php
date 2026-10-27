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
        // 1. Primary Work Orders Table (Client-issued and Internally generated)
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            
            // Unique Internal CRM ID
            $table->string('order_number');
            
            // Order Classification: 'client_issued' or 'internal'
            $table->string('order_type')->default('client_issued');
            
            // Original Client Work-Order / Contract Reference (kept separate from internal CRM ID)
            $table->string('external_reference_number')->nullable();
            
            $table->string('title');
            $table->text('description')->nullable();
            
            // Client relationship (nullable for self-directed internal jobs)
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            
            // Project and Site linkages
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            
            // Staff / Supervisor Assignment
            $table->foreignId('assigned_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Operational Dates
            $table->date('issue_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            
            // Valuation
            $table->decimal('total_amount', 15, 2)->default(0.00);
            
            // Management / Operational Status: draft, pending_approval, approved, in_progress, completed, cancelled
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Document Verification Status: none, extracted, scanned_manual_entry_required, verified, failed
            $table->string('extraction_status')->default('none');
            $table->json('extracted_data')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Non-destructive Duplicate Detection Flagging
            $table->boolean('is_duplicate_flagged')->default(false);
            $table->string('duplicate_reason')->nullable();
            $table->foreignId('duplicate_matched_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('duplicate_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('duplicate_resolved_at')->nullable();
            
            // Audit & Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique(['company_id', 'order_number']);
            $table->index(['company_id', 'order_type', 'status'], 'wo_comp_type_status_idx');
            $table->index(['company_id', 'client_id', 'external_reference_number'], 'wo_comp_client_ref_idx');
            $table->index(['company_id', 'project_id'], 'wo_comp_proj_idx');
            $table->index(['company_id', 'extraction_status'], 'wo_comp_extraction_idx');
        });

        // 2. Work Order Items (Line Items / BOQ entries)
        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->integer('item_number')->default(1);
            $table->string('description');
            $table->foreignId('work_category_id')->nullable()->constrained('construction_work_categories')->nullOnDelete();
            $table->string('unit_of_measure', 30)->default('units');
            $table->decimal('quantity', 12, 3)->default(1.000);
            $table->decimal('unit_rate', 15, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'item_number'], 'wo_item_order_num_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
    }
};
