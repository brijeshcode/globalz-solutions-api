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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // Main Info Tab
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('code', 20)->unique()->index(); // Auto-generated starting from 50000000
            $table->string('name')->index();

            $table->unsignedBigInteger('customer_type_id')->nullable()->index();
            $table->unsignedBigInteger('customer_group_id')->nullable()->index();
            $table->unsignedBigInteger('customer_province_id')->nullable()->index();
            $table->unsignedBigInteger('customer_zone_id')->nullable()->index();


            // $table->decimal('opening_balance', 15, 4)->default(0); // Starting balance
            $table->decimal('current_balance', 15, 4)->default(0); // Running balance

            // Additional Info Tab
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            $table->string('telephone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('url')->nullable();
            $table->string('google_map')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('gps_coordinates')->nullable(); // Format: 33.9024493,35.5750987
            $table->string('mof_tax_number', 50)->nullable(); // Tax number

            // Sales Info Tab
            $table->unsignedBigInteger('salesperson_id')->nullable()->index();
            $table->unsignedBigInteger('customer_payment_term_id')->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0); // Default discount %
            $table->decimal('credit_limit', 15, 4)->default(5000); // Credit limit

            // Other Tab
            $table->text('notes')->nullable();

            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->foreign('parent_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('salesperson_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('customer_type_id')->references('id')->on('customer_types')->onDelete('set null');
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->onDelete('set null');
            $table->foreign('customer_province_id')->references('id')->on('customer_provinces')->onDelete('set null');
            $table->foreign('customer_zone_id')->references('id')->on('customer_zones')->onDelete('set null');
            $table->foreign('customer_payment_term_id')->references('id')->on('customer_payment_terms')->onDelete('set null');


            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Additional Indexes for performance
            $table->index(['is_active', 'created_at']);
            $table->index(['customer_type_id', 'customer_group_id']);
            $table->index(['customer_province_id', 'customer_zone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
