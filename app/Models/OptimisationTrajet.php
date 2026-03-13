<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptimisationTrajet extends Model
{
    protected $fillable = [
        'date_calcul',
        'wilaya_depart',
        'wilaya_arrivee',
        'trajet_optimal_json', // Stocker le trajet calculé
        'colis_regroupes_json', // Liste des colis regroupés
        'nb_colis',
        'poids_total',
        'distance_km',
        'duree_estimee',
        'carburant_estime',
        'peages_estimes',
        'cout_total_estime'
    ];

    protected $casts = [
        'date_calcul' => 'datetime',
        'trajet_optimal_json' => 'array',
        'colis_regroupes_json' => 'array'
    ];
}
