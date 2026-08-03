<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('tax_usd', 12, 4)->default(0)->after('total_usd');
            $table->decimal('tax_usd_percent', 5, 2)->default(0)->after('tax_usd');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['tax_usd', 'tax_usd_percent']);
        });
    }
};
