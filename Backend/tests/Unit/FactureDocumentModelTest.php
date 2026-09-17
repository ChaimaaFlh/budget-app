<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\BonCommande;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\Facture;
use App\Models\FactureDocument;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactureDocumentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_belongs_to_facture(): void
    {
        $document = $this->createDocument();

        $this->assertInstanceOf(Facture::class, $document->facture);
    }

    public function test_document_fillable_attributes_are_saved(): void
    {
        $document = $this->createDocument([
            'nom_original' => 'justificatif.pdf',
            'mime_type' => 'application/pdf',
            'taille_octets' => 5000,
        ]);

        $this->assertDatabaseHas('facture_documents', [
            'nom_original' => 'justificatif.pdf',
            'mime_type' => 'application/pdf',
        ]);
        $this->assertEquals(5000, $document->taille_octets);
    }

    public function test_type_defaults_to_piece_justificative(): void
    {
        $facture = $this->createFacture();
        $document = FactureDocument::create([
            'facture_id' => $facture->id,
            'nom_original' => 'doc.pdf',
            'chemin' => 'documents/doc.pdf',
            'mime_type' => 'application/pdf',
            'taille_octets' => 100,
        ]);

        $this->assertEquals('piece_justificative', $document->fresh()->type);
    }

    public function test_taille_octets_is_cast_to_integer(): void
    {
        $document = $this->createDocument(['taille_octets' => '2048']);

        $this->assertIsInt($document->taille_octets);
        $this->assertEquals(2048, $document->taille_octets);
    }

    public function test_deleting_facture_cascades_to_documents(): void
    {
        $document = $this->createDocument();
        $facture = $document->facture;

        $facture->forceDelete();

        $this->assertDatabaseCount('facture_documents', 0);
    }

    private function createDocument(array $attributes = []): FactureDocument
    {
        $facture = $this->createFacture();

        return FactureDocument::create(array_merge([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'facture.pdf',
            'chemin' => 'factures/facture.pdf',
            'mime_type' => 'application/pdf',
            'taille_octets' => 12345,
        ], $attributes));
    }

    private function createFacture(): Facture
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 100000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);
        $ligneBudget = LigneBudget::create([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ]);
        $user = User::factory()->create(['departement_id' => $departement->id]);
        $bonCommande = BonCommande::create([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligneBudget->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat test',
            'montant_bc' => 1000,
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        return Facture::create([
            'bon_commande_id' => $bonCommande->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 100,
            'type_reglement' => 'acompte',
        ]);
    }
}