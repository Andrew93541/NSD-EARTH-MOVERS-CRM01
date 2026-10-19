<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('quotation_number')->index();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->date('valid_until');
            $table->string('project_title');
            $table->text('project_description')->nullable();
            $table->string('status')->default('draft')->index(); // draft, sent, accepted, rejected, expired
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->string('discount_type')->nullable(); // fixed, percentage
            $table->decimal('discount_rate', 8, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->string('tax_type')->default('gst_18'); // gst_18, igst_18, gst_12, gst_5, exempt, custom
            $table->decimal('tax_rate', 5, 2)->default(18.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->foreignId('converted_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'date']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->integer('item_order')->default(0);
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 50);
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'item_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
