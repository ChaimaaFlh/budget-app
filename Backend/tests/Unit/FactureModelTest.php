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

class FactureModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_facture_belongs_to_bon_commande(): void
    {
        $facture = $this->createFacture();

        $this->assertInstanceOf(BonCommande::class, $facture->bonCommande);
    }

    public function test_facture_has_many_documents(): void
    {
        $facture = $this->createFacture();

        FactureDocument::create([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'facture.pdf',
            'chemin' => 'factures/facture.pdf',
            'mime_type' => 'application/pdf',
            'taille_octets' => 12345,
        ]);

        $this->assertCount(1, $facture->fresh()->documents);
    }

    public function test_montant_is_cast_to_decimal_with_two_places(): void
    {
        $facture = $this->createFacture(['montant' => 199.999]);

        $this->assertEquals('200.00', $facture->montant);
    }

    public function test_dates_are_cast_to_date(): void
    {
        $facture = $this->createFacture([
            'date_reception' => '2026-02-01',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $facture->date_reception);
    }

    public function test_facture_uses_soft_deletes(): void
    {
        $facture = $this->createFacture();
        $facture->delete();

        $this->assertSoftDeleted('factures', ['id' => $facture->id]);
    }

    public function test_deleting_bon_commande_cascades_to_factures(): void
    {
        $facture = $this->createFacture();
        $bonCommande = $facture->bonCommande;

        $bonCommande->forceDelete();

        $this->assertDatabaseCount('factures', 0);
    }

    private function createFacture(array $attributes = []): Facture
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

        return Facture::create(array_merge([
            'bon_commande_id' => $bonCommande->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 100,
            'type_reglement' => 'acompte',
        ], $attributes));
    }
}