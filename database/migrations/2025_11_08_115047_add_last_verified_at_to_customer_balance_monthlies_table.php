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
        Schema::table('customer_balance_monthlies', function (Blueprint $table) {
            $table->timestamp('last_verified_at')->nullable()->comment('when full rebuild last run')->after('closing_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_balance_monthlies', function (Blueprint $table) {
            $table->dropColumn('last_verified_at');
        });
    }
};
