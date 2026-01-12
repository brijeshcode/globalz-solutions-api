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
        Schema::table('income_transactions', function (Blueprint $table) {
            $table->decimal('amount_usd', 20, 8)->default(0)->after('amount');
            $table->unsignedBigInteger('currency_id')->nullable()->after('amount_usd');
            $table->decimal('currency_rate', 10, 4)->default(0)->after('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_transactions', function (Blueprint $table) {
            $table->dropColumn(['amount_usd', 'currency_id', 'currency_rate']);
        });
    }
};
