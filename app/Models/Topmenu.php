<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topmenu extends Model
{
    use HasFactory;

    protected $table ='frst_menu';
    protected $primaryKey ='id';
    protected $fillable = [
        'name','created_at' 
    ];
}
