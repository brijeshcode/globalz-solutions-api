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
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // auto generated invoice code
            $table->datetime('date');
            $table->enum('prefix', ['RTX', 'RTN'])->default('RTN');

            $table->unsignedBigInteger('salesperson_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();

            $table->rate('currency_rate')->default(0);

            $table->money('total')->default(0);
            $table->money('total_usd')->default(0);

            $table->decimal('total_volume_cbm', 10,4)->default(0);
            $table->decimal('total_weight_kg', 10,4)->default(0);

            // Approval fields
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->text('approve_note')->nullable();

            // return received
            $table->foreignId('return_received_by')->nullable()->constrained('users')->onDelete('set null')->comment('warehouse manager');
            $table->dateTime('return_received_at')->nullable();
            $table->text('return_received_note')->nullable();

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
        Schema::dropIfExists('customer_returns');
    }
};
