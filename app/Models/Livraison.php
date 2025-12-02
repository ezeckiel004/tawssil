<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Livraison extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'demande_livraisons_id',
        'livreur_distributeur_id',
        'livreur_ramasseur_id',
        'bordereau_id',
        'code_pin',
        'date_ramassage',
        'date_livraison',
        'status',
    ];

    protected $casts = [
        'status'         => 'string',
        'date_ramassage' => 'date',
        'date_livraison' => 'date',
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
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function demandeLivraison()
    {
        return $this->belongsTo(DemandeLivraison::class, 'demande_livraisons_id');
    }

    public function livreurDistributeur()
    {
        return $this->belongsTo(Livreur::class, 'livreur_distributeur_id');
    }

    public function livreurRamasseur()
    {
        return $this->belongsTo(Livreur::class, 'livreur_ramasseur_id');
    }

    public function bordereau()
    {
        return $this->belongsTo(Bordereau::class);
    }

    public function commentaires()
    {
        return $this->hasMany(Commentaire::class);
    }
}
