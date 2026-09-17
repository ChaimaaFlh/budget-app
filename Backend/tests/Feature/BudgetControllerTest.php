<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/budgets');

        $response->assertStatus(401);
    }

    public function test_admin_can_list_all_budgets(): void
    {
        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);
        $this->createBudget();
        $this->createBudget();

        $response = $this->getJson('/api/budgets');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json());
    }

    public function test_authorized_user_can_create_budget(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);

        $response = $this->postJson('/api/budgets', [
            'nom' => 'Budget Fonctionnement 2026',
            'montant_global' => 500000,
            'annee' => 2026,
            'type' => 'fonctionnement',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('budgets', ['nom' => 'Budget Fonctionnement 2026']);
    }

    public function test_unauthorized_user_cannot_create_budget(): void
    {
        $this->loginWithPermissions(['ligne.create']);

        $response = $this->postJson('/api/budgets', [
            'nom' => 'Budget Test',
            'montant_global' => 500000,
            'annee' => 2026,
            'type' => 'fonctionnement',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('budgets', ['nom' => 'Budget Test']);
    }

    public function test_user_without_department_wide_access_cannot_create_budget(): void
    {
        $this->loginWithPermissions(['budget.create']);

        $response = $this->postJson('/api/budgets', [
            'nom' => 'Budget Test',
            'montant_global' => 500000,
            'annee' => 2026,
            'type' => 'fonctionnement',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_fails_without_required_fields(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);

        $response = $this->postJson('/api/budgets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom', 'montant_global', 'annee', 'type']);
    }

    public function test_store_generates_code_automatically_when_not_provided(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);

        $response = $this->postJson('/api/budgets', [
            'nom' => 'Budget Sans Code',
            'montant_global' => 100000,
            'annee' => 2026,
            'type' => 'investissement',
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.code'));
    }

    public function test_store_fails_with_duplicate_code(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);
        $existing = $this->createBudget(['code' => 'BUD-DUP']);

        $response = $this->postJson('/api/budgets', [
            'code' => 'BUD-DUP',
            'nom' => 'Budget Dupliqué',
            'montant_global' => 100000,
            'annee' => 2026,
            'type' => 'investissement',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_show_returns_404_for_unknown_budget(): void
    {
        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson('/api/budgets/999999');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_budget(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);
        $budget = $this->createBudget();

        $response = $this->putJson("/api/budgets/{$budget->id}", [
            'nom' => 'Budget Modifié',
            'montant_global' => 200000,
            'description' => 'Mise à jour',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Budget Modifié', $budget->fresh()->nom);
    }

    public function test_update_fails_when_new_montant_below_total_alloue(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);
        $budget = $this->createBudget(['montant_global' => 100000]);
        $this->createLigneBudget($budget, ['montant_alloue' => 80000]);

        $response = $this->putJson("/api/budgets/{$budget->id}", [
            'nom' => $budget->nom,
            'montant_global' => 50000,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_delete_budget_with_ligne_budgets(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);
        $budget = $this->createBudget();
        $this->createLigneBudget($budget);

        $response = $this->deleteJson("/api/budgets/{$budget->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    public function test_authorized_user_can_delete_unused_budget(): void
    {
        $this->loginWithPermissions(['budget.create', 'departement.view_all', 'user.manage_permissions']);
        $budget = $this->createBudget();

        $response = $this->deleteJson("/api/budgets/{$budget->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
    }

    public function test_admin_can_close_budget(): void
    {
        $this->loginWithPermissions(['budget.close', 'departement.view_all', 'user.manage_permissions']);
        $budget = $this->createBudget();

        $response = $this->postJson("/api/budgets/{$budget->id}/close");

        $response->assertStatus(200);
        $this->assertEquals('clos', $budget->fresh()->statut);
    }

    public function test_user_without_budget_close_permission_cannot_close_budget(): void
    {
        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);
        $budget = $this->createBudget();

        $response = $this->postJson("/api/budgets/{$budget->id}/close");

        $response->assertStatus(403);
    }

    private function createBudget(array $attributes = []): Budget
    {
        return Budget::create(array_merge([
            'nom' => 'Budget Test',
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 100000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ], $attributes));
    }

    private function createLigneBudget(Budget $budget, array $attributes = []): LigneBudget
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);

        return LigneBudget::create(array_merge([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 1000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ], $attributes));
    }
}