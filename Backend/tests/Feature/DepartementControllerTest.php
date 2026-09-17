<?php

namespace Tests\Feature;

use App\Models\Departement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class DepartementControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_user_with_view_all_can_list_departements(): void
    {
        $this->loginWithPermissions(['departement.view_all']);

        $response = $this->getJson('/api/departements');

        $response->assertStatus(200);
        $this->assertCount(Departement::count(), $response->json());
    }

    public function test_user_without_view_all_cannot_list_departements(): void
    {
        $this->loginWithPermissions(['bc.create']);

        $response = $this->getJson('/api/departements');

        $response->assertStatus(403);
    }

    public function test_user_with_view_all_can_show_a_departement(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $departement = Departement::first();

        $response = $this->getJson("/api/departements/{$departement->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $departement->id]);
    }

    public function test_show_returns_404_for_unknown_departement(): void
    {
        $this->loginWithPermissions(['departement.view_all']);

        $response = $this->getJson('/api/departements/999999');

        $response->assertStatus(404);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/departements');

        $response->assertStatus(401);
    }

    public function test_authorized_user_can_create_departement(): void
    {
        $this->loginWithPermissions(['departement.view_all']);

        $response = $this->postJson('/api/departements', [
            'nom' => 'Nouveau Département',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('departements', ['nom' => 'Nouveau Département']);
    }

    public function test_unauthorized_user_cannot_create_departement(): void
    {
        $this->loginWithPermissions(['bc.create']);

        $response = $this->postJson('/api/departements', [
            'nom' => 'Nouveau Département',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('departements', ['nom' => 'Nouveau Département']);
    }

    public function test_store_fails_with_duplicate_nom(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $existing = Departement::first();

        $response = $this->postJson('/api/departements', [
            'nom' => $existing->nom,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_store_fails_without_nom(): void
    {
        $this->loginWithPermissions(['departement.view_all']);

        $response = $this->postJson('/api/departements', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_authorized_user_can_update_departement(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $departement = Departement::create(['nom' => 'Ancien Nom']);

        $response = $this->putJson("/api/departements/{$departement->id}", [
            'nom' => 'Nom Modifié',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Nom Modifié', $departement->fresh()->nom);
    }

    public function test_update_allows_keeping_same_nom(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $departement = Departement::create(['nom' => 'Nom Stable']);

        $response = $this->putJson("/api/departements/{$departement->id}", [
            'nom' => 'Nom Stable',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_fails_with_duplicate_nom_from_other_departement(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $other = Departement::first();
        $departement = Departement::create(['nom' => 'Nom Original']);

        $response = $this->putJson("/api/departements/{$departement->id}", [
            'nom' => $other->nom,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_authorized_user_can_delete_unused_departement(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $departement = Departement::create(['nom' => 'À Supprimer']);

        $response = $this->deleteJson("/api/departements/{$departement->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('departements', ['id' => $departement->id]);
    }

    public function test_cannot_delete_departement_with_users(): void
    {
        $this->loginWithPermissions(['departement.view_all']);
        $departement = Departement::create(['nom' => 'Avec Utilisateurs']);
        $this->createUser(['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/departements/{$departement->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('departements', ['id' => $departement->id]);
    }

    public function test_unauthorized_user_cannot_delete_departement(): void
    {
        $this->loginWithPermissions(['bc.create']);
        $departement = Departement::create(['nom' => 'Protégé']);

        $response = $this->deleteJson("/api/departements/{$departement->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('departements', ['id' => $departement->id]);
    }
}