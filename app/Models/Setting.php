<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['site_name', 'logo_path'];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['site_name' => config('app.name')]);
    }
}
