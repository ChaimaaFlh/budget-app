<?php

namespace Tests\Feature;

use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\Facture;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class FactureControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $bon = $this->createBonCommande(Departement::first());

        $response = $this->getJson("/api/bons-commande/{$bon->id}/factures");

        $response->assertStatus(401);
    }

    public function test_authorized_user_can_create_facture(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 5000]);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures", [
            'montant' => 2000,
            'type_reglement' => 'acompte',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('factures', ['bon_commande_id' => $bon->id, 'montant' => 2000]);
        $this->assertEquals(2000, $bon->fresh()->montant_consomme);
    }

    public function test_unauthorized_user_cannot_create_facture(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures", [
            'montant' => 100,
            'type_reglement' => 'acompte',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_fails_when_sum_exceeds_bon_commande_amount(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 1000]);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures", [
            'montant' => 1500,
            'type_reglement' => 'acompte',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_fails_with_duplicate_ref_facture(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 10000]);
        Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-DUP',
            'montant' => 1000,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures", [
            'ref_facture' => 'FAC-DUP',
            'montant' => 1000,
            'type_reglement' => 'acompte',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ref_facture']);
    }

    public function test_user_cannot_access_factures_from_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $bon = $this->createBonCommande($departementB);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/bons-commande/{$bon->id}/factures");

        $response->assertStatus(403);
    }

    public function test_show_returns_404_when_facture_not_linked_to_bon(): void
    {
        $departement = Departement::first();
        $bonA = $this->createBonCommande($departement, ['montant_bc' => 10000]);
        $bonB = $this->createBonCommande($departement, ['montant_bc' => 10000]);
        $factureB = Facture::create([
            'bon_commande_id' => $bonB->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 500,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson("/api/bons-commande/{$bonA->id}/factures/{$factureB->id}");

        $response->assertStatus(404);
    }

    public function test_authorized_user_can_update_facture(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 10000]);
        $facture = Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 1000,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions(['facture.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}", [
            'ref_facture' => $facture->ref_facture,
            'montant' => 2500,
            'type_reglement' => 'finale',
            'statut' => 'reception',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2500, $facture->fresh()->montant);
        $this->assertEquals(2500, $bon->fresh()->montant_consomme);
    }

    public function test_update_fails_when_sum_exceeds_bon_commande_amount(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 3000]);
        $factureA = Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 1000,
            'type_reglement' => 'acompte',
        ]);
        Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 1000,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions(['facture.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/bons-commande/{$bon->id}/factures/{$factureA->id}", [
            'ref_facture' => $factureA->ref_facture,
            'montant' => 2500,
            'type_reglement' => 'acompte',
        ]);

        $response->assertStatus(422);
    }

    public function test_authorized_user_can_delete_facture(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 10000]);
        $facture = Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 500,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions(['facture.delete'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('factures', ['id' => $facture->id]);
        $this->assertEquals(0, $bon->fresh()->montant_consomme);
    }

    public function test_unauthorized_user_cannot_delete_facture(): void
    {
        $departement = Departement::first();
        $bon = $this->createBonCommande($departement, ['montant_bc' => 10000]);
        $facture = Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 500,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('factures', ['id' => $facture->id]);
    }

    private function createBonCommande(Departement $departement, array $attributes = []): BonCommande
    {
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 1000000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);
        $ligne = LigneBudget::create([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 100000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ]);
        $user = $this->createUser(['departement_id' => $departement->id]);

        return BonCommande::create(array_merge([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligne->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat test',
            'montant_bc' => 5000,
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
        ], $attributes));
    }
}
