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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->unsignedBigInteger('tax_code_id'); // Required
            
            $table->decimal('amount', 15, 4);
            $table->decimal('amount_usd', 20, 8)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('currency_rate', 10, 4)->default(0);

            // Additional Information
            $table->text('notes')->nullable();
            
            // System Fields
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
