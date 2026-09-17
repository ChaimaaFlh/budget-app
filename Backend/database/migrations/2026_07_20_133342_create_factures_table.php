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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bon_commande_id')->constrained('bons_commande')->onDelete('cascade');
            $table->string('ref_facture');
            $table->decimal('montant', 15, 2);
            $table->date('date_reception')->nullable();
            $table->enum('type_reglement', ['acompte', 'finale']);
            $table->string('piece_jointe')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
