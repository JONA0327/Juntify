<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkExistingMigrations extends Command
{
    protected $signature = 'migrations:mark-existing {--batch=} {--dry-run}';
    protected $description = 'Marca todas las migraciones presentes en database/migrations como ejecutadas sin correrlas. Evita recrear tablas ya existentes.';

    public function handle(): int
    {
        $migrationsDir = database_path('migrations');
        if (!is_dir($migrationsDir)) {
            $this->error('Directorio de migraciones no encontrado.');
            return self::FAILURE;
        }

        $files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.php');
        if (!$files) {
            $this->info('No se encontraron migraciones.');
            return self::SUCCESS;
        }

        $existing = DB::table('migrations')->pluck('migration')->all();
        $existingMap = array_flip($existing);

        $batch = $this->option('batch');
        if ($batch === null || $batch === '') {
            $current = DB::table('migrations')->max('batch');
            $batch = $current ? ($current + 1) : 1;
        } else {
            $batch = (int) $batch;
            if ($batch <= 0) { $batch = 1; }
        }

        $dry = (bool) $this->option('dry-run');

        $inserted = 0; $skipped = 0; $errors = 0; $pending = [];
        foreach ($files as $file) {
            $base = basename($file, '.php');
            if (isset($existingMap[$base])) { $skipped++; continue; }
            $pending[] = $base;
        }

        if (empty($pending)) {
            $this->info('Todas las migraciones ya están marcadas. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->line('Batch sugerido: <info>' . $batch . '</info>');
        $this->line('Migraciones a marcar (' . count($pending) . '):');
        foreach ($pending as $mig) {
            $this->line('  - ' . $mig);
        }

        if ($dry) {
            $this->comment('Dry run: no se insertó nada.');
            return self::SUCCESS;
        }

        foreach ($pending as $mig) {
            try {
                DB::table('migrations')->insert([
                    'migration' => $mig,
                    'batch' => $batch,
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn('Error insertando ' . $mig . ': ' . $e->getMessage());
            }
        }

        $this->info("Insertadas: $inserted | Ya existentes: $skipped | Errores: $errors | Batch: $batch");
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
