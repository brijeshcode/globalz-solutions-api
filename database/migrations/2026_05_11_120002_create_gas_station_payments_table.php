<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_station_payments', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');
            $table->string('code', 50)->unique();

            $table->foreignId('gas_station_id')->constrained('gas_stations')->onDelete('restrict');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');

            $table->decimal('amount', 15, 4);
            $table->decimal('amount_usd', 20, 8)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('currency_rate', 10, 4)->default(0);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'gas_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_station_payments');
    }
};
