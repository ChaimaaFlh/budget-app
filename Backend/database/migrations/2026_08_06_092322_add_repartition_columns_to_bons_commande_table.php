<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->string('repartition_groupe')->nullable()->after('gestion_depassement');
            $table->unsignedInteger('repartition_ordre')->nullable()->after('repartition_groupe');
            $table->unsignedInteger('repartition_total')->nullable()->after('repartition_ordre');
            $table->index('repartition_groupe');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropColumn(['repartition_groupe', 'repartition_ordre', 'repartition_total']);
        });
    }
};