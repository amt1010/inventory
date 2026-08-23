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
        $seen = [];
        // A corrupt tree (categories pointing at each other, however it got that
        // way) would otherwise recurse forever here -- the same guard path() and
        // pathLabel() already use to stay bounded.
        $collect = function (int $id) use (&$collect, $childrenOf, &$ids, &$seen): void {
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $ids[] = $id;
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $collect($childId);
            }
        };
        $collect($category->id);

        return $ids;
    }

    /**
     * The full published category tree, nested to whatever depth the data
     * has. One query total (same flat-fetch-then-build-in-PHP technique as
     * descendantAndSelfIds()) regardless of depth.
     *
     * @return list<array{id: int, name: string, path: string, children: array}>
     */
    public static function publishedTree(): array
    {
        $all = Category::query()
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug']);

        $childrenOf = [];
        foreach ($all as $category) {
            $childrenOf[$category->parent_id ?? 0][] = $category;
        }

        $build = function (?int $parentId, string $parentPath, int $depth) use (&$build, $childrenOf): array {
            if ($depth > 50) {
                return [];
            }

            $nodes = [];
            foreach ($childrenOf[$parentId ?? 0] ?? [] as $category) {
                $path = $parentPath === '' ? $category->slug : $parentPath.'/'.$category->slug;

                $nodes[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'path' => $path,
                    'children' => $build($category->id, $path, $depth + 1),
                ];
            }

            return $nodes;
        };

        return $build(null, '', 0);
    }
}
