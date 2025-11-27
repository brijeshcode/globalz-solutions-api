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
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();

            $table->datetime('date');
            
            $table->enum('prefix', ['ALL'])->default('ALL');
            $table->string('code', 50)->unique();
            
            $table->unsignedBigInteger('employee_id')->index()->comment('only sales departments');
            $table->unsignedBigInteger('account_id')->index();
            
            // admin can pay with othere currency, in that case we will also show usd equivalance of that currency.
            $table->unsignedBigInteger('currency_id')->index()->comment('curreny of account');
            $table->rate('currency_rate')->default(1);

            $table->money('amount')->default(0);
            $table->money('amount_usd')->default(0);

            $table->text('note')->nullable(); // for remark 

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allowances');
    }
};
