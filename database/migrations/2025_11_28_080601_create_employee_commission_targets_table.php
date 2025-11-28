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
        Schema::create('employee_commission_targets', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('commission_target_id');
            $table->unsignedTinyInteger('month')->comment('1 for Jan, 12 for Dec');
            $table->year('year');
            
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_commission_targets');
    }
};
