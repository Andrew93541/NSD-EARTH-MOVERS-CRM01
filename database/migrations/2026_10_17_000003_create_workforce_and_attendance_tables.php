<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->decimal('standard_hours', 4, 2)->default(8.00);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->index();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time, contract, daily_wage, machine_operator
            $table->date('joining_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->foreignId('assigned_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('daily_wage_rate', 10, 2)->default(0.00);
            $table->decimal('monthly_salary', 12, 2)->default(0.00);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('aadhaar_number')->nullable();
            $table->string('status')->default('active'); // active, inactive, terminated
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('punch_time');
            $table->string('punch_type')->default('in'); // in, out
            $table->string('source')->default('manual'); // biometric_import, manual, mobile_geo
            $table->string('device_id')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'punch_time']);
        });

        Schema::create('daily_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->time('first_in')->nullable();
            $table->time('last_out')->nullable();
            $table->string('status')->default('present'); // present, half_day, absent, paid_leave, weekly_off
            $table->decimal('worked_hours', 4, 2)->default(8.00);
            $table->decimal('overtime_hours', 4, 2)->default(0.00);
            $table->boolean('is_overtime_approved')->default(false);
            $table->foreignId('overtime_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'attendance_date']);
            $table->index(['company_id', 'attendance_date', 'status']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type')->default('casual'); // casual, sick, privilege, unpaid
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('total_days')->default(1);
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('daily_attendance');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('shifts');
    }
};
