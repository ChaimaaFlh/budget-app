<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use Illuminate\Http\Request;

class DepartementController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureDepartmentAccess($request);
        return response()->json(Departement::orderByDesc('created_at')->orderByDesc('id')->get(), 200);
    }

    public function store(Request $request)
    {
        $this->ensureDepartmentAccess($request);
        $validated = $request->validate([
            'nom' => 'required|string|unique:departements,nom',
        ]);

        $departement = Departement::create($validated);

        return response()->json(['message' => 'Département créé.', 'data' => $departement], 201);
    }

    public function show(Request $request, string $id)
    {
        $this->ensureDepartmentAccess($request);
        $departement = Departement::findOrFail($id);
        return response()->json($departement, 200);
    }

    public function update(Request $request, string $id)
    {
        $this->ensureDepartmentAccess($request);
        $departement = Departement::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'required|string|unique:departements,nom,' . $id,
        ]);

        $departement->update($validated);

        return response()->json(['message' => 'Département mis à jour.', 'data' => $departement], 200);
    }

    public function destroy(Request $request, string $id)
    {
        $this->ensureDepartmentAccess($request);
        $departement = Departement::findOrFail($id);

        if ($departement->users()->exists() || $departement->ligneBudgets()->exists() || $departement->bonsCommande()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer : des enregistrements y sont rattachés.'], 400);
        }

        $departement->delete();

        return response()->json(['message' => 'Département supprimé.'], 200);
    }

    private function ensureDepartmentAccess(Request $request): void
    {
        if (!$request->user()->hasPermission('departement.view_all')) {
            abort(response()->json([
                'message' => "Action non autorisée : permission 'departement.view_all' requise.",
            ], 403));
        }
    }
}