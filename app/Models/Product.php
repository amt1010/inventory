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
        if ($this->publishBlockers() !== []) {
            return false;
        }

        $this->status = 'published';
        $this->save();

        return true;
    }

    /**
     * Every unmet precondition keeping this product from being published, each
     * phrased as an instruction the user can act on. Empty when the product is
     * ready to go live. Centralising the checks here lets callers (e.g. the
     * admin Publish action) tell the user *all* the details to fix at once
     * instead of failing silently or one reason at a time.
     *
     * @return list<string>
     */
    public function publishBlockers(): array
    {
        $blockers = [];

        if (blank($this->price_display)) {
            $blockers[] = 'Set a price on the product’s edit form (the “Price” field, Admin only).';
        }

        if (! $this->category->isPublished()) {
            $blockers[] = 'Publish its category “'.$this->category->name.'” first — it is not live yet.';
        }

        return $blockers;
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
        // Every key must be a real column: the Scout `database` engine matches
        // `column LIKE %term%` using these keys as column names. Indexing the
        // descriptive copy (not just name/sku) lets a brand or keyword that only
        // appears in the description surface the product.
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
        ];
    }
}
