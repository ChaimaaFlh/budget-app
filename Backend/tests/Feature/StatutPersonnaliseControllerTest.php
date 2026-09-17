<?php

namespace Tests\Feature;

use App\Models\StatutPersonnalise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class StatutPersonnaliseControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_authenticated_user_can_create_and_list_a_colored_custom_status(): void
    {
        $this->loginWithPermissions([]);

        $this->postJson('/api/statuts-personnalises', [
            'type' => 'facture',
            'libelle' => 'En contrôle',
            'couleur' => '#123456',
        ])->assertCreated()->assertJsonPath('couleur', '#123456');

        $this->getJson('/api/statuts-personnalises?type=facture')
            ->assertOk()
            ->assertJsonFragment(['libelle' => 'En contrôle', 'couleur' => '#123456']);
    }

    public function test_custom_status_can_be_deleted_when_unused(): void
    {
        $this->loginWithPermissions([]);
        $status = StatutPersonnalise::create([
            'type' => 'bon_commande',
            'libelle' => 'À archiver',
            'couleur' => '#64748B',
        ]);

        $this->deleteJson("/api/statuts-personnalises/{$status->id}")
            ->assertOk();

        $this->assertDatabaseMissing('statuts_personnalises', ['id' => $status->id]);
    }

    public function test_status_requires_a_valid_hex_color(): void
    {
        $this->loginWithPermissions([]);

        $this->postJson('/api/statuts-personnalises', [
            'type' => 'facture',
            'libelle' => 'À vérifier',
            'couleur' => 'bleu',
        ])->assertUnprocessable()->assertJsonValidationErrors('couleur');
    }
}
