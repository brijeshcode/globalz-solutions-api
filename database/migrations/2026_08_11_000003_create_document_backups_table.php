<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->string('disk', 30); // s3 / ftp / dropbox / future drivers — kept as string for flexibility
            $table->string('status', 10)->default('success'); // success | failed
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->default(0);
            // Denormalized from the document so the ledger can be grouped/zipped on its own
            // (path shape: documents/{tenant}/{year}/{month}/{module}/{file}).
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('module')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('backed_up_at')->nullable(); // set on success only
            $table->timestamps();

            $table->unique(['document_id', 'disk']);
            $table->index(['disk', 'status']);
            $table->index(['module', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_backups');
    }
};
