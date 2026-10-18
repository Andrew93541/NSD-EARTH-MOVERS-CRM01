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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            // Polymorphic parent relation
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            
            // Classification & Title
            $table->string('category', 60)->default('general')->index();
            $table->string('title', 255)->nullable();
            
            // File Storage Metadata
            $table->string('original_filename', 255);
            $table->string('storage_filename', 255);
            $table->string('disk', 50)->default('local');
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 64)->nullable()->index(); // SHA-256
            
            // Governance, Confidentiality & Versioning
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_confidential')->default(false)->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('superseded_by_id')->nullable()->constrained('documents')->nullOnDelete();
            
            // Status & Malware Scanner
            $table->string('status', 30)->default('active')->index(); // active, superseded, archived, quarantined
            $table->string('scan_status', 30)->default('unscanned'); // unscanned, clean, infected, pending
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Composite Indexes for high-performance scoped lookups
            $table->index(['documentable_type', 'documentable_id'], 'documents_parent_lookup_idx');
            $table->index(['company_id', 'category'], 'documents_company_category_idx');
            $table->index(['company_id', 'is_confidential'], 'documents_company_confidential_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
