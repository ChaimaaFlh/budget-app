<?php

namespace Tests\Unit;

use App\Models\Categorie;
use App\Models\Departement;
use App\Models\Permission;
use App\Models\SousCategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorieModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_categorie_belongs_to_departement(): void
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Fournitures']);

        $this->assertInstanceOf(Departement::class, $categorie->departement);
        $this->assertEquals($departement->id, $categorie->departement->id);
    }

    public function test_categorie_has_many_sous_categories(): void
    {
        $categorie = Categorie::create(['nom' => 'Fournitures']);
        SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Mobilier']);

        $this->assertCount(2, $categorie->fresh()->sousCategories);
    }

    public function test_categorie_uses_soft_deletes(): void
    {
        $categorie = Categorie::create(['nom' => 'Fournitures']);
        $categorie->delete();

        $this->assertSoftDeleted('categories', ['id' => $categorie->id]);
    }

    public function test_scope_visible_par_utilisateur_restricts_to_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $categorieA = Categorie::create(['departement_id' => $departementA->id, 'nom' => 'Cat A']);
        Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);

        $user = User::factory()->create(['departement_id' => $departementA->id]);

        $visibles = Categorie::visibleParUtilisateur($user)->get();

        $this->assertCount(1, $visibles);
        $this->assertEquals($categorieA->id, $visibles->first()->id);
    }

    public function test_scope_visible_par_utilisateur_shows_all_with_department_wide_access(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        Categorie::create(['departement_id' => $departementA->id, 'nom' => 'Cat A']);
        Categorie::create(['departement_id' => $departementB->id, 'nom' => 'Cat B']);

        $user = User::factory()->create(['departement_id' => $departementA->id]);
        $ids = Permission::whereIn('code', ['departement.view_all', 'user.manage_permissions'])->pluck('id');
        $user->permissions()->attach($ids);

        $visibles = Categorie::visibleParUtilisateur($user->fresh())->get();

        $this->assertCount(2, $visibles);
    }
}