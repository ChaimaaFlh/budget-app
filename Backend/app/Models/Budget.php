<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Budget extends Model
{
    use SoftDeletes;

    protected $table = 'budgets';
    
    protected $fillable = ['nom', 'code', 'montant_global', 'description', 'annee', 'statut', 'type'];

    protected $casts = ['montant_global' => 'decimal:2'];


    public function ligne_budgets(){
        return $this->hasMany(LigneBudget::class, 'budget_id');
    }

    public function departements()
    {
        return $this->belongsToMany(Departement::class, 'budget_departement')
            ->withTimestamps();
    }

    public function estClos(): bool
    {
        return $this->statut === 'clos';
    }
}