<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Categorie extends Model
{
    use SoftDeletes;

    protected $table = 'categories';
    
    protected $fillable = ['departement_id', 'nom', 'description'];

    public function departement()
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    public function scopeVisibleParUtilisateur($query, User $user)
    {
        if ($user->hasDepartmentWideAccess()) {
            return $query;
        }

        return $query->where('departement_id', $user->departement_id);
    }

    public function sousCategories()
{
    return $this->hasMany(SousCategorie::class, 'categorie_id');
}
}
