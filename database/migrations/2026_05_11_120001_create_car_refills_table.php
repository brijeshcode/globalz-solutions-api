<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_refills', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');
            $table->string('code', 50)->unique();

            $table->foreignId('car_id')->constrained('cars')->onDelete('restrict');
            $table->foreignId('gas_station_id')->constrained('gas_stations')->onDelete('restrict');
            $table->foreignId('driver_id')->constrained('employees')->onDelete('restrict');

            $table->decimal('odometer', 10, 2);
            $table->decimal('km_driven', 10, 2)->default(0);
            $table->decimal('amount', 15, 4);
            $table->decimal('amount_usd', 20, 8)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('currency_rate', 10, 4)->default(0);
            $table->integer('invoices_count')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'car_id']);
            $table->index(['date', 'gas_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_refills');
    }
};
