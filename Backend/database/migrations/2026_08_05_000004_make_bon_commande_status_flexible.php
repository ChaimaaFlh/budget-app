<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bons_commande DROP CONSTRAINT IF EXISTS bons_commande_statut_check');
        }

        Schema::table('bons_commande', function (Blueprint $table) {
            $table->string('statut', 100)->default('brouillon')->change();
        });
    }

    public function down(): void
    {
        // Un retour vers un enum supprimerait les statuts personnalisés existants.
    }
};
