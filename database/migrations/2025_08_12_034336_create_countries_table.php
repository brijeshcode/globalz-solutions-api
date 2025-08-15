<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 3)->unique(); // ISO 3166-1 alpha-3 (USA, CAN, GBR)
            $table->string('iso2', 2)->unique(); // ISO 3166-1 alpha-2 (US, CA, GB)
            $table->string('phone_code', 10)->nullable(); // +1, +44, +91
            $table->boolean('is_active')->default(true);
            
            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'deleted_at']);
            $table->index('name');
            $table->index('code');
            $table->index('iso2');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};