<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_fillable_attributes_are_saved(): void
    {
        $budget = Budget::create([
            'nom' => 'Budget Test',
            'code' => 'BUD-001',
            'montant_global' => 100000,
            'description' => 'Un budget de test',
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);

        $this->assertDatabaseHas('budgets', ['code' => 'BUD-001']);
        $this->assertEquals('Budget Test', $budget->nom);
    }

    public function test_budget_has_many_ligne_budgets(): void
    {
        $budget = $this->createBudget();
        $departement = Departement::first();
        $categorie = Categorie::create(['nom' => 'Cat']);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'SousCat']);

        LigneBudget::create([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-001',
            'intitule' => 'Ligne test',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ]);

        $this->assertCount(1, $budget->fresh()->ligne_budgets);
    }

    public function test_est_clos_returns_true_when_statut_is_clos(): void
    {
        $budget = $this->createBudget(['statut' => 'clos']);

        $this->assertTrue($budget->estClos());
    }

    public function test_est_clos_returns_false_when_statut_is_ouvert(): void
    {
        $budget = $this->createBudget(['statut' => 'ouvert']);

        $this->assertFalse($budget->estClos());
    }

    public function test_budget_code_must_be_unique(): void
    {
        $this->createBudget(['code' => 'BUD-DUP']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->createBudget(['code' => 'BUD-DUP']);
    }

    public function test_budget_uses_soft_deletes(): void
    {
        $budget = $this->createBudget();
        $budget->delete();

        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
        $this->assertNull(Budget::find($budget->id));
        $this->assertNotNull(Budget::withTrashed()->find($budget->id));
    }

    public function test_montant_global_is_cast_to_decimal_with_two_places(): void
    {
        $budget = $this->createBudget(['montant_global' => 12345.678]);

        $this->assertEquals('12345.68', $budget->montant_global);
    }

    private function createBudget(array $attributes = []): Budget
    {
        return Budget::create(array_merge([
            'nom' => 'Budget Test',
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 100000,
            'description' => 'Un budget de test',
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ], $attributes));
    }
}