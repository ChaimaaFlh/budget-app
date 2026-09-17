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
        Schema::create('departements', function (Blueprint $table) {
            $table->id();

            $table->string('nom')->unique();

            $table->timestamps();
        });

        DB::table('departements')->insert([
            
                ['nom' => 'Administration et Finances (DAF)', 'created_at' => now(), 'updated_at' => now()],
                ['nom' => 'Systèmes d\'Information (DSI)', 'created_at' => now(), 'updated_at' => now()],
                ['nom' => 'Commercial et Ventes', 'created_at' => now(), 'updated_at' => now()],
                ['nom' => 'Communication et Marketing', 'created_at' => now(), 'updated_at' => now()],
                ['nom' => 'Audit Interne et Contrôle', 'created_at' => now(), 'updated_at' => now()],
                ['nom' => 'Qualité et Conformité', 'created_at' => now(), 'updated_at' => now()],
            
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
