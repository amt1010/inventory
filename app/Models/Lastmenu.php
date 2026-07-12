<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lastmenu extends Model
{
    use HasFactory;

    protected $table ='last_menu';
    protected $primaryKey ='id';
    protected $fillable = [
        'name','created_at' 
    ];
}
