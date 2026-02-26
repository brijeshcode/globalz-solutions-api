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
        Schema::table('price_lists', function (Blueprint $table) {
            $table->renameColumn('is_default', 'is_default_inv');
        });

        Schema::table('price_lists', function (Blueprint $table) {
            $table->boolean('is_default_inx')->default(false)->after('is_default_inv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropColumn('is_default_inx');
        });

        Schema::table('price_lists', function (Blueprint $table) {
            $table->renameColumn('is_default_inv', 'is_default');
        });
    }
};
