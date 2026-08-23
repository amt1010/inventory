<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('material_type')->default('raw_material')->after('category_id');
            $table->string('created_by')->nullable()->after('sort_order');
        });

        // SQLite (used in tests) can't alter a column's nullability or drop/
        // re-add a foreign key in place the way MySQL can; recreate the FK
        // as nullable on both drivers via the schema builder's own
        // change()/dropForeign() so this migration runs identically in both.
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
        });

        // material_type has no real default going forward (the importer and
        // the manual form both always set it explicitly) -- the temporary
        // 'raw_material' default above only exists to backfill existing rows
        // without a NOT NULL violation; drop it now that backfill is done.
        Schema::table('products', function (Blueprint $table) {
            $table->string('material_type')->default(null)->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('created_by')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->dropColumn(['material_type', 'created_by']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
