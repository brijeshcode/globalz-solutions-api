<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_status_histories', function (Blueprint $table) {
            $table->foreignId('car_id')->nullable()->after('changed_by')
                  ->constrained('cars')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sale_status_histories', function (Blueprint $table) {
            $table->dropForeign(['car_id']);
            $table->dropColumn('car_id');
        });
    }
};
