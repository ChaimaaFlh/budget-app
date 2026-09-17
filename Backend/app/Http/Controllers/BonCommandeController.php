<?php

namespace App\Http\Controllers;

use App\Models\BonCommande;
use App\Models\LigneBudget;
use App\Support\ReferenceGenerator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\StatutPersonnalise;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BonCommandeController extends Controller
{
    public function index(Request $request)
    {
        $bons = BonCommande::visibleParUtilisateur($request->user())
            ->with(['ligneBudget.budget', 'user', 'fournisseurRelation', 'factures', 'tranches'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($bons, 200);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('bc.create')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'bc.create' requise.",
            ], 403);
        }
        if (ReferenceGenerator::isAutomatic($request->input('numero_bc'), 'BC')) {
            $request->merge(['numero_bc' => null]);
        }

        $validated = $request->validate([
            'numero_bc'       => 'nullable|string|unique:bons_commande,numero_bc',
            'ligne_budget_id' => 'required|exists:ligne_budgets,id',
            'intitule_bc'     => 'required|string|max:255',
            'montant_bc'      => 'required|numeric|min:0',
            'description'     => 'nullable|string',
            'date_achat'      => 'required|date',
            'fournisseur'     => 'required|string|max:255',
            'fournisseur_id'  => 'nullable|exists:fournisseurs,id',
            'annuite'         => 'nullable|integer|min:2000|max:2200',
            'gestion_depassement' => 'nullable|in:bloquer,surplus,report_annuite_suivante,repartition_automatique',
            'mode_repartition' => 'nullable|in:bons_multiples,bon_unique',
            'type_paiement'   => 'nullable|string|max:255',
            'statut'          => ['nullable', 'string', 'max:100', Rule::in($this->statutsDisponibles())],
            'departement_id'  => 'nullable|exists:departements,id',
        ]);

        $genererNumero = empty($validated['numero_bc'])
            || ReferenceGenerator::isAutomatic($validated['numero_bc'], 'BC');

        $ligne = LigneBudget::findOrFail($validated['ligne_budget_id']);

        $anneeAchat = Carbon::parse($validated['date_achat'])->year;
        $modeGestion = $validated['gestion_depassement'] ?? 'bloquer';
        $anneeBon = isset($validated['annuite']) ? (int) $validated['annuite'] : $anneeAchat;

        if ($modeGestion === 'repartition_automatique') {
            // La vérification d'une annuité unique ne s'applique pas ici : la
            // répartition sur plusieurs annuités est gérée par creerAvecRepartition().
        } else {
            $estReport = $modeGestion === 'report_annuite_suivante';
            if ($estReport && $anneeBon <= $anneeAchat) {
                return response()->json(['message' => "Choisissez une annuité postérieure à l'année d'achat pour le report."], 422);
            }
            if (!$estReport && $anneeBon !== $anneeAchat) {
                return response()->json(['message' => "L'annuité consommée doit correspondre à l'année d'achat."], 422);
            }
            $annuite = $ligne->annuites()->where('annee', $anneeBon)->first();
            if (!$annuite) {
                return response()->json(['message' => "Aucune annuité n'est prévue pour l'année {$anneeBon} sur cette ligne budgétaire."], 422);
            }
            $engageCentimes = BonCommande::engagementAnnuiteCentimes($ligne->id, $anneeBon);
            $depassementCentimes = $engageCentimes + $this->toCentimes($validated['montant_bc']) - $this->toCentimes($annuite->montant);
            if ($depassementCentimes > 0 && $modeGestion !== 'surplus') {
                return response()->json(['message' => "Dépassement de l'annuité sélectionnée.", 'depassement' => $depassementCentimes / 100], 422);
            }
            $validated['annuite'] = $anneeBon;
        }
        if (!empty($validated['fournisseur_id'])) $validated['fournisseur'] = \App\Models\Fournisseur::findOrFail($validated['fournisseur_id'])->nom;

        // Le département d'un bon de commande est toujours celui de sa ligne budgétaire.
        $departementId = $ligne->departement_id;

        if (!$request->user()->hasDepartmentWideAccess()
            && $departementId !== $request->user()->departement_id) {
            return response()->json([
                'message' => 'Un bon de commande doit appartenir au même département que sa ligne budgétaire.',
            ], 422);
        }

        if (!$ligne->estEncoreValide()) {
            return response()->json([
                'message' => "Impossible de créer un bon de commande : la période de validité de cette ligne budgétaire a expiré le {$ligne->date_fin_validite->format('d/m/Y')}.",
            ], 422);
        }

        $validated['departement_id'] = $departementId;
        $validated['user_id'] = $request->user()->id;

        if ($modeGestion === 'repartition_automatique') {
            $modeRepartition = $validated['mode_repartition'] ?? 'bons_multiples';
            return $this->creerAvecRepartition($validated, $ligne, $anneeAchat, $modeRepartition);
        }

        $bon = DB::transaction(function () use ($validated, $genererNumero) {
            $ligne = LigneBudget::lockForUpdate()->findOrFail($validated['ligne_budget_id']);
            $annuite = $ligne->annuites()
                ->where('annee', $validated['annuite'])
                ->lockForUpdate()
                ->firstOrFail();
            $engageCentimes = BonCommande::engagementAnnuiteCentimes($ligne->id, $validated['annuite']);
            $montantCentimes = $this->toCentimes($validated['montant_bc']);
            $annuiteCentimes = $this->toCentimes($annuite->montant);

            if ($engageCentimes + $montantCentimes > $annuiteCentimes
                && ($validated['gestion_depassement'] ?? 'bloquer') !== 'surplus') {
                abort(response()->json([
                    'message' => 'Dépassement de l\'annuité sélectionnée.',
                    'depassement' => ($engageCentimes + $montantCentimes - $annuiteCentimes) / 100,
                ], 422));
            }

            return $genererNumero
                ? ReferenceGenerator::create(BonCommande::class, 'numero_bc', 'BC', $validated)
                : BonCommande::create($validated);
        }, 3);

        return response()->json([
            'message' => 'Bon de commande créé avec succès !',
            'data'    => $bon,
        ], 201);
    }

    /**
     * Répartit automatiquement le montant d'un bon de commande sur l'annuité
     * de l'année d'achat puis, si elle ne suffit pas, sur les annuités
     * suivantes de la même ligne budgétaire (dans l'ordre chronologique),
     * jusqu'à consommation totale du montant.
     *
     * Deux modes possibles (choisis par l'utilisateur au moment de la
     * création) :
     * - 'bons_multiples' (comportement historique) : crée une ligne de bon de
     *   commande par annuité concernée, reliées par un même
     *   repartition_groupe. Chaque bon a son propre numéro.
     * - 'bon_unique' : crée UN SEUL bon de commande (un seul numéro), dont le
     *   montant_bc est le total demandé ; le détail de la répartition par
     *   annuité est stocké dans bon_commande_tranches et consultable via la
     *   relation tranches() / dans le détail du bon.
     */
    private function creerAvecRepartition(array $validated, LigneBudget $ligne, int $anneeDepart, string $modeRepartition = 'bons_multiples')
    {
        $montantRestantCentimes = $this->toCentimes($validated['montant_bc']);

        $bons = DB::transaction(function () use ($validated, $ligne, $anneeDepart, $modeRepartition, &$montantRestantCentimes) {
            $ligne = LigneBudget::lockForUpdate()->findOrFail($ligne->id);

            $annuites = $ligne->annuites()
                ->where('annee', '>=', $anneeDepart)
                ->orderBy('annee')
                ->lockForUpdate()
                ->get();

            if ($annuites->isEmpty()) {
                abort(response()->json([
                    'message' => "Aucune annuité disponible à partir de {$anneeDepart} sur cette ligne budgétaire.",
                ], 422));
            }

            $tranches = [];
            foreach ($annuites as $annuite) {
                if ($montantRestantCentimes <= 0) {
                    break;
                }

                $engageCentimes = BonCommande::engagementAnnuiteCentimes($ligne->id, $annuite->annee);
                $disponibleCentimes = $this->toCentimes($annuite->montant) - $engageCentimes;

                if ($disponibleCentimes <= 0) {
                    continue;
                }

                $trancheCentimes = min($disponibleCentimes, $montantRestantCentimes);
                $tranches[] = ['annee' => $annuite->annee, 'montant' => $trancheCentimes / 100];
                $montantRestantCentimes -= $trancheCentimes;
            }

            if ($montantRestantCentimes > 0) {
                $derniereAnnee = $annuites->last()->annee;
                abort(response()->json([
                    'message' => "Montant trop élevé : même en répartissant sur toutes les annuités disponibles à partir de {$anneeDepart} (jusqu'à {$derniereAnnee}), il reste "
                        . number_format($montantRestantCentimes / 100, 2, ',', ' ')
                        . " à couvrir. Ajoutez une annuité supplémentaire à cette ligne budgétaire ou réduisez le montant.",
                    'depassement' => $montantRestantCentimes / 100,
                ], 422));
            }

            $groupe = (string) \Illuminate\Support\Str::uuid();
            $total = count($tranches);

            if ($modeRepartition === 'bon_unique') {
                $donneesBon = $validated;
                $donneesBon['annuite'] = $tranches[0]['annee'];
                $donneesBon['montant_bc'] = array_sum(array_column($tranches, 'montant'));
                $donneesBon['repartition_groupe'] = $groupe;
                $donneesBon['repartition_ordre'] = 1;
                $donneesBon['repartition_total'] = 1;
                $donneesBon['mode_repartition'] = 'bon_unique';
                if ($total > 1) {
                    $premiereAnnee = $tranches[0]['annee'];
                    $derniereAnnee = $tranches[$total - 1]['annee'];
                    $donneesBon['intitule_bc'] = $validated['intitule_bc'] . " (réparti {$premiereAnnee}–{$derniereAnnee})";
                }
                unset($donneesBon['numero_bc']);

                $bon = ReferenceGenerator::create(BonCommande::class, 'numero_bc', 'BC', $donneesBon);

                foreach ($tranches as $index => $tranche) {
                    $bon->tranches()->create([
                        'annuite' => $tranche['annee'],
                        'montant' => $tranche['montant'],
                        'ordre'   => $index + 1,
                    ]);
                }

                return [$bon->fresh('tranches')];
            }

            // Mode 'bons_multiples' (comportement historique) : un bon distinct par annuité.
            $bonsCrees = [];
            foreach ($tranches as $index => $tranche) {
                $donneesTranche = $validated;
                $donneesTranche['annuite'] = $tranche['annee'];
                $donneesTranche['montant_bc'] = $tranche['montant'];
                $donneesTranche['repartition_groupe'] = $groupe;
                $donneesTranche['repartition_ordre'] = $index + 1;
                $donneesTranche['repartition_total'] = $total;
                $donneesTranche['mode_repartition'] = 'bons_multiples';
                if ($total > 1) {
                    $donneesTranche['intitule_bc'] = $validated['intitule_bc'] . " (tranche " . ($index + 1) . "/{$total} — {$tranche['annee']})";
                }
                // Chaque tranche obtient sa propre référence auto-générée : un numéro
                // saisi manuellement ne peut pas être réutilisé sur plusieurs lignes.
                unset($donneesTranche['numero_bc']);

                $bonsCrees[] = ReferenceGenerator::create(BonCommande::class, 'numero_bc', 'BC', $donneesTranche);
            }

            return $bonsCrees;
        }, 3);

        $premierBon = $bons[0];
        if (count($bons) > 1) {
            $message = 'Bon de commande créé et réparti automatiquement sur ' . count($bons) . ' annuités (' . count($bons) . ' bons liés).';
        } elseif ($premierBon->tranches()->count() > 1) {
            $message = 'Bon de commande créé, réparti en interne sur ' . $premierBon->tranches()->count() . ' annuités.';
        } else {
            $message = 'Bon de commande créé avec succès !';
        }

        return response()->json([
            'message' => $message,
            'data' => $bons,
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        $bon = BonCommande::with(['ligneBudget.budget', 'user', 'tranches'])->find($id);

        if (!$bon) {
            return response()->json(['message' => 'Bon de commande introuvable.'], 404);
        }

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        return response()->json($bon, 200);
    }

    public function update(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('bc.edit')) {
            return response()->json(['message' => "Action non autorisée : permission 'bc.edit' requise."], 403);
        }

        $bon = BonCommande::findOrFail($id);

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        $validated = $request->validate([
            'numero_bc'       => 'nullable|string|unique:bons_commande,numero_bc,' . $id,
            'ligne_budget_id' => 'required|exists:ligne_budgets,id',
            'intitule_bc'     => 'required|string|max:255',
            'montant_bc'      => 'required|numeric|min:0',
            'description'     => 'nullable|string',
            'date_achat'      => 'required|date',
            'fournisseur'     => 'required|string|max:255',
            'fournisseur_id'  => 'nullable|exists:fournisseurs,id',
            'annuite'         => 'nullable|integer|min:2000|max:2200',
            'gestion_depassement' => 'nullable|in:bloquer,surplus,report_annuite_suivante,repartition_automatique',
            'type_paiement'   => 'nullable|string|max:255',
            'statut'          => ['nullable', 'string', 'max:100', Rule::in($this->statutsDisponibles())],
        ]);

        if (!$request->user()->hasDepartmentWideAccess()) {
            unset($validated['numero_bc']);
        }

        $ligne = LigneBudget::findOrFail($validated['ligne_budget_id']);

        if (($validated['gestion_depassement'] ?? 'bloquer') === 'repartition_automatique') {
            return response()->json([
                'message' => "La répartition automatique sur plusieurs annuités n'est pas disponible en modification. Supprimez ce bon de commande et recréez-le avec ce mode.",
            ], 422);
        }

        $anneeAchat = Carbon::parse($validated['date_achat'])->year;
        $anneeBon = isset($validated['annuite']) ? (int) $validated['annuite'] : $anneeAchat;
        $estReport = ($validated['gestion_depassement'] ?? 'bloquer') === 'report_annuite_suivante';
        if ($estReport && $anneeBon <= $anneeAchat) {
            return response()->json(['message' => "Choisissez une annuité postérieure à l'année d'achat pour le report."], 422);
        }
        if (!$estReport && $anneeBon !== $anneeAchat) {
            return response()->json(['message' => "L'annuité consommée doit correspondre à l'année d'achat."], 422);
        }
        $annuite = $ligne->annuites()->where('annee', $anneeBon)->first();
        if (!$annuite) {
            return response()->json(['message' => "Aucune annuité n'est prévue pour l'année {$anneeBon} sur cette ligne budgétaire."], 422);
        }
        $engageCentimes = BonCommande::engagementAnnuiteCentimes($ligne->id, $anneeBon, $bon->id);
        $depassementCentimes = $engageCentimes + $this->toCentimes($validated['montant_bc']) - $this->toCentimes($annuite->montant);
        if ($depassementCentimes > 0 && ($validated['gestion_depassement'] ?? 'bloquer') !== 'surplus') {
            return response()->json(['message' => "Dépassement de l'annuité sélectionnée.", 'depassement' => $depassementCentimes / 100], 422);
        }
        $validated['annuite'] = $anneeBon;

        if ($ligne->departement_id !== $bon->departement_id) {
            return response()->json([
                'message' => 'Un bon de commande doit appartenir au même département que sa ligne budgétaire.',
            ], 422);
        }

        if (!$ligne->estEncoreValide()) {
            return response()->json([
                'message' => "Impossible de modifier ce bon de commande : la période de validité de cette ligne budgétaire a expiré le {$ligne->date_fin_validite->format('d/m/Y')}.",
            ], 422);
        }

        $bon = DB::transaction(function () use ($bon, $validated) {
            $bon = BonCommande::lockForUpdate()->findOrFail($bon->id);
            $ligne = LigneBudget::lockForUpdate()->findOrFail($validated['ligne_budget_id']);
            $annuite = $ligne->annuites()
                ->where('annee', $validated['annuite'])
                ->lockForUpdate()
                ->firstOrFail();
            $engageCentimes = BonCommande::engagementAnnuiteCentimes($ligne->id, $validated['annuite'], $bon->id);
            $montantCentimes = $this->toCentimes($validated['montant_bc']);
            $annuiteCentimes = $this->toCentimes($annuite->montant);

            if ($engageCentimes + $montantCentimes > $annuiteCentimes
                && ($validated['gestion_depassement'] ?? 'bloquer') !== 'surplus') {
                abort(response()->json([
                    'message' => 'Dépassement de l\'annuité sélectionnée.',
                    'depassement' => ($engageCentimes + $montantCentimes - $annuiteCentimes) / 100,
                ], 422));
            }

            $bon->update($validated);
            return $bon;
        }, 3);

        return response()->json([
            'message' => 'Bon de commande mis à jour avec succès !',
            'data'    => $bon,
        ], 200);
    }

    public function destroy(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('bc.delete')) {
            return response()->json(['message' => "Action non autorisée : permission 'bc.delete' requise."], 403);
        }

        $bon = BonCommande::findOrFail($id);

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        if ($bon->factures()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce bon de commande car des factures y sont rattachées.',
            ], 400);
        }

        $bon->delete();

        return response()->json(['message' => 'Bon de commande supprimé avec succès !'], 200);
    }

    public function validateBc(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('bc.validate')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'bc.validate' requise.",
            ], 403);
        }

        $bon = BonCommande::findOrFail($id);

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }
        if ($bon->statut === 'reglee') return response()->json(['message' => 'Un bon réglé est verrouillé.'], 422);
        $bon->update(['statut' => 'envoye']);

        return response()->json(['message' => 'Bon de commande validé.', 'data' => $bon], 200);
    }

    public function unvalidateBc(Request $request, string $id)
    {
        $bon = BonCommande::findOrFail($id);

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        $bon->update(['statut' => 'brouillon']);

        return response()->json(['message' => 'Validation du bon de commande annulée.', 'data' => $bon], 200);
    }

    private function statutsDisponibles(): array
    {
        return array_merge(['brouillon', 'envoye', 'reception', 'validation', 'paiement', 'reglee'], StatutPersonnalise::where('type', 'bon_commande')->pluck('libelle')->all());
    }

    private function toCentimes(mixed $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }
}