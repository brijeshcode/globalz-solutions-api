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
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            
            // Main Information
            $table->string('code', 20)->unique()->index(); // Tax code like "VAT", "NOTAX"
            $table->string('name'); // Tax name like "V.A.T", "No Tax"
            $table->text('description')->nullable(); // Optional description
            
            // Tax Configuration
            $table->decimal('tax_percent', 5, 2)->default(0.00); // Tax percentage (e.g., 15.00 for 15%, 0.00 for no tax)
            $table->enum('type', ['inclusive', 'exclusive'])->default('exclusive'); // How tax is applied
            
            // System Fields
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Mark default tax code
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Key Constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['is_active']);
            $table->index(['is_default']);
            $table->index(['type']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};