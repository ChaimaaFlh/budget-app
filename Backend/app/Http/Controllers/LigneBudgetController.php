<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\Budget;
use App\Models\Categorie;
use App\Support\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LigneBudgetController extends Controller
{
    public function index(Request $request)
    {
        $lignes = LigneBudget::visibleParUtilisateur($request->user())
            ->with(['sous_categorie', 'categorie', 'budget', 'annuites' => fn ($query) => $query->orderBy('annee')])
            ->withSum('bons_commande as total_consomme', 'montant_bc')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($lignes as $ligne) {
            $consomme = (float) ($ligne->total_consomme ?? 0);
            $alloue = (float) $ligne->montant_alloue;
            $ligne->montant_disponible = $alloue - $consomme;

            if ($consomme == 0) {
                $ligne->statut = 'Disponible';
            } elseif ($consomme < $alloue) {
                $ligne->statut = 'Partiellement Consommé';
            } elseif ($consomme == $alloue) {
                $ligne->statut = 'Totalement Consommé';
            } else {
                $ligne->statut = 'Dépassement';
            }
        }

        return response()->json($lignes, 200);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('ligne.create')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'ligne.create' requise.",
            ], 403);
        }
        if (ReferenceGenerator::isAutomatic($request->input('code'), 'LB')) {
            $request->merge(['code' => null]);
        }

        $validated = $request->validate([
            'code'                       => 'nullable|string|unique:ligne_budgets,code',
            'sous_categorie_id'          => 'nullable|exists:sous_categories,id|required_without:categorie_id',
            'categorie_id'               => 'nullable|exists:categories,id|required_without:sous_categorie_id',
            'budget_id'                  => 'required|exists:budgets,id',
            'intitule'                   => 'required|string|max:255',
            'montant_alloue'             => 'required|numeric|min:0',
            'date_debut_amortissement'   => 'required|date',
            'duree_amortissement_annees' => 'nullable|integer|min:1|max:100',
            'departement_id'             => 'nullable|exists:departements,id',
        ]);

        if (!empty($validated['sous_categorie_id'])) {
            $sousCategorie = \App\Models\SousCategorie::findOrFail($validated['sous_categorie_id']);
            $validated['categorie_id'] = $sousCategorie->categorie_id;
        }

        $departementId = $request->user()->hasDepartmentWideAccess()
            ? ($validated['departement_id'] ?? $request->user()->departement_id)
            : $request->user()->departement_id;
        $categorie = Categorie::findOrFail($validated['categorie_id']);
        if ((int) $categorie->departement_id !== (int) $departementId) {
            return response()->json([
                'message' => 'La catégorie sélectionnée n’appartient pas au département de la ligne.',
            ], 422);
        }

        $validated['departement_id'] = $departementId;
        $genererCode = empty($validated['code'])
            || ReferenceGenerator::isAutomatic($validated['code'], 'LB');

        $ligne = DB::transaction(function () use ($validated, $genererCode) {
            $budget = Budget::lockForUpdate()->findOrFail($validated['budget_id']);
            if ($budget->estClos()) {
                abort(response()->json([
                    'message' => 'Impossible de créer une ligne budgétaire : le budget est clôturé.',
                ], 422));
            }

            $totalAlloueCentimes = $this->toCentimes($budget->ligne_budgets()->sum('montant_alloue'));
            $montantGlobalCentimes = $this->toCentimes($budget->montant_global);
            $nouveauMontantCentimes = $this->toCentimes($validated['montant_alloue']);
            if ($totalAlloueCentimes + $nouveauMontantCentimes > $montantGlobalCentimes) {
                abort(response()->json([
                    'message' => 'Le montant alloué dépasse le budget global disponible.',
                    'montant_disponible_budget' => ($montantGlobalCentimes - $totalAlloueCentimes) / 100,
                ], 422));
            }

            $ligne = $genererCode
                ? ReferenceGenerator::create(LigneBudget::class, 'code', 'LB', $validated)
                : LigneBudget::create($validated);
            $this->createDefaultAnnuites($ligne->load('budget'));

            // À la création, la répartition est uniforme pour être simple à utiliser.
            // Elle peut ensuite être ajustée annuellement via la gestion des annuités.
            return $ligne;
        }, 3);

        return response()->json([
            'message' => 'Ligne budgétaire créée avec succès !',
            'data'    => $ligne,
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        $ligne = LigneBudget::with(['sous_categorie', 'budget'])
            ->withSum('bons_commande as total_consomme', 'montant_bc')
            ->find($id);

        if (!$ligne) {
            return response()->json(['message' => 'Ligne budgétaire introuvable.'], 404);
        }

        if (!$request->user()->hasDepartmentWideAccess()
            && $ligne->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        $consomme = $ligne->total_consomme ?? 0;
        $alloue = $ligne->montant_alloue;
        $ligne->montant_disponible = $alloue - $consomme;

        if ($consomme == 0) {
            $ligne->statut = 'Disponible';
        } elseif ($consomme < $alloue) {
            $ligne->statut = 'Partiellement consommé';
        } elseif ($consomme == $alloue) {
            $ligne->statut = 'Totalement consommé';
        } else {
            $ligne->statut = 'Dépassement';
        }

        return response()->json($ligne, 200);
    }

    public function update(Request $request, string $id)
    {
        $ligne = LigneBudget::findOrFail($id);

        if (!$request->user()->hasDepartmentWideAccess()
            && $ligne->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        if (!$request->user()->hasPermission('ligne.edit')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'ligne.edit' requise.",
            ], 403);
        }

        $validated = $request->validate([
            'code'                       => 'nullable|string|unique:ligne_budgets,code,' . $id,
            'sous_categorie_id'          => 'nullable|exists:sous_categories,id|required_without:categorie_id',
            'categorie_id'               => 'nullable|exists:categories,id|required_without:sous_categorie_id',
            'budget_id'                  => 'required|exists:budgets,id',
            'intitule'                   => 'required|string|max:255',
            'montant_alloue'             => 'required|numeric|min:0',
            'date_debut_amortissement'   => 'required|date',
            'duree_amortissement_annees' => 'nullable|integer|min:1|max:100',
        ]);

        if (!empty($validated['sous_categorie_id'])) {
            $sousCategorie = \App\Models\SousCategorie::findOrFail($validated['sous_categorie_id']);
            $validated['categorie_id'] = $sousCategorie->categorie_id;
        }

        $categorie = Categorie::findOrFail($validated['categorie_id']);
        if ((int) $categorie->departement_id !== (int) $ligne->departement_id) {
            return response()->json([
                'message' => 'La catégorie sélectionnée n’appartient pas au département de la ligne.',
            ], 422);
        }

        $ligne = DB::transaction(function () use ($id, $validated) {
            $ligne = LigneBudget::lockForUpdate()->findOrFail($id);
            $budget = Budget::lockForUpdate()->findOrFail($validated['budget_id']);
            $montantEngageCentimes = $this->toCentimes($ligne->bons_commande()->sum('montant_bc'));
            $nouveauMontantCentimes = $this->toCentimes($validated['montant_alloue']);

            if ($nouveauMontantCentimes < $montantEngageCentimes) {
                abort(response()->json([
                    'message' => 'Le montant alloué ne peut pas être inférieur aux bons de commande déjà engagés.',
                    'montant_engage' => $montantEngageCentimes / 100,
                ], 422));
            }

            $totalAlloueCentimes = $this->toCentimes($budget->ligne_budgets()
                ->where('id', '!=', $id)
                ->sum('montant_alloue'));
            $montantGlobalCentimes = $this->toCentimes($budget->montant_global);

            if ($totalAlloueCentimes + $nouveauMontantCentimes > $montantGlobalCentimes) {
                abort(response()->json([
                    'message' => 'Le montant alloué dépasse le budget global disponible.',
                    'montant_disponible_budget' => ($montantGlobalCentimes - $totalAlloueCentimes) / 100,
                ], 422));
            }

            $ligne->update($validated);
            return $ligne;
        }, 3);

        return response()->json([
            'message' => 'Ligne budgétaire mise à jour avec succès !',
            'data'    => $ligne,
        ], 200);
    }

    public function destroy(Request $request, string $id)
    {
        $ligne = LigneBudget::findOrFail($id);

        if (!$request->user()->hasDepartmentWideAccess()
            && $ligne->departement_id !== $request->user()->departement_id) {
            return response()->json(['message' => 'Accès refusé à ce département.'], 403);
        }

        if (!$request->user()->hasPermission('ligne.delete')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'ligne.delete' requise.",
            ], 403);
        }

        if ($ligne->bons_commande()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette ligne car des bons de commande y sont rattachés.',
            ], 400);
        }

        $ligne->delete();

        return response()->json([
            'message' => 'Ligne budgétaire supprimée avec succès !',
        ], 200);
    }

    private function createDefaultAnnuites(LigneBudget $ligne): void
    {
        $fonctionnement = $ligne->budget->type === 'fonctionnement';
        $nombreAnnees = $fonctionnement ? 1 : $ligne->duree_amortissement_annees;
        $anneeDebut = $fonctionnement ? (int) $ligne->budget->annee : (int) $ligne->date_debut_amortissement->year;
        $totalCentimes = (int) round(((float) $ligne->montant_alloue) * 100);
        $part = intdiv($totalCentimes, $nombreAnnees);
        $reste = $totalCentimes - ($part * $nombreAnnees);

        for ($index = 0; $index < $nombreAnnees; $index++) {
            LigneBudgetAnnuite::create([
                'ligne_budget_id' => $ligne->id,
                'annee' => $anneeDebut + $index,
                'montant' => ($part + ($index === $nombreAnnees - 1 ? $reste : 0)) / 100,
            ]);
        }
    }

    private function toCentimes(mixed $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }

}
