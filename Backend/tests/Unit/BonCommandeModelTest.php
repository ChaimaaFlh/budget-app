<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\BonCommande;
use App\Models\Departement;
use App\Models\Facture;
use App\Models\LigneBudget;
use App\Models\Permission;
use App\Models\SousCategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonCommandeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_bon_commande_belongs_to_departement(): void
    {
        $bonCommande = $this->createBonCommande();

        $this->assertInstanceOf(Departement::class, $bonCommande->departement);
    }

    public function test_bon_commande_belongs_to_ligne_budget(): void
    {
        $bonCommande = $this->createBonCommande();

        $this->assertInstanceOf(LigneBudget::class, $bonCommande->ligneBudget);
    }

    public function test_bon_commande_belongs_to_user(): void
    {
        $bonCommande = $this->createBonCommande();

        $this->assertInstanceOf(User::class, $bonCommande->user);
    }

    public function test_bon_commande_has_many_factures(): void
    {
        $bonCommande = $this->createBonCommande();

        Facture::create([
            'bon_commande_id' => $bonCommande->id,
            'ref_facture' => 'FAC-001',
            'montant' => 100,
            'type_reglement' => 'acompte',
        ]);

        $this->assertCount(1, $bonCommande->fresh()->factures);
    }

    public function test_statut_defaults_to_brouillon(): void
    {
        $bonCommande = $this->createBonCommande();

        $this->assertEquals('brouillon', $bonCommande->fresh()->statut);
    }

    public function test_numero_bc_must_be_unique(): void
    {
        $this->createBonCommande(['numero_bc' => 'BC-DUP']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->createBonCommande(['numero_bc' => 'BC-DUP']);
    }

    public function test_bon_commande_uses_soft_deletes(): void
    {
        $bonCommande = $this->createBonCommande();
        $bonCommande->delete();

        $this->assertSoftDeleted('bons_commande', ['id' => $bonCommande->id]);
    }

    public function test_scope_visible_par_utilisateur_restricts_to_own_departement(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $bonA = $this->createBonCommande(['departement_id' => $departementA->id]);
        $this->createBonCommande(['departement_id' => $departementB->id]);

        $user = User::factory()->create(['departement_id' => $departementA->id]);

        $visibles = BonCommande::visibleParUtilisateur($user)->get();

        $this->assertCount(1, $visibles);
        $this->assertEquals($bonA->id, $visibles->first()->id);
    }

    public function test_scope_visible_par_utilisateur_shows_all_with_department_wide_access(): void
    {
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();

        $this->createBonCommande(['departement_id' => $departementA->id]);
        $this->createBonCommande(['departement_id' => $departementB->id]);

        $user = User::factory()->create(['departement_id' => $departementA->id]);
        $ids = Permission::whereIn('code', ['departement.view_all', 'user.manage_permissions'])->pluck('id');
        $user->permissions()->attach($ids);

        $visibles = BonCommande::visibleParUtilisateur($user->fresh())->get();

        $this->assertCount(2, $visibles);
    }

    private function createBonCommande(array $attributes = []): BonCommande
    {
        $departement = $attributes['departement_id'] ?? null
            ? Departement::find($attributes['departement_id'])
            : Departement::first();

        $categorie = Categorie::create(['nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 100000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);
        $ligneBudget = LigneBudget::create([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 5000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ]);
        $user = User::factory()->create(['departement_id' => $departement->id]);

        return BonCommande::create(array_merge([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligneBudget->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat test',
            'montant_bc' => 1000,
            'description' => 'Description test',
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
            'type_paiement' => 'virement',
        ], $attributes));
    }
}