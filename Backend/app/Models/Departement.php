<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    protected $fillable = ['nom'];

    public function users()
    {
        return $this->hasMany(User::class, 'departement_id');
    }

    public function ligneBudgets()
    {
        return $this->hasMany(LigneBudget::class, 'departement_id');
    }

    public function categories()
    {
        return $this->hasMany(Categorie::class, 'departement_id');
    }

    public function bonsCommande()
    {
        return $this->hasMany(BonCommande::class, 'departement_id');
    }

    public function budgets()
    {
        return $this->belongsToMany(Budget::class, 'budget_departement')
            ->withTimestamps();
    }
}