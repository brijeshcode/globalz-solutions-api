<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_target_rules', function (Blueprint $table) {
            // Change type from enum to string for future flexibility
            $table->string('type', 50)->change();

            // New columns
            $table->string('period', 20)->default('monthly')->after('type');
            $table->string('amount_type', 20)->default('set')->after('include_type');     // 'set' or 'auto'
            $table->unsignedInteger('number_of_months')->default(0)->after('amount_type'); // 0 = not applicable
            $table->decimal('push_target_percent', 8, 2)->default(0)->after('number_of_months');
            $table->string('reward_type', 20)->default('percent')->after('maximum_amount'); // 'fixed' or 'percent'
            $table->decimal('fixed_reward', 14, 4)->nullable()->after('percent');
        });
    }

    public function down(): void
    {
        Schema::table('commission_target_rules', function (Blueprint $table) {
            $table->dropColumn(['period', 'amount_type', 'number_of_months', 'push_target_percent', 'reward_type', 'fixed_reward']);
            $table->enum('type', ['fuel', 'payment', 'sale'])->change();
        });
    }
};
