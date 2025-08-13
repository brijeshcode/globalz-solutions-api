<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->integer('days')->nullable()->comment('Number of days for payment');
            $table->enum('type', ['net', 'due_on_receipt', 'cash_on_delivery', 'advance', 'credit'])->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable()->comment('Early payment discount percentage');
            $table->integer('discount_days')->nullable()->comment('Days within which discount applies');
            $table->boolean('is_active')->default(true);
            
            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'deleted_at']);
            $table->index('name');
            $table->index('days');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_terms');
    }
};