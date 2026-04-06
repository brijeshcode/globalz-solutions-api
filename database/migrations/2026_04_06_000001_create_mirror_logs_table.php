<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mirror_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'running', 'success', 'failed'])->default('pending');
            $table->unsignedBigInteger('triggered_by')->nullable()->comment('null = scheduler, user_id = manual');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('remote_host', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mirror_logs');
    }
};
