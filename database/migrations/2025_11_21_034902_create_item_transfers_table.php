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
        Schema::create('item_transfers', function (Blueprint $table) {
            $table->id();

            $table->datetime('date'); 

            $table->string('code', 50)->unique()->comment('transction code');
            $table->enum('prefix', ['TRAN'])->default('TRAN');

            $table->unsignedBigInteger('from_warehouse_id')->index();
            $table->unsignedBigInteger('to_warehouse_id')->index();

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
        Schema::dropIfExists('item_transfers');
    }
};
