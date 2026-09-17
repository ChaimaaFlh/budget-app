<?php

namespace App\Console\Commands;

use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Facture;
use App\Models\FactureDocument;
use App\Models\Fournisseur;
use App\Models\FournisseurScore;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\SousCategorie;
use App\Models\StatutPersonnalise;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supprime UNIQUEMENT les données créées par DatabaseSeeder (identifiées
 * par les mêmes clés métier : e-mails, codes, numéros, noms), en respectant
 * l'ordre inverse des dépendances. Les départements et permissions (créés
 * par les migrations, pas par le seeder) ne sont jamais touchés, et toute
 * donnée créée manuellement depuis l'application est préservée.
 */
class UnseedDemoData extends Command
{
    protected $signature = 'demo:unseed {--force : Exécute sans demander de confirmation}';

    protected $description = 'Supprime les données de démonstration injectées par DatabaseSeeder, sans toucher au reste';

    private array $emailsUtilisateursDemo = [
        'admin.demo@agma.ma',
        'alae.lechheb@agma.ma',
        'salma.idrissi@agma.ma',
        'nadia.chraibi@agma.ma',
        'omar.fassi@agma.ma',
        'karim.zouiten@agma.ma',
        'imane.benkirane@agma.ma',
    ];

    private array $nomsFournisseursDemo = [
        'DataSecure Maroc',
        'CyberDefence Africa',
        'Audit & Advisory Maroc',
        'ConformIT Reporting',
        'Réseau Courtage Plus',
        'CRM Courtage Solutions',
        'MediaCom Casablanca',
        'Evenza Events',
        'Qualité Conseil',
        'QualiDoc Maroc',
    ];

    private array $codesBudgetsDemo = [
        'BUD-FON-2025', 'BUD-INV-2025', 'BUD-FON-2026', 'BUD-INV-2026',
    ];

    private array $codesLignesDemo = [
        'LB-2026-DSI-101', 'LB-2025-DSI-102',
        'LB-2026-DAF-101', 'LB-2025-DAF-102',
        'LB-2026-COM-101', 'LB-2025-COM-102',
        'LB-2026-MAR-101', 'LB-2025-MAR-102',
        'LB-2026-AUD-101', 'LB-2025-AUD-102',
        'LB-2026-QUA-101', 'LB-2025-QUA-102',
    ];

    private array $numerosBcDemo = [
        'BC-2026-1001', 'BC-2026-1002', 'BC-2026-1003', 'BC-2026-1004',
        'BC-2026-1005', 'BC-2026-1006', 'BC-2026-1007', 'BC-2026-1008',
        'BC-2025-9001', 'BC-2025-9002', 'BC-2025-9003', 'BC-2025-9004',
        'BC-2025-9005', 'BC-2025-9006',
    ];

    private array $refsFacturesDemo = [
        'FAC-2026-2001', 'FAC-2026-2002', 'FAC-2026-2003', 'FAC-2026-2004',
        'FAC-2026-2005', 'FAC-2026-2006',
        'FAC-2025-9001', 'FAC-2025-9002', 'FAC-2025-9003', 'FAC-2025-9004',
        'FAC-2025-9005', 'FAC-2025-9006',
    ];

    /** Intitulés exacts insérés par la migration create_departements_table, dans l'ordre des clés du seeder. */
    private array $departementsParCle = [
        'daf' => "Administration et Finances (DAF)",
        'dsi' => "Systèmes d'Information (DSI)",
        'commerciale' => 'Commercial et Ventes',
        'communication' => 'Communication et Marketing',
        'audit' => 'Audit Interne et Contrôle',
        'qualite' => 'Qualité et Conformité',
    ];

    /** [departement_key, nom_categorie] tel que défini dans DatabaseSeeder::seedCategories(). */
    private array $categoriesDemo = [
        ['dsi', 'Infrastructures SI'],
        ['dsi', 'Cybersécurité'],
        ['daf', 'Finance et conformité'],
        ['commerciale', 'Développement commercial'],
        ['communication', 'Communication et marque'],
        ['audit', 'Audit interne'],
        ['qualite', 'Qualité et conformité'],
    ];

    private array $statutsPersonnalisesDemo = [
        ['type' => 'bon_commande', 'libelle' => 'En attente fournisseur'],
        ['type' => 'bon_commande', 'libelle' => 'Litige'],
        ['type' => 'facture', 'libelle' => 'Contestée'],
        ['type' => 'facture', 'libelle' => 'En relance'],
    ];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Cette commande est réservée aux environnements local/testing. Utilisez --force pour outrepasser.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Supprimer les données de démonstration (utilisateurs, fournisseurs, budgets, lignes, bons de commande, factures... créés par le seeder) ? Vos données créées manuellement seront conservées.')) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            $this->supprimerFactures();
            $this->supprimerBonsCommande();
            $this->supprimerLignesBudgetaires();
            $this->supprimerBudgets();
            $this->supprimerCategoriesEtSousCategories();
            $this->supprimerFournisseurs();
            $this->supprimerUtilisateurs();
            $this->supprimerStatutsPersonnalises();
        });

        $this->info('Données de démonstration supprimées. Le reste de la base est inchangé.');

        return self::SUCCESS;
    }

    private function supprimerFactures(): void
    {
        $factures = Facture::withTrashed()->whereIn('ref_facture', $this->refsFacturesDemo)->get();
        FactureDocument::whereIn('facture_id', $factures->pluck('id'))->delete();
        Facture::withTrashed()->whereIn('id', $factures->pluck('id'))->forceDelete();
        $this->line('Factures de démonstration supprimées : ' . $factures->count());
    }

    private function supprimerBonsCommande(): void
    {
        $count = BonCommande::withTrashed()->whereIn('numero_bc', $this->numerosBcDemo)->forceDelete();
        $this->line("Bons de commande de démonstration supprimés : {$count}");
    }

    private function supprimerLignesBudgetaires(): void
    {
        $lignes = LigneBudget::withTrashed()->whereIn('code', $this->codesLignesDemo)->get();
        LigneBudgetAnnuite::whereIn('ligne_budget_id', $lignes->pluck('id'))->delete();
        LigneBudget::withTrashed()->whereIn('id', $lignes->pluck('id'))->forceDelete();
        $this->line('Lignes budgétaires de démonstration supprimées : ' . $lignes->count());
    }

    private function supprimerBudgets(): void
    {
        $budgets = Budget::withTrashed()->whereIn('code', $this->codesBudgetsDemo)->get();
        foreach ($budgets as $budget) {
            $budget->departements()->detach();
        }
        Budget::withTrashed()->whereIn('id', $budgets->pluck('id'))->forceDelete();
        $this->line('Budgets de démonstration supprimés : ' . $budgets->count());
    }

    private function supprimerCategoriesEtSousCategories(): void
    {
        $total = 0;
        foreach ($this->categoriesDemo as [$departementKey, $nomCategorie]) {
            $nomDepartement = $this->departementsParCle[$departementKey];
            $departementId = \App\Models\Departement::where('nom', $nomDepartement)->value('id');

            if (! $departementId) {
                continue;
            }

            $categorie = Categorie::withTrashed()
                ->where('departement_id', $departementId)
                ->where('nom', $nomCategorie)
                ->first();

            if (! $categorie) {
                continue;
            }

            SousCategorie::withTrashed()->where('categorie_id', $categorie->id)->forceDelete();
            $categorie->forceDelete();
            $total++;
        }
        $this->line("Catégories de démonstration supprimées : {$total}");
    }

    private function supprimerFournisseurs(): void
    {
        $fournisseurs = Fournisseur::whereIn('nom', $this->nomsFournisseursDemo)->get();
        FournisseurScore::whereIn('fournisseur_id', $fournisseurs->pluck('id'))->delete();
        Fournisseur::whereIn('id', $fournisseurs->pluck('id'))->delete();
        $this->line('Fournisseurs de démonstration supprimés : ' . $fournisseurs->count());
    }

    private function supprimerUtilisateurs(): void
    {
        $utilisateurs = User::whereIn('email', $this->emailsUtilisateursDemo)->get();
        foreach ($utilisateurs as $utilisateur) {
            $utilisateur->permissions()->detach();
            $utilisateur->tokens()->delete();
        }
        User::whereIn('id', $utilisateurs->pluck('id'))->delete();
        $this->line('Utilisateurs de démonstration supprimés : ' . $utilisateurs->count());
    }

    private function supprimerStatutsPersonnalises(): void
    {
        $count = 0;
        foreach ($this->statutsPersonnalisesDemo as $definition) {
            $count += StatutPersonnalise::where('type', $definition['type'])
                ->where('libelle', $definition['libelle'])
                ->delete();
        }
        $this->line("Statuts personnalisés de démonstration supprimés : {$count}");
    }
}