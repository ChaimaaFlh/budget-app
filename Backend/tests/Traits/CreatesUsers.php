<?php

namespace Tests\Traits;

use App\Models\Departement;
use App\Models\Permission;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Helpers communs pour créer des utilisateurs de test (avec ou sans
 * permissions) et les authentifier via Sanctum dans les Feature tests.
 */
trait CreatesUsers
{
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'departement_id' => Departement::first()->id ?? Departement::factory(),
            'is_active' => true,
            'must_change_password' => false,
        ], $attributes));
    }

    protected function createUserWithPermissions(array $permissionCodes = [], array $attributes = []): User
    {
        $user = $this->createUser($attributes);

        if (!empty($permissionCodes)) {
            $legacyPermissions = [
                'budget.create' => ['budget.edit', 'budget.delete'],
                'ligne.edit' => ['ligne.delete'],
                'bc.create' => ['bc.edit', 'bc.delete'],
                'facture.create' => ['facture.edit', 'facture.delete'],
                'categorie.manage' => ['categorie.create', 'categorie.edit', 'categorie.delete', 'souscategorie.create', 'souscategorie.edit', 'souscategorie.delete'],
            ];
            $permissionCodes = collect($permissionCodes)
                ->flatMap(fn (string $code) => array_merge([$code], $legacyPermissions[$code] ?? []))
                ->unique()
                ->all();
            $ids = Permission::whereIn('code', $permissionCodes)->pluck('id');
            $user->permissions()->sync($ids);
        }

        return $user;
    }

    protected function actingAsUser(User $user, array $abilities = ['*']): User
    {
        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    protected function loginWithPermissions(array $permissionCodes = [], array $attributes = []): User
    {
        $user = $this->createUserWithPermissions($permissionCodes, $attributes);
        $this->actingAsUser($user);

        return $user;
    }
}
