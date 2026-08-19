<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('seller_site_name')->nullable();
            $table->string('seller_logo_path')->nullable();
            $table->string('seller_theme_accent_color')->default('#059669');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['seller_site_name', 'seller_logo_path', 'seller_theme_accent_color']);
        });
    }
};
