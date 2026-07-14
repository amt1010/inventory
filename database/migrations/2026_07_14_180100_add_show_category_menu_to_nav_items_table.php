<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->boolean('show_category_menu')->default(false)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->dropColumn('show_category_menu');
        });
    }
};
