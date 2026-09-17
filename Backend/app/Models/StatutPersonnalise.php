<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatutPersonnalise extends Model
{
    protected $table = 'statuts_personnalises';
    protected $fillable = ['type', 'libelle', 'couleur'];
}
