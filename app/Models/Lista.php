<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lista extends Model
{
    use HasFactory;
    public function consultas()
    {
        return $this->belongsToMany('App\Models\Consulta')->withTimestamps();
    }
}
