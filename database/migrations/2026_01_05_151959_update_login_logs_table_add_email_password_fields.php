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
        Schema::table('login_logs', function (Blueprint $table) {
            // Make user_id nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Add email and password fields for tracking failed login attempts
            $table->string('email')->nullable()->after('user_id');
            $table->string('password')->nullable()->after('email');
            $table->text('note')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            // Revert user_id to non-nullable
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            // Drop email, password and note fields
            $table->dropColumn(['email', 'password', 'note']);
        });
    }
};
