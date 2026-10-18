<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->after('status'); // normal, urgent, critical
            $table->foreignId('warehouse_id')->nullable()->after('site_id')->constrained('warehouses')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('reason');
            $table->timestamp('submitted_at')->nullable()->after('request_date');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->string('specifications')->nullable()->after('material_id');
            $table->decimal('quantity_ordered', 12, 3)->default(0.000)->after('quantity_requested');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('purchase_request_item_id')->nullable()->after('material_id')->constrained('purchase_request_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_item_id']);
            $table->dropColumn('purchase_request_item_id');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn(['specifications', 'quantity_ordered']);
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['priority', 'warehouse_id', 'rejection_reason', 'submitted_at']);
        });
    }
};
