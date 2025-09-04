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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_type_id')->constrained('account_types')->onDelete('restrict');
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->string('name')->unique();
            $table->decimal('opening_balance', 15, 4)->default(0); // Starting balance
            $table->decimal('current_balance', 15, 4)->default(0); // Running balance
            $table->text('description')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'deleted_at']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
