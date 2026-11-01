<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'USUARIO';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['CON_USR'];

    // Laravel busca "password"; nuestra columna es CON_USR
    public function getAuthPassword()
    {
        return $this->CON_USR;
    }

    // No tenemos columna remember_token
    public function getRememberTokenName()
    {
        return '';
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'COD_ROL', 'COD_ROL');
    }
}