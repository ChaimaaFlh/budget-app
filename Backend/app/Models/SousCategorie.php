<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class SousCategorie extends Model
{
    use SoftDeletes;

    protected $table = 'sous_categories';

    protected $fillable = ['categorie_id', 'nom', 'description'];
    
    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function ligneBudgets()
    {
        return $this->hasMany(LigneBudget::class, 'sous_categorie_id');
    }
}
