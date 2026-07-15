<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEditTrail extends Model
{
    protected $fillable = ['product_id', 'staff_id', 'changes', 'accepted_at'];

    protected $casts = [
        'changes' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
