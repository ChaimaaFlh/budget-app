<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LigneBudgetAnnuite extends Model
{
    protected $table = 'ligne_budget_annuites';

    protected $fillable = ['ligne_budget_id', 'annee', 'montant'];

    protected $casts = ['montant' => 'decimal:2'];

    public function ligneBudget()
    {
        return $this->belongsTo(LigneBudget::class, 'ligne_budget_id');
    }
}
