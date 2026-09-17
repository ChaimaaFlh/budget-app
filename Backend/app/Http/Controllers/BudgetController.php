<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Support\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    
    public function index(Request $request)
    {
        $lignesVisibles = fn ($query) => $request->user()->hasDepartmentWideAccess()
            ? $query
            : $query->where('departement_id', $request->user()->departement_id);

        $budgets = Budget::query()
            ->when(
                !$request->user()->hasDepartmentWideAccess(),
                fn ($query) => $query->where(function ($q) use ($request, $lignesVisibles) {
                    $q->whereHas('ligne_budgets', $lignesVisibles)
                      ->orWhereHas('departements', fn ($q2) => $q2->where('departements.id', $request->user()->departement_id));
                })
            )
            ->with('departements')
            ->withSum(['ligne_budgets as total_alloue' => $lignesVisibles], 'montant_alloue')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        
        foreach ($budgets as $budget){
            $budget->montant_disponible = (float) $budget->montant_global - (float) ($budget->total_alloue ?? 0);
        }

        return response()->json($budgets, 200);
    }

    
    public function store(Request $request)
    {
        $this->ensureCanManageBudgets($request, 'budget.create');
        if (ReferenceGenerator::isAutomatic($request->input('code'), 'BUD')) {
            $request->merge(['code' => null]);
        }

        $validated = $request->validate([
            'code'            => 'nullable|string|unique:budgets,code',
            'nom'             => 'required|string|max:255',
            'montant_global'  => 'required|numeric|min:0',
            'description'     => 'nullable|string',
            'annee'           => 'required|integer|min:2000',
            'statut'          => 'nullable|in:ouvert,clos',
            'type'            => 'required|in:investissement,fonctionnement',
            'departement_ids'   => 'nullable|array',
            'departement_ids.*' => 'exists:departements,id',
        ]);

        $genererCode = empty($validated['code'])
            || ReferenceGenerator::isAutomatic($validated['code'], 'BUD');
        $departementIds = $validated['departement_ids'] ?? [];
        unset($validated['departement_ids']);

        $budget = $genererCode
            ? ReferenceGenerator::create(Budget::class, 'code', 'BUD', $validated)
            : Budget::create($validated);

        if (!empty($departementIds)) {
            $budget->departements()->sync($departementIds);
        }

        return response()->json([
            'message' => 'Budget créé avec succès !',
            'data'    => $budget->load('departements')
        ], 201);
    }

    
    public function show(Request $request, string $id)
    {
        $lignesVisibles = fn ($query) => $request->user()->hasDepartmentWideAccess()
            ? $query
            : $query->where('departement_id', $request->user()->departement_id);

        $budget = Budget::with(['ligne_budgets' => $lignesVisibles, 'departements'])
            ->when(
                !$request->user()->hasDepartmentWideAccess(),
                fn ($query) => $query->where(function ($q) use ($request, $lignesVisibles) {
                    $q->whereHas('ligne_budgets', $lignesVisibles)
                      ->orWhereHas('departements', fn ($q2) => $q2->where('departements.id', $request->user()->departement_id));
                })
            )
            ->withSum(['ligne_budgets as total_alloue' => $lignesVisibles], 'montant_alloue')
            ->find($id);

        if (!$budget){
            return response()->json(['message' => 'Budget introuvable.'], 404);
        }

        $budget->montant_disponible = (float) $budget->montant_global - (float) ($budget->total_alloue ?? 0);
        
        return response()->json($budget, 200);
    }

    
    public function update(Request $request, string $id)
    {
        $budget = Budget::findOrFail($id);
        $this->ensureCanManageBudgets($request, 'budget.edit');

        $validated = $request->validate([
            'code'            => 'nullable|string|unique:budgets,code,' . $id,
            'nom'             => 'required|string|max:255',
            'montant_global'  => 'required|numeric|min:0',
            'description'     => 'nullable|string',
            'departement_ids'   => 'nullable|array',
            'departement_ids.*' => 'exists:departements,id',
        ]);

        $departementIds = $validated['departement_ids'] ?? null;
        unset($validated['departement_ids']);

        $budget = DB::transaction(function () use ($id, $validated, $departementIds) {
            $budget = Budget::lockForUpdate()->findOrFail($id);
            $totalAlloueCentimes = $this->toCentimes($budget->ligne_budgets()->sum('montant_alloue'));

            if ($this->toCentimes($validated['montant_global']) < $totalAlloueCentimes) {
                abort(response()->json([
                    'message' => 'Le nouveau montant global est inférieur au montant déjà alloué aux lignes budgétaires.',
                    'total_alloue' => $totalAlloueCentimes / 100,
                ], 422));
            }

            $budget->update($validated);
            if ($departementIds !== null) {
                $budget->departements()->sync($departementIds);
            }

            return $budget;
        }, 3);

        return response()->json([
            'message' => 'Budget mis à jour avec succès !',
            'data'    => $budget->load('departements')
        ], 200);
    }

    
    public function destroy(Request $request, string $id)
    {
        $budget = Budget::findOrFail($id);
        $this->ensureCanManageBudgets($request, 'budget.delete');

        if ($budget->ligne_budgets()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce budget car des lignes budgétaires y sont rattachées.'
            ], 400);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget supprimé avec succès !'], 200);
    }

    public function close(Request $request, string $id)
{
    if (!$request->user()->hasPermission('budget.close') || !$request->user()->hasDepartmentWideAccess()) {
        return response()->json(['message' => "Action non autorisée : permission 'budget.close' requise."], 403);
    }
    
    $budget = Budget::findOrFail($id);
    $budget->update(['statut' => 'clos']);

    return response()->json([
        'message' => 'Budget clôturé avec succès !',
        'data'    => $budget,
    ], 200);
}

    private function ensureCanManageBudgets(Request $request, string $permission): void
    {
        if (!$request->user()->hasPermission($permission) || !$request->user()->hasDepartmentWideAccess()) {
            abort(response()->json([
                'message' => "Action non autorisée : gestion globale des budgets réservée à l'Administrateur.",
            ], 403));
        }
    }

    private function toCentimes(mixed $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }
}
