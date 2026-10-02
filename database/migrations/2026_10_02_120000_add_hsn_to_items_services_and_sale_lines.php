<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('hsn')->nullable()->after('description');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('hsn')->nullable()->after('name');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('hsn')->nullable()->after('item_id');
        });

        Schema::table('sale_services', function (Blueprint $table) {
            $table->string('hsn')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('hsn'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('hsn'));
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('hsn'));
        Schema::table('sale_services', fn (Blueprint $table) => $table->dropColumn('hsn'));
    }
};
