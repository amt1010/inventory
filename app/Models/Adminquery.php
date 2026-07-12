<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adminquery extends Model
{
    use HasFactory;

    protected $table ='admn_qery';
    protected $primaryKey ='id';
    protected $fillable = [
        'query_for', 'name', 'code', 'user_id', 'reason', 'status', 'created_at', 'updated_at'
    ];
}
