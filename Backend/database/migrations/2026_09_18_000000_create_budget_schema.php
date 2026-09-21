<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create departements
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        // Insert departements data
        DB::table('departements')->insert([
            ['nom' => 'Administration et Finances (DAF)', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Systèmes d\'Information (DSI)', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Commercial et Ventes', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Communication et Marketing', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Audit Interne et Contrôle', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Qualité et Conformité', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
        ]);

        // Create users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departement_id')->nullable()->references('id')->on('departements')->onDelete('set null');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });

        // Create password_reset_tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Create sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->ipAddress();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Create cache tables
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
            $table->index('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });

        // Create jobs tables
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->text('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->index(['connection', 'queue', 'failed_at']);
        });

        // Create categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departement_id')->nullable()->references('id')->on('departements')->onDelete('set null');
            $table->string('nom');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Create budgets
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->unique();
            $table->decimal('montant_global', 15, 2);
            $table->text('description')->nullable();
            $table->integer('annee');
            $table->string('statut')->default('ouvert');
            $table->string('type');
            $table->softDeletes();
            $table->timestamps();
        });

        // Create sous_categories
        Schema::create('sous_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_id')->constrained('categories')->onDelete('cascade');
            $table->string('nom');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Create ligne_budgets
        Schema::create('ligne_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sous_categorie_id')->nullable()->references('id')->on('sous_categories')->onDelete('set null');
            $table->foreignId('categorie_id')->nullable()->references('id')->on('categories')->onDelete('set null');
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->foreignId('departement_id')->constrained('departements')->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('intitule');
            $table->decimal('montant_alloue', 15, 2)->default(0);
            $table->date('date_debut_amortissement');
            $table->smallInteger('duree_amortissement_annees')->default(5);
            $table->softDeletes();
            $table->timestamps();
        });

        // Create budget_departement
        Schema::create('budget_departement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->foreignId('departement_id')->constrained('departements')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['budget_id', 'departement_id']);
        });

        // Create ligne_budget_annuites
        Schema::create('ligne_budget_annuites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ligne_budget_id')->constrained('ligne_budgets')->onDelete('cascade');
            $table->integer('annee');
            $table->decimal('montant', 15, 2);
            $table->timestamps();
            $table->unique(['ligne_budget_id', 'annee']);
        });

        // Create fournisseurs
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->integer('score_annee')->nullable();
            $table->timestamps();
        });

        // Create bons_commande
        Schema::create('bons_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ligne_budget_id')->constrained('ligne_budgets')->onDelete('cascade');
            $table->foreignId('departement_id')->constrained('departements')->onDelete('restrict');
            $table->foreignId('fournisseur_id')->nullable()->references('id')->on('fournisseurs')->onDelete('set null');
            $table->string('numero_bc')->unique();
            $table->string('intitule_bc');
            $table->decimal('montant_bc', 15, 2);
            $table->decimal('montant_consomme', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->date('date_achat');
            $table->integer('annuite')->nullable();
            $table->string('fournisseur');
            $table->string('type_paiement')->nullable();
            $table->string('statut')->default('brouillon');
            $table->string('gestion_depassement')->default('bloquer');
            $table->string('repartition_groupe')->nullable()->index();
            $table->integer('repartition_ordre')->nullable();
            $table->integer('repartition_total')->nullable();
            $table->string('mode_repartition')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Create bon_commande_tranches
        Schema::create('bon_commande_tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_commande_id')->constrained('bons_commande')->onDelete('cascade');
            $table->integer('annuite');
            $table->decimal('montant', 14, 2);
            $table->integer('ordre')->default(1);
            $table->timestamps();
            $table->index(['bon_commande_id', 'annuite']);
        });

        // Create factures
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_commande_id')->constrained('bons_commande')->onDelete('cascade');
            $table->string('ref_facture')->unique();
            $table->decimal('montant', 15, 2);
            $table->date('date_reception')->nullable();
            $table->string('type_reglement');
            $table->string('piece_jointe')->nullable();
            $table->string('statut')->default('reception');
            $table->date('date_paiement')->nullable();
            $table->date('date_echeance')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Create facture_documents
        Schema::create('facture_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->onDelete('cascade');
            $table->string('type')->default('piece_justificative');
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime_type');
            $table->unsignedBigInteger('taille_octets');
            $table->timestamps();
        });

        // Create permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        // Insert permissions data
        DB::table('permissions')->insert([
            ['code' => 'budget.create', 'libelle' => 'Créer un budget', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'budget.close', 'libelle' => 'Clôturer un budget', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'ligne.create', 'libelle' => 'Créer une ligne budgétaire', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'ligne.edit', 'libelle' => 'Modifier une ligne budgétaire existante', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'bc.create', 'libelle' => 'Créer un bon de commande', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'bc.validate', 'libelle' => 'Valider un bon de commande', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'facture.create', 'libelle' => 'Enregistrer une facture', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'departement.view_all', 'libelle' => 'Consulter tous les départements', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'user.manage_permissions', 'libelle' => 'Gérer les permissions des utilisateurs', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'categorie.manage', 'libelle' => 'Créer/gérer les catégories et sous-catégories', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'fournisseur.create', 'libelle' => 'Créer un fournisseur', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'fournisseur.edit', 'libelle' => 'Modifier un fournisseur', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['code' => 'fournisseur.delete', 'libelle' => 'Supprimer un fournisseur', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
        ]);

        // Create user_permissions
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        // Create permission_packs
        Schema::create('permission_packs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert permission_packs data
        DB::table('permission_packs')->insert([
            ['nom' => 'Chef de projet', 'description' => 'Pack Chef de projet', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Directeur', 'description' => 'Pack Directeur', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Lecteur', 'description' => 'Pack Lecteur', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Employé', 'description' => 'Rôle Employé', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
            ['nom' => 'Responsable', 'description' => 'Rôle Responsable', 'created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')],
        ]);

        // Create permission_pack_permission
        Schema::create('permission_pack_permission', function (Blueprint $table) {
            $table->foreignId('permission_pack_id')->constrained('permission_packs')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->primary(['permission_pack_id', 'permission_id']);
        });

        // Create statuts_personnalises
        Schema::create('statuts_personnalises', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('libelle');
            $table->string('couleur')->default('#64748B');
            $table->timestamps();
            $table->unique(['type', 'libelle']);
        });

        // Create fournisseur_scores
        Schema::create('fournisseur_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->integer('annee');
            $table->decimal('score', 5, 2);
            $table->timestamps();
            $table->unique(['fournisseur_id', 'annee']);
        });

        // Create personal_access_tokens
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 80)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('fournisseur_scores');
        Schema::dropIfExists('statuts_personnalises');
        Schema::dropIfExists('permission_pack_permission');
        Schema::dropIfExists('permission_packs');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('facture_documents');
        Schema::dropIfExists('factures');
        Schema::dropIfExists('bon_commande_tranches');
        Schema::dropIfExists('bons_commande');
        Schema::dropIfExists('fournisseurs');
        Schema::dropIfExists('ligne_budget_annuites');
        Schema::dropIfExists('budget_departement');
        Schema::dropIfExists('ligne_budgets');
        Schema::dropIfExists('sous_categories');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('departements');
    }
};

