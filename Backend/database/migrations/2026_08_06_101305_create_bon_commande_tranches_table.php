<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bon_commande_tranches')) {
            return;
        }

        Schema::create('bon_commande_tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_commande_id')->constrained('bons_commande')->cascadeOnDelete();
            $table->unsignedInteger('annuite');
            $table->decimal('montant', 14, 2);
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();

            $table->index(['bon_commande_id', 'annuite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bon_commande_tranches');
    }
};