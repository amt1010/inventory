<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->json('applications')->nullable();
            $table->string('spec_sheet_path')->nullable();
            $table->string('price_display')->nullable();
            $table->string('status')->default('pending_review'); // pending_review|published|rejected|archived
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
