<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Gestionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wilaya_id',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
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
        });
    }

    /**
     * Relations
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function codesPromo()
    {
        return $this->hasMany(CodePromo::class);
    }

    /**
     * Scope pour filtrer par wilaya
     */
    public function scopeByWilaya($query, $wilayaId)
    {
        return $query->where('wilaya_id', $wilayaId);
    }

    /**
     * Scope pour les gestionnaires actifs
     */
    public function scopeActif($query)
    {
        return $query->where('status', 'active');
    }
}