<?php

namespace App\Http\Controllers;

use App\Models\StatutPersonnalise;
use App\Models\BonCommande;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatutPersonnaliseController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->validate(['type' => ['required', Rule::in(['bon_commande', 'facture'])]])['type'];
        return StatutPersonnalise::where('type', $type)->orderBy('libelle')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['bon_commande', 'facture'])],
            'libelle' => ['required', 'string', 'max:100'],
            'couleur' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $status = StatutPersonnalise::firstOrCreate(
            ['type' => $data['type'], 'libelle' => $data['libelle']],
            ['couleur' => $data['couleur']],
        );
        return response()->json($status, $status->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(StatutPersonnalise $statutPersonnalise)
    {
        $utilise = $statutPersonnalise->type === 'bon_commande'
            ? BonCommande::where('statut', $statutPersonnalise->libelle)->exists()
            : Facture::where('statut', $statutPersonnalise->libelle)->exists();
        if ($utilise) {
            return response()->json(['message' => 'Ce statut est déjà utilisé et ne peut pas être supprimé.'], 422);
        }
        $statutPersonnalise->delete();
        return response()->json(['message' => 'Statut supprimé.']);
    }
}
