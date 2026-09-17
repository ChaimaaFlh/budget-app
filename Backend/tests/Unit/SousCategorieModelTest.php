<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SousCategorieModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sous_categorie_belongs_to_categorie(): void
    {
        $categorie = Categorie::create(['nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);

        $this->assertInstanceOf(Categorie::class, $sousCategorie->categorie);
        $this->assertEquals($categorie->id, $sousCategorie->categorie->id);
    }

    public function test_sous_categorie_has_many_ligne_budgets(): void
    {
        $categorie = Categorie::create(['nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);
        $departement = Departement::first();
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

        $this->assertCount(1, $sousCategorie->fresh()->ligneBudgets);
    }

    public function test_sous_categorie_uses_soft_deletes(): void
    {
        $categorie = Categorie::create(['nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);

        $sousCategorie->delete();

        $this->assertSoftDeleted('sous_categories', ['id' => $sousCategorie->id]);
    }

    public function test_deleting_categorie_cascades_to_sous_categories(): void
    {
        $categorie = Categorie::create(['nom' => 'Fournitures']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Papeterie']);

        $categorie->forceDelete();

        $this->assertDatabaseCount('sous_categories', 0);
        $this->assertNull(SousCategorie::find($sousCategorie->id));
    }
}