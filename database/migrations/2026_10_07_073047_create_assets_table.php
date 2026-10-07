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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->nullable()->comment('purchase date');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedSmallInteger('currency_id')->nullable()->default(null);
            $table->rate('currency_rate')->default(1);
            $table->moneyMedium('amount')->default(0);
            $table->moneySmall('amount_usd')->default(0);
            $table->text('note')->nullable();


            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
