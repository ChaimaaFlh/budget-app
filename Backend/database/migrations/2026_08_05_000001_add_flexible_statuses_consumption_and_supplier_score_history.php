<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('statuts_personnalises', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // bon_commande ou facture
            $table->string('libelle');
            $table->timestamps();
            $table->unique(['type', 'libelle']);
        });

        Schema::table('bons_commande', function (Blueprint $table) {
            $table->decimal('montant_consomme', 15, 2)->default(0)->after('montant_bc');
        });
        DB::table('bons_commande')->orderBy('id')->each(function ($bon) {
            $montant = DB::table('factures')
                ->where('bon_commande_id', $bon->id)
                ->whereNull('deleted_at')
                ->sum('montant');
            DB::table('bons_commande')->where('id', $bon->id)->update(['montant_consomme' => $montant]);
        });

        Schema::create('fournisseur_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fournisseur_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('annee');
            $table->decimal('score', 5, 2);
            $table->timestamps();
            $table->unique(['fournisseur_id', 'annee']);
        });
        DB::table('fournisseurs')->whereNotNull('score')->orderBy('id')->each(function ($fournisseur) {
            DB::table('fournisseur_scores')->insert([
                'fournisseur_id' => $fournisseur->id,
                'annee' => $fournisseur->score_annee ?: now()->year,
                'score' => $fournisseur->score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseur_scores');
        Schema::table('bons_commande', fn (Blueprint $table) => $table->dropColumn('montant_consomme'));
        Schema::dropIfExists('statuts_personnalises');
    }
};
