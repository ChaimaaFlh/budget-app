<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactureDocument extends Model
{
    protected $fillable = [
        'facture_id',
        'type',
        'nom_original',
        'chemin',
        'mime_type',
        'taille_octets',
    ];

    protected $casts = [
        'taille_octets' => 'integer',
    ];

    public function facture()
    {
        return $this->belongsTo(Facture::class, 'facture_id');
    }
}