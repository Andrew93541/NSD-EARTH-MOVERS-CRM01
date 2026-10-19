<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Non-destructive schema enhancement:
     * 1. Unique index on quotations (company_id, quotation_number)
     * 2. Unique index on material_issues (company_id, issue_number)
     * 3. returned_quantity tracking on material_issue_items
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->unique(['company_id', 'quotation_number'], 'quotations_company_quotation_unique');
        });

        Schema::table('material_issues', function (Blueprint $table) {
            $table->unique(['company_id', 'issue_number'], 'material_issues_company_issue_unique');
        });

        Schema::table('material_issue_items', function (Blueprint $table) {
            if (! Schema::hasColumn('material_issue_items', 'returned_quantity')) {
                $table->decimal('returned_quantity', 12, 3)->default(0.000)->after('issued_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_issue_items', function (Blueprint $table) {
            if (Schema::hasColumn('material_issue_items', 'returned_quantity')) {
                $table->dropColumn('returned_quantity');
            }
        });

        Schema::table('material_issues', function (Blueprint $table) {
            $table->dropUnique('material_issues_company_issue_unique');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique('quotations_company_quotation_unique');
        });
    }
};
