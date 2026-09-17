<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Departement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ReferenceEndpointTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/references/next/budget');

        $response->assertStatus(401);
    }

    #[DataProvider('typesProvider')]
    public function test_returns_the_first_reference_for_each_type(string $type, string $prefix): void
    {
        $this->loginWithPermissions([], ['departement_id' => Departement::first()->id]);

        $response = $this->getJson("/api/references/next/{$type}");

        $response->assertStatus(200)
            ->assertJson(['reference' => "{$prefix}-" . now()->year . '-0001']);
    }

    public static function typesProvider(): array
    {
        return [
            'budget' => ['budget', 'BUD'],
            'ligne' => ['ligne', 'LB'],
            'bon-commande' => ['bon-commande', 'BC'],
            'facture' => ['facture', 'FAC'],
        ];
    }

    public function test_returns_404_for_an_unknown_type(): void
    {
        $this->loginWithPermissions([], ['departement_id' => Departement::first()->id]);

        $response = $this->getJson('/api/references/next/inconnu');

        $response->assertStatus(404);
    }

    public function test_reference_increments_after_a_budget_is_created(): void
    {
        $year = now()->year;
        Budget::create([
            'nom' => 'Budget existant',
            'code' => "BUD-{$year}-0001",
            'montant_global' => 1000,
            'annee' => $year,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);

        $this->loginWithPermissions([], ['departement_id' => Departement::first()->id]);

        $response = $this->getJson('/api/references/next/budget');

        $response->assertStatus(200)
            ->assertJson(['reference' => "BUD-{$year}-0002"]);
    }
}