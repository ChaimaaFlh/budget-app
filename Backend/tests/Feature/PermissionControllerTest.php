<?php

namespace Tests\Feature;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_authenticated_user_can_list_all_permissions(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);

        $response = $this->getJson('/api/permissions');

        $response->assertStatus(200);
        $this->assertCount(Permission::where('code', '!=', 'categorie.manage')->count(), $response->json());
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/permissions');

        $response->assertStatus(401);
    }

    public function test_admin_can_view_a_users_permissions(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUserWithPermissions(['bc.create', 'ligne.create']);

        $response = $this->getJson("/api/users/{$target->id}/permissions");

        $response->assertStatus(200);
        $this->assertCount(4, $response->json());
    }

    public function test_non_admin_cannot_view_a_users_permissions(): void
    {
        $this->loginWithPermissions(['bc.create']);
        $target = $this->createUserWithPermissions(['bc.create']);

        $response = $this->getJson("/api/users/{$target->id}/permissions");

        $response->assertStatus(403);
    }

    public function test_user_permissions_returns_404_for_unknown_user(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);

        $response = $this->getJson('/api/users/999999/permissions');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_a_users_permissions(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser();
        $ids = Permission::whereIn('code', ['bc.create', 'ligne.create'])->pluck('id');

        $response = $this->putJson("/api/users/{$target->id}/permissions", [
            'permission_ids' => $ids->all(),
        ]);

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing($ids->all(), $target->permissions()->pluck('permissions.id')->all());
    }

    public function test_non_admin_cannot_update_permissions(): void
    {
        $this->loginWithPermissions(['bc.create']);
        $target = $this->createUser();
        $ids = Permission::whereIn('code', ['bc.create'])->pluck('id');

        $response = $this->putJson("/api/users/{$target->id}/permissions", [
            'permission_ids' => $ids->all(),
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_update_their_own_permissions(): void
    {
        $admin = $this->loginWithPermissions(['user.manage_permissions']);
        $ids = Permission::whereIn('code', ['bc.create'])->pluck('id');

        $response = $this->putJson("/api/users/{$admin->id}/permissions", [
            'permission_ids' => $ids->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_update_permissions_fails_with_invalid_permission_id(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser();

        $response = $this->putJson("/api/users/{$target->id}/permissions", [
            'permission_ids' => [999999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids.0']);
    }

    public function test_cannot_assign_view_all_without_manage_permissions(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser();
        $viewAllId = Permission::where('code', 'departement.view_all')->value('id');

        $response = $this->putJson("/api/users/{$target->id}/permissions", [
            'permission_ids' => [$viewAllId],
        ]);

        $response->assertStatus(422);
        $this->assertCount(0, $target->fresh()->permissions);
    }

    public function test_can_assign_view_all_together_with_manage_permissions(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $target = $this->createUser();
        $ids = Permission::whereIn('code', ['departement.view_all', 'user.manage_permissions'])->pluck('id');

        $response = $this->putJson("/api/users/{$target->id}/permissions", [
            'permission_ids' => $ids->all(),
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $target->fresh()->permissions);
    }

    public function test_update_permissions_returns_404_for_unknown_user(): void
    {
        $this->loginWithPermissions(['user.manage_permissions']);
        $ids = Permission::whereIn('code', ['bc.create'])->pluck('id');

        $response = $this->putJson('/api/users/999999/permissions', [
            'permission_ids' => $ids->all(),
        ]);

        $response->assertStatus(404);
    }
}
