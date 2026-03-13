<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Livreur extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'demande_adhesions_id',
        'type', // 'distributeur' or 'ramasseur'
        'desactiver',
        'wilaya_id', // ⚠️ Code wilaya du livreur (01 à 58)
    ];

    protected $casts = [
        'desactiver' => 'boolean',
        'wilaya_id' => 'string',
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
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function demandeAdhesion()
    {
        return $this->belongsTo(DemandeAdhesion::class, 'demande_adhesions_id');
    }

    public function livraisonsDistribution()
    {
        return $this->hasMany(Livraison::class, 'livreur_distributeur_id');
    }

    public function livraisonsRamassage()
    {
        return $this->hasMany(Livraison::class, 'livreur_ramasseur_id');
    }

    public function commentaires()
    {
        return $this->hasMany(Commentaire::class);
    }

    /**
     * Scope pour filtrer par wilaya
     */
    public function scopeByWilaya($query, $wilayaId)
    {
        return $query->where('wilaya_id', $wilayaId);
    }

    /**
     * Scope pour les livreurs actifs
     */
    public function scopeActif($query)
    {
        return $query->where('desactiver', false);
    }

    /**
     * Scope pour les livreurs inactifs
     */
    public function scopeInactif($query)
    {
        return $query->where('desactiver', true);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Vérifier si le livreur est disponible
     */
    public function isDisponible(): bool
    {
        return !$this->desactiver;
    }

    /**
     * Obtenir le nom complet du livreur via l'utilisateur associé
     */
    public function getNomCompletAttribute(): string
    {
        return $this->user ? $this->user->prenom . ' ' . $this->user->nom : 'Nom inconnu';
    }
}