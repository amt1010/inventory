<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'sort_order', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(function (ProductImage $image) {
            if ($image->is_primary) {
                static::where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
