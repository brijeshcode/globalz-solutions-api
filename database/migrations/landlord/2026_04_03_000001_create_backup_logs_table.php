<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('tenant_key', 100)->index();
            $table->string('database_name', 255);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('disk', ['local', 's3', 'ftp', 'dropbox'])->default('local');
            $table->enum('status', ['pending', 'running', 'success', 'failed'])->default('pending');
            $table->enum('tier', ['daily', 'weekly', 'monthly', 'yearly'])->default('daily');
            $table->string('compression', 20)->default('gzip');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('triggered_by')->nullable()->comment('null = scheduler, user_id = manual');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tier']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('backup_logs');
    }
};
