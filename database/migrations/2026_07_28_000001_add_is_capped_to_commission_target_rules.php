<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_target_rules', function (Blueprint $table) {
            // When true, the reward stops growing once achievement reaches maximum_amount.
            // When false, maximum_amount is only the progress target — the reward keeps
            // growing past it. Defaults true to preserve the previous (always-capped) behaviour.
            $table->boolean('is_capped')->default(true)->after('maximum_amount');
        });
    }

    public function down(): void
    {
        Schema::table('commission_target_rules', function (Blueprint $table) {
            $table->dropColumn('is_capped');
        });
    }
};
