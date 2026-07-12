<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submenu extends Model
{
    use HasFactory;

    protected $table ='submenu';
    protected $primaryKey ='id';
    protected $fillable = [
        'name','m_name','created_at','updated_at' 
    ];
}
