<?php
// app/Models/GestionnaireGain.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GestionnaireGain extends Model
{
    use HasFactory;

    protected $fillable = [
        'gestionnaire_id',
        'livraison_id',
        'wilaya_type',
        'montant_commission',
        'pourcentage_applique',
        'date_calcul',
        'status',
        'date_demande',
        'date_paiement',
        'note_admin'
    ];

    protected $casts = [
        'montant_commission' => 'decimal:2',
        'pourcentage_applique' => 'decimal:2',
        'date_calcul' => 'datetime',
        'date_demande' => 'datetime',
        'date_paiement' => 'datetime'
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

    public function gestionnaire()
    {
        return $this->belongsTo(Gestionnaire::class);
    }

    public function livraison()
    {
        return $this->belongsTo(Livraison::class);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('status', 'en_attente');
    }

    public function scopeDemandeEnvoyee($query)
    {
        return $query->where('status', 'demande_envoyee');
    }

    public function scopeNonPayes($query)
    {
        return $query->whereIn('status', ['en_attente', 'demande_envoyee']);
    }
}
