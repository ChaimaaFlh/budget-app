<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LigneBudgetAnnuiteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_annuite_belongs_to_ligne_budget(): void
    {
        $ligneBudget = $this->createLigneBudget();
        $annuite = LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2026,
            'montant' => 1500,
        ]);

        $this->assertInstanceOf(LigneBudget::class, $annuite->ligneBudget);
        $this->assertEquals($ligneBudget->id, $annuite->ligneBudget->id);
    }

    public function test_annuite_fillable_attributes_are_saved(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $annuite = LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2027,
            'montant' => 2500,
        ]);

        $this->assertDatabaseHas('ligne_budget_annuites', [
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2027,
        ]);
        $this->assertEquals(2027, $annuite->annee);
    }

    public function test_montant_is_cast_to_decimal_with_two_places(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $annuite = LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2026,
            'montant' => 999.999,
        ]);

        $this->assertEquals('1000.00', $annuite->montant);
    }

    public function test_annee_must_be_unique_per_ligne_budget(): void
    {
        $ligneBudget = $this->createLigneBudget();

        LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2026,
            'montant' => 1000,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2026,
            'montant' => 2000,
        ]);
    }

    public function test_same_annee_is_allowed_for_different_ligne_budgets(): void
    {
        $ligneBudgetA = $this->createLigneBudget();
        $ligneBudgetB = $this->createLigneBudget();

        LigneBudgetAnnuite::create(['ligne_budget_id' => $ligneBudgetA->id, 'annee' => 2026, 'montant' => 1000]);
        LigneBudgetAnnuite::create(['ligne_budget_id' => $ligneBudgetB->id, 'annee' => 2026, 'montant' => 2000]);

        $this->assertEquals(2, LigneBudgetAnnuite::where('annee', 2026)->count());
    }

    public function test_deleting_ligne_budget_cascades_to_annuites(): void
    {
        $ligneBudget = $this->createLigneBudget();
        LigneBudgetAnnuite::create(['ligne_budget_id' => $ligneBudget->id, 'annee' => 2026, 'montant' => 1000]);

        $ligneBudget->forceDelete();

        $this->assertDatabaseCount('ligne_budget_annuites', 0);
    }

    private function createLigneBudget(array $attributes = []): LigneBudget
    {
        $departement = Departement::first();
        $categorie = Categorie::create(['nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create([
            'categorie_id' => $categorie->id,
            'nom' => 'Sous-catégorie ' . uniqid(),
        ]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 100000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);

        return LigneBudget::create(array_merge([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne budgétaire test',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ], $attributes));
    }
}