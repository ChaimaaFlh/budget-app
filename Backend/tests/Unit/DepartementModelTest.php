<?php

namespace Tests\Unit;

use App\Models\Departement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartementModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_departement_has_many_users(): void
    {
        $departement = Departement::first();
        User::factory()->count(2)->create(['departement_id' => $departement->id]);

        $this->assertCount(2, $departement->users);
    }

    public function test_departement_nom_is_fillable(): void
    {
        $departement = Departement::create(['nom' => 'Test Département']);

        $this->assertDatabaseHas('departements', ['nom' => 'Test Département']);
        $this->assertEquals('Test Département', $departement->nom);
    }

    public function test_migration_seeds_six_default_departements(): void
    {
        $this->assertEquals(6, Departement::count());
    }
}
