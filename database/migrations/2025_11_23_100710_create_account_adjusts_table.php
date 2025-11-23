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
        Schema::create('account_adjusts', function (Blueprint $table) {
            $table->id();
            $table->datetime('date'); 
            $table->enum('prefix', ['ADJ'])->default('ADJ');
            $table->enum('type', ['Credit', 'Debit'])->default('Debit');
            $table->unsignedBigInteger('account_id')->index();
            $table->money('amount')->default(0);

            $table->text('note')->nullable();  // for remark

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
        Schema::dropIfExists('account_adjusts');
    }
};
