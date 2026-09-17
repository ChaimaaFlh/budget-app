<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Departement;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class CategorieControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }

    public function test_user_only_sees_categories_from_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $categorieA = Categorie::create(['departement_id' => $departementA->id, 'nom' => 'Cat A']);
        Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($categorieA->id, $response->json('0.id'));
    }

    public function test_admin_sees_categories_from_all_departements(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        Categorie::create(['departement_id' => $departementA->id, 'nom' => 'Cat A']);
        Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);

        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json());
    }

    public function test_authorized_user_can_create_categorie(): void
    {
        $departement = Departement::first();
        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/categories', ['nom' => 'Fournitures']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['nom' => 'Fournitures', 'departement_id' => $departement->id]);
    }

    public function test_unauthorized_user_cannot_create_categorie(): void
    {
        $this->loginWithPermissions([]);

        $response = $this->postJson('/api/categories', ['nom' => 'Fournitures']);

        $response->assertStatus(403);
    }

    public function test_store_fails_with_duplicate_nom_in_same_departement(): void
    {
        $departement = Departement::first();
        Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->postJson('/api/categories', ['nom' => 'Fournitures']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_store_allows_same_nom_in_different_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Fournitures']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departementA->id]);

        $response = $this->postJson('/api/categories', ['nom' => 'Fournitures']);

        $response->assertStatus(201);
    }

    public function test_show_returns_404_for_categorie_from_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $categorie = Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/categories/{$categorie->id}");

        $response->assertStatus(404);
    }

    public function test_authorized_user_can_update_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Ancien Nom']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/categories/{$categorie->id}", ['nom' => 'Nouveau Nom']);

        $response->assertStatus(200);
        $this->assertEquals('Nouveau Nom', $categorie->fresh()->nom);
    }

    public function test_update_fails_with_duplicate_nom_from_other_categorie(): void
    {
        $departement = Departement::first();
        $other = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Cat Existante']);
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Cat À Modifier']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/categories/{$categorie->id}", ['nom' => $other->nom]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_unauthorized_user_cannot_update_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Cat']);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/categories/{$categorie->id}", ['nom' => 'Autre Nom']);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_categorie_with_sous_categories(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);
        SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/categories/{$categorie->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('categories', ['id' => $categorie->id]);
    }

    public function test_authorized_user_can_delete_unused_categorie(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);

        $this->loginWithPermissions(['categorie.manage'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/categories/{$categorie->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('categories', ['id' => $categorie->id]);
    }
}