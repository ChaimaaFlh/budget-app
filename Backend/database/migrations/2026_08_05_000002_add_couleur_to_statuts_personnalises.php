<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('statuts_personnalises', function (Blueprint $table) {
            $table->string('couleur', 7)->default('#64748B')->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('statuts_personnalises', fn (Blueprint $table) => $table->dropColumn('couleur'));
    }
};
