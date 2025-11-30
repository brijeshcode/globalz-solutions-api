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
        Schema::create('commission_target_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('commission_target_id')->index();
            $table->enum('type',['fuel', 'payment', 'sale']);
            $table->enum('percent_type',['fixed', 'dynamic'])->default('dynamic');
            $table->decimal('minimum_amount',14, 4);
            $table->decimal('maximum_amount',14, 4);
            $table->decimal('percent',8, 4);
            $table->string('comission_label', 100);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_target_items');
    }
};
