<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'deleted_at']);
        });

        Schema::create('expense_category_expense_tag', function (Blueprint $table) {
            $table->foreignId('expense_tag_id')->constrained('expense_tags')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->unique(['expense_tag_id', 'expense_category_id'], 'expense_cat_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_category_expense_tag');
        Schema::dropIfExists('expense_tags');
    }
};
