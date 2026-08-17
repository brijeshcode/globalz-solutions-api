<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Central flag table for the "syncin" feature: marks whether a given
     * transaction record has been copied into the client's legacy system.
     * Keyed by the record's own table name + id so no existing migration
     * needs to change.
     */
    public function up(): void
    {
        Schema::create('sync_to_old', function (Blueprint $table) {
            $table->id();
            $table->string('model')->comment("The record's table name, e.g. sales, customer_payments");
            $table->unsignedBigInteger('model_id')->comment("The record's id within its own table");
            $table->boolean('is_synced')->default(false)->comment('Whether the record has been copied to the legacy system');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['model', 'model_id'], 'sync_to_old_model_record_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_to_old');
    }
};
