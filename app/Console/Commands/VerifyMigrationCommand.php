<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class VerifyMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:migration
                            {--table= : Verificar solo una tabla específica}
                            {--detailed : Mostrar información detallada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica que los datos se hayan migrado correctamente comparando BD antigua vs nueva';

    /**
     * Mapeo de tablas para verificación
     */
    protected $tableMappings = [
        // Usuarios y Permisos
        'users' => 'users',
        'permissions' => 'permissions',
        'notifications' => 'notifications',
        'contacts' => 'contacts',

        // Organización y Grupos
        'organizations' => 'organizations',
        'groups' => 'groups',
        'organization_user' => 'organization_user',
        'group_user' => 'group_user',
        'group_codes' => 'group_codes',
        'organization_activities' => 'organization_activities',

        // Reuniones y Transcripciones
        'transcriptions_laravel' => 'transcriptions_laravel',
        'meeting_content_containers' => 'meeting_content_containers',
        'shared_meetings' => 'shared_meetings',
        'transcription_temps' => 'transcription_temps',
        'pending_recordings' => 'pending_recordings',

        // Tareas
        'tasks' => 'tasks',
        'tasks_laravel' => 'tasks_laravel',

        // Archivos y Drive
        'google_tokens' => 'google_tokens',
        'folders' => 'folders',
        'subfolders' => 'subfolders',
        'organization_folders' => 'organization_folders',
        'container_files' => 'container_files',

        // Planes y Pagos
        'plans' => 'plans',
        'user_subscriptions' => 'user_subscriptions',
        'payments' => 'payments',
        'plan_limits' => 'plan_limits',

        // AI y Conversaciones (Transformaciones especiales)
        'chats' => 'conversations',
        'ai_chat_sessions' => 'conversations',
        'chat_messages' => 'conversation_messages',
        'ai_chat_messages' => 'conversation_messages',
        'analyzers' => 'analyzers',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔍 Verificando migración de datos...\n");

        try {
            // Verificar conexiones
            $this->checkConnections();

            $table = $this->option('table');
            $detailed = $this->option('detailed');

            if ($table) {
                $this->verifyTable($table, $detailed);
            } else {
                $this->verifyAllTables($detailed);
            }

            $this->info("\n✅ Verificación completada!");

        } catch (Exception $e) {
            $this->error("❌ Error durante la verificación: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Verificar conexiones
     */
    protected function checkConnections(): void
    {
        try {
            DB::connection('juntify_old_local')->getPdo();
            $this->info("✅ Conexión a BD antigua OK");

            DB::connection('mysql')->getPdo();
            $this->info("✅ Conexión a BD nueva OK\n");

        } catch (Exception $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    /**
     * Verificar todas las tablas
     */
    protected function verifyAllTables(bool $detailed): void
    {
        $results = [];
        $totalOld = 0;
        $totalNew = 0;

        foreach ($this->tableMappings as $oldTable => $newTable) {
            $result = $this->compareTable($oldTable, $newTable, $detailed);
            $results[] = $result;

            // Solo sumar si son números válidos
            if (is_numeric($result['old_count'])) {
                $totalOld += $result['old_count'];
            }
            if (is_numeric($result['new_count'])) {
                $totalNew += $result['new_count'];
            }
        }

        // Mostrar tabla resumen
        $this->displayResultsTable($results);

        // Resumen total
        $this->info("\n📊 RESUMEN TOTAL:");
        $this->info("BD Antigua: {$totalOld} registros");
        $this->info("BD Nueva: {$totalNew} registros");

        $percentage = $totalOld > 0 ? round(($totalNew / $totalOld) * 100, 2) : 0;
        $this->info("Migración: {$percentage}% completada");
    }

    /**
     * Verificar una tabla específica
     */
    protected function verifyTable(string $oldTableName, bool $detailed): void
    {
        $newTableName = $this->tableMappings[$oldTableName] ?? $oldTableName;
        $result = $this->compareTable($oldTableName, $newTableName, $detailed);

        $this->displayResultsTable([$result]);

        if ($detailed) {
            $this->showDetailedComparison($oldTableName, $newTableName);
        }
    }

    /**
     * Comparar una tabla entre ambas BDs
     */
    protected function compareTable(string $oldTable, string $newTable, bool $detailed): array
    {
        $oldCount = 0;
        $newCount = 0;
        $status = '❌';

        try {
            // Verificar si las tablas existen
            if (!$this->tableExists('juntify_old_local', $oldTable)) {
                $status = '⚠️ ';
                $oldCount = 'N/A';
            } else {
                $oldCount = DB::connection('juntify_old_local')->table($oldTable)->count();
            }

            if (!$this->tableExists('mysql', $newTable)) {
                $status = '⚠️ ';
                $newCount = 'N/A';
            } else {
                $newCount = DB::connection('mysql')->table($newTable)->count();
            }

            // Determinar estado
            if ($oldCount === 'N/A' || $newCount === 'N/A') {
                $status = '⚠️ ';
            } elseif ($oldCount === $newCount) {
                $status = '✅';
            } elseif ($newCount > 0 && $newCount < $oldCount) {
                $status = '🔄';
            } else {
                $status = '❌';
            }

        } catch (Exception $e) {
            $status = '❌';
            $oldCount = 'Error';
            $newCount = 'Error';
        }

        return [
            'old_table' => $oldTable,
            'new_table' => $newTable,
            'old_count' => $oldCount,
            'new_count' => $newCount,
            'status' => $status
        ];
    }

    /**
     * Verificar si una tabla existe
     */
    protected function tableExists(string $connection, string $tableName): bool
    {
        try {
            DB::connection($connection)->table($tableName)->exists();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mostrar tabla de resultados
     */
    protected function displayResultsTable(array $results): void
    {
        $tableData = [];

        foreach ($results as $result) {
            $difference = '';
            if (is_numeric($result['old_count']) && is_numeric($result['new_count'])) {
                $diff = $result['new_count'] - $result['old_count'];
                $difference = $diff === 0 ? '=' : ($diff > 0 ? "+{$diff}" : $diff);
            }

            $tableData[] = [
                $result['status'],
                $result['old_table'],
                $result['new_table'],
                $result['old_count'],
                $result['new_count'],
                $difference
            ];
        }

        $this->table([
            'Estado',
            'Tabla Antigua',
            'Tabla Nueva',
            'Registros Antiguos',
            'Registros Nuevos',
            'Diferencia'
        ], $tableData);
    }

    /**
     * Mostrar comparación detallada de una tabla
     */
    protected function showDetailedComparison(string $oldTable, string $newTable): void
    {
        $this->info("\n🔍 Análisis detallado: {$oldTable} -> {$newTable}");

        try {
            // Comparar estructura de columnas
            $oldColumns = $this->getTableColumns('juntify_old_local', $oldTable);
            $newColumns = $this->getTableColumns('mysql', $newTable);

            $this->info("\n📋 Columnas en BD antigua: " . implode(', ', $oldColumns));
            $this->info("📋 Columnas en BD nueva: " . implode(', ', $newColumns));

            // Encontrar diferencias
            $missingInNew = array_diff($oldColumns, $newColumns);
            $newInNew = array_diff($newColumns, $oldColumns);

            if (!empty($missingInNew)) {
                $this->warn("⚠️  Columnas faltantes en BD nueva: " . implode(', ', $missingInNew));
            }

            if (!empty($newInNew)) {
                $this->info("✨ Columnas nuevas en BD nueva: " . implode(', ', $newInNew));
            }

            // Mostrar algunos registros de muestra
            $this->showSampleRecords($oldTable, $newTable);

        } catch (Exception $e) {
            $this->error("Error en análisis detallado: " . $e->getMessage());
        }
    }

    /**
     * Obtener columnas de una tabla
     */
    protected function getTableColumns(string $connection, string $tableName): array
    {
        try {
            $columns = DB::connection($connection)->select("DESCRIBE {$tableName}");
            return array_map(fn($col) => $col->Field, $columns);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Mostrar registros de muestra
     */
    protected function showSampleRecords(string $oldTable, string $newTable): void
    {
        try {
            $this->info("\n📄 Registros de muestra:");

            // Muestra de BD antigua
            $oldSample = DB::connection('juntify_old_local')->table($oldTable)->take(3)->get();
            $this->info("BD Antigua ({$oldSample->count()} primeros):");
            foreach ($oldSample as $record) {
                $sample = array_slice((array) $record, 0, 3, true);
                $this->info("  " . json_encode($sample));
            }

            // Muestra de BD nueva
            $newSample = DB::connection('mysql')->table($newTable)->take(3)->get();
            $this->info("BD Nueva ({$newSample->count()} primeros):");
            foreach ($newSample as $record) {
                $sample = array_slice((array) $record, 0, 3, true);
                $this->info("  " . json_encode($sample));
            }

        } catch (Exception $e) {
            $this->error("Error mostrando registros de muestra: " . $e->getMessage());
        }
    }
}
