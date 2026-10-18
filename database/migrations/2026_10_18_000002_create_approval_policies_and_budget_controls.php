<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_approval_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('min_amount', 15, 2)->default(0.00);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->unsignedTinyInteger('approval_level')->default(1);
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('approver_role_slug')->nullable();
            $table->boolean('requires_owner')->default(false);
            $table->boolean('over_budget_requires_owner')->default(true);
            $table->string('unknown_price_rule')->default('escalate_to_owner'); // require_pricing, escalate_to_owner, allow_tier1
            $table->boolean('prevent_self_approval')->default(true);
            $table->boolean('urgent_escalation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'approval_level'], 'proc_policies_co_active_lvl_idx');
        });

        Schema::create('purchase_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('procurement_approval_policies')->nullOnDelete();
            $table->unsignedTinyInteger('approval_level')->default(1);
            $table->foreignId('approver_id')->constrained('users');
            $table->string('action')->default('approved'); // approved, rejected
            $table->text('comments')->nullable();
            $table->boolean('is_budget_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->decimal('snapshot_estimated_total', 15, 2)->default(0.00);
            $table->decimal('snapshot_remaining_budget', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'approval_level'], 'pr_approvals_req_lvl_idx');
            $table->index(['company_id', 'created_at'], 'pr_approvals_co_created_idx');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->decimal('estimated_total', 15, 2)->default(0.00)->after('reason');
            $table->boolean('is_pricing_complete')->default(true)->after('estimated_total');
            $table->boolean('is_over_budget')->default(false)->after('is_pricing_complete');
            $table->decimal('budget_overrun_amount', 15, 2)->default(0.00)->after('is_over_budget');
            $table->unsignedTinyInteger('current_approval_level')->default(1)->after('budget_overrun_amount');
            $table->unsignedTinyInteger('required_approval_levels')->default(1)->after('current_approval_level');
            $table->boolean('budget_override_approved')->default(false)->after('required_approval_levels');
            $table->text('budget_override_reason')->nullable()->after('budget_override_approved');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_total',
                'is_pricing_complete',
                'is_over_budget',
                'budget_overrun_amount',
                'current_approval_level',
                'required_approval_levels',
                'budget_override_approved',
                'budget_override_reason',
            ]);
        });

        Schema::dropIfExists('purchase_request_approvals');
        Schema::dropIfExists('procurement_approval_policies');
    }
};
