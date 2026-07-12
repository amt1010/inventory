<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomAttribute extends Model
{
    protected $fillable = ['label', 'value', 'file_path', 'sort_order'];

    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }
}
