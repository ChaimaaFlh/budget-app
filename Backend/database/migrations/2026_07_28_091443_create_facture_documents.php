<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('facture_id')->constrained('factures')->onDelete('cascade');
            $table->enum('type', ['scan_facture', 'piece_justificative'])->default('piece_justificative');
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime_type');
            $table->unsignedBigInteger('taille_octets');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_documents');
    }
};