<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clearer name: this column holds the reward CALCULATION type (fixed | dynamic),
        // i.e. how the reward grows. The old name "percent_type" was misleading.
        // Raw CHANGE is used because Schema::renameColumn() mangles an enum's default value.
        DB::statement("ALTER TABLE commission_target_rules CHANGE percent_type reward_calculation_type ENUM('fixed','dynamic') NOT NULL DEFAULT 'dynamic'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE commission_target_rules CHANGE reward_calculation_type percent_type ENUM('fixed','dynamic') NOT NULL DEFAULT 'dynamic'");
    }
};
