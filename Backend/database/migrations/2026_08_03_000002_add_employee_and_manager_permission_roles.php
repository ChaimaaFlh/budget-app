<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roles = [
            'Employé' => [
                'bc.create',
                'facture.create',
            ],
            'Responsable' => [
                'ligne.create', 'ligne.edit',
                'bc.create', 'bc.edit', 'bc.validate',
                'facture.create', 'facture.edit',
                'fournisseur.create', 'fournisseur.edit',
            ],
        ];

        foreach ($roles as $nom => $codes) {
            DB::table('permission_packs')->updateOrInsert(
                ['nom' => $nom],
                ['description' => "Rôle {$nom}", 'created_at' => now(), 'updated_at' => now()],
            );
            $roleId = DB::table('permission_packs')->where('nom', $nom)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_pack_permission')->updateOrInsert(
                    ['permission_pack_id' => $roleId, 'permission_id' => $permissionId],
                    [],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('permission_packs')->whereIn('nom', ['Employé', 'Responsable'])->delete();
    }
};
