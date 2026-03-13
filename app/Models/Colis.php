<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Colis extends Model
{
    use HasFactory;

    protected $fillable = [
        'colis_type',
        'colis_label',
        'colis_photo',
        'colis_description',
        'poids',
        'hauteur',
        'largeur',
        'colis_prix',
    ];

    protected $casts = [
        'poids'   => 'float',
        'hauteur' => 'float',
        'largeur' => 'float',
        'colis_prix' => 'float',
    ];





    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    /**
     * Relations
     */
    public function demandeLivraisons()
    {
        return $this->hasMany(DemandeLivraison::class);
    }
}
