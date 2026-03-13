<?php
// app/Models/Commune.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'nom',
        'nom_arabe',
        'wilaya_code',
        'postal_code'
    ];

    public function wilaya()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_code', 'code');
    }
}
