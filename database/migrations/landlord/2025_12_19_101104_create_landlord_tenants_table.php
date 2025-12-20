<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // staging, live, abc
            $table->string('domain')->unique(); // staging.globalzsolutions.com
            $table->string('database'); // staging_db

            // Separate credentials per tenant
            $table->string('database_username')->nullable();
            $table->text('database_password')->nullable(); // Encrypted

            // Optional: Additional metadata
            $table->json('settings')->nullable(); // Features, config, etc.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
