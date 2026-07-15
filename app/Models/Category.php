<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'proposed_by_seller_id', 'name', 'slug', 'description', 'image', 'status', 'sort_order',
    ];

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

    public function path(): string
    {
        $segments = [$this->slug];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($segments, $parent->slug);
            $parent = $parent->parent;
        }

        return implode('/', $segments);
    }
}
