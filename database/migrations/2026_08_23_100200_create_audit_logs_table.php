<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('importer_label');
            $table->foreignId('performed_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('successful_rows')->nullable();
            $table->unsignedInteger('failed_rows')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('filament_import_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
