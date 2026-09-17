<?php

namespace Tests\Feature;

use App\Models\Fournisseur;
use App\Models\FournisseurScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class FournisseurControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_supplier_score_is_the_average_of_the_last_five_years(): void
    {
        $fournisseur = Fournisseur::create(['nom' => 'Atlas Services']);
        FournisseurScore::create(['fournisseur_id' => $fournisseur->id, 'annee' => now()->year, 'score' => 80]);
        FournisseurScore::create(['fournisseur_id' => $fournisseur->id, 'annee' => now()->year - 4, 'score' => 100]);
        FournisseurScore::create(['fournisseur_id' => $fournisseur->id, 'annee' => now()->year - 5, 'score' => 20]);
        $this->loginWithPermissions([]);

        $this->getJson('/api/fournisseurs')
            ->assertOk()
            ->assertJsonPath('0.score', '90.00');

        $this->getJson("/api/fournisseurs/{$fournisseur->id}")
            ->assertOk()
            ->assertJsonPath('score_moyen', 90)
            ->assertJsonCount(2, 'scores');
    }
}
