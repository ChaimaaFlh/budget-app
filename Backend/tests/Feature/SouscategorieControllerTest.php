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

class SousCategorieControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/sous-categories');

        $response->assertStatus(401);
    }

    public function test_user_only_sees_sous_categories_from_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $categorieA = Categorie::create(['departement_id' => $departementA->id, 'nom' => 'Cat A']);
        $categorieB = Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);
        $sousCategorieA = SousCategorie::create(['categorie_id' => $categorieA->id, 'nom' => 'Sous A']);
        SousCategorie::create(['categorie_id' => $categorieB->id, 'nom' => 'Sous B']);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson('/api/sous-categories');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($sousCategorieA->id, $response->json('0.id'));
    }

    public function test_authorized_user_can_create_sous_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/sous-categories', [
            'categorie_id' => $categorie->id,
            'nom' => 'Papeterie',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sous_categories', ['nom' => 'Papeterie', 'categorie_id' => $categorie->id]);
    }

    public function test_unauthorized_user_cannot_create_sous_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/sous-categories', [
            'categorie_id' => $categorie->id,
            'nom' => 'Papeterie',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_fails_when_categorie_not_visible_to_user(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $categorie = Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Fournitures']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departementA->id]);

        $response = $this->postJson('/api/sous-categories', [
            'categorie_id' => $categorie->id,
            'nom' => 'Papeterie',
        ]);

        $response->assertStatus(404);
    }

    public function test_store_fails_without_required_fields(): void
    {
        $departement = Departement::first();
        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/sous-categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['categorie_id', 'nom']);
    }

    public function test_show_returns_404_for_sous_categorie_from_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $categorie = Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous B']);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/sous-categories/{$sousCategorie->id}");

        $response->assertStatus(404);
    }

    public function test_authorized_user_can_update_sous_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Ancien Nom']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/sous-categories/{$sousCategorie->id}", [
            'categorie_id' => $categorie->id,
            'nom' => 'Nouveau Nom',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Nouveau Nom', $sousCategorie->fresh()->nom);
    }

    public function test_unauthorized_user_cannot_update_sous_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/sous-categories/{$sousCategorie->id}", [
            'categorie_id' => $categorie->id,
            'nom' => 'Autre Nom',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_sous_categorie_with_ligne_budgets(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $budget = Budget::create([
            'nom' => 'Budget',
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 10000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);
        LigneBudget::create([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 500,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ]);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/sous-categories/{$sousCategorie->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('sous_categories', ['id' => $sousCategorie->id]);
    }

    public function test_authorized_user_can_delete_unused_sous_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/sous-categories/{$sousCategorie->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('sous_categories', ['id' => $sousCategorie->id]);
    }
}