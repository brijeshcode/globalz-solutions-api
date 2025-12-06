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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();

            $table->datetime('date');

            $table->enum('prefix', ['SAL'])->default('SAL');
            $table->string('code', 50)->unique();
            
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('account_id')->index();
            
            $table->unsignedTinyInteger('month')->comment('1 for Jan, 12 for Dec');
            $table->year('year');

            $table->decimal('sub_total', 8,2)->default(0);
            $table->decimal('base_salary',8, 2)->default(0);
            $table->decimal('advance_payment', 8,2)->default(0);
            $table->decimal('others', 8,2)->default(0);
            $table->decimal('final_total', 8,2);

            $table->text('others_note')->nullable();
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
        Schema::dropIfExists('salaries');
    }
};
