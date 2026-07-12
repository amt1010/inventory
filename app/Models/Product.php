<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table ='menu';
    protected $primaryKey ='id';
    protected $fillable = [
        'name','catgry_typ','created_at' 
    ];
}
