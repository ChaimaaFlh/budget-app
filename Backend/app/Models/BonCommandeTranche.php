<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonCommandeTranche extends Model
{
    protected $table = 'bon_commande_tranches';

    protected $fillable = [
        'bon_commande_id',
        'annuite',
        'montant',
        'ordre',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function bonCommande()
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }
}