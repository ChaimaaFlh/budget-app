<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // PostgreSQL represents Laravel's former enum as a CHECK constraint.
        // Remove it before introducing the workflow states below.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bons_commande DROP CONSTRAINT IF EXISTS bons_commande_statut_check');
            DB::statement("UPDATE bons_commande SET statut = 'envoye' WHERE statut = 'validé'");
        }
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->timestamps();
        });
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->foreignId('fournisseur_id')->nullable()->after('departement_id')->constrained('fournisseurs')->nullOnDelete();
            $table->integer('annuite')->nullable()->after('date_achat');
            $table->string('gestion_depassement')->default('bloquer')->after('statut');
        });
        Schema::table('factures', function (Blueprint $table) {
            $table->string('statut')->default('reception')->after('type_reglement');
            $table->date('date_paiement')->nullable()->after('date_validation_si');
            $table->date('date_echeance')->nullable()->after('date_paiement');
        });
        Schema::create('permission_packs', function (Blueprint $table) {
            $table->id(); $table->string('nom')->unique(); $table->string('description')->nullable(); $table->timestamps();
        });
        Schema::create('permission_pack_permission', function (Blueprint $table) {
            $table->foreignId('permission_pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_pack_id', 'permission_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('permission_pack_permission'); Schema::dropIfExists('permission_packs'); Schema::table('factures', fn(Blueprint $t) => $t->dropColumn(['statut','date_paiement','date_echeance'])); Schema::table('bons_commande', function(Blueprint $t) { $t->dropConstrainedForeignId('fournisseur_id'); $t->dropColumn(['annuite','gestion_depassement']); }); Schema::dropIfExists('fournisseurs'); }
};
