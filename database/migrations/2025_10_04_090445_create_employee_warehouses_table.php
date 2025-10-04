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
        Schema::create('employee_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            
            // Optional: Add metadata about the assignment
            $table->boolean('is_primary')->default(false); // Mark primary warehouse
            
            $table->timestamps();
            
            // Prevent duplicate assignments
            $table->unique(['employee_id', 'warehouse_id']);
            
            // Indexes for performance
            $table->index('employee_id');
            $table->index('warehouse_id');
            $table->index('is_primary');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_warehouses');
    }
};
