<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'seller_id', 'category_id', 'name', 'slug', 'sku', 'short_description',
        'description', 'features', 'applications', 'spec_sheet_path',
        'price_display', 'quantity', 'status', 'rejection_reason', 'sort_order',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function customAttributes(): MorphMany
    {
        return $this->morphMany(CustomAttribute::class, 'attributable');
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function favoritedBy(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function editTrails(): HasMany
    {
        return $this->hasMany(ProductEditTrail::class);
    }

    public function latestPendingEditTrail(): ?ProductEditTrail
    {
        return $this->editTrails()->whereNull('accepted_at')->latest()->first();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publish(): bool
    {
        if (blank($this->price_display)) {
            return false;
        }

        if (! $this->category->isPublished()) {
            return false;
        }

        $this->status = 'published';
        $this->save();

        return true;
    }

    public function statusAfterEdit(): string
    {
        return $this->status === 'published' ? 'pending_review' : $this->status;
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function path(): string
    {
        return $this->category->path().'/'.$this->slug;
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
        ];
    }
}
