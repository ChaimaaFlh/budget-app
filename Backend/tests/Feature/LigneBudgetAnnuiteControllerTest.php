<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\SousCategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class LigneBudgetAnnuiteControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        $ligne = $this->createLigneFonctionnement(Departement::first());

        $response = $this->getJson("/api/ligne-budgets/{$ligne->id}/annuites");

        $response->assertStatus(401);
    }

    public function test_authorized_user_can_list_annuites(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement);
        LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 10000]);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->getJson("/api/ligne-budgets/{$ligne->id}/annuites");

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_user_cannot_list_annuites_from_another_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        $ligne = $this->createLigneFonctionnement($departementB);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/ligne-budgets/{$ligne->id}/annuites");

        $response->assertStatus(403);
    }

    public function test_store_requires_ligne_edit_permission(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annee' => 2026,
            'montant' => 10000,
        ]);

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_create_annuite_for_fonctionnement_line(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement, ['montant_alloue' => 10000]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annee' => 2026,
            'montant' => 10000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ligne_budget_annuites', [
            'ligne_budget_id' => $ligne->id,
            'annee' => 2026,
            'montant' => 10000,
        ]);
    }

    public function test_store_rejects_year_outside_fonctionnement_budget_year(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement, ['montant_alloue' => 10000], 2026);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annee' => 2027,
            'montant' => 10000,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('ligne_budget_annuites', 0);
    }

    public function test_store_rejects_year_outside_amortissement_period_for_investissement_line(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneInvestissement($departement, [
            'montant_alloue' => 50000,
            'date_debut_amortissement' => '2026-01-01',
            'duree_amortissement_annees' => 3,
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        // Période valide : 2026 à 2028. On teste une année hors période (2029).
        $response = $this->postJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annee' => 2029,
            'montant' => 10000,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_accepts_year_within_amortissement_period_for_investissement_line(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneInvestissement($departement, [
            'montant_alloue' => 30000,
            'date_debut_amortissement' => '2026-01-01',
            'duree_amortissement_annees' => 3,
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annee' => 2028,
            'montant' => 10000,
        ]);

        $response->assertStatus(201);
    }

    public function test_store_rejects_amount_exceeding_ligne_allocation(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement, ['montant_alloue' => 5000]);
        LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 4000]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        // updateOrCreate sur une année différente porterait le total à 4000 + 2000 = 6000 > 5000
        $response = $this->postJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annee' => 2026,
            'montant' => 6000,
        ]);

        $response->assertStatus(422);
    }

    public function test_authorized_user_can_update_annuite(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement, ['montant_alloue' => 20000]);
        $annuite = LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 5000]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}/annuites/{$annuite->id}", [
            'montant' => 15000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(15000, $annuite->fresh()->montant);
    }

    public function test_update_rejects_amount_exceeding_ligne_allocation(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement, ['montant_alloue' => 10000]);
        $annuiteA = LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 4000]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}/annuites/{$annuiteA->id}", [
            'montant' => 11000,
        ]);

        $response->assertStatus(422);
    }

    public function test_authorized_user_can_delete_annuite(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement);
        $annuite = LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 1000]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/ligne-budgets/{$ligne->id}/annuites/{$annuite->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('ligne_budget_annuites', ['id' => $annuite->id]);
    }

    public function test_unauthorized_user_cannot_delete_annuite(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement);
        $annuite = LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 1000]);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/ligne-budgets/{$ligne->id}/annuites/{$annuite->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('ligne_budget_annuites', ['id' => $annuite->id]);
    }

    public function test_replace_all_succeeds_when_sum_matches_allocation_exactly(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneInvestissement($departement, [
            'montant_alloue' => 30000,
            'date_debut_amortissement' => '2026-01-01',
            'duree_amortissement_annees' => 3,
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annuites' => [
                ['annee' => 2026, 'montant' => 10000],
                ['annee' => 2027, 'montant' => 10000],
                ['annee' => 2028, 'montant' => 10000],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('ligne_budget_annuites', 3);
    }

    public function test_replace_all_fails_when_sum_does_not_match_allocation(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneInvestissement($departement, [
            'montant_alloue' => 30000,
            'date_debut_amortissement' => '2026-01-01',
            'duree_amortissement_annees' => 3,
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annuites' => [
                ['annee' => 2026, 'montant' => 10000],
                ['annee' => 2027, 'montant' => 5000],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('ligne_budget_annuites', 0);
    }

    public function test_replace_all_fails_with_duplicate_years(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneInvestissement($departement, [
            'montant_alloue' => 20000,
            'date_debut_amortissement' => '2026-01-01',
            'duree_amortissement_annees' => 3,
        ]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annuites' => [
                ['annee' => 2026, 'montant' => 10000],
                ['annee' => 2026, 'montant' => 10000],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_replace_all_replaces_existing_annuites(): void
    {
        $departement = Departement::first();
        $ligne = $this->createLigneFonctionnement($departement, ['montant_alloue' => 10000]);
        LigneBudgetAnnuite::create(['ligne_budget_id' => $ligne->id, 'annee' => 2026, 'montant' => 10000]);

        $this->loginWithPermissions(['ligne.edit'], ['departement_id' => $departement->id]);

        $response = $this->putJson("/api/ligne-budgets/{$ligne->id}/annuites", [
            'annuites' => [
                ['annee' => 2026, 'montant' => 10000],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('ligne_budget_annuites', 1);
    }

    private function createLigneFonctionnement(
        Departement $departement,
        array $ligneAttributes = [],
        int $anneeBudget = 2026,
    ): LigneBudget {
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 1000000,
            'annee' => $anneeBudget,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);

        return LigneBudget::create(array_merge([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne fonctionnement test',
            'montant_alloue' => 100000,
            'date_debut_amortissement' => "{$anneeBudget}-01-01",
            'duree_amortissement_annees' => 1,
        ], $ligneAttributes));
    }

    private function createLigneInvestissement(Departement $departement, array $ligneAttributes = []): LigneBudget
    {
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 1000000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'investissement',
        ]);

        return LigneBudget::create(array_merge([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne investissement test',
            'montant_alloue' => 100000,
            'date_debut_amortissement' => '2026-01-01',
            'duree_amortissement_annees' => 5,
        ], $ligneAttributes));
    }
}