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
        Schema::create('commission_targets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('prefix', ['COMTAR'])->default('COMTAR');
            $table->datetime('date');
            $table->date('effective_date')->comment('always first date on the month');
            
            $table->string('name', 50)->unique();
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
        Schema::dropIfExists('commission_targets');
    }
};
