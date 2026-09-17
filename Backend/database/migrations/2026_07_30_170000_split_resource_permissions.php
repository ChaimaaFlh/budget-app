<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'budget.edit' => 'Modifier un budget', 'budget.delete' => 'Supprimer un budget',
            'ligne.delete' => 'Supprimer une ligne budgétaire',
            'bc.edit' => 'Modifier un bon de commande', 'bc.delete' => 'Supprimer un bon de commande', 'bc.unvalidate' => 'Annuler la validation d’un bon de commande',
            'facture.edit' => 'Modifier une facture', 'facture.delete' => 'Supprimer une facture',
            'categorie.create' => 'Créer une catégorie', 'categorie.edit' => 'Modifier une catégorie', 'categorie.delete' => 'Supprimer une catégorie',
            'souscategorie.create' => 'Créer une sous-catégorie', 'souscategorie.edit' => 'Modifier une sous-catégorie', 'souscategorie.delete' => 'Supprimer une sous-catégorie',
        ];

        foreach ($permissions as $code => $libelle) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['libelle' => $libelle, 'created_at' => $now, 'updated_at' => $now]);
        }

        $legacyMap = [
            'budget.create' => ['budget.edit', 'budget.delete'],
            'ligne.edit' => ['ligne.delete'],
            'bc.create' => ['bc.edit', 'bc.delete'],
            'facture.create' => ['facture.edit', 'facture.delete'],
            'categorie.manage' => ['categorie.create', 'categorie.edit', 'categorie.delete', 'souscategorie.create', 'souscategorie.edit', 'souscategorie.delete'],
        ];
        foreach ($legacyMap as $legacyCode => $newCodes) {
            $legacyId = DB::table('permissions')->where('code', $legacyCode)->value('id');
            if (!$legacyId) continue;
            $users = DB::table('user_permissions')->where('permission_id', $legacyId)->pluck('user_id');
            foreach ($newCodes as $newCode) {
                $newId = DB::table('permissions')->where('code', $newCode)->value('id');
                foreach ($users as $userId) {
                    DB::table('user_permissions')->updateOrInsert(['user_id' => $userId, 'permission_id' => $newId], ['created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('user_permissions')->whereIn('permission_id', DB::table('permissions')->whereIn('code', [
            'budget.edit', 'budget.delete', 'ligne.delete', 'bc.edit', 'bc.delete', 'bc.unvalidate',
            'facture.edit', 'facture.delete', 'categorie.create', 'categorie.edit', 'categorie.delete',
            'souscategorie.create', 'souscategorie.edit', 'souscategorie.delete',
        ])->pluck('id'))->delete();
        DB::table('permissions')->whereIn('code', [
            'budget.edit', 'budget.delete', 'ligne.delete', 'bc.edit', 'bc.delete', 'bc.unvalidate',
            'facture.edit', 'facture.delete', 'categorie.create', 'categorie.edit', 'categorie.delete',
            'souscategorie.create', 'souscategorie.edit', 'souscategorie.delete',
        ])->delete();
    }
};
