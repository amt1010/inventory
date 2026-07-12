<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thirdmenu extends Model
{
    use HasFactory;

    protected $table ='thirdmenu';
    protected $primaryKey ='id';
    protected $fillable = [
        'name','m_id','sm_id','created_at','updated_at' 
    ];
}
