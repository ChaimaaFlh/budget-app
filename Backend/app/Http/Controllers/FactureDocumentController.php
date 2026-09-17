<?php

namespace App\Http\Controllers;

use App\Models\BonCommande;
use App\Models\Facture;
use App\Models\FactureDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FactureDocumentController extends Controller
{
    // Un scan de facture + jusqu'à 4 pièces justificatives, ou toute autre
    // combinaison, tant que le total ne dépasse pas 5 documents.
    private const MAX_DOCUMENTS_PAR_FACTURE = 5;
    private const TAILLE_MAX_KO = 10240; // 10 Mo
    // Stockage privé : les fichiers ne sont accessibles qu'via download(),
    // qui applique les mêmes règles d'accès par département que le reste de l'API.
    private const DISQUE = 'local';

    public function index(Request $request, string $bonCommandeId, string $factureId)
    {
        $facture = $this->factureAccessible($request, $bonCommandeId, $factureId);

        return response()->json(
            $facture->documents()->orderBy('type')->orderBy('created_at')->get(),
            200
        );
    }

    public function store(Request $request, string $bonCommandeId, string $factureId)
    {
        if (!$request->user()->hasPermission('facture.create')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'facture.create' requise.",
            ], 403);
        }

        $facture = $this->factureAccessible($request, $bonCommandeId, $factureId);

        $validated = $request->validate([
            'documents'   => 'required|array|min:1',
            'documents.*' => 'file|max:' . self::TAILLE_MAX_KO . '|mimes:pdf,jpg,jpeg,png',
            'types'       => 'required|array|min:1',
            'types.*'     => 'in:scan_facture,piece_justificative',
        ]);

        if (count($validated['documents']) !== count($validated['types'])) {
            throw ValidationException::withMessages([
                'documents' => ['Chaque fichier doit avoir un type associé (scan_facture ou piece_justificative).'],
            ]);
        }

        $nombreExistant = $facture->documents()->count();
        $nombreApresAjout = $nombreExistant + count($validated['documents']);

        if ($nombreApresAjout > self::MAX_DOCUMENTS_PAR_FACTURE) {
            return response()->json([
                'message' => 'Un maximum de ' . self::MAX_DOCUMENTS_PAR_FACTURE . ' documents est autorisé par facture.',
                'documents_restants' => max(0, self::MAX_DOCUMENTS_PAR_FACTURE - $nombreExistant),
            ], 422);
        }

        $aDejaUnScan = $facture->documents()->where('type', 'scan_facture')->exists();
        $nombreNouveauxScans = collect($validated['types'])->filter(fn ($type) => $type === 'scan_facture')->count();

        if ($nombreNouveauxScans > 1 || ($aDejaUnScan && $nombreNouveauxScans > 0)) {
            return response()->json([
                'message' => 'Un seul scan de facture est autorisé par facture ; les autres documents doivent être des pièces justificatives.',
            ], 422);
        }

        $documentsCrees = [];
        foreach ($validated['documents'] as $index => $fichier) {
            $chemin = $fichier->store('factures/' . $facture->id, self::DISQUE);

            $documentsCrees[] = FactureDocument::create([
                'facture_id'    => $facture->id,
                'type'          => $validated['types'][$index],
                'nom_original'  => $fichier->getClientOriginalName(),
                'chemin'        => $chemin,
                'mime_type'     => $fichier->getClientMimeType(),
                'taille_octets' => $fichier->getSize(),
            ]);
        }

        return response()->json([
            'message' => count($documentsCrees) > 1 ? 'Documents ajoutés avec succès !' : 'Document ajouté avec succès !',
            'data'    => $documentsCrees,
        ], 201);
    }

    public function download(Request $request, string $bonCommandeId, string $factureId, string $documentId)
    {
        $facture = $this->factureAccessible($request, $bonCommandeId, $factureId);
        $document = $facture->documents()->findOrFail($documentId);

        if (!Storage::disk(self::DISQUE)->exists($document->chemin)) {
            return response()->json(['message' => 'Fichier introuvable sur le serveur.'], 404);
        }

        return Storage::disk(self::DISQUE)->download($document->chemin, $document->nom_original);
    }

    public function destroy(Request $request, string $bonCommandeId, string $factureId, string $documentId)
    {
        if (!$request->user()->hasPermission('facture.create')) {
            return response()->json([
                'message' => "Action non autorisée : permission 'facture.create' requise.",
            ], 403);
        }

        $facture = $this->factureAccessible($request, $bonCommandeId, $factureId);
        $document = $facture->documents()->findOrFail($documentId);

        Storage::disk(self::DISQUE)->delete($document->chemin);
        $document->delete();

        return response()->json(['message' => 'Document supprimé.'], 200);
    }

    private function factureAccessible(Request $request, string $bonCommandeId, string $factureId): Facture
    {
        $bon = BonCommande::findOrFail($bonCommandeId);

        if (!$request->user()->hasDepartmentWideAccess()
            && $bon->departement_id !== $request->user()->departement_id) {
            abort(response()->json(['message' => 'Accès refusé à ce département.'], 403));
        }

        return Facture::where('bon_commande_id', $bonCommandeId)->findOrFail($factureId);
    }
}