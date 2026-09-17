<?php

namespace Tests\Unit;

use App\Models\Departement;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_a_departement(): void
    {
        $departement = Departement::first();
        $user = User::factory()->create(['departement_id' => $departement->id]);

        $this->assertInstanceOf(Departement::class, $user->departement);
        $this->assertEquals($departement->id, $user->departement->id);
    }

    public function test_user_has_many_permissions(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);
        $permission = Permission::where('code', 'bc.create')->first();

        $user->permissions()->attach($permission->id);

        $this->assertCount(1, $user->permissions);
        $this->assertEquals('bc.create', $user->permissions->first()->code);
    }

    public function test_has_permission_returns_true_when_assigned(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);
        $permission = Permission::where('code', 'ligne.create')->first();
        $user->permissions()->attach($permission->id);

        $this->assertTrue($user->hasPermission('ligne.create'));
    }

    public function test_has_permission_returns_false_when_not_assigned(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);

        $this->assertFalse($user->hasPermission('ligne.create'));
    }

    public function test_has_permission_returns_false_for_unknown_code(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);

        $this->assertFalse($user->hasPermission('code.qui.nexiste.pas'));
    }

    public function test_has_department_wide_access_requires_both_permissions(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);
        $viewAll = Permission::where('code', 'departement.view_all')->first();

        $user->permissions()->attach($viewAll->id);
        $this->assertFalse($user->fresh()->hasDepartmentWideAccess());

        $manage = Permission::where('code', 'user.manage_permissions')->first();
        $user->permissions()->attach($manage->id);
        $this->assertTrue($user->fresh()->hasDepartmentWideAccess());
    }

    public function test_has_department_wide_access_is_false_with_no_permissions(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);

        $this->assertFalse($user->hasDepartmentWideAccess());
    }

    public function test_password_is_hidden_from_array_and_json(): void
    {
        $user = User::factory()->create(['departement_id' => Departement::first()->id]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_password_is_automatically_hashed_via_cast(): void
    {
        $user = User::factory()->create([
            'departement_id' => Departement::first()->id,
            'password' => 'plain-text-password',
        ]);

        $this->assertNotEquals('plain-text-password', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('plain-text-password', $user->password));
    }
}