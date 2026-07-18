<?php

namespace App\Filament\Support;

use App\Models\Category;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class CategoryTree
{
    /**
     * How many levels of subcategories can be added inline in one create form.
     * Filament cannot nest repeaters to unbounded depth in a static schema, so
     * this is the practical cap; deeper trees are built by editing a child and
     * adding more beneath it.
     */
    public const MAX_DEPTH = 5;

    /**
     * A recursively-nested "Subcategories" repeater. Each row is a subcategory
     * name plus its own "Add subcategory" repeater, down to $remainingDepth.
     */
    public static function subcategoriesRepeater(int $remainingDepth = self::MAX_DEPTH): Repeater
    {
        $schema = [
            TextInput::make('name')
                ->label('Subcategory name')
                ->required(),
        ];

        if ($remainingDepth > 1) {
            $schema[] = self::subcategoriesRepeater($remainingDepth - 1);
        }

        return Repeater::make('subcategories')
            ->label('Subcategories')
            ->schema($schema)
            ->addActionLabel('Add subcategory')
            ->collapsible()
            ->defaultItems(0)
            ->visibleOn('create');
    }

    /**
     * Recursively persist the nested repeater data as Category records beneath
     * $parent. $attributes is merged into every created node (e.g. the status,
     * or a seller's proposed_by_seller_id).
     *
     * @param  array<int, array<string, mixed>>|null  $subcategories
     * @param  array<string, mixed>  $attributes
     */
    public static function persist(Category $parent, ?array $subcategories, array $attributes = []): void
    {
        foreach ($subcategories ?? [] as $node) {
            $name = trim((string) ($node['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $child = Category::create(array_merge($attributes, [
                'parent_id' => $parent->id,
                'name' => $name,
                'slug' => self::uniqueSlug($name, $parent->id),
            ]));

            self::persist($child, $node['subcategories'] ?? [], $attributes);
        }
    }

    /**
     * A slug unique among siblings under the given parent.
     */
    public static function uniqueSlug(string $name, ?int $parentId): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('parent_id', $parentId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
