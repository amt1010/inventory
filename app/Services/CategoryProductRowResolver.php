<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Shared by both bulk-import paths (Filament's CSV Importer and the XLSX
 * runner) so the category-resolution and product-duplicate-skip rules stay
 * in exactly one place. Returns an unsaved Product with category_id/name/
 * slug already set, or null when the row should produce no product at all
 * (blank product name -- category-only row -- or the product already
 * exists in its resolved category).
 */
class CategoryProductRowResolver
{
    /**
     * @param  array{product_name?: ?string, parent_name?: ?string, parent_description?: ?string, sub1_name?: ?string, sub1_description?: ?string, sub2_name?: ?string, sub2_description?: ?string}  $row
     */
    public function resolveProduct(array $row): ?Product
    {
        $category = (new CategoryChainResolver())->resolve([
            'parent_name' => $row['parent_name'] ?? null,
            'parent_description' => $row['parent_description'] ?? null,
            'sub1_name' => $row['sub1_name'] ?? null,
            'sub1_description' => $row['sub1_description'] ?? null,
            'sub2_name' => $row['sub2_name'] ?? null,
            'sub2_description' => $row['sub2_description'] ?? null,
        ]);

        $name = trim((string) ($row['product_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $existing = Product::query()
            ->where('category_id', $category->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return null;
        }

        $product = new Product();
        $product->category_id = $category->id;
        $product->name = $name;
        $product->slug = $this->uniqueSlug($name, $category->id);

        return $product;
    }

    private function uniqueSlug(string $name, int $categoryId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('category_id', $categoryId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
