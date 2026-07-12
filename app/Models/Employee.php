<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class Employee extends Authenticatable
{
    use HasFactory;

    protected $table ='employees';
    protected $primaryKey ='id';
    protected $fillable = [
        'emp_type', 'emp_name', 'email', 'contact_no', 'password', 'image',
         'full_addrs', 'created_at' 
    ];

    public function getAuthPassword()
    {
     return $this->password;
    }
}
