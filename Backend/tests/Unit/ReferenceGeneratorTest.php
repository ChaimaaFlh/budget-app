<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Support\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_first_reference_of_the_year(): void
    {
        $reference = ReferenceGenerator::make(Budget::class, 'code', 'BUD');

        $this->assertSame('BUD-' . now()->year . '-0001', $reference);
    }

    public function test_increments_from_the_last_existing_reference(): void
    {
        $year = now()->year;
        $this->createBudget("BUD-{$year}-0001");
        $this->createBudget("BUD-{$year}-0007");

        $reference = ReferenceGenerator::make(Budget::class, 'code', 'BUD');

        $this->assertSame("BUD-{$year}-0008", $reference);
    }

    public function test_ignores_references_from_a_different_year(): void
    {
        $year = now()->year;
        $this->createBudget('BUD-2019-0099');

        $reference = ReferenceGenerator::make(Budget::class, 'code', 'BUD');

        $this->assertSame("BUD-{$year}-0001", $reference);
    }

    public function test_ignores_references_with_a_different_prefix(): void
    {
        $year = now()->year;
        $this->createBudget("LB-{$year}-0050");

        $reference = ReferenceGenerator::make(Budget::class, 'code', 'BUD');

        $this->assertSame("BUD-{$year}-0001", $reference);
    }

    public function test_takes_soft_deleted_records_into_account(): void
    {
        $year = now()->year;
        $budget = $this->createBudget("BUD-{$year}-0003");
        $budget->delete();

        $reference = ReferenceGenerator::make(Budget::class, 'code', 'BUD');

        $this->assertSame("BUD-{$year}-0004", $reference);
    }

    private function createBudget(string $code): Budget
    {
        return Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => $code,
            'montant_global' => 1000,
            'annee' => now()->year,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);
    }
}