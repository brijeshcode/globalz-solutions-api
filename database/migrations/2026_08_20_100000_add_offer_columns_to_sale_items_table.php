<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_offer_id')->nullable()->after('item_id')->index();
            $table->enum('offer_role', ['main', 'free'])->nullable()->after('item_offer_id');

            $table->foreign('item_offer_id')->references('id')->on('item_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['item_offer_id']);
            $table->dropColumn(['item_offer_id', 'offer_role']);
        });
    }
};
