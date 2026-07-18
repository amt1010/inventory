<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class CategoryHierarchy
{
    public const SEPARATOR = ' › ';

    /**
     * Build `[id => "Top › Sub › Leaf"]` select options so category fields show
     * where each category sits in the tree, not just its leaf name. The label
     * always reflects the full ancestor path; the optional constraint only
     * limits which categories appear as selectable options.
     *
     * @param  (\Closure(Builder): mixed)|null  $constrain
     * @return array<int, string>
     */
    public static function options(?\Closure $constrain = null): array
    {
        $all = Category::query()->get(['id', 'parent_id', 'name'])->keyBy('id');

        $query = Category::query();

        if ($constrain) {
            $constrain($query);
        }

        $options = [];
        foreach ($query->get(['id']) as $category) {
            $options[$category->id] = self::pathLabel($category->id, $all);
        }

        asort($options);

        return $options;
    }

    /**
     * The " › "-joined ancestor path for a single category, given a keyed map of
     * every category (id => model with name + parent_id).
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $all
     */
    public static function pathLabel(int $id, $all): string
    {
        $names = [];
        $current = $all[$id] ?? null;
        $guard = 0;

        while ($current && $guard++ < 50) {
            array_unshift($names, $current->name);
            $current = $current->parent_id ? ($all[$current->parent_id] ?? null) : null;
        }

        return implode(self::SEPARATOR, $names);
    }

    /**
     * The ids of a category and all of its descendants -- the set that must be
     * excluded when choosing a parent, so a category can never become its own
     * ancestor.
     *
     * @return list<int>
     */
    public static function descendantAndSelfIds(Category $category): array
    {
        $all = Category::query()->get(['id', 'parent_id']);
        $childrenOf = [];
        foreach ($all as $node) {
            $childrenOf[$node->parent_id ?? 0][] = $node->id;
        }

        $ids = [];
        $collect = function (int $id) use (&$collect, $childrenOf, &$ids): void {
            $ids[] = $id;
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $collect($childId);
            }
        };
        $collect($category->id);

        return $ids;
    }
}
