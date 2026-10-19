<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('issue_number')->index();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('issue_date');
            $table->string('purpose')->nullable();
            $table->string('status')->default('completed')->index(); // completed, returned, cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users');
            $table->string('received_by_name')->nullable();
            $table->string('vehicle_number', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_cost', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'project_id']);
            $table->index(['company_id', 'site_id']);
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('material_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_issue_id')->constrained('material_issues')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('requested_quantity', 12, 3);
            $table->decimal('issued_quantity', 12, 3);
            $table->string('unit_of_measure', 50);
            $table->decimal('unit_rate', 12, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['material_issue_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_issue_items');
        Schema::dropIfExists('material_issues');
    }
};
