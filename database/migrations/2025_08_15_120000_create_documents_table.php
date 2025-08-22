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
            
            // Polymorphic relationship fields
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            
            // File information
            $table->string('original_name', 500)->comment('Original filename uploaded by user');
            $table->string('file_name')->comment('Stored filename (unique)');
            $table->text('file_path')->comment('Full storage path');
            $table->unsignedBigInteger('file_size')->comment('File size in bytes');
            $table->string('mime_type')->comment('MIME type');
            $table->string('file_extension', 10)->comment('File extension');
            
            // Document metadata
            $table->string('title')->nullable()->comment('Optional document title/description');
            $table->text('description')->nullable()->comment('Optional document description');
            $table->string('document_type', 100)->nullable()->comment('Optional categorization (contract, invoice, certificate, etc.)');
            
            // Organization
            $table->string('folder')->nullable()->comment('Optional folder/category within the module');
            $table->json('tags')->nullable()->comment('Tags for advanced organization');
            $table->integer('sort_order')->default(0)->comment('For ordering documents');
            
            // Access control and metadata
            $table->boolean('is_public')->default(false)->comment('Whether document is publicly accessible');
            $table->boolean('is_featured')->default(false)->comment('Featured/important documents');
            $table->json('metadata')->nullable()->comment('Additional file metadata');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('User who uploaded the document');
            
            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['documentable_type', 'documentable_id'], 'documents_documentable_index');
            $table->index('document_type');
            $table->index('mime_type');
            $table->index('file_extension');
            $table->index('is_featured');
            $table->index('uploaded_by');
            $table->index('deleted_at');
            
            // Foreign key constraints
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
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