<?php

namespace App\Models;

use App\Exceptions\CategoryWouldFormCycle;
use App\Support\CategoryHierarchy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Category extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'parent_id', 'proposed_by_seller_id', 'name', 'slug', 'description', 'image', 'status', 'sort_order',
    ];

    protected static function booted(): void
    {
        // Backstop against a corrupt tree: refuse to save a category whose
        // parent is, directly or transitively, itself. descendantAndSelfIds()
        // reflects the committed tree, so setting parent_id to the category's
        // own id or any of its current descendants is what would close a loop.
        static::saving(function (self $category): void {
            if ($category->parent_id === null || ! $category->exists) {
                return;
            }

            if (in_array((int) $category->parent_id, CategoryHierarchy::descendantAndSelfIds($category), true)) {
                throw CategoryWouldFormCycle::for($category);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'proposed_by_seller_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'description' => strip_tags((string) $this->description),
        ];
    }

    public function path(): string
    {
        $segments = [$this->slug];
        $seen = [$this->id => true];
        $parent = $this->parent;

        // Guard against a corrupt tree (a category that is, directly or
        // transitively, its own ancestor): stop the moment we revisit a node
        // instead of walking the parent chain forever.
        while ($parent && ! isset($seen[$parent->id])) {
            array_unshift($segments, $parent->slug);
            $seen[$parent->id] = true;
            $parent = $parent->parent;
        }

        return implode('/', $segments);
    }
}
