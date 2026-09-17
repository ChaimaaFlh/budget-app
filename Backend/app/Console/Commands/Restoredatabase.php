<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Restaure une sauvegarde créée par db:backup. Vide chaque table présente
 * dans la sauvegarde puis réinjecte les lignes telles quelles (avec leurs
 * anciens ID), en désactivant temporairement les contraintes de clé
 * étrangère pour ne pas dépendre de l'ordre d'insertion.
 */
class RestoreDatabase extends Command
{
    protected $signature = 'db:restore {file? : Nom du fichier (sans extension) dans storage/app/backups. Par défaut : le plus récent}
                             {--force : Exécute sans confirmation}';

    protected $description = "Restaure une sauvegarde créée par db:backup (vide puis réinjecte chaque table concernée)";

    public function handle(): int
    {
        $chemin = $this->resoudreChemin($this->argument('file'));

        if (! $chemin) {
            return self::FAILURE;
        }

        $donnees = json_decode(Storage::disk('local')->get($chemin), true);

        if (! is_array($donnees) || ! isset($donnees['tables'])) {
            $this->error('Fichier de sauvegarde invalide.');

            return self::FAILURE;
        }

        $this->info('Sauvegarde du ' . ($donnees['created_at'] ?? '?') . ' — ' . count($donnees['tables']) . ' table(s).');

        if (! $this->option('force') && ! $this->confirm('Cette opération va VIDER puis réinjecter les données dans chaque table de la sauvegarde. Continuer ?')) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        $driver = DB::connection()->getDriverName();

        DB::transaction(function () use ($donnees, $driver) {
            $this->desactiverContraintesFK($driver);

            // Phase 1 : on vide TOUTES les tables avant de réinjecter quoi que
            // ce soit — sur PostgreSQL, TRUNCATE ... CASCADE peut vider une
            // table déjà restaurée si on truncate/insert table par table.
            foreach ($donnees['tables'] as $table => $lignes) {
                if (! Schema::hasTable($table)) {
                    $this->warn("Table absente, ignorée : {$table}");
                    continue;
                }
                DB::table($table)->truncate();
            }

            // Phase 2 : réinjection des données.
            foreach ($donnees['tables'] as $table => $lignes) {
                if (! Schema::hasTable($table) || empty($lignes)) {
                    continue;
                }

                foreach (array_chunk($lignes, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                if ($driver === 'pgsql' && Schema::hasColumn($table, 'id')) {
                    try {
                        DB::statement("SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1))");
                    } catch (\Throwable) {
                        // Pas de séquence sur cette colonne (id non auto-incrémenté) : rien à faire.
                    }
                }

                $this->line(" - {$table} : " . count($lignes) . ' ligne(s) restaurée(s)');
            }

            $this->reactiverContraintesFK($driver);
        });

        $this->info('Restauration terminée.');

        return self::SUCCESS;
    }

    private function resoudreChemin(?string $fichier): ?string
    {
        if ($fichier) {
            $chemin = "backups/{$fichier}.json";
            if (! Storage::disk('local')->exists($chemin)) {
                $this->error("Fichier introuvable : storage/app/private/{$chemin}");

                return null;
            }

            return $chemin;
        }

        $dernier = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.json'))
            ->sortDesc()
            ->first();

        if (! $dernier) {
            $this->error('Aucune sauvegarde trouvée dans storage/app/private/backups. Lancez d’abord : php artisan db:backup');

            return null;
        }

        return $dernier;
    }

    private function desactiverContraintesFK(string $driver): void
    {
        match ($driver) {
            'pgsql' => DB::statement('SET session_replication_role = replica;'),
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=0;'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF;'),
            default => null,
        };
    }

    private function reactiverContraintesFK(string $driver): void
    {
        match ($driver) {
            'pgsql' => DB::statement('SET session_replication_role = DEFAULT;'),
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=1;'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON;'),
            default => null,
        };
    }
}