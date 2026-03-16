<?php
// app/Models/Navette.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Navette extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'heure_depart',
        'heure_arrivee',
        'wilaya_depart_id',
        'wilaya_transit_id',
        'wilaya_arrivee_id',
        'livreur_id', // Changé de chauffeur_id à livreur_id
        'vehicule_immatriculation',
        'capacite_max',
        'status',
        'date_depart',
        'date_arrivee_prevue',
        'date_arrivee_reelle',
        'prix_base',
        'prix_par_colis',
        'distance_km',
        'carburant_estime',
        'peages_estimes',
        'created_by',
        'notes'
    ];

    protected $casts = [
        'heure_depart' => 'datetime',
        'heure_arrivee' => 'datetime',
        'date_depart' => 'datetime',
        'date_arrivee_prevue' => 'datetime',
        'date_arrivee_reelle' => 'datetime',
        'prix_base' => 'decimal:2',
        'prix_par_colis' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'carburant_estime' => 'decimal:2',
        'peages_estimes' => 'decimal:2',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->reference)) {
                $model->reference = 'NAV-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Relations
     */
    public function wilayaDepart()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_depart_id', 'code');
    }

    public function wilayaTransit()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_transit_id', 'code');
    }

    public function wilayaArrivee()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_arrivee_id', 'code');
    }

    public function livreur() // Renommé de chauffeur() à livreur()
    {
        return $this->belongsTo(Livreur::class, 'livreur_id');
    }

    public function colis()
    {
        return $this->belongsToMany(Colis::class, 'navette_colis')
            ->withPivot('position_chargement', 'date_chargement', 'date_dechargement', 'qr_code_scan', 'incident_notes')
            ->withTimestamps();
    }

    public function livraisons()
    {
        return $this->hasMany(Livraison::class);
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gains()
    {
        return $this->hasMany(GainLivreur::class);
    }

    /**
     * Accesseurs
     */
    public function getNbColisAttribute(): int
    {
        return $this->colis()->count();
    }

    public function getPoidsTotalAttribute(): float
    {
        return $this->colis()->sum('poids');
    }

    public function getValeurTotaleAttribute(): float
    {
        return $this->colis()->sum('colis_prix');
    }

    public function getTauxRemplissageAttribute(): float
    {
        if ($this->capacite_max <= 0) return 0;
        return round(($this->nb_colis / $this->capacite_max) * 100, 2);
    }

    public function getDureeReelleAttribute(): ?string
    {
        if (!$this->date_depart || !$this->date_arrivee_reelle) {
            return null;
        }

        $duree = $this->date_depart->diffInMinutes($this->date_arrivee_reelle);
        $heures = floor($duree / 60);
        $minutes = $duree % 60;

        return $heures . 'h ' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Scopes
     */
    public function scopeByWilayaDepart($query, $wilayaId)
    {
        return $query->where('wilaya_depart_id', $wilayaId);
    }

    public function scopeByWilayaArrivee($query, $wilayaId)
    {
        return $query->where('wilaya_arrivee_id', $wilayaId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeEnCours($query)
    {
        return $query->where('status', 'en_cours');
    }

    public function scopePlanifiees($query)
    {
        return $query->where('status', 'planifiee');
    }

    public function scopeTerminees($query)
    {
        return $query->where('status', 'terminee');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date_depart', $date);
    }

    public function scopeByPeriode($query, $debut, $fin)
    {
        return $query->whereBetween('date_depart', [$debut, $fin]);
    }

    public function scopeWithLivreurDisponible($query) // Renommé de withChauffeurDisponible
    {
        return $query->whereHas('livreur', function ($q) {
            $q->where('desactiver', false);
        });
    }
}
