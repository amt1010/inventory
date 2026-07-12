<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addproduct extends Model
{
    use HasFactory;

    protected $table ='product';
    protected $primaryKey ='id';
    protected $fillable = [
        'name', 'category', 'subcategory', 'company', 'quantity', 'price', 
        'descrp', 'status', 'image','created_at', 'updated_at'
    ];
}
