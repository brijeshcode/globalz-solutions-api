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
        Schema::create('price_list_bulk_updates', function (Blueprint $table) {
            $table->id();

            $table->date('date');
            $table->text('note')->nullable();
            $table->json('filters')->nullable()->comment('Filters used to fetch the price list items');
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('price_list_count')->default(0);

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
        Schema::dropIfExists('price_list_bulk_updates');
    }
};
