<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategorieController extends Controller
{
    
    public function index(Request $request)
    {
        $categories = Categorie::visibleParUtilisateur($request->user())
            ->with(['sousCategories', 'departement'])
            ->orderBy('nom')
            ->get();

        return response()->json($categories, 200);
    }

    
    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('categorie.create')) {
            return response()->json(['message' => "Action non autorisée : permission 'categorie.create' requise."], 403);
        }

        $departementId = $request->user()->hasDepartmentWideAccess()
            ? ($request->input('departement_id') ?? $request->user()->departement_id)
            : $request->user()->departement_id;

        $validated = $request->validate([
            'nom'          => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'nom')->where(
                    fn ($query) => $query->where('departement_id', $departementId),
                ),
            ],
            'description'  => 'nullable|string',
            'departement_id' => $request->user()->hasDepartmentWideAccess()
                ? 'required|exists:departements,id'
                : 'nullable',
        ]);

        $validated['departement_id'] = $departementId;
        $categorie = Categorie::create($validated);

        return response()->json([
            'message' => 'Catégorie créée avec succès !',
            'data'    => $categorie
        ], 201);
    }

    
    public function show(Request $request, string $id)
    {
        $categorie = Categorie::visibleParUtilisateur($request->user())
            ->with('sousCategories')
            ->find($id);

        if (!$categorie){
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        return response()->json($categorie, 200);
    }

    
    public function update(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('categorie.edit')) {
            return response()->json(['message' => "Action non autorisée : permission 'categorie.edit' requise."], 403);
        }

        $categorie = Categorie::visibleParUtilisateur($request->user())->findOrFail($id);

        $departementId = $request->user()->hasDepartmentWideAccess()
            ? ($request->input('departement_id') ?? $categorie->departement_id)
            : $categorie->departement_id;

        $validated = $request->validate([
            'nom'          => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'nom')
                    ->where(fn ($query) => $query->where('departement_id', $departementId))
                    ->ignore($categorie->id),
            ],
            'description'  => 'nullable|string',
            'departement_id' => $request->user()->hasDepartmentWideAccess()
                ? 'required|exists:departements,id'
                : 'nullable',
        ]);

        $validated['departement_id'] = $departementId;
        $categorie->update($validated);

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès !',
            'data'    => $categorie
        ], 200);
    }

    
    public function destroy(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('categorie.delete')) {
            return response()->json(['message' => "Action non autorisée : permission 'categorie.delete' requise."], 403);
        }

        $categorie = Categorie::visibleParUtilisateur($request->user())->findOrFail($id);

        if ($categorie->sousCategories()->exists()){
            return response()->json([
                'message' => 'Impossible de supprimer cette catégorie car des sous-catégories y sont rattachées.'
            ], 400);
        }

        $categorie->delete();

        return response()->json(['message' => 'Catégorie supprimée avec succès !'], 200);
    }
}