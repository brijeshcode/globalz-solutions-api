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
        Schema::create('activity_log_details', function (Blueprint $table) {
            $table->id();

            // Link to parent
            $table->unsignedBigInteger('activity_log_id')->comment('FK to activity_logs');
            $table->unsignedInteger('batch_no')->comment('Batch number from parent');

            // What changed
            $table->string('model')->comment('Model that changed (parent or child)');
            $table->unsignedBigInteger('model_id')->comment('ID of the changed record');
            $table->string('event', 50)->comment('created, updated, deleted');

            // Change details
            $table->json('changes')->nullable()->comment('Old and new values');

            // Who and when
            $table->unsignedBigInteger('changed_by')->nullable()->comment('User ID');
            $table->timestamp('timestamp')->comment('When this specific change occurred');

            // Indexes
            $table->index(['activity_log_id', 'batch_no'], 'idx_activity_log');
            $table->index(['model', 'model_id'], 'idx_model_lookup');
            $table->index('timestamp', 'idx_timestamp');

            // Foreign keys
            $table->foreign('activity_log_id')->references('id')->on('activity_logs')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_details');
    }
};
