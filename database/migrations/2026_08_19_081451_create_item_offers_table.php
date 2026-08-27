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
        Schema::create('item_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('free_item_id')->constrained('items')->cascadeOnDelete();
            $table->date('date');
            $table->date('validity_date');
            $table->integer('minimum_quantity');
            $table->integer('free_quantity');
            $table->integer('usage_limit')->default(100)->comment('auto expire after reaching this');
            $table->boolean('can_change_quantity')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['item_id']);
            $table->index(['validity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_offers');
    }
};
