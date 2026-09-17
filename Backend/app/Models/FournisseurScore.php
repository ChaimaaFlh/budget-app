<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FournisseurScore extends Model
{
    protected $fillable = ['fournisseur_id', 'annee', 'score'];
    protected $casts = ['score' => 'decimal:2', 'annee' => 'integer'];

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class);
    }
}
