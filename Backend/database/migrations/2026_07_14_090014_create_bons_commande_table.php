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
        Schema::create('bons_commande', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ligne_budget_id')->constrained('ligne_budgets')->onDelete('cascade');
            $table->foreignId('departement_id')->constrained('departements')->restrictOnDelete();
            $table->string('numero_bc')->unique();
            $table->string('intitule_bc');
            $table->decimal('montant_bc', 15, 2);
            $table->text('description')->nullable();
            $table->date('date_achat');
            $table->string('fournisseur');
            $table->string('type_paiement')->nullable();
            $table->enum('statut', ['brouillon', 'validé'])->default('brouillon');
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bons_commande');
    }
};
