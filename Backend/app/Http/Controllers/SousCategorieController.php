<?php

namespace App\Http\Controllers;

use App\Models\SousCategorie;
use App\Models\Categorie;
use Illuminate\Http\Request;

class SousCategorieController extends Controller
{
    
    public function index(Request $request)
    {
        $sousCategories = SousCategorie::with('categorie')
            ->whereHas('categorie', fn ($query) => $query->visibleParUtilisateur($request->user()))
            ->orderBy('nom')
            ->get();

        return response()->json($sousCategories, 200);
    }

    
    

    
    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('souscategorie.create')) {
            return response()->json(['message' => "Action non autorisée : permission 'souscategorie.create' requise."], 403);
        }

        $validated = $request->validate([
            'categorie_id'      => 'required|exists:categories,id',
            'nom'               => 'required|string|max:255',
            'description'       => 'nullable|string',
        ]);

        Categorie::visibleParUtilisateur($request->user())->findOrFail($validated['categorie_id']);
        $sousCategorie = SousCategorie::create($validated);

        return response()->json([
            'message' => 'Sous-catégorie créée avec succès !',
            'data'    => $sousCategorie
        ], 201);
    }

    
    public function show(Request $request, string $id)
    {
        $sousCategorie = SousCategorie::with('categorie')
            ->whereHas('categorie', fn ($query) => $query->visibleParUtilisateur($request->user()))
            ->find($id);

        if (!$sousCategorie){
            return response()->json(['message' => 'Sous-catégorie introuvable.'], 404);
        }

        return response()->json($sousCategorie, 200);
    }

   

    
    public function update(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('souscategorie.edit')) {
            return response()->json(['message' => "Action non autorisée : permission 'souscategorie.edit' requise."], 403);
        }

        $sousCategorie = SousCategorie::whereHas(
            'categorie',
            fn ($query) => $query->visibleParUtilisateur($request->user()),
        )->findOrFail($id);

        $validated = $request->validate([
            'categorie_id'      => 'required|exists:categories,id',
            'nom'               => 'required|string|max:255',
            'description'       => 'nullable|string',
        ]);

        Categorie::visibleParUtilisateur($request->user())->findOrFail($validated['categorie_id']);
        $sousCategorie->update($validated);

        return response()->json([
            'message' => 'Sous-catégorie mise à jour avec succès !',
            'data'    => $sousCategorie
        ], 200);
    }

    
    public function destroy(Request $request, string $id)
    {
        if (!$request->user()->hasPermission('souscategorie.delete')) {
            return response()->json(['message' => "Action non autorisée : permission 'souscategorie.delete' requise."], 403);
        }

        $sousCategorie = SousCategorie::whereHas(
            'categorie',
            fn ($query) => $query->visibleParUtilisateur($request->user()),
        )->findOrFail($id);

        if ($sousCategorie->ligneBudgets()->exists()){
           return response()->json([
                'message' => 'Impossible de supprimer cette sous-catégorie car des lignes budgétaires y sont rattachées.'
            ], 400); 
        }
        
        $sousCategorie->delete();

        return response()->json(['message' => 'Sous-catégorie supprimée avec succès !'], 200);
    }
}
