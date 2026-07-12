<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Allnews extends Model
{
    use HasFactory;

    protected $table ='all_news';
    protected $primaryKey ='id';
    protected $fillable = [
        'category', 'subcategory', 'title', 'description', 'image', 
        'status', 'emp_id', 'time','created_at', 'updated_at'
    ];
}
