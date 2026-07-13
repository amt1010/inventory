<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->default('Request a Quote');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('company')->nullable();
            $table->string('country')->nullable();
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('message')->nullable();
            $table->string('contact_preference')->default('email'); // email|phone
            $table->string('source_url')->nullable();
            $table->string('status')->default('new'); // new|in_progress|closed
            $table->foreignId('assigned_to')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
