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
        Schema::create('ligne_budgets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sous_categorie_id')->constrained('sous_categories')->nullOnDelete();
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->foreignId('departement_id')->constrained('departements')->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('intitule');
            $table->decimal('montant_alloue', 15, 2)->default(0.00);
            $table->date('date_debut_amortissement');
            $table->unsignedTinyInteger('duree_amortissement_annees')->default(5);

            $table->softDeletes();
            $table->timestamps();
        });

        // Now the column exists, so change() has a real column to alter
        Schema::table('ligne_budgets', function (Blueprint $table) {
            $table->foreignId('sous_categorie_id')->nullable()->change();
        });

        DB::statement('
            UPDATE ligne_budgets
            SET categorie_id = (
                SELECT categorie_id FROM sous_categories
                WHERE sous_categories.id = ligne_budgets.sous_categorie_id
            )
            WHERE sous_categorie_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_budgets');
    }
};
