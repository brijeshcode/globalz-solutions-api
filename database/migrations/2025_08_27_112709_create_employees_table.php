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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            
            // Basic Info (Required fields marked in requirements)
            $table->string('code', 50)->unique(); // Auto generated starting from 100
            $table->string('name'); // Required
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20); // Required
            $table->string('email')->nullable();
            $table->date('start_date')->nullable();
            $table->decimal('base_salary',8, 2)->nullable();
            
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Authorable fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'deleted_at']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
