<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->decimal('score', 5, 2)->nullable()->after('adresse');
            $table->unsignedInteger('score_annee')->nullable()->after('score');
        });

        $now = now();
        foreach ([
            'fournisseur.create' => 'Créer un fournisseur',
            'fournisseur.edit' => 'Modifier un fournisseur',
            'fournisseur.delete' => 'Supprimer un fournisseur',
        ] as $code => $libelle) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['libelle' => $libelle, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $administrators = DB::table('user_permissions')
            ->where('permission_id', DB::table('permissions')->where('code', 'user.manage_permissions')->value('id'))
            ->pluck('user_id');
        $permissionIds = DB::table('permissions')
            ->whereIn('code', ['fournisseur.create', 'fournisseur.edit', 'fournisseur.delete'])
            ->pluck('id');
        foreach ($administrators as $userId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('user_permissions')->updateOrInsert(
                    ['user_id' => $userId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('code', [
            'fournisseur.create', 'fournisseur.edit', 'fournisseur.delete',
        ])->delete();

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn(['score', 'score_annee']);
        });
    }
};
