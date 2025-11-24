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
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');

            $table->string('code', 50)->unique()->comment('transction code');
            $table->enum('prefix', ['TRF'])->default('TRF');

            $table->unsignedBigInteger('from_account_id')->index();
            $table->unsignedBigInteger('to_account_id')->index();
            $table->unsignedBigInteger('from_currency_id');
            $table->unsignedBigInteger('to_currency_id');

            $table->money('received_amount')->default(0);
            $table->money('sent_amount')->default(0);
            $table->rate('currency_rate')->default(0);
            $table->text('note')->nullable();  // for remark

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
        Schema::dropIfExists('account_transfers');
    }
};
