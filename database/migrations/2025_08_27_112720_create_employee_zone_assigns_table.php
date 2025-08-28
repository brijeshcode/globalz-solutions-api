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
        Schema::create('employee_zone_assigns', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('customer_zone_id');
            
            
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('customer_zone_id')->references('id')->on('customer_zones')->onDelete('cascade');
            
            // Unique constraint - employee can be assigned to same zone only once
            $table->unique(['employee_id', 'customer_zone_id']);
            
            // Indexes
            $table->index(['employee_id']);
            $table->index(['customer_zone_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_zone_assigns');
    }
};
