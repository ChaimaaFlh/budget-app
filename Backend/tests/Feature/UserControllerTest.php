<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class UserControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_admin_can_list_users(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $this->createUser();
        $this->createUser();

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json()));
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $this->loginWithPermissions(['bc.create']);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_admin_can_create_user(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $departement = Departement::first();

        $response = $this->postJson('/api/users', [
            'name' => 'Nouvel Utilisateur',
            'email' => 'nouveau@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
            'departement_id' => $departement->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@example.com',
            'is_active' => 1,
            'must_change_password' => 1,
        ]);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $this->loginWithPermissions(['bc.create']);
        $departement = Departement::first();

        $response = $this->postJson('/api/users', [
            'name' => 'Nouvel Utilisateur',
            'email' => 'nouveau2@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
            'departement_id' => $departement->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'nouveau2@example.com']);
    }

    public function test_store_fails_with_duplicate_email(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $existing = $this->createUser(['email' => 'duplicate@example.com']);
        $departement = Departement::first();

        $response = $this->postJson('/api/users', [
            'name' => 'Doublon',
            'email' => 'duplicate@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
            'departement_id' => $departement->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_fails_with_short_password(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $departement = Departement::first();

        $response = $this->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'departement_id' => $departement->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_store_fails_with_invalid_departement(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);

        $response = $this->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'test2@example.com',
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
            'departement_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['departement_id']);
    }

    public function test_admin_can_deactivate_another_user(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser(['is_active' => true]);

        $response = $this->patchJson("/api/users/{$target->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_deactivating_user_revokes_their_tokens(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser(['is_active' => true]);
        $target->createToken('api-token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->patchJson("/api/users/{$target->id}/status", [
            'is_active' => false,
        ])->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = $this->loginWithPermissions(['user.manage_permissions']);

        $response = $this->patchJson("/api/users/{$admin->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_non_admin_cannot_update_user_status(): void
    {
        $this->loginWithPermissions(['bc.create']);
        $target = $this->createUser(['is_active' => true]);

        $response = $this->patchJson("/api/users/{$target->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_status_fails_with_non_boolean_value(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser();

        $response = $this->patchJson("/api/users/{$target->id}/status", [
            'is_active' => 'not-a-boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_update_status_returns_404_for_unknown_user(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);

        $response = $this->patchJson('/api/users/999999/status', [
            'is_active' => false,
        ]);

        $response->assertStatus(404);
    }
}