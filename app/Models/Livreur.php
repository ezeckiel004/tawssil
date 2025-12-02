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
        'desactiver'

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
}
