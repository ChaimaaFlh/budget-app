<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
        });

        \DB::table('permissions')->insert([
            ['code' => 'budget.create', 'libelle' => 'Créer un budget', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'budget.close', 'libelle' => 'Clôturer un budget', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ligne.create', 'libelle' => 'Créer une ligne budgétaire', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ligne.edit', 'libelle' => 'Modifier une ligne budgétaire existante', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'bc.create', 'libelle' => 'Créer un bon de commande', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'bc.validate', 'libelle' => 'Valider un bon de commande', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'facture.create', 'libelle' => 'Enregistrer une facture', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'departement.view_all', 'libelle' => 'Consulter tous les départements', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'user.manage_permissions', 'libelle' => 'Gérer les permissions des utilisateurs', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'categorie.manage', 'libelle' => 'Créer/gérer les catégories et sous-catégories', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permissions');
    }
};
