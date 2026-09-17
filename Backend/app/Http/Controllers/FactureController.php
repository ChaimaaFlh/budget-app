<?php

namespace App\Http\Controllers;

use App\Models\BonCommande;
use App\Models\Facture;
use App\Support\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\StatutPersonnalise;
use Illuminate\Support\Facades\DB;

class FactureController extends Controller
{
    public function index(Request $request, string $bonCommandeId)
{
    $bon = $this->bonAccessible($request, $bonCommandeId);
    return response()->json($bon->factures()->withCount('documents')->orderByDesc('created_at')->orderByDesc('id')->get(), 200);
}

    public function store(Request $request, string $bonCommandeId)
    {

        if (!$request->user()->hasPermission('facture.create')) {
            return response()->json(['message' => "Action non autorisée : permission 'facture.create' requise."], 403);
        }
        if (ReferenceGenerator::isAutomatic($request->input('ref_facture'), 'FAC')) {
            $request->merge(['ref_facture' => null]);
        }
        
        $bon = $this->bonAccessible($request, $bonCommandeId);
        $validated = $request->validate([
            'ref_facture'          => 'nullable|string|max:255|unique:factures,ref_facture',
            'montant'              => 'required|numeric|min:0',
            'date_reception'       => 'nullable|date',
            'type_reglement'       => 'required|in:acompte,finale',
            'statut'               => ['nullable', 'string', 'max:100', Rule::in($this->statutsDisponibles())],
            'date_paiement'        => 'nullable|date',
            'date_echeance'        => 'nullable|date',
            'piece_jointe'         => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $sommeExistanteCentimes = $this->toCentimes($bon->factures()->sum('montant'));
        $montantCentimes = $this->toCentimes($validated['montant']);
        $montantBonCentimes = $this->toCentimes($bon->montant_bc);
        if ($sommeExistanteCentimes + $montantCentimes > $montantBonCentimes) {
            return response()->json([
                'message' => 'La somme des factures dépasse le montant du bon de commande.',
                'montant_disponible' => ($montantBonCentimes - $sommeExistanteCentimes) / 100,
            ], 422);
        }

        if ($request->hasFile('piece_jointe')) {
            $validated['piece_jointe'] = $request->file('piece_jointe')->store('factures', 'public');
        }

        $genererReference = empty($validated['ref_facture'])
            || ReferenceGenerator::isAutomatic($validated['ref_facture'], 'FAC');
        $validated['bon_commande_id'] = $bon->id;
        $facture = DB::transaction(function () use ($bon, $validated, $genererReference) {
            $bon = BonCommande::lockForUpdate()->findOrFail($bon->id);
            $sommeExistanteCentimes = $this->toCentimes($bon->factures()->sum('montant'));
            $montantCentimes = $this->toCentimes($validated['montant']);
            $montantBonCentimes = $this->toCentimes($bon->montant_bc);
            if ($sommeExistanteCentimes + $montantCentimes > $montantBonCentimes) {
                abort(response()->json([
                    'message' => 'La somme des factures dépasse le montant du bon de commande.',
                    'montant_disponible' => ($montantBonCentimes - $sommeExistanteCentimes) / 100,
                ], 422));
            }

            $facture = $genererReference
                ? ReferenceGenerator::create(Facture::class, 'ref_facture', 'FAC', $validated)
                : Facture::create($validated);
            $this->synchroniserBonCommande($bon);
            return $facture;
        }, 3);

        return response()->json(['message' => 'Facture enregistrée.', 'data' => $facture], 201);
    }

    public function show(Request $request, string $bonCommandeId, string $factureId)
    {
        $this->bonAccessible($request, $bonCommandeId);
        $facture = Facture::where('bon_commande_id', $bonCommandeId)->findOrFail($factureId);
        return response()->json($facture, 200);
    }

    public function update(Request $request, string $bonCommandeId, string $factureId)
    {
        if (!$request->user()->hasPermission('facture.edit')) {
            return response()->json(['message' => "Action non autorisée : permission 'facture.edit' requise."], 403);
        }

        $bon = $this->bonAccessible($request, $bonCommandeId);
        $facture = Facture::where('bon_commande_id', $bonCommandeId)->findOrFail($factureId);

        $validated = $request->validate([
            'ref_facture'          => 'required|string|max:255',
            'montant'              => 'required|numeric|min:0',
            'date_reception'       => 'nullable|date',
            'type_reglement'       => 'required|in:acompte,finale',
            'statut'               => ['required', 'string', 'max:100', Rule::in($this->statutsDisponibles())],
            'date_paiement'        => 'nullable|date',
            'date_echeance'        => 'nullable|date',
            'piece_jointe'         => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $sommeExistanteCentimes = $this->toCentimes($bon->factures()->where('id', '!=', $facture->id)->sum('montant'));
        $montantCentimes = $this->toCentimes($validated['montant']);
        $montantBonCentimes = $this->toCentimes($bon->montant_bc);
        if ($sommeExistanteCentimes + $montantCentimes > $montantBonCentimes) {
            return response()->json([
                'message' => 'La somme des factures dépasse le montant du bon de commande.',
                'montant_disponible' => ($montantBonCentimes - $sommeExistanteCentimes) / 100,
            ], 422);
        }

        if ($request->hasFile('piece_jointe')) {
            $validated['piece_jointe'] = $request->file('piece_jointe')->store('factures', 'public');
        }

        $facture = DB::transaction(function () use ($bon, $facture, $validated) {
            $bon = BonCommande::lockForUpdate()->findOrFail($bon->id);
            $facture = Facture::lockForUpdate()->findOrFail($facture->id);
            $sommeExistanteCentimes = $this->toCentimes($bon->factures()
                ->where('id', '!=', $facture->id)
                ->sum('montant'));
            $montantCentimes = $this->toCentimes($validated['montant']);
            $montantBonCentimes = $this->toCentimes($bon->montant_bc);
            if ($sommeExistanteCentimes + $montantCentimes > $montantBonCentimes) {
                abort(response()->json([
                    'message' => 'La somme des factures dépasse le montant du bon de commande.',
                    'montant_disponible' => ($montantBonCentimes - $sommeExistanteCentimes) / 100,
                ], 422));
            }

            $facture->update($validated);
            $this->synchroniserBonCommande($bon);
            return $facture;
        }, 3);

        return response()->json(['message' => 'Facture mise à jour.', 'data' => $facture], 200);
    }

    public function destroy(Request $request, string $bonCommandeId, string $factureId)
    {
        if (!$request->user()->hasPermission('facture.delete')) {
            return response()->json(['message' => "Action non autorisée : permission 'facture.delete' requise."], 403);
        }

        $bon = $this->bonAccessible($request, $bonCommandeId);
        $facture = Facture::where('bon_commande_id', $bonCommandeId)->findOrFail($factureId);
        if ($facture->piece_jointe) {
            Storage::disk('public')->delete($facture->piece_jointe);
        }
        $facture->delete();
        $this->synchroniserBonCommande(BonCommande::findOrFail($bonCommandeId));

        return response()->json(['message' => 'Facture supprimée.'], 200);
    }

    private function bonAccessible(Request $request, string $bonCommandeId): BonCommande
    {
        $bon = BonCommande::findOrFail($bonCommandeId);

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            abort(response()->json(['message' => 'Accès refusé à ce département.'], 403));
        }

        return $bon;
    }

    private function synchroniserBonCommande(BonCommande $bon): void
    {
        $factures = $bon->factures()->orderByDesc('updated_at')->get();
        $montantConsomme = $factures->sum('montant');

        if ($factures->isEmpty()) {
            $bon->update(['montant_consomme' => $montantConsomme]);
            return;
        }

        $statutsStandards = ['reception', 'validation', 'paiement', 'reglee'];
        $statutPersonnalise = $factures
            ->pluck('statut')
            ->first(fn ($statut) => !in_array($statut, $statutsStandards, true));

        if ($statutPersonnalise !== null) {
            // Rend le statut propagé disponible aussi dans les écrans des BC.
            StatutPersonnalise::firstOrCreate([
                'type' => 'bon_commande',
                'libelle' => $statutPersonnalise,
            ]);
            $statut = $statutPersonnalise;
        } elseif ($factures->every(fn ($facture) => $facture->statut === 'reglee')) {
            $statut = 'reglee';
        } elseif ($factures->contains(fn ($facture) => in_array($facture->statut, ['paiement', 'reglee'], true))) {
            $statut = 'paiement';
        } elseif ($factures->contains(fn ($facture) => $facture->statut === 'validation')) {
            $statut = 'validation';
        } else {
            $statut = 'reception';
        }

        $bon->update([
            'montant_consomme' => $montantConsomme,
            'statut' => $statut,
        ]);
    }

    private function statutsDisponibles(): array
    {
        return array_merge(['reception', 'validation', 'paiement', 'reglee'], StatutPersonnalise::where('type', 'facture')->pluck('libelle')->all());
    }

    private function toCentimes(mixed $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }
}