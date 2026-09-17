<?php

namespace Tests\Feature;

use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Departement;
use App\Models\Facture;
use App\Models\FactureDocument;
use App\Models\LigneBudget;
use App\Models\SousCategorie;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class FactureDocumentControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_index_requires_authentication(): void
    {
        [$bon, $facture] = $this->createFactureContext(Departement::first());

        $response = $this->getJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents");

        $response->assertStatus(401);
    }

    public function test_authorized_user_can_upload_documents(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')],
            'types' => ['scan_facture'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('facture_documents', [
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'scan.pdf',
        ]);
        $document = FactureDocument::where('facture_id', $facture->id)->first();
        Storage::disk('local')->assertExists($document->chemin);
    }

    public function test_unauthorized_user_cannot_upload_documents(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')],
            'types' => ['scan_facture'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('facture_documents', 0);
    }

    public function test_store_rejects_mismatched_documents_and_types_count(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [
                UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('piece.pdf', 100, 'application/pdf'),
            ],
            'types' => ['scan_facture'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['documents']);
    }

    public function test_store_rejects_invalid_document_type(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')],
            'types' => ['contrat'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['types.0']);
    }

    public function test_store_rejects_disallowed_mime_type(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')],
            'types' => ['piece_justificative'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['documents.0']);
    }

    public function test_store_fails_when_exceeding_five_documents(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        foreach (range(1, 5) as $i) {
            FactureDocument::create([
                'facture_id' => $facture->id,
                'type' => 'piece_justificative',
                'nom_original' => "piece{$i}.pdf",
                'chemin' => "factures/{$facture->id}/piece{$i}.pdf",
                'mime_type' => 'application/pdf',
                'taille_octets' => 1000,
            ]);
        }

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf')],
            'types' => ['piece_justificative'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('documents_restants', 0);
    }

    public function test_store_fails_when_a_scan_already_exists(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        FactureDocument::create([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'scan-existant.pdf',
            'chemin' => "factures/{$facture->id}/scan-existant.pdf",
            'mime_type' => 'application/pdf',
            'taille_octets' => 1000,
        ]);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [UploadedFile::fake()->create('nouveau-scan.pdf', 100, 'application/pdf')],
            'types' => ['scan_facture'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('facture_documents', 1);
    }

    public function test_store_fails_when_two_scans_sent_at_once(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->postJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents", [
            'documents' => [
                UploadedFile::fake()->create('scan1.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('scan2.pdf', 100, 'application/pdf'),
            ],
            'types' => ['scan_facture', 'scan_facture'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('facture_documents', 0);
    }

    public function test_user_can_download_document(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);
        Storage::disk('local')->put("factures/{$facture->id}/scan.pdf", 'contenu-fictif');
        $document = FactureDocument::create([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'scan.pdf',
            'chemin' => "factures/{$facture->id}/scan.pdf",
            'mime_type' => 'application/pdf',
            'taille_octets' => 1000,
        ]);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->get("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents/{$document->id}/download");

        $response->assertOk();
    }

    public function test_download_returns_404_when_file_missing_on_disk(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);
        $document = FactureDocument::create([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'scan.pdf',
            'chemin' => "factures/{$facture->id}/introuvable.pdf",
            'mime_type' => 'application/pdf',
            'taille_octets' => 1000,
        ]);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->getJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents/{$document->id}/download");

        $response->assertStatus(404);
    }

    public function test_user_cannot_access_documents_from_another_departement(): void
    {
        Storage::fake('local');
        $departementA = Departement::first();
        $departementB = Departement::skip(1)->first();
        [$bon, $facture] = $this->createFactureContext($departementB);

        $this->loginWithPermissions([], ['departement_id' => $departementA->id]);

        $response = $this->getJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents");

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_delete_document(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);
        Storage::disk('local')->put("factures/{$facture->id}/scan.pdf", 'contenu-fictif');
        $document = FactureDocument::create([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'scan.pdf',
            'chemin' => "factures/{$facture->id}/scan.pdf",
            'mime_type' => 'application/pdf',
            'taille_octets' => 1000,
        ]);

        $this->loginWithPermissions(['facture.create'], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('facture_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing("factures/{$facture->id}/scan.pdf");
    }

    public function test_unauthorized_user_cannot_delete_document(): void
    {
        Storage::fake('local');
        $departement = Departement::first();
        [$bon, $facture] = $this->createFactureContext($departement);
        $document = FactureDocument::create([
            'facture_id' => $facture->id,
            'type' => 'scan_facture',
            'nom_original' => 'scan.pdf',
            'chemin' => "factures/{$facture->id}/scan.pdf",
            'mime_type' => 'application/pdf',
            'taille_octets' => 1000,
        ]);

        $this->loginWithPermissions([], ['departement_id' => $departement->id]);

        $response = $this->deleteJson("/api/bons-commande/{$bon->id}/factures/{$facture->id}/documents/{$document->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('facture_documents', ['id' => $document->id]);
    }

    private function createFactureContext(Departement $departement): array
    {
        $categorie = Categorie::create(['departement_id' => $departement->id, 'nom' => 'Catégorie ' . uniqid()]);
        $sousCategorie = SousCategorie::create(['categorie_id' => $categorie->id, 'nom' => 'Sous-cat ' . uniqid()]);
        $budget = Budget::create([
            'nom' => 'Budget ' . uniqid(),
            'code' => 'BUD-' . uniqid(),
            'montant_global' => 1000000,
            'annee' => 2026,
            'statut' => 'ouvert',
            'type' => 'fonctionnement',
        ]);
        $ligne = LigneBudget::create([
            'sous_categorie_id' => $sousCategorie->id,
            'categorie_id' => $categorie->id,
            'budget_id' => $budget->id,
            'departement_id' => $departement->id,
            'code' => 'LB-' . uniqid(),
            'intitule' => 'Ligne test',
            'montant_alloue' => 100000,
            'date_debut_amortissement' => now(),
            'duree_amortissement_annees' => 5,
        ]);
        $user = $this->createUser(['departement_id' => $departement->id]);
        $bon = BonCommande::create([
            'user_id' => $user->id,
            'ligne_budget_id' => $ligne->id,
            'departement_id' => $departement->id,
            'numero_bc' => 'BC-' . uniqid(),
            'intitule_bc' => 'Achat test',
            'montant_bc' => 10000,
            'date_achat' => now(),
            'fournisseur' => 'Fournisseur Test',
        ]);
        $facture = Facture::create([
            'bon_commande_id' => $bon->id,
            'ref_facture' => 'FAC-' . uniqid(),
            'montant' => 1000,
            'type_reglement' => 'acompte',
        ]);

        return [$bon, $facture];
    }
}