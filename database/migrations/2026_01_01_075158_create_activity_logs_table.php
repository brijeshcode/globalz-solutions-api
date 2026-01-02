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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Which entity this log tracks
            $table->string('model')->comment('Fully qualified model class name');
            $table->unsignedBigInteger('model_id')->comment('ID of the main entity');
            $table->string('model_display')->nullable()->comment('Human-readable identifier');

            // Last change info
            $table->string('last_event_type', 50)->comment('created, updated, deleted');
            $table->unsignedInteger('last_batch_no')->default(1)->comment('Current batch number');
            $table->unsignedBigInteger('last_changed_by')->nullable()->comment('User ID who made last change');
            $table->timestamp('timestamp')->comment('When last change occurred');

            // Tracking
            $table->boolean('seen_all')->default(false)->comment('All changes reviewed/approved');

            // Indexes
            $table->index(['model', 'model_id'], 'idx_model_lookup');
            $table->index('timestamp', 'idx_timestamp');
            $table->index('last_changed_by', 'idx_last_changed_by');

            // Foreign key
            $table->foreign('last_changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
