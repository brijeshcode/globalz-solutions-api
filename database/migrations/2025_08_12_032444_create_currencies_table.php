<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 3)->unique(); // USD, EUR, GBP
            $table->string('symbol', 10)->nullable(); // $, €, £
            $table->string('symbol_position', 10)->default('before'); // before, after
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('decimal_separator', 5)->default('.');
            $table->string('thousand_separator', 5)->default(',');
            $table->boolean('is_active')->default(true);
            $table->string('calculation_type',20)->default('multiply')->comment('Used to calculate USD value with give rate');
            
            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'deleted_at']);
            $table->index('name');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};