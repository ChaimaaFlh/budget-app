<?php

namespace Tests\Feature;

use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class LigneBudgetControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/ligne-budgets');

        $response->assertStatus(401);
    }

    public function test_user_only_sees_ligne_budgets_from_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $ligneA = $this->createLigneBudget($departementA);
        $this->createLigneBudget($departementB);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson('/api/ligne-budgets');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($ligneA->id, $response->json('0.id'));
    }

    public function test_admin_sees_ligne_budgets_from_all_departements(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $this->createLigneBudget($departementA);
        $this->createLigneBudget($departementB);

        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson('/api/ligne-budgets');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json());
    }

    public function test_authorized_user_can_create_ligne_budget(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $budget = Budget::create([
            'nom' => 'Budget', 'code' => 'BUD-' . uniqid(), 'montant_global' => 100000,
            'annee' => 2026, 'statut' => 'ouvert', 'type' => 'fonctionnement',
        ]);

        $this->loginWithPermissions(['ligne.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/ligne-budgets', [
            'sous_categorie_id' => $sousCategorie->id,
            'budget_id' => $budget->id,
            'intitule' => 'Achat de papeterie',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ligne_budgets', ['intitule' => 'Achat de papeterie']);
    }

    public function test_ligne_budget_creation_generates_default_annuites(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $budget = Budget::create([
            'nom' => 'Budget', 'code' => 'BUD-' . uniqid(), 'montant_global' => 100000,
            'annee' => 2026, 'statut' => 'ouvert', 'type' => 'fonctionnement',
        ]);

        $this->loginWithPermissions(['ligne.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/ligne-budgets', [
            'sous_categorie_id' => $sousCategorie->id,
            'budget_id' => $budget->id,
            'intitule' => 'Achat de papeterie',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ligne_budget_annuites', [
            'ligne_budget_id' => $response->json('data.id'),
            'annee' => $budget->annee,
        ]);
    }

    public function test_unauthorized_user_cannot_create_ligne_budget(): void
    {
        $this->loginWithPermissions([]);

        $response = $this->postJson('/api/ligne-budgets', []);

        $response->assertStatus(403);
    }

    public function test_store_fails_when_categorie_belongs_to_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $categorie = Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $budget = Budget::create([
            'nom' => 'Budget', 'code' => 'BUD-' . uniqid(), 'montant_global' => 100000,
            'annee' => 2026, 'statut' => 'ouvert', 'type' => 'fonctionnement',
        ]);

        $this->loginWithPermissions(['ligne.create'], ['departement_id' => $departementA->id]);

        $response = $this->postJson('/api/ligne-budgets', [
            'sous_categorie_id' => $sousCategorie->id,
            'budget_id' => $budget->id,
            'intitule' => 'Achat de papeterie',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_store_fails_when_budget_is_clos(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $budget = Budget::create([
            'nom' => 'Budget', 'code' => 'BUD-' . uniqid(), 'montant_global' => 100000,
            'annee' => 2026, 'statut' => 'clos', 'type' => 'fonctionnement',
        ]);

        $this->loginWithPermissions(['ligne.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/ligne-budgets', [
            'sous_categorie_id' => $sousCategorie->id,
            'budget_id' => $budget->id,
            'intitule' => 'Achat de papeterie',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_store_fails_when_montant_exceeds_budget_global(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $budget = Budget::create([
            'nom' => 'Budget', 'code' => 'BUD-' . uniqid(), 'montant_global' => 1000,
            'annee' => 2026, 'statut' => 'ouvert', 'type' => 'fonctionnement',
        ]);

        $this->loginWithPermissions(['ligne.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/ligne-budgets', [
            'sous_categorie_id' => $sousCategorie->id,
            'budget_id' => $budget->id,
            'intitule' => 'Achat de papeterie',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_show_returns_404_for_unknown_ligne_budget(): void
    {
        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson('/api/ligne-budgets/999999');

        $response->assertStatus(404);
    }

    public function test_user_cannot_view_ligne_budget_from_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $ligne = $this->createLigneBudget($departementB);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/ligne-budgets/{$ligne->id}");

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_update_ligne_budget(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudget($departement);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}", [
            'categorie_id' => $ligne->categorie_id,
            'budget_id' => $ligne->budget_id,
            'intitule' => 'Intitulé mis à jour',
            'montant_alloue' => 2000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Intitulé mis à jour', $ligne->fresh()->intitule);
    }

    public function test_update_fails_when_montant_below_engaged_amount(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudget($departement, ['montant_alloue' => 5000]);
        $user = $this->createUser(['departement_id' => $departement->id]);

        BonCommande::create([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligne->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat',
            'montant_bc' => 4000,
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}", [
            'categorie_id' => $ligne->categorie_id,
            'budget_id' => $ligne->budget_id,
            'intitule' => $ligne->intitule,
            'montant_alloue' => 1000,
            'date_debut_amortissement' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_delete_ligne_budget_with_bons_commande(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudget($departement);
        $user = $this->createUser(['departement_id' => $departement->id]);

        BonCommande::create([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligne->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat',
            'montant_bc' => 100,
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/ligne-budgets/{$ligne->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('ligne_budgets', ['id' => $ligne->id]);
    }

    public function test_authorized_user_can_delete_unused_ligne_budget(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneBudget($departement);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/ligne-budgets/{$ligne->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('ligne_budgets', ['id' => $ligne->id]);
    }

    private function createLigneBudget(Departement $departement, array $attributes = []): LigneBudget
    {
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 100000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);

        return LigneBudget::create(array_merge([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ], $attributes));
    }
}