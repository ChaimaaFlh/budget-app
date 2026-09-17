<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = DB::table('categories')
            ->whereNull('departement_id')
            ->whereNull('deleted_at')
            ->get();

        foreach ($categories as $category) {
            $departementIds = DB::table('ligne_budgets as lignes')
                ->leftJoin('sous_categories as sous_categories', 'sous_categories.id', '=', 'lignes.sous_categorie_id')
                ->where(function ($query) use ($category) {
                    $query->where('lignes.categorie_id', $category->id)
                        ->orWhere('sous_categories.categorie_id', $category->id);
                })
                ->whereNull('lignes.deleted_at')
                ->whereNotNull('lignes.departement_id')
                ->distinct()
                ->orderBy('lignes.departement_id')
                ->pluck('lignes.departement_id')
                ->all();

            if ($departementIds === []) {
                continue;
            }

            DB::table('categories')->where('id', $category->id)->update([
                'departement_id' => array_shift($departementIds),
            ]);

            $sousCategories = DB::table('sous_categories')
                ->where('categorie_id', $category->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($departementIds as $departementId) {
                $newCategoryId = DB::table('categories')->insertGetId([
                    'departement_id' => $departementId,
                    'nom' => $category->nom,
                    'description' => $category->description,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ]);

                foreach ($sousCategories as $sousCategorie) {
                    $newSousCategorieId = DB::table('sous_categories')->insertGetId([
                        'categorie_id' => $newCategoryId,
                        'nom' => $sousCategorie->nom,
                        'description' => $sousCategorie->description,
                        'created_at' => $sousCategorie->created_at,
                        'updated_at' => $sousCategorie->updated_at,
                    ]);

                    DB::table('ligne_budgets')
                        ->where('departement_id', $departementId)
                        ->where('sous_categorie_id', $sousCategorie->id)
                        ->update([
                            'categorie_id' => $newCategoryId,
                            'sous_categorie_id' => $newSousCategorieId,
                        ]);
                }

                DB::table('ligne_budgets')
                    ->where('categorie_id', $category->id)
                    ->where('departement_id', $departementId)
                    ->update(['categorie_id' => $newCategoryId]);
            }
        }
    }

    public function down(): void
    {
        // La réparation du référentiel n'est pas réversible sans perdre les
        // associations créées pour chaque département.
    }
};
