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
        Schema::create('item_adjust_items', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('item_adjust_id')->index();
            
            $table->unsignedBigInteger('item_id')->index();
            $table->string('item_code'); // comes form items table 

            $table->quantity('quantity')->default(0);

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
        Schema::dropIfExists('item_adjust_items');
    }
};
