<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product_image extends Model
{
    use HasFactory;

    protected $table ='product_images';
    protected $primaryKey ='id';
    protected $fillable = [
        'p_id', 'image', 'created_at', 'updated_at'
    ]; 
}
