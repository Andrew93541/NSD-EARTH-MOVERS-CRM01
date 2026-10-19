<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configurable construction work categories & subcategories
        if (! Schema::hasTable('construction_work_categories')) {
            Schema::create('construction_work_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('construction_work_categories')->nullOnDelete();
                $table->string('name');
                $table->string('code')->index();
                $table->string('unit_of_measure', 20)->nullable(); // e.g. CuM, SqM, Rmt, MT, Nos, Hours
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'is_active']);
            });
        }

        // 2. Enhance daily_attendance with multi-stage approval workflow
        Schema::table('daily_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_attendance', 'approval_status')) {
                $table->string('approval_status')->default('approved')->after('notes'); // draft, submitted, pending_approval, approved, rejected
                $table->text('rejection_reason')->nullable()->after('approval_status');
                $table->timestamp('submitted_at')->nullable()->after('rejection_reason');
                $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');

                $table->index(['company_id', 'attendance_date', 'approval_status'], 'daily_att_comp_date_appr_idx');
            }
        });

        // 3. Auditable attendance correction history
        if (! Schema::hasTable('daily_attendance_corrections')) {
            Schema::create('daily_attendance_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('daily_attendance_id')->constrained('daily_attendance')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users');
                $table->string('previous_status');
                $table->string('proposed_status');
                $table->decimal('previous_worked_hours', 4, 2)->default(0.00);
                $table->decimal('proposed_worked_hours', 4, 2)->default(0.00);
                $table->decimal('previous_overtime_hours', 4, 2)->default(0.00);
                $table->decimal('proposed_overtime_hours', 4, 2)->default(0.00);
                $table->text('reason');
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('actioned_at')->nullable();
                $table->text('action_notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['daily_attendance_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendance_corrections');

        Schema::table('daily_attendance', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'attendance_date', 'approval_status']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approval_status',
                'rejection_reason',
                'submitted_at',
                'approved_by',
                'approved_at',
            ]);
        });

        Schema::dropIfExists('construction_work_categories');
    }
};
