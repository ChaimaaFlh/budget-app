<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class BonCommande extends Model
{
    use SoftDeletes;

    protected $table = 'bons_commande';
    
    protected $fillable = [
        'user_id',
        'ligne_budget_id',
        'departement_id',
        'numero_bc',
        'intitule_bc',
        'montant_bc',
        'montant_consomme',
        'description',
        'date_achat',
        'fournisseur',
        'fournisseur_id', 'annuite', 'gestion_depassement',
        'repartition_groupe', 'repartition_ordre', 'repartition_total', 'mode_repartition',
        'type_paiement',
        'statut',
    ];

    protected $casts = [
        'montant_bc' => 'decimal:2',
        'montant_consomme' => 'decimal:2',
        'date_achat' => 'date',
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    public function ligneBudget()
    {
        return $this->belongsTo(LigneBudget::class, 'ligne_budget_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function factures()
    {
        return $this->hasMany(Facture::class, 'bon_commande_id');
    }
    public function fournisseurRelation() { return $this->belongsTo(Fournisseur::class, 'fournisseur_id'); }

    /**
     * Détail des tranches (annuité par annuité) lorsque ce bon a été créé en
     * mode "bon unique" avec répartition automatique du dépassement.
     */
    public function tranches()
    {
        return $this->hasMany(BonCommandeTranche::class, 'bon_commande_id')->orderBy('ordre');
    }

    public function scopeVisibleParUtilisateur($query, $user)
    {
        if ($user->hasDepartmentWideAccess()) {
            return $query;
        }

        return $query->where('departement_id', $user->departement_id);
    }

    /**
     * Montant (en centimes) déjà engagé sur une ligne budgétaire pour une
     * annuité donnée, tous modes de gestion confondus.
     *
     * Un bon "classique" ou une tranche issue du mode "plusieurs bons" est
     * compté directement via sa colonne montant_bc/annuite. Un bon créé en
     * mode "bon unique" porte un montant_bc GLOBAL (toutes annuités
     * confondues) : il est donc exclu du calcul direct et remplacé par le
     * détail de ses tranches (bon_commande_tranches), seule source fiable de
     * la répartition par annuité pour ce type de bon.
     *
     * C'est ce calcul qui doit être utilisé PARTOUT où l'on a besoin du
     * "disponible" d'une annuité (création/modification de bon, répartition
     * automatique, listing des annuités) afin que le résultat reste correct
     * quel que soit le mode utilisé par les bons déjà créés sur la ligne.
     */
    public static function engagementAnnuiteCentimes(int $ligneBudgetId, int $annee, ?int $excludeBonId = null): int
    {
        $toCentimes = fn ($montant) => (int) round(((float) $montant) * 100);

        $directCentimes = $toCentimes(
            static::where('ligne_budget_id', $ligneBudgetId)
                ->where('annuite', $annee)
                ->where(function ($query) {
                    $query->whereNull('mode_repartition')
                        ->orWhere('mode_repartition', '!=', 'bon_unique');
                })
                ->when($excludeBonId, fn ($query) => $query->where('id', '!=', $excludeBonId))
                ->sum('montant_bc')
        );

        $tranchesCentimes = $toCentimes(
            BonCommandeTranche::where('annuite', $annee)
                ->whereHas('bonCommande', function ($query) use ($ligneBudgetId, $excludeBonId) {
                    $query->where('ligne_budget_id', $ligneBudgetId);
                    if ($excludeBonId) {
                        $query->where('id', '!=', $excludeBonId);
                    }
                })
                ->sum('montant')
        );

        return $directCentimes + $tranchesCentimes;
    }
}