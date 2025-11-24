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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            
            // Main Information
            $table->string('code', 50)->unique()->index(); // Auto-generated starting from 5000, supports existing codes like "800947000018A"
            $table->string('short_name')->nullable();
            $table->text('description'); // Required field
            $table->unsignedBigInteger('item_type_id')->nullable(); // Required - references item_types table
            
            // Classification Fields - Foreign Keys (using existing tables)
            $table->unsignedBigInteger('item_family_id')->nullable();
            $table->unsignedBigInteger('item_group_id')->nullable();
            $table->unsignedBigInteger('item_category_id')->nullable();
            $table->unsignedBigInteger('item_profit_margin_id')->nullable();
            $table->unsignedBigInteger('item_brand_id')->nullable();
            $table->unsignedBigInteger('item_unit_id'); // Required - references item_units table
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('tax_code_id'); // Required
            
            // Physical Properties
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('weight', 10, 4)->nullable();
            $table->string('barcode')->nullable()->index();
            
            // Pricing Information (with higher precision for large decimal places)
            $table->decimal('base_cost', 15, 6)->nullable()->comment('Reference price when started buying from supplier');
            $table->decimal('base_sell', 15, 6)->nullable()->comment('Reference selling price');
            $table->decimal('starting_price', 15, 6)->nullable()->comment('Initial starting price');
            
            // Inventory Management
            $table->decimal('starting_quantity', 15, 6)->default(0)->comment('Initial stock quantity');
            $table->decimal('low_quantity_alert', 15, 6)->nullable()->comment('Alert when stock is below this level');
            
            // Cost Calculation Method
            $table->enum('cost_calculation', ['weighted_average', 'last_cost'])->default('weighted_average');
            
            // Additional Information
            $table->text('notes')->nullable();
            
            // System Fields
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Key Constraints
            $table->foreign('item_type_id')->references('id')->on('item_types')->onDelete('set null');
            $table->foreign('item_family_id')->references('id')->on('item_families')->onDelete('set null');
            $table->foreign('item_group_id')->references('id')->on('item_groups')->onDelete('set null');
            $table->foreign('item_category_id')->references('id')->on('item_categories')->onDelete('set null');
            $table->foreign('item_brand_id')->references('id')->on('item_brands')->onDelete('set null');
            $table->foreign('item_unit_id')->references('id')->on('item_units')->onDelete('restrict');
            $table->foreign('item_profit_margin_id')->references('id')->on('item_profit_margins')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('tax_code_id')->references('id')->on('tax_codes')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for better performance
            $table->index(['item_type_id']);
            $table->index(['is_active']);
            $table->index(['item_family_id']);
            $table->index(['item_group_id']);
            $table->index(['item_category_id']);
            $table->index(['item_brand_id']);
            $table->index(['supplier_id']);
            $table->index(['created_at']); 
            
            // Composite indexes for common queries
            $table->index(['item_type_id', 'is_active']);
            $table->index(['item_family_id', 'item_group_id']);
            $table->index(['starting_quantity', 'low_quantity_alert']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};