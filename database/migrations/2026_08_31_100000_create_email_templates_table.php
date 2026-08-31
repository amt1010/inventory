<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_system')->default(false);
            $table->string('subject');
            $table->longText('body');
            $table->string('default_cc')->nullable();
            $table->string('default_bcc')->nullable();
            $table->string('draft_subject');
            $table->longText('draft_body');
            $table->string('draft_default_cc')->nullable();
            $table->string('draft_default_bcc')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
