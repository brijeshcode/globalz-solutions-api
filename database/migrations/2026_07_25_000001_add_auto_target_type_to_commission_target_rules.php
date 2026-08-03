<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_target_rules', function (Blueprint $table) {
            // Only relevant when amount_type = 'auto'. Decides how the computed auto target
            // (avg × (1 + push%)) is applied:
            //   min  -> [min = T, max = 0]   (threshold; must reach T)  — default
            //   max  -> [min = 0, max = T]   (ceiling; scale from 0)
            //   both -> [min = T, max = T]   (T is both threshold and cap)
            $table->string('auto_target_type', 10)->default('min')->after('push_target_percent');
        });
    }

    public function down(): void
    {
        Schema::table('commission_target_rules', function (Blueprint $table) {
            $table->dropColumn('auto_target_type');
        });
    }
};
