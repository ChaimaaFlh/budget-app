<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (! Schema::hasColumn('bons_commande', 'mode_repartition')) {
                $table->string('mode_repartition')->nullable()->after('repartition_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (Schema::hasColumn('bons_commande', 'mode_repartition')) {
                $table->dropColumn('mode_repartition');
            }
        });
    }
};