<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            // Snapshot of the customer's salesperson at payment creation time, so commission
            // crediting stays fixed even if the customer is later reassigned (mirrors how
            // sales and customer_returns store salesperson_id).
            $table->unsignedBigInteger('salesperson_id')->nullable()->index()->after('customer_id');
        });

        // Backfill existing rows from their customer's current salesperson.
        DB::statement('
            UPDATE customer_payments cp
            JOIN customers c ON c.id = cp.customer_id
            SET cp.salesperson_id = c.salesperson_id
            WHERE cp.salesperson_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropIndex(['salesperson_id']);
            $table->dropColumn('salesperson_id');
        });
    }
};
