<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            
            // Main Info Tab
            $table->string('code', 50)->unique()->comment('Unique supplier code starting from 1000');
            $table->string('name');
            $table->foreignId('supplier_type_id')->nullable()->constrained('supplier_types')->onDelete('set null');
            $table->foreignId('country_id')->nullable()->constrained('countries')->onDelete('set null');
            // we will remove the opening balance once client confirms if he aggree to use credit note for supplier 
            $table->decimal('opening_balance', 15, 2)->default(0)->comment('Starting balance');
            $table->decimal('current_balance', 15, 4)->default(0); // Running balance
            
            // Contact Info Tab
            $table->text('address')->nullable()->comment('Full address');
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('url')->nullable()->comment('Website URL');
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_mobile')->nullable();
            
            // Purchase Info Tab
            $table->foreignId('payment_term_id')->nullable()->constrained('supplier_payment_terms')->onDelete('set null');
            $table->string('ship_from')->nullable()->comment('Shipping origin location');
            $table->text('bank_info')->nullable()->comment('Bank details for payments');
            $table->decimal('discount_percentage', 5, 2)->nullable()->comment('Default discount percentage');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onDelete('set null');
            
            // Other Tab
            $table->text('notes')->nullable()->comment('General notes about supplier');
            $table->json('attachments')->nullable()->comment('Uploaded documents/contracts');
            
            // System fields
            $table->boolean('is_active')->default(true);
            
            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['is_active', 'deleted_at']);
            $table->index('code');
            $table->index('name');
            $table->index('country_id');
            $table->index('supplier_type_id');
            $table->index('payment_term_id');
            $table->index('currency_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};