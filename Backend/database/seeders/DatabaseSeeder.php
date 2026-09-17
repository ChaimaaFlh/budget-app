<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BonCommande;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\Facture;
use App\Models\FactureDocument;
use App\Models\Fournisseur;
use App\Models\FournisseurScore;
use App\Models\LigneBudget;
use App\Models\LigneBudgetAnnuite;
use App\Models\Permission;
use App\Models\SousCategorie;
use App\Models\StatutPersonnalise;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de démonstration COMPLET, couvrant l'intégralité du périmètre
 * fonctionnel actuel de l'application. Il alimente notamment :
 *
 *  - la table `fournisseurs` (fournisseur_id, en plus du champ texte legacy)
 *    ET leur historique de score annuel (`fournisseur_scores`) ;
 *  - des budgets clos (2025) ET ouverts (2026), fonctionnement ET
 *    investissement, rattachés à leurs départements (`budget_departement`) ;
 *  - deux lignes budgétaires par département, dont deux avec amortissement
 *    sur 2 exercices pour tester les annuités multi-années ;
 *  - des bons de commande couvrant les 6 statuts du workflow
 *    (brouillon, envoyé, réception, validation, paiement, réglée), avec
 *    leur `montant_consomme` recalculé à partir des factures réelles ;
 *  - les 3 modes de gestion de dépassement d'annuité
 *    (bloquer, surplus, report_annuite_suivante) ;
 *  - des factures couvrant les 4 statuts du workflow
 *    (réception, validation, paiement, réglée) ;
 *  - des documents de factures (scans + pièces justificatives) ;
 *  - des statuts personnalisés de démonstration (`statuts_personnalises`)
 *    pour les bons de commande et les factures.
 *
 * Idempotent (rejouable via `php artisan db:seed`).
 * Ne s'exécute qu'en environnement local/testing.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $departements = $this->getDepartements();
        $permissions = $this->getPermissions();
        $users = $this->seedUsers($departements, $permissions);
        $fournisseurs = $this->seedFournisseurs();
        $this->seedFournisseurScores($fournisseurs);
        [$categories, $sousCategories] = $this->seedCategories($departements);
        $budgets = $this->seedBudgets();
        $lignes = $this->seedLignesBudgetaires($departements, $categories, $sousCategories, $budgets);
        $this->seedBudgetDepartements($budgets, $lignes, $departements);
        $this->seedAnnuites($lignes);
        $bonsCommande = $this->seedBonsCommande($users, $lignes, $fournisseurs);
        $factures = $this->seedFactures($bonsCommande);
        $this->seedFactureDocuments($factures);
        $this->recalculerMontantConsomme($bonsCommande);
        $this->seedStatutsPersonnalises();
    }

    /**
     * Départements déjà créés par la migration create_departements_table
     * (jamais recréés ici : les intitulés exacts appartiennent à la migration).
     */
    private function getDepartements(): array
    {
        $cles = ['daf', 'dsi', 'commerciale', 'communication', 'audit', 'qualite'];

        $existants = Departement::orderBy('id')->get();

        if ($existants->count() < count($cles)) {
            throw new \RuntimeException(
                'Départements introuvables : vérifiez que la migration create_departements_table a bien été exécutée.'
            );
        }

        return array_combine($cles, $existants->take(count($cles))->all());
    }

    /** Permissions déjà créées par les migrations create_permissions_table / split_resource_permissions. */
    private function getPermissions(): array
    {
        $permissions = Permission::all()->keyBy('code');

        if ($permissions->isEmpty()) {
            throw new \RuntimeException(
                'Aucune permission trouvée : vérifiez que la migration create_permissions_table a bien été exécutée.'
            );
        }

        return $permissions->all();
    }

    /** Un administrateur transverse + un utilisateur par département. */
    private function seedUsers(array $dep, array $perm): array
    {
        $defaultPassword = Hash::make('Password@2026');
        $adminPassword = Hash::make('AdminDemo2026!');

        $defs = [
            'admin.demo@agma.ma' => [
                'name' => 'Administrateur Démo',
                'departement' => 'daf',
                'password' => $adminPassword,
                'is_active' => true,
                'must_change_password' => false,
                'permissions' => array_keys($perm),
            ],
            'alae.lechheb@agma.ma' => [
                'name' => 'Alae Lechheb',
                'departement' => 'dsi',
                'permissions' => ['ligne.create', 'ligne.edit', 'bc.create', 'bc.validate', 'categorie.manage', 'facture.create'],
            ],
            'salma.idrissi@agma.ma' => [
                'name' => 'Salma Idrissi',
                'departement' => 'daf',
                'permissions' => ['budget.create', 'budget.close', 'facture.create'],
            ],
            'nadia.chraibi@agma.ma' => [
                'name' => 'Nadia Chraibi',
                'departement' => 'commerciale',
                'permissions' => ['bc.create', 'facture.create'],
            ],
            'omar.fassi@agma.ma' => [
                'name' => 'Omar Fassi',
                'departement' => 'communication',
                'permissions' => ['bc.create', 'facture.create'],
            ],
            'karim.zouiten@agma.ma' => [
                'name' => 'Karim Zouiten',
                'departement' => 'audit',
                'permissions' => ['ligne.edit', 'departement.view_all', 'bc.create', 'facture.create'],
            ],
            'imane.benkirane@agma.ma' => [
                'name' => 'Imane Benkirane',
                'departement' => 'qualite',
                'permissions' => ['bc.create', 'ligne.edit', 'facture.create'],
            ],
        ];

        // Les permissions "génériques" historiques sont éclatées en permissions
        // fines par la migration split_resource_permissions : on les ajoute
        // toutes pour rester cohérent avec le comportement réel de l'app.
        $legacyPermissions = [
            'budget.create' => ['budget.edit', 'budget.delete'],
            'ligne.edit' => ['ligne.delete'],
            'bc.create' => ['bc.edit', 'bc.delete', 'bc.unvalidate'],
            'facture.create' => ['facture.edit', 'facture.delete'],
            'categorie.manage' => ['categorie.create', 'categorie.edit', 'categorie.delete', 'souscategorie.create', 'souscategorie.edit', 'souscategorie.delete'],
        ];

        $users = [];
        foreach ($defs as $email => $attrs) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $attrs['name'],
                    'departement_id' => $dep[$attrs['departement']]->id,
                    'password' => $attrs['password'] ?? $defaultPassword,
                    'is_active' => $attrs['is_active'] ?? true,
                    'must_change_password' => $attrs['must_change_password'] ?? true,
                ]
            );

            $permissionCodes = collect($attrs['permissions'])
                ->flatMap(fn (string $code) => array_merge([$code], $legacyPermissions[$code] ?? []))
                ->filter(fn (string $code) => isset($perm[$code]))
                ->unique();
            $permissionIds = $permissionCodes
                ->map(fn (string $code) => $perm[$code]->id)
                ->all();

            $user->permissions()->syncWithoutDetaching($permissionIds);

            $users[$email] = $user;
        }

        return $users;
    }

    /**
     * Table `fournisseurs` : référencée par fournisseur_id sur les bons de
     * commande. Le champ `score` legacy est laissé vide ici : le score
     * "officiel" affiché par l'app est la moyenne de `fournisseur_scores`
     * (voir seedFournisseurScores), qui est alimentée séparément.
     */
    private function seedFournisseurs(): array
    {
        $definitions = [
            'DataSecure Maroc' => ['email' => 'contact@datasecure.ma', 'telephone' => '0522110011'],
            'CyberDefence Africa' => ['email' => 'contact@cyberdefence-africa.com', 'telephone' => '0522110022'],
            'Audit & Advisory Maroc' => ['email' => 'contact@audit-advisory.ma', 'telephone' => '0522110033'],
            'ConformIT Reporting' => ['email' => 'contact@conformit.ma', 'telephone' => '0522110044'],
            'Réseau Courtage Plus' => ['email' => 'contact@courtageplus.ma', 'telephone' => '0522110055'],
            'CRM Courtage Solutions' => ['email' => 'contact@crm-courtage.ma', 'telephone' => '0522110066'],
            'MediaCom Casablanca' => ['email' => 'contact@mediacom-casa.ma', 'telephone' => '0522110077'],
            'Evenza Events' => ['email' => 'contact@evenza.ma', 'telephone' => '0522110088'],
            'Qualité Conseil' => ['email' => 'contact@qualiteconseil.ma', 'telephone' => '0522110099'],
            'QualiDoc Maroc' => ['email' => 'contact@qualidoc.ma', 'telephone' => '0522110100'],
        ];

        $fournisseurs = [];
        foreach ($definitions as $nom => $attrs) {
            $fournisseurs[$nom] = Fournisseur::firstOrCreate(
                ['nom' => $nom],
                [...$attrs, 'adresse' => 'Casablanca, Maroc'],
            );
        }

        return $fournisseurs;
    }

    /**
     * Historique de score sur 2 exercices (2025, 2026) pour chaque
     * fournisseur, alimentant la table `fournisseur_scores` utilisée par
     * FournisseurController pour calculer la moyenne affichée. Le champ
     * `score`/`score_annee` legacy de la table `fournisseurs` est aligné
     * sur le dernier exercice, à titre indicatif.
     */
    private function seedFournisseurScores(array $fournisseurs): void
    {
        $definitions = [
            'DataSecure Maroc' => ['2025' => 35, '2026' => 30],
            'CyberDefence Africa' => ['2024' => 65, '2025' => 74, '2026' => 80],
            'Audit & Advisory Maroc' => ['2025' => 70, '2026' => 66],
            'ConformIT Reporting' => ['2025' => 58, '2026' => 62],
            'Réseau Courtage Plus' => ['2025' => 77, '2026' => 81],
            'CRM Courtage Solutions' => ['2025' => 65, '2026' => 69],
            'MediaCom Casablanca' => ['2025' => 72, '2026' => 75],
            'Evenza Events' => ['2025' => 60, '2026' => 57],
            'Qualité Conseil' => ['2025' => 85, '2026' => 88],
            'QualiDoc Maroc' => ['2025' => 68, '2026' => 71],
        ];

        foreach ($definitions as $nom => $scoresParAnnee) {
            $fournisseur = $fournisseurs[$nom];
            $dernierAnnee = null;
            $dernierScore = null;

            foreach ($scoresParAnnee as $annee => $score) {
                FournisseurScore::updateOrCreate(
                    ['fournisseur_id' => $fournisseur->id, 'annee' => (int) $annee],
                    ['score' => $score],
                );
                $dernierAnnee = (int) $annee;
                $dernierScore = $score;
            }

            $fournisseur->update(['score' => $dernierScore, 'score_annee' => $dernierAnnee]);
        }
    }

    /** Deux catégories par département (mêmes intitulés que DatabaseSeeder). */
    private function seedCategories(array $departements): array
    {
        $definitions = [
            'dsi' => [
                'Infrastructures SI' => ['Serveurs et stockage', 'Réseau et télécommunications'],
                'Cybersécurité' => ['Protection des postes', 'Sécurité des accès'],
            ],
            'daf' => [
                'Finance et conformité' => ['Audit réglementaire', 'Outils de reporting'],
            ],
            'commerciale' => [
                'Développement commercial' => ['Animation du réseau', 'Outils CRM'],
            ],
            'communication' => [
                'Communication et marque' => ['Campagnes médias', 'Événementiel'],
            ],
            'audit' => [
                'Audit interne' => ['Missions d’audit', 'Formation et certification'],
            ],
            'qualite' => [
                'Qualité et conformité' => ['Certification', 'Documentation qualité'],
            ],
        ];

        $categories = [];
        $sousCategories = [];

        foreach ($definitions as $departementKey => $groupes) {
            foreach ($groupes as $nomCategorie => $nomsSousCategories) {
                $categorie = Categorie::firstOrCreate(
                    ['departement_id' => $departements[$departementKey]->id, 'nom' => $nomCategorie],
                    ['description' => "Nomenclature AGMA Assurances — {$nomCategorie}"],
                );
                $categories["{$departementKey}.{$nomCategorie}"] = $categorie;

                foreach ($nomsSousCategories as $nomSousCategorie) {
                    $sousCategories["{$departementKey}.{$nomCategorie}.{$nomSousCategorie}"] = SousCategorie::firstOrCreate(
                        ['categorie_id' => $categorie->id, 'nom' => $nomSousCategorie],
                        ['description' => "Poste {$nomSousCategorie}"],
                    );
                }
            }
        }

        return [$categories, $sousCategories];
    }

    /**
     * Budgets clos (2025) et ouverts (2026), fonctionnement et
     * investissement. BUD-INV-2025 n'a volontairement aucune ligne
     * budgétaire rattachée : il illustre un budget alloué à un département
     * (voir seedBudgetDepartements) mais jamais consommé.
     */
    private function seedBudgets(): array
    {
        $definitions = [
            'BUD-FON-2025' => ['nom' => 'Budget de fonctionnement 2025', 'montant_global' => 1800000, 'annee' => 2025, 'statut' => 'clos', 'type' => 'fonctionnement'],
            'BUD-INV-2025' => ['nom' => 'Budget d’investissement 2025', 'montant_global' => 2200000, 'annee' => 2025, 'statut' => 'clos', 'type' => 'investissement'],
            'BUD-FON-2026' => ['nom' => 'Budget de fonctionnement 2026', 'montant_global' => 2400000, 'annee' => 2026, 'statut' => 'ouvert', 'type' => 'fonctionnement'],
            'BUD-INV-2026' => ['nom' => 'Budget d’investissement 2026', 'montant_global' => 900000, 'annee' => 2026, 'statut' => 'ouvert', 'type' => 'investissement'],
        ];

        $budgets = [];
        foreach ($definitions as $code => $attributes) {
            $budgets[$code] = Budget::updateOrCreate(
                ['code' => $code],
                [...$attributes, 'code' => $code, 'description' => 'Budget de démonstration AGMA Assurances'],
            );
        }

        return $budgets;
    }

    /**
     * Deux lignes par département :
     *  - une ligne "A" 2026 (budget ouvert) qui porte la démonstration du
     *    workflow des bons de commande / factures ;
     *  - une ligne "B" 2025 (budget clos) qui représente un exercice déjà
     *    entièrement soldé.
     * dsi-A et audit-A sont amorties sur 2 exercices pour tester les
     * annuités multi-années et les dépassements.
     */
    private function seedLignesBudgetaires(array $departements, array $categories, array $sousCategories, array $budgets): array
    {
        $definitions = [
            'LB-2026-DSI-101' => ['budget' => 'BUD-INV-2026', 'dep' => 'dsi', 'cat' => 'Infrastructures SI', 'sub' => 'Serveurs et stockage', 'intitule' => 'Extension de la capacité de stockage cloud', 'montant' => 500000, 'date' => '2026-01-01', 'duree' => 2],
            'LB-2025-DSI-102' => ['budget' => 'BUD-FON-2025', 'dep' => 'dsi', 'cat' => 'Cybersécurité', 'sub' => 'Protection des postes', 'intitule' => 'Renouvellement antivirus du parc 2025', 'montant' => 90000, 'date' => '2025-01-01', 'duree' => 1],

            'LB-2026-DAF-101' => ['budget' => 'BUD-FON-2026', 'dep' => 'daf', 'cat' => 'Finance et conformité', 'sub' => 'Audit réglementaire', 'intitule' => 'Audit réglementaire Solvabilité 2026', 'montant' => 260000, 'date' => '2026-01-01', 'duree' => 1],
            'LB-2025-DAF-102' => ['budget' => 'BUD-FON-2025', 'dep' => 'daf', 'cat' => 'Finance et conformité', 'sub' => 'Outils de reporting', 'intitule' => 'Refonte de l’outil de reporting réglementaire', 'montant' => 130000, 'date' => '2025-01-01', 'duree' => 1],

            'LB-2026-COM-101' => ['budget' => 'BUD-FON-2026', 'dep' => 'commerciale', 'cat' => 'Développement commercial', 'sub' => 'Animation du réseau', 'intitule' => 'Programme de fidélisation des courtiers 2026', 'montant' => 210000, 'date' => '2026-01-01', 'duree' => 1],
            'LB-2025-COM-102' => ['budget' => 'BUD-FON-2025', 'dep' => 'commerciale', 'cat' => 'Développement commercial', 'sub' => 'Outils CRM', 'intitule' => 'Déploiement d’un CRM courtage', 'montant' => 175000, 'date' => '2025-01-01', 'duree' => 1],

            'LB-2026-MAR-101' => ['budget' => 'BUD-FON-2026', 'dep' => 'communication', 'cat' => 'Communication et marque', 'sub' => 'Campagnes médias', 'intitule' => 'Campagne multirisque habitation 2026', 'montant' => 320000, 'date' => '2026-02-01', 'duree' => 1],
            'LB-2025-MAR-102' => ['budget' => 'BUD-FON-2025', 'dep' => 'communication', 'cat' => 'Communication et marque', 'sub' => 'Événementiel', 'intitule' => 'Forum assurance Casablanca 2025', 'montant' => 95000, 'date' => '2025-03-01', 'duree' => 1],

            'LB-2026-AUD-101' => ['budget' => 'BUD-FON-2026', 'dep' => 'audit', 'cat' => 'Audit interne', 'sub' => 'Missions d’audit', 'intitule' => 'Mission d’audit interne des succursales 2026', 'montant' => 200000, 'date' => '2026-01-01', 'duree' => 2],
            'LB-2025-AUD-102' => ['budget' => 'BUD-FON-2025', 'dep' => 'audit', 'cat' => 'Audit interne', 'sub' => 'Formation et certification', 'intitule' => 'Certification CIA de l’équipe audit', 'montant' => 60000, 'date' => '2025-01-01', 'duree' => 1],

            'LB-2026-QUA-101' => ['budget' => 'BUD-FON-2026', 'dep' => 'qualite', 'cat' => 'Qualité et conformité', 'sub' => 'Certification', 'intitule' => 'Renouvellement de la certification ISO 9001:2026', 'montant' => 140000, 'date' => '2026-01-01', 'duree' => 1],
            'LB-2025-QUA-102' => ['budget' => 'BUD-FON-2025', 'dep' => 'qualite', 'cat' => 'Qualité et conformité', 'sub' => 'Documentation qualité', 'intitule' => 'Refonte de la documentation qualité 2025', 'montant' => 55000, 'date' => '2025-01-01', 'duree' => 1],
        ];

        $lignes = [];
        foreach ($definitions as $code => $attributes) {
            $categoryKey = "{$attributes['dep']}.{$attributes['cat']}";
            $subCategoryKey = "{$categoryKey}.{$attributes['sub']}";
            $lignes[$code] = LigneBudget::updateOrCreate(
                ['code' => $code],
                [
                    'budget_id' => $budgets[$attributes['budget']]->id,
                    'departement_id' => $departements[$attributes['dep']]->id,
                    'categorie_id' => $categories[$categoryKey]->id,
                    'sous_categorie_id' => $sousCategories[$subCategoryKey]->id,
                    'intitule' => $attributes['intitule'],
                    'montant_alloue' => $attributes['montant'],
                    'date_debut_amortissement' => $attributes['date'],
                    'duree_amortissement_annees' => $attributes['duree'],
                ],
            );
        }

        return $lignes;
    }

    /**
     * Rattache chaque budget à ses départements (table pivot
     * `budget_departement`), utilisée par BudgetController pour déterminer
     * la visibilité d'un budget même avant toute ligne budgétaire créée.
     * Déduit automatiquement les départements à partir des lignes
     * budgétaires existantes, puis ajoute BUD-INV-2025 → dsi à titre de
     * démonstration d'un budget alloué mais non consommé.
     */
    private function seedBudgetDepartements(array $budgets, array $lignes, array $departements): void
    {
        $departementIdsParBudget = [];

        foreach ($lignes as $ligne) {
            $departementIdsParBudget[$ligne->budget_id][$ligne->departement_id] = true;
        }

        $departementIdsParBudget[$budgets['BUD-INV-2025']->id][$departements['dsi']->id] = true;

        foreach ($departementIdsParBudget as $budgetId => $departementIds) {
            $budget = collect($budgets)->firstWhere('id', $budgetId);
            $budget->departements()->sync(array_keys($departementIds));
        }
    }

    private function seedAnnuites(array $lignes): void
    {
        foreach ($lignes as $ligne) {
            $anneeDebut = (int) $ligne->date_debut_amortissement->format('Y');
            $duree = (int) $ligne->duree_amortissement_annees;
            $part = round((float) $ligne->montant_alloue / $duree, 2);
            $cumule = 0.0;

            for ($index = 0; $index < $duree; $index++) {
                $montant = $index === $duree - 1
                    ? round((float) $ligne->montant_alloue - $cumule, 2)
                    : $part;
                $cumule += $montant;

                LigneBudgetAnnuite::updateOrCreate(
                    ['ligne_budget_id' => $ligne->id, 'annee' => $anneeDebut + $index],
                    ['montant' => $montant],
                );
            }
        }
    }

    /**
     * Bons de commande couvrant les 6 statuts du workflow ainsi que les
     * 3 modes de gestion de dépassement d'annuité.
     */
    private function seedBonsCommande(array $users, array $lignes, array $fournisseurs): array
    {
        $definitions = [
            // -- Lignes "A" 2026 : un statut différent du workflow sur chacune --
            'BC-2026-1001' => ['ligne' => 'LB-2026-DSI-101', 'user' => 'alae.lechheb@agma.ma', 'montant' => 150000, 'date' => '2026-01-15', 'fournisseur' => 'DataSecure Maroc', 'objet' => 'Extension du stockage cloud — phase 1', 'statut' => 'brouillon', 'depassement' => 'bloquer'],
            'BC-2026-1002' => ['ligne' => 'LB-2026-DAF-101', 'user' => 'salma.idrissi@agma.ma', 'montant' => 260000, 'date' => '2026-01-20', 'fournisseur' => 'Audit & Advisory Maroc', 'objet' => 'Audit Solvabilité 2026', 'statut' => 'envoye', 'depassement' => 'bloquer'],
            'BC-2026-1003' => ['ligne' => 'LB-2026-COM-101', 'user' => 'nadia.chraibi@agma.ma', 'montant' => 210000, 'date' => '2026-02-03', 'fournisseur' => 'Réseau Courtage Plus', 'objet' => 'Programme de fidélisation courtiers 2026', 'statut' => 'reception', 'depassement' => 'bloquer'],
            'BC-2026-1004' => ['ligne' => 'LB-2026-MAR-101', 'user' => 'omar.fassi@agma.ma', 'montant' => 320000, 'date' => '2026-02-10', 'fournisseur' => 'MediaCom Casablanca', 'objet' => 'Campagne média habitation 2026', 'statut' => 'validation', 'depassement' => 'bloquer'],
            'BC-2026-1005' => ['ligne' => 'LB-2026-AUD-101', 'user' => 'karim.zouiten@agma.ma', 'montant' => 90000, 'date' => '2026-01-12', 'fournisseur' => 'Audit & Advisory Maroc', 'objet' => 'Mission d’audit interne 2026 — tranche 1', 'statut' => 'paiement', 'depassement' => 'bloquer'],
            'BC-2026-1006' => ['ligne' => 'LB-2026-QUA-101', 'user' => 'imane.benkirane@agma.ma', 'montant' => 140000, 'date' => '2026-01-08', 'fournisseur' => 'Qualité Conseil', 'objet' => 'Renouvellement ISO 9001:2026', 'statut' => 'reglee', 'depassement' => 'bloquer'],

            // -- Dépassements d'annuité --
            // DSI : annuité 2026 = 250 000 (500 000 / 2 ans). 150 000 + 150 000 = 300 000 > 250 000.
            'BC-2026-1007' => ['ligne' => 'LB-2026-DSI-101', 'user' => 'alae.lechheb@agma.ma', 'montant' => 150000, 'date' => '2026-03-05', 'fournisseur' => 'CyberDefence Africa', 'objet' => 'Extension du stockage cloud — phase 2 (surplus assumé)', 'statut' => 'envoye', 'depassement' => 'surplus'],
            // Audit : annuité 2026 = 100 000 (200 000 / 2 ans). 90 000 + 40 000 = 130 000 > 100 000,
            // le dépassement de 30 000 est reporté sur l'annuité 2027 (voir plus bas).
            'BC-2026-1008' => ['ligne' => 'LB-2026-AUD-101', 'user' => 'karim.zouiten@agma.ma', 'montant' => 40000, 'date' => '2026-04-02', 'fournisseur' => 'Audit & Advisory Maroc', 'objet' => 'Mission d’audit interne 2026 — extension de périmètre', 'statut' => 'reception', 'depassement' => 'report_annuite_suivante'],

            // -- Lignes "B" 2025 : exercices déjà entièrement soldés --
            'BC-2025-9001' => ['ligne' => 'LB-2025-DSI-102', 'user' => 'alae.lechheb@agma.ma', 'montant' => 90000, 'date' => '2025-01-18', 'fournisseur' => 'CyberDefence Africa', 'objet' => 'Renouvellement antivirus 2025', 'statut' => 'reglee', 'depassement' => 'bloquer'],
            'BC-2025-9002' => ['ligne' => 'LB-2025-DAF-102', 'user' => 'salma.idrissi@agma.ma', 'montant' => 130000, 'date' => '2025-02-05', 'fournisseur' => 'ConformIT Reporting', 'objet' => 'Refonte outil de reporting 2025', 'statut' => 'reglee', 'depassement' => 'bloquer'],
            'BC-2025-9003' => ['ligne' => 'LB-2025-COM-102', 'user' => 'nadia.chraibi@agma.ma', 'montant' => 175000, 'date' => '2025-01-25', 'fournisseur' => 'CRM Courtage Solutions', 'objet' => 'Déploiement CRM courtage 2025', 'statut' => 'reglee', 'depassement' => 'bloquer'],
            'BC-2025-9004' => ['ligne' => 'LB-2025-MAR-102', 'user' => 'omar.fassi@agma.ma', 'montant' => 95000, 'date' => '2025-03-10', 'fournisseur' => 'Evenza Events', 'objet' => 'Forum assurance Casablanca 2025', 'statut' => 'reglee', 'depassement' => 'bloquer'],
            'BC-2025-9005' => ['ligne' => 'LB-2025-AUD-102', 'user' => 'karim.zouiten@agma.ma', 'montant' => 60000, 'date' => '2025-01-15', 'fournisseur' => 'Audit & Advisory Maroc', 'objet' => 'Certification CIA équipe audit 2025', 'statut' => 'reglee', 'depassement' => 'bloquer'],
            'BC-2025-9006' => ['ligne' => 'LB-2025-QUA-102', 'user' => 'imane.benkirane@agma.ma', 'montant' => 55000, 'date' => '2025-01-20', 'fournisseur' => 'QualiDoc Maroc', 'objet' => 'Refonte documentation qualité 2025', 'statut' => 'reglee', 'depassement' => 'bloquer'],
        ];

        $bons = [];
        foreach ($definitions as $numero => $attributes) {
            $ligne = $lignes[$attributes['ligne']];
            $fournisseur = $fournisseurs[$attributes['fournisseur']];

            $bons[$numero] = BonCommande::updateOrCreate(
                ['numero_bc' => $numero],
                [
                    'user_id' => $users[$attributes['user']]->id,
                    'ligne_budget_id' => $ligne->id,
                    'departement_id' => $ligne->departement_id,
                    'intitule_bc' => $attributes['objet'],
                    'montant_bc' => $attributes['montant'],
                    'description' => 'Bon de commande de démonstration AGMA Assurances.',
                    'date_achat' => $attributes['date'],
                    'annuite' => (int) substr($attributes['date'], 0, 4)
                        + ($attributes['depassement'] === 'report_annuite_suivante' ? 1 : 0),
                    'fournisseur' => $fournisseur->nom,
                    'fournisseur_id' => $fournisseur->id,
                    'type_paiement' => 'Virement bancaire',
                    'statut' => $attributes['statut'],
                    'gestion_depassement' => $attributes['depassement'],
                ],
            );
        }

        return $bons;
    }

    /** Factures couvrant les 4 statuts du workflow (réception → réglée). */
    private function seedFactures(array $bonsCommande): array
    {
        $definitions = [
            // Lignes "A" 2026, à partir du statut BC "reception"
            'FAC-2026-2001' => ['bc' => 'BC-2026-1003', 'montant' => 100000, 'type' => 'finale', 'statut' => 'reception', 'date' => '2026-02-10'],
            'FAC-2026-2002' => ['bc' => 'BC-2026-1004', 'montant' => 270000, 'type' => 'finale', 'statut' => 'validation', 'date' => '2026-02-15'],
            'FAC-2026-2003' => ['bc' => 'BC-2026-1005', 'montant' => 90000, 'type' => 'finale', 'statut' => 'paiement', 'date' => '2026-01-20', 'echeance' => '2026-02-19', 'paiement' => null],
            'FAC-2026-2004' => ['bc' => 'BC-2026-1006', 'montant' => 84000, 'type' => 'acompte', 'statut' => 'reglee', 'date' => '2026-01-12', 'echeance' => '2026-02-11', 'paiement' => '2026-02-05'],
            'FAC-2026-2005' => ['bc' => 'BC-2026-1006', 'montant' => 56000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2026-01-30', 'echeance' => '2026-03-01', 'paiement' => '2026-02-20'],
            'FAC-2026-2006' => ['bc' => 'BC-2026-1008', 'montant' => 40000, 'type' => 'finale', 'statut' => 'reception', 'date' => '2026-04-04'],

            // Lignes "B" 2025, entièrement soldées
            'FAC-2025-9001' => ['bc' => 'BC-2025-9001', 'montant' => 90000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2025-01-25', 'echeance' => '2025-02-24', 'paiement' => '2025-02-18'],
            'FAC-2025-9002' => ['bc' => 'BC-2025-9002', 'montant' => 130000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2025-02-12', 'echeance' => '2025-03-14', 'paiement' => '2025-03-05'],
            'FAC-2025-9003' => ['bc' => 'BC-2025-9003', 'montant' => 115000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2025-02-01', 'echeance' => '2025-03-03', 'paiement' => '2025-02-25'],
            'FAC-2025-9004' => ['bc' => 'BC-2025-9004', 'montant' => 80000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2025-03-15', 'echeance' => '2025-04-14', 'paiement' => '2025-04-02'],
            'FAC-2025-9005' => ['bc' => 'BC-2025-9005', 'montant' => 30000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2025-01-22', 'echeance' => '2025-02-21', 'paiement' => '2025-02-14'],
            'FAC-2025-9006' => ['bc' => 'BC-2025-9006', 'montant' => 55000, 'type' => 'finale', 'statut' => 'reglee', 'date' => '2025-01-27', 'echeance' => '2025-02-26', 'paiement' => '2025-02-19'],
        ];

        $factures = [];
        foreach ($definitions as $reference => $attributes) {
            $factures[$reference] = Facture::updateOrCreate(
                ['ref_facture' => $reference],
                [
                    'bon_commande_id' => $bonsCommande[$attributes['bc']]->id,
                    'montant' => $attributes['montant'],
                    'type_reglement' => $attributes['type'],
                    'statut' => $attributes['statut'],
                    'date_reception' => $attributes['date'],
                    'date_echeance' => $attributes['echeance'] ?? null,
                    'date_paiement' => $attributes['paiement'] ?? null,
                ],
            );
        }

        return $factures;
    }

    /** Documents attachés aux factures déjà passées en paiement ou réglées. */
    private function seedFactureDocuments(array $factures): void
    {
        $definitions = [
            ['facture' => 'FAC-2026-2003', 'type' => 'scan_facture', 'nom' => 'FAC-2026-2003-scan.pdf', 'taille' => 168_200],
            ['facture' => 'FAC-2026-2004', 'type' => 'scan_facture', 'nom' => 'FAC-2026-2004-scan.pdf', 'taille' => 142_900],
            ['facture' => 'FAC-2026-2005', 'type' => 'scan_facture', 'nom' => 'FAC-2026-2005-scan.pdf', 'taille' => 151_400],
            ['facture' => 'FAC-2026-2005', 'type' => 'piece_justificative', 'nom' => 'FAC-2026-2005-pv-reception.pdf', 'taille' => 88_300],
            ['facture' => 'FAC-2025-9001', 'type' => 'scan_facture', 'nom' => 'FAC-2025-9001-scan.pdf', 'taille' => 133_700],
            ['facture' => 'FAC-2025-9002', 'type' => 'scan_facture', 'nom' => 'FAC-2025-9002-scan.pdf', 'taille' => 149_500],
            ['facture' => 'FAC-2025-9003', 'type' => 'scan_facture', 'nom' => 'FAC-2025-9003-scan.pdf', 'taille' => 162_100],
            ['facture' => 'FAC-2025-9004', 'type' => 'scan_facture', 'nom' => 'FAC-2025-9004-scan.pdf', 'taille' => 121_800],
            ['facture' => 'FAC-2025-9005', 'type' => 'scan_facture', 'nom' => 'FAC-2025-9005-scan.pdf', 'taille' => 118_600],
            ['facture' => 'FAC-2025-9006', 'type' => 'scan_facture', 'nom' => 'FAC-2025-9006-scan.pdf', 'taille' => 109_200],
        ];

        foreach ($definitions as $attributes) {
            $facture = $factures[$attributes['facture']];
            $chemin = "factures/{$facture->id}/{$attributes['nom']}";

            FactureDocument::updateOrCreate(
                ['facture_id' => $facture->id, 'chemin' => $chemin],
                [
                    'type' => $attributes['type'],
                    'nom_original' => $attributes['nom'],
                    'mime_type' => 'application/pdf',
                    'taille_octets' => $attributes['taille'],
                ],
            );
        }
    }

    /**
     * Recalcule `montant_consomme` sur chaque bon de commande à partir de
     * la somme réelle de ses factures (même logique que la migration
     * add_flexible_statuses_consumption_and_supplier_score_history), afin
     * que la consommation affichée par l'app reste exacte après le seed.
     */
    private function recalculerMontantConsomme(array $bonsCommande): void
    {
        foreach ($bonsCommande as $bon) {
            $montant = $bon->factures()->sum('montant');
            $bon->update(['montant_consomme' => $montant]);
        }
    }

    /**
     * Statuts personnalisés de démonstration, illustrant la fonctionnalité
     * de personnalisation des statuts (indépendante des statuts figés du
     * workflow standard) pour les bons de commande et les factures.
     */
    private function seedStatutsPersonnalises(): void
    {
        $definitions = [
            ['type' => 'bon_commande', 'libelle' => 'En attente fournisseur', 'couleur' => '#F59E0B'],
            ['type' => 'bon_commande', 'libelle' => 'Litige', 'couleur' => '#EF4444'],
            ['type' => 'facture', 'libelle' => 'Contestée', 'couleur' => '#EF4444'],
            ['type' => 'facture', 'libelle' => 'En relance', 'couleur' => '#F59E0B'],
        ];

        foreach ($definitions as $attributes) {
            StatutPersonnalise::firstOrCreate(
                ['type' => $attributes['type'], 'libelle' => $attributes['libelle']],
                ['couleur' => $attributes['couleur']],
            );
        }
    }
}