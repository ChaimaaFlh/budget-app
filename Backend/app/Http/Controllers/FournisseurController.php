<?php
namespace App\Http\Controllers;
use App\Models\Fournisseur;
use App\Models\FournisseurScore;
use Illuminate\Http\Request;
class FournisseurController extends Controller {
 public function index() {
  $from = now()->year - 4;
  return Fournisseur::withCount('bonsCommande')->with(['scores' => fn ($query) => $query->where('annee', '>=', $from)->orderByDesc('annee')])->orderByDesc('created_at')->orderByDesc('id')->get()->each(fn ($f) => $f->setAttribute('score', $f->scores->avg('score')));
 }
 public function store(Request $r) { $v=$r->validate($this->rules()); $fournisseur = Fournisseur::create($this->supplierAttributes($v)); $this->saveScore($fournisseur, $v); return response()->json($fournisseur,201); }
 public function update(Request $r,Fournisseur $fournisseur) { $v=$r->validate($this->rules($fournisseur)); $fournisseur->update($this->supplierAttributes($v)); $this->saveScore($fournisseur, $v); return $fournisseur; }
 public function destroy(Fournisseur $fournisseur) { if ($fournisseur->bonsCommande()->exists()) return response()->json(['message' => 'Impossible de supprimer un fournisseur associé à des bons de commande.'], 422); $fournisseur->delete(); return response()->json(['message' => 'Fournisseur supprimé.']); }
 public function show(Fournisseur $fournisseur) { $scores = $fournisseur->scores()->where('annee', '>=', now()->year - 4)->orderByDesc('annee')->get(); return ['fournisseur' => $fournisseur, 'bons_commande_count' => $fournisseur->bonsCommande()->count(), 'score_moyen' => $scores->avg('score'), 'scores' => $scores]; }
 private function rules(?Fournisseur $fournisseur = null): array { return ['nom' => 'required|string|max:255|unique:fournisseurs,nom'.($fournisseur ? ','.$fournisseur->id : ''), 'email' => 'nullable|email', 'telephone' => 'nullable|string|max:50', 'adresse' => 'nullable|string', 'score' => 'nullable|numeric|min:0|max:100', 'score_annee' => 'nullable|integer|min:2000|max:2200']; }
 private function supplierAttributes(array $data): array { return collect($data)->except(['score', 'score_annee'])->all(); }
 private function saveScore(Fournisseur $fournisseur, array $data): void { if (($data['score'] ?? null) === null) return; $annee = $data['score_annee'] ?? now()->year; FournisseurScore::updateOrCreate(['fournisseur_id' => $fournisseur->id, 'annee' => $annee], ['score' => $data['score']]); }
}
