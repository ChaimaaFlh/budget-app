<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\Permission;
use App\Models\SousCategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LigneBudgetModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ligne_budget_belongs_to_budget(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $this->assertInstanceOf(Budget::class, $ligneBudget->budget);
    }

    public function test_ligne_budget_belongs_to_sous_categorie(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $this->assertInstanceOf(SousCategorie::class, $ligneBudget->sous_categorie);
    }

    public function test_ligne_budget_belongs_to_categorie(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $this->assertInstanceOf(Categorie::class, $ligneBudget->categorie);
    }

    public function test_ligne_budget_belongs_to_departement(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $this->assertInstanceOf(Departement::class, $ligneBudget->departement);
    }

    public function test_ligne_budget_has_many_bons_commande(): void
    {
        $ligneBudget = $this->createLigneBudget();

        $this->assertCount(0, $ligneBudget->bons_commande);
    }

    public function test_ligne_budget_has_many_annuites(): void
    {
        $ligneBudget = $this->createLigneBudget();

        LigneBudgetAnnuite::create([
            'ligne_budget_id' => $ligneBudget->id,
            'annee' => 2026,
            'montant' => 1000,
        ]);

        $this->assertCount(1, $ligneBudget->fresh()->annuites);
    }

    public function test_date_fin_validite_is_computed_from_debut_plus_duree(): void
    {
        $ligneBudget = $this->createLigneBudget([
            'date_debut_amortissement' => '2026-01-15',
            'duree_amortissement_annees' => 5,
        ]);

        $this->assertEquals('2031-01-15', $ligneBudget->date_fin_validite->format('Y-m-d'));
    }

    public function test_est_encore_valide_returns_true_before_end_date(): void
    {
        $ligneBudget = $this->createLigneBudget([
            'date_debut_amortissement' => now()->subYear(),
            'duree_amortissement_annees' => 5,
        ]);

        $this->assertTrue($ligneBudget->estEncoreValide());
    }

    public function test_est_encore_valide_returns_false_after_end_date(): void
    {
        $ligneBudget = $this->createLigneBudget([
            'date_debut_amortissement' => now()->subYears(10),
            'duree_amortissement_annees' => 5,
        ]);

        $this->assertFalse($ligneBudget->estEncoreValide());
    }

    public function test_scope_visible_par_utilisateur_restricts_to_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $ligneA = $this->createLigneBudget(['departement_id' => $departementA->id]);
        $this->createLigneBudget(['departement_id' => $departementB->id]);

        $user = User::factory()->create(['departement_id' => $departementA->id]);

        $visibles = LigneBudget::visibleParUtilisateur($user)->get();

        $this->assertCount(1, $visibles);
        $this->assertEquals($ligneA->id, $visibles->first()->id);
    }

    public function test_scope_visible_par_utilisateur_shows_all_with_department_wide_access(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $this->createLigneBudget(['departement_id' => $departementA->id]);
        $this->createLigneBudget(['departement_id' => $departementB->id]);

        $user = User::factory()->create(['departement_id' => $departementA->id]);
        $ids = Permission::whereIn('code', ['departement.view_all', 'user.manage_permissions'])->pluck('id');
        $user->permissions()->attach($ids);

        $visibles = LigneBudget::visibleParUtilisateur($user->fresh())->get();

        $this->assertCount(2, $visibles);
    }

    public function test_ligne_budget_code_must_be_unique(): void
    {
        $this->createLigneBudget(['code' => 'LB-DUP']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->createLigneBudget(['code' => 'LB-DUP']);
    }

    public function test_ligne_budget_uses_soft_deletes(): void
    {
        $ligneBudget = $this->createLigneBudget();
        $ligneBudget->delete();

        $this->assertSoftDeleted('ligne_budgets', ['id' => $ligneBudget->id]);
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