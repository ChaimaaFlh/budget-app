<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class LigneBudget extends Model
{
    use SoftDeletes;

    protected $table = 'ligne_budgets';
    
    protected $fillable = [
        'sous_categorie_id',
        'categorie_id',
        'budget_id',
        'departement_id',
        'code',
        'intitule',
        'montant_alloue',
        'date_debut_amortissement',
        'duree_amortissement_annees'
    ];

    protected $casts = [
        'montant_alloue' => 'decimal:2',
        'date_debut_amortissement' => 'date',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function sous_categorie()
    {
        return $this->belongsTo(SousCategorie::class, 'sous_categorie_id');
    }

    public function bons_commande()
    {
        return $this->hasMany(BonCommande::class, 'ligne_budget_id');
    }
    
    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function annuites()
    {
        return $this->hasMany(LigneBudgetAnnuite::class, 'ligne_budget_id');
    }

    public function departement()
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    public function getDateFinValiditeAttribute()
    {
        return $this->date_debut_amortissement
            ->copy()
            ->addYears($this->duree_amortissement_annees);
    }

    public function estEncoreValide(): bool
    {
        return now()->lessThanOrEqualTo($this->date_fin_validite);
    }


    public function scopeVisibleParUtilisateur($query, $user)
    {
        if ($user->hasDepartmentWideAccess()) {
            return $query;
        }

        return $query->where('departement_id', $user->departement_id);
    }
}
