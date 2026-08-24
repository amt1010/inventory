<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryChainResolver
{
    /**
     * @param  array{parent_name?: ?string, parent_description?: ?string, sub1_name?: ?string, sub1_description?: ?string, sub2_name?: ?string, sub2_description?: ?string}  $row
     */
    public function resolve(array $row): Category
    {
        $levels = [
            [$row['parent_name'] ?? null, $row['parent_description'] ?? null],
            [$row['sub1_name'] ?? null, $row['sub1_description'] ?? null],
            [$row['sub2_name'] ?? null, $row['sub2_description'] ?? null],
        ];

        // A level only "counts" (and gets created, using the placeholder if
        // blank) when it or a deeper level actually has a name in the row --
        // trailing blank levels are simply absent, not placeholder rows.
        $deepestPresent = -1;
        foreach ($levels as $index => [$name]) {
            if (filled($name)) {
                $deepestPresent = $index;
            }
        }

        $parent = null;

        for ($index = 0; $index <= $deepestPresent; $index++) {
            [$name, $description] = $levels[$index];
            $name = filled($name) ? trim($name) : Category::PLACEHOLDER;

            $parent = $this->resolveLevel($name, $description, $parent?->id);
        }

        return $parent;
    }

    private function resolveLevel(string $name, ?string $description, ?int $parentId): Category
    {
        $existing = Category::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Category::create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $parentId),
            'description' => filled($description) ? $description : null,
            'status' => 'draft',
            'created_by' => 'admin_bulk_upload',
        ]);
    }

    private function uniqueSlug(string $name, ?int $parentId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('parent_id', $parentId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
