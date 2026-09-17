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
        Schema::create('ligne_budget_annuites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ligne_budget_id')->constrained('ligne_budgets')->onDelete('cascade');
            $table->integer('annee');
            $table->decimal('montant', 15, 2);

            $table->timestamps();

            $table->unique(['ligne_budget_id', 'annee']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_budget_annuites');
    }
};
