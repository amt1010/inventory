<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Features and applications move from JSON arrays (one bullet per row) to
     * rich-text HTML. Widen the columns to TEXT first (a JSON column rejects
     * arbitrary HTML), then convert any existing array data into a bulleted list.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('features')->nullable()->change();
            $table->text('applications')->nullable()->change();
        });

        foreach (DB::table('products')->get(['id', 'features', 'applications']) as $product) {
            DB::table('products')->where('id', $product->id)->update([
                'features' => self::arrayToHtml($product->features),
                'applications' => self::arrayToHtml($product->applications),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('features')->nullable()->change();
            $table->json('applications')->nullable()->change();
        });
    }

    private static function arrayToHtml(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        // Already HTML / a plain string, not a JSON array -- leave it as-is.
        if (! is_array($decoded)) {
            return $raw;
        }

        $items = array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $decoded),
            fn ($item) => $item !== '',
        ));

        if ($items === []) {
            return null;
        }

        return '<ul>'.implode('', array_map(fn ($item) => '<li>'.e($item).'</li>', $items)).'</ul>';
    }
};
