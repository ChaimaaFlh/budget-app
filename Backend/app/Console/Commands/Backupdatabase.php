<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sauvegarde toutes les données applicatives (hors tables techniques Laravel)
 * dans un fichier JSON sous storage/app/backups. À utiliser avant tout
 * `migrate:fresh`, puis restaurer avec `db:restore`.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--name= : Nom du fichier (sans extension). Par défaut : horodatage}';

    protected $description = 'Sauvegarde toutes les données de la base dans un fichier JSON restaurable avec db:restore';

    private array $tablesExclues = [
        'migrations', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($table) => in_array($table, $this->tablesExclues, true))
            ->values();

        if ($tables->isEmpty()) {
            $this->error('Aucune table trouvée.');

            return self::FAILURE;
        }

        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $nom = $this->option('name') ?: now()->format('Y_m_d_His');
        $chemin = "backups/{$nom}.json";

        Storage::disk('local')->put($chemin, json_encode([
            'created_at' => now()->toDateTimeString(),
            'driver' => DB::connection()->getDriverName(),
            'tables' => $snapshot,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Sauvegarde créée : storage/app/private/{$chemin}");
        foreach ($snapshot as $table => $rows) {
            $this->line(" - {$table} : " . count($rows) . ' ligne(s)');
        }
        $this->line('');
        $this->line("Pour restaurer : php artisan db:restore {$nom}");

        return self::SUCCESS;
    }
}