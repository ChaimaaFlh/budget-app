<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facture extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bon_commande_id', 'ref_facture', 'montant',
        'date_reception', 'date_paiement', 'date_echeance', 'statut', 'type_reglement', 'piece_jointe',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_reception' => 'date',
        'date_paiement' => 'date', 'date_echeance' => 'date',
    ];

    public function bonCommande()
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function documents()
    {
        return $this->hasMany(FactureDocument::class, 'facture_id');
    }
}