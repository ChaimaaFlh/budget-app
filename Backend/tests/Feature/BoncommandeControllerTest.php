<?php

namespace Tests\Feature;

use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\Facture;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\SousCategorie;
use App\Models\StatutPersonnalise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class BonCommandeControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/bons-commande');

        $response->assertStatus(401);
    }

    public function test_user_only_sees_bons_commande_from_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $ligneA = $this->createLigneBudgetWithAnnuite($departementA, 2026, 10000);
        $ligneB = $this->createLigneBudgetWithAnnuite($departementB, 2026, 10000);
        $bonA = $this->createBonCommande($ligneA, $departementA);
        $this->createBonCommande($ligneB, $departementB);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson('/api/bons-commande');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($bonA->id, $response->json('0.id'));
    }

    public function test_authorized_user_can_create_bon_commande(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, now()->year, 10000);

        $this->loginWithPermissions(['bc.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/bons-commande', [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Achat ordinateurs',
            'montant_bc' => 3000,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Dell',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bons_commande', ['intitule_bc' => 'Achat ordinateurs']);
    }

    public function test_unauthorized_user_cannot_create_bon_commande(): void
    {
        $this->loginWithPermissions([]);

        $response = $this->postJson('/api/bons-commande', []);

        $response->assertStatus(403);
    }

    public function test_store_fails_without_annuite_for_purchase_year(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2020, 10000);

        $this->loginWithPermissions(['bc.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/bons-commande', [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Achat',
            'montant_bc' => 1000,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_fails_when_amount_exceeds_annuite_disponible(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, now()->year, 1000);

        $this->loginWithPermissions(['bc.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/bons-commande', [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Achat',
            'montant_bc' => 5000,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_fails_when_ligne_belongs_to_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $ligne = $this->createLigneBudgetWithAnnuite($departementB, now()->year, 10000);

        $this->loginWithPermissions(['bc.create'], ['departement_id' => $departementA->id]);

        $response = $this->postJson('/api/bons-commande', [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Achat',
            'montant_bc' => 1000,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_fails_when_ligne_budget_expired(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, now()->year, 10000, [
            'date_debut_amortissement' => now()->subYears(10),
            'duree_amortissement_annees' => 1,
        ]);

        $this->loginWithPermissions(['bc.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/bons-commande', [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Achat',
            'montant_bc' => 1000,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_show_returns_404_for_unknown_bon_commande(): void
    {
        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson('/api/bons-commande/999999');

        $response->assertStatus(404);
    }

    public function test_user_cannot_view_bon_commande_from_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $ligne = $this->createLigneBudgetWithAnnuite($departementB, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departementB);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/bons-commande/{$bon->id}");

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_update_bon_commande(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement, ['montant_bc' => 1000]);

        $this->loginWithPermissions(['bc.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/bons-commande/{$bon->id}", [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Achat mis à jour',
            'montant_bc' => 1500,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Achat mis à jour', $bon->fresh()->intitule_bc);
    }

    public function test_bon_commande_with_a_custom_status_can_be_updated(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        StatutPersonnalise::create(['type' => 'bon_commande', 'libelle' => 'En contrôle', 'couleur' => '#123456']);
        $bon = $this->createBonCommande($ligne, $departement, [
            'montant_bc' => 1000,
            'statut' => 'En contrôle',
        ]);

        $this->loginWithPermissions(['bc.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/bons-commande/{$bon->id}", [
            'ligne_budget_id' => $ligne->id,
            'intitule_bc' => 'Modification autorisée',
            'montant_bc' => 1500,
            'date_achat' => now()->toDateString(),
            'fournisseur' => 'Fournisseur Test',
            'statut' => 'En contrôle',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Modification autorisée', $bon->fresh()->intitule_bc);
    }

    public function test_cannot_delete_bon_commande_with_factures(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement, ['montant_bc' => 1000]);
        Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 500,
            'type_reglement' => 'acompte',
        ]);

        $this->loginWithPermissions(['bc.delete'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/bons-commande/{$bon->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('bons_commande', ['id' => $bon->id]);
    }

    public function test_authorized_user_can_delete_unused_bon_commande(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement);

        $this->loginWithPermissions(['bc.delete'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/bons-commande/{$bon->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('bons_commande', ['id' => $bon->id]);
    }

    public function test_authorized_user_can_validate_bon_commande(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement);

        $this->loginWithPermissions(['bc.validate'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/validate");

        $response->assertStatus(200);
        $this->assertEquals('envoye', $bon->fresh()->statut);
    }

    public function test_user_without_bc_validate_permission_cannot_validate(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement);

        $this->loginWithPermissions(['bc.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/validate");

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_cancel_bon_commande_validation(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement, ['statut' => 'validé']);

        $this->loginWithPermissions(['bc.unvalidate'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/unvalidate");

        $response->assertStatus(200);
        $this->assertEquals('brouillon', $bon->fresh()->statut);
    }

    public function test_user_without_unvalidate_permission_cannot_cancel_validation(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudgetWithAnnuite($departement, 2026, 10000);
        $bon = $this->createBonCommande($ligne, $departement, ['statut' => 'validé']);

        $this->loginWithPermissions(['bc.validate'], ['departement_id' => $departement->id]);

        $this->postJson("/api/bons-commande/{$bon->id}/unvalidate")
            ->assertStatus(403);
    }

    private function createLigneBudgetWithAnnuite(
        Departement $departement,
        int $annee,
        float $montantAnnuite,
        array $ligneAttributes = []
    ): LigneBudget {
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 1000000,
            'annee' => $annee,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);

        $ligne = LigneBudget::create(array_merge([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => $montantAnnuite,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ], $ligneAttributes));

        LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligne->id,
            'annee' => $annee,
            'montant' => $montantAnnuite,
        ]);

        return $ligne;
    }

    private function createBonCommande(LigneBudget $ligne, Departement $departement, array $attributes = []): BonCommande
    {
        $user = $this->createUser(['departement_id' => $departement->id]);

        return BonCommande::create(array_merge([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligne->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat test',
            'montant_bc' => 500,
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
        ], $attributes));
    }
}
