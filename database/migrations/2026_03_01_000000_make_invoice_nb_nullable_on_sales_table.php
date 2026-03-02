<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_nb1', 200)->nullable()->default(null)->change();
            $table->string('invoice_nb2', 200)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_nb1', 200)->nullable(false)->default('Payment in USD or Market Price.')->change();
            $table->string('invoice_nb2', 200)->nullable(false)->default('ملاحظة : ألضريبة على ألقيمة المضافة لا تسترد بعد ثلاثة أشهر من تاريخ إصدار ألفاتورة')->change();
        });
    }
};
