<?php

namespace App\Http\Controllers;

use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LigneBudgetAnnuiteController extends Controller
{
    public function index(Request $request, string $ligneBudgetId)
    {
        $ligne = LigneBudget::findOrFail($ligneBudgetId);
        $this->authorizeLine($request, $ligne);

        // Le montant "disponible" doit tenir compte de l'engagé réel sur
        // chaque annuité, y compris pour les bons créés en mode "bon unique"
        // (répartition automatique) dont le détail par annuité vit dans
        // bon_commande_tranches et non dans la colonne montant_bc du bon.
        // Sans ce calcul, une annuité déjà consommée par une répartition
        // automatique pouvait sembler "disponible" à tort — ou, selon les
        // écrans, ne plus apparaître clairement comme utilisable.
        $annuites = $ligne->annuites()->orderBy('annee')->get()->map(function ($annuite) use ($ligne) {
            $engageCentimes = \App\Models\BonCommande::engagementAnnuiteCentimes($ligne->id, (int) $annuite->annee);
            $montantCentimes = (int) round(((float) $annuite->montant) * 100);

            $annuite->montant_engage = round($engageCentimes / 100, 2);
            $annuite->montant_disponible = round(max(0, $montantCentimes - $engageCentimes) / 100, 2);

            return $annuite;
        });

        return response()->json($annuites, 200);
    }

    public function store(Request $request, string $ligneBudgetId)
    {
        $ligne = LigneBudget::with('budget')->findOrFail($ligneBudgetId);
        $this->authorizeLine($request, $ligne, true);

        $validated = $request->validate([
            'annee'   => 'required|integer',
            'montant' => 'required|numeric|min:0',
        ]);

        $this->assertAnneeValide($ligne, (int) $validated['annee']);
        $annuite = DB::transaction(function () use ($ligne, $validated) {
            $ligne = LigneBudget::lockForUpdate()->findOrFail($ligne->id);
            $annuiteExistante = $ligne->annuites()
                ->where('annee', $validated['annee'])
                ->lockForUpdate()
                ->first();
            $this->assertSommeAnnuitesValide($ligne, $validated['montant'], $annuiteExistante?->id);

            return LigneBudgetAnnuite::updateOrCreate(
                ['ligne_budget_id' => $ligne->id, 'annee' => $validated['annee']],
                ['montant' => $validated['montant']]
            );
        }, 3);

        return response()->json(['message' => 'Annuité enregistrée.', 'data' => $annuite], 201);
    }

    public function update(Request $request, string $ligneBudgetId, string $annuiteId)
    {
        $ligne = LigneBudget::findOrFail($ligneBudgetId);
        $this->authorizeLine($request, $ligne, true);
        $annuite = LigneBudgetAnnuite::where('ligne_budget_id', $ligne->id)->findOrFail($annuiteId);

        $validated = $request->validate(['montant' => 'required|numeric|min:0']);

        $annuite = DB::transaction(function () use ($ligne, $annuite, $validated) {
            $ligne = LigneBudget::lockForUpdate()->findOrFail($ligne->id);
            $annuite = LigneBudgetAnnuite::where('ligne_budget_id', $ligne->id)
                ->lockForUpdate()
                ->findOrFail($annuite->id);
            $this->assertSommeAnnuitesValide($ligne, $validated['montant'], excludeId: $annuite->id);
            $annuite->update($validated);

            return $annuite;
        }, 3);

        return response()->json(['message' => 'Annuité mise à jour.', 'data' => $annuite], 200);
    }

    public function destroy(Request $request, string $ligneBudgetId, string $annuiteId)
    {
        $ligne = LigneBudget::findOrFail($ligneBudgetId);
        $this->authorizeLine($request, $ligne, true);
        $annuite = LigneBudgetAnnuite::where('ligne_budget_id', $ligne->id)->findOrFail($annuiteId);
        $annuite->delete();

        return response()->json(['message' => 'Annuité supprimée.'], 200);
    }

    public function replaceAll(Request $request, string $ligneBudgetId)
    {
        $ligne = LigneBudget::with('budget')->findOrFail($ligneBudgetId);
        $this->authorizeLine($request, $ligne, true);
        $validated = $request->validate([
            'annuites' => 'required|array|min:1',
            'annuites.*.annee' => 'required|integer',
            'annuites.*.montant' => 'required|numeric|min:0',
        ]);
        $annees = [];
        foreach ($validated['annuites'] as $annuite) {
            $this->assertAnneeValide($ligne, (int) $annuite['annee']);
            if (in_array($annuite['annee'], $annees, true)) {
                return response()->json(['message' => 'Chaque année ne peut apparaître qu’une seule fois.'], 422);
            }
            $annees[] = $annuite['annee'];
        }
        $totalCentimes = array_sum(array_map(fn ($a) => (int) round($a['montant'] * 100), $validated['annuites']));
        $alloueCentimes = (int) round(((float) $ligne->montant_alloue) * 100);
        if ($totalCentimes !== $alloueCentimes) {
            return response()->json(['message' => 'La somme des annuités doit être exactement égale au montant alloué.'], 422);
        }
        DB::transaction(function () use ($ligne, $validated) {
            $ligne = LigneBudget::lockForUpdate()->findOrFail($ligne->id);
            $ligne->annuites()->delete();
            foreach ($validated['annuites'] as $annuite) $ligne->annuites()->create($annuite);
        });
        return response()->json(['message' => 'Répartition annuelle enregistrée.', 'data' => $ligne->annuites()->orderBy('annee')->get()]);
    }

    private function assertSommeAnnuitesValide(LigneBudget $ligne, float $nouveauMontant, ?int $excludeId = null): void
    {
        $sommeExistanteCentimes = $this->toCentimes($ligne->annuites()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('montant'));
        $nouveauMontantCentimes = $this->toCentimes($nouveauMontant);
        $montantAlloueCentimes = $this->toCentimes($ligne->montant_alloue);

        if ($sommeExistanteCentimes + $nouveauMontantCentimes > $montantAlloueCentimes) {
            abort(response()->json([
                'message' => 'La somme des annuités dépasse le montant alloué de la ligne budgétaire.',
                'montant_disponible' => ($montantAlloueCentimes - $sommeExistanteCentimes) / 100,
            ], 422));
        }
    }

    private function assertAnneeValide(LigneBudget $ligne, int $annee): void
    {
        $budget = $ligne->budget;
        $anneeDebut = (int) $ligne->date_debut_amortissement->year;
        $anneeFin = $anneeDebut + $ligne->duree_amortissement_annees - 1;

        if ($budget->type === 'fonctionnement' && $annee !== (int) $budget->annee) {
            abort(response()->json(['message' => "Une ligne de fonctionnement ne peut être ventilée que sur l'année du budget."], 422));
        }

        if ($budget->type === 'investissement' && ($annee < $anneeDebut || $annee > $anneeFin)) {
            abort(response()->json(['message' => "L'année de l'annuité doit être comprise dans la période d'amortissement."], 422));
        }
    }

    private function authorizeLine(Request $request, LigneBudget $ligne, bool $requiresEditPermission = false): void
    {
        if (!$request->user()->hasDepartmentWideAccess()
            && $ligne->departement_id !== $request->user()->departement_id) {
            abort(response()->json(['message' => 'Accès refusé à ce département.'], 403));
        }

        if ($requiresEditPermission && !$request->user()->hasPermission('ligne.edit')) {
            abort(response()->json(['message' => "Action non autorisée : permission 'ligne.edit' requise."], 403));
        }
    }

    private function toCentimes(mixed $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }
}