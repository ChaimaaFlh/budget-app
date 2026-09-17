<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Fournisseur extends Model {
    protected $fillable = ['nom', 'email', 'telephone', 'adresse', 'score', 'score_annee'];

    protected $casts = ['score' => 'decimal:2', 'score_annee' => 'integer'];

    public function bonsCommande() { return $this->hasMany(BonCommande::class); }
    public function scores() { return $this->hasMany(FournisseurScore::class); }
}
