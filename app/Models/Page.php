<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'meta_title', 'meta_description', 'status',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
