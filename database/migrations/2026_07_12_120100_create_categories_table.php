<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('draft'); // draft|published
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Enforces uniqueness among siblings that share a non-null parent.
            // Root-level (parent_id IS NULL) slug uniqueness is enforced at the
            // application layer in CategoryResource (Task 8), since MySQL unique
            // indexes treat NULL values as distinct from one another.
            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
