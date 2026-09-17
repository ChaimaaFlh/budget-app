<?php

namespace Tests\Unit;

use App\Models\Departement;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_seed_resource_level_permissions(): void
    {
        // 10 permissions de base + 14 ajoutées par split_resource_permissions
        // + 3 ajoutées par add_supplier_scores_and_permissions (fournisseur.*) = 27
        $this->assertEquals(27, Permission::count());
        $this->assertNotNull(Permission::where('code', 'bc.unvalidate')->first());
        $this->assertNotNull(Permission::where('code', 'budget.edit')->first());
        $this->assertNotNull(Permission::where('code', 'souscategorie.delete')->first());
        $this->assertNotNull(Permission::where('code', 'fournisseur.create')->first());
    }

    public function test_permission_belongs_to_many_users(): void
    {
        $permission = Permission::where('code', 'budget.create')->first();
        $userA = User::factory()->create(['departement_id' => Departement::first()->id]);
        $userB = User::factory()->create(['departement_id' => Departement::first()->id]);

        $permission->users()->attach([$userA->id, $userB->id]);

        $this->assertCount(2, $permission->fresh()->users);
    }

    public function test_permission_code_is_unique(): void
    {
        Permission::create(['code' => 'custom.unique', 'libelle' => 'Permission personnalisée']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Permission::create(['code' => 'custom.unique', 'libelle' => 'Doublon']);
    }

    public function test_permission_fillable_attributes_are_saved(): void
    {
        $permission = Permission::create(['code' => 'custom.test', 'libelle' => 'Permission de test']);

        $this->assertDatabaseHas('permissions', ['code' => 'custom.test']);
        $this->assertEquals('Permission de test', $permission->libelle);
    }
}