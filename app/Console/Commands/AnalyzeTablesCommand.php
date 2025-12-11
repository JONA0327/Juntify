<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class AnalyzeTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:tables
                            {--export=json : Exportar resultado en formato json o table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analiza las tablas disponibles en ambas BD y muestra cuáles se pueden migrar';

    /**
     * Todas las tablas esperadas según el análisis
     */
    protected $expectedTables = [
        // Migración Directa
        'users', 'permissions', 'password_reset_tokens', 'notifications', 'contacts',
        'organizations', 'groups', 'organization_user', 'group_user', 'group_codes', 'organization_activities',
        'transcriptions_laravel', 'meeting_content_containers', 'meeting_content_relations',
        'shared_meetings', 'transcription_temps', 'pending_recordings',
        'tasks', 'tasks_laravel',
        'google_tokens', 'folders', 'subfolders', 'organization_google_tokens',
        'organization_folders', 'organization_subfolders', 'organization_group_folders',
        'organization_container_folders', 'container_files', 'pending_folders',
        'plans', 'user_subscriptions', 'payments', 'plan_limits',
        'monthly_meeting_usage', 'ai_daily_usage', 'limits', 'analyzers',

        // Transformaciones Especiales
        'chats', 'ai_chat_sessions', 'chat_messages', 'ai_chat_messages'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔍 Analizando tablas disponibles en ambas bases de datos...\n");

        try {
            $analysis = $this->performAnalysis();

            $exportFormat = $this->option('export');
            if ($exportFormat === 'json') {
                $this->exportJson($analysis);
            } else {
                $this->displayResults($analysis);
            }

        } catch (Exception $e) {
            $this->error("❌ Error durante el análisis: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Realizar el análisis completo
     */
    protected function performAnalysis(): array
    {
        // Obtener tablas de ambas bases de datos
        $oldTables = $this->getTablesFromConnection('juntify_old_local');
        $newTables = $this->getTablesFromConnection('mysql');

        $analysis = [
            'old_db_info' => [
                'total_tables' => count($oldTables),
                'tables' => $oldTables
            ],
            'new_db_info' => [
                'total_tables' => count($newTables),
                'tables' => $newTables
            ],
            'migration_plan' => []
        ];

        // Analizar cada tabla esperada
        foreach ($this->expectedTables as $tableName) {
            $status = $this->analyzeTable($tableName, $oldTables, $newTables);
            $analysis['migration_plan'][$tableName] = $status;
        }

        return $analysis;
    }

    /**
     * Obtener lista de tablas de una conexión
     */
    protected function getTablesFromConnection(string $connection): array
    {
        try {
            $tables = DB::connection($connection)->select('SHOW TABLES');
            $tableNames = [];

            foreach ($tables as $table) {
                $tableArray = (array) $table;
                $tableName = reset($tableArray);
                $tableNames[] = $tableName;
            }

            return $tableNames;
        } catch (Exception $e) {
            $this->warn("⚠️  No se pudo conectar a '$connection': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analizar el estado de una tabla específica
     */
    protected function analyzeTable(string $tableName, array $oldTables, array $newTables): array
    {
        $existsInOld = in_array($tableName, $oldTables);
        $existsInNew = in_array($tableName, $newTables);

        $status = [
            'exists_in_old' => $existsInOld,
            'exists_in_new' => $existsInNew,
            'can_migrate' => false,
            'migration_type' => 'none',
            'record_count_old' => 0,
            'estimated_time' => '0s'
        ];

        // Determinar si es transformación especial
        $isTransformation = in_array($tableName, ['chats', 'ai_chat_sessions', 'chat_messages', 'ai_chat_messages']);

        if ($isTransformation) {
            $targetTable = $this->getTransformationTarget($tableName);
            $targetExists = in_array($targetTable, $newTables);

            if ($existsInOld && $targetExists) {
                $status['can_migrate'] = true;
                $status['migration_type'] = 'transformation';
                $status['target_table'] = $targetTable;

                // Contar registros en BD antigua
                try {
                    $count = DB::connection('juntify_old_local')->table($tableName)->count();
                    $status['record_count_old'] = $count;
                    $status['estimated_time'] = $this->estimateMigrationTime($count);
                } catch (Exception $e) {
                    $status['record_count_old'] = 'Error';
                }
            } elseif ($existsInOld && !$targetExists) {
                $status['migration_type'] = 'skip_no_target';
                $status['target_table'] = $targetTable;
            } elseif (!$existsInOld) {
                $status['migration_type'] = 'skip_no_source';
                $status['target_table'] = $targetTable;
            }
        } else {
            // Migración directa tradicional
            if ($existsInOld && $existsInNew) {
                $status['can_migrate'] = true;
                $status['migration_type'] = 'direct';

                // Contar registros en BD antigua
                try {
                    $count = DB::connection('juntify_old_local')->table($tableName)->count();
                    $status['record_count_old'] = $count;
                    $status['estimated_time'] = $this->estimateMigrationTime($count);
                } catch (Exception $e) {
                    $status['record_count_old'] = 'Error';
                }
            } elseif ($existsInOld && !$existsInNew) {
                $status['migration_type'] = 'skip_no_target';
            } elseif (!$existsInOld && $existsInNew) {
                $status['migration_type'] = 'skip_no_source';
            }
        }

        return $status;
    }

    /**
     * Obtener tabla objetivo para transformaciones
     */
    protected function getTransformationTarget(string $tableName): string
    {
        $mapping = [
            'chats' => 'conversations',
            'ai_chat_sessions' => 'conversations',
            'chat_messages' => 'conversation_messages',
            'ai_chat_messages' => 'conversation_messages'
        ];

        return $mapping[$tableName] ?? $tableName;
    }

    /**
     * Verificar si existe la tabla conversations en la BD nueva
     */
    protected function hasConversationTables(array $newTables): bool
    {
        return in_array('conversations', $newTables) && in_array('conversation_messages', $newTables);
    }

    /**
     * Estimar tiempo de migración
     */
    protected function estimateMigrationTime(int $recordCount): string
    {
        if ($recordCount == 0) return '0s';
        if ($recordCount < 1000) return '<1min';
        if ($recordCount < 10000) return '1-5min';
        if ($recordCount < 100000) return '5-30min';
        return '>30min';
    }

    /**
     * Mostrar resultados en pantalla
     */
    protected function displayResults(array $analysis): void
    {
        $this->info("📊 RESUMEN DE BASES DE DATOS:");
        $this->info("BD Antigua: {$analysis['old_db_info']['total_tables']} tablas");
        $this->info("BD Nueva: {$analysis['new_db_info']['total_tables']} tablas\n");

        // Preparar datos para la tabla
        $tableData = [];
        $canMigrate = 0;
        $totalRecords = 0;

        foreach ($analysis['migration_plan'] as $tableName => $status) {
            $icon = $this->getStatusIcon($status);
            $target = $status['target_table'] ?? $tableName;
            $records = $status['record_count_old'];
            $time = $status['estimated_time'];

            $tableData[] = [
                $icon,
                $tableName,
                $target,
                $status['migration_type'],
                $records,
                $time
            ];

            if ($status['can_migrate']) {
                $canMigrate++;
                if (is_numeric($records)) {
                    $totalRecords += $records;
                }
            }
        }

        $this->table([
            'Estado',
            'Tabla Origen',
            'Tabla Destino',
            'Tipo Migración',
            'Registros',
            'Tiempo Est.'
        ], $tableData);

        $this->info("\n🎯 RESUMEN DE MIGRACIÓN:");
        $this->info("✅ Tablas que se pueden migrar: $canMigrate");
        $this->info("📊 Total de registros a migrar: " . number_format($totalRecords));
        $this->info("⏱️  Tiempo estimado total: " . $this->estimateMigrationTime($totalRecords));

        // Mostrar comandos recomendados
        $this->info("\n🚀 COMANDOS RECOMENDADOS:");
        $this->info("1. php artisan migrate:old-data --dry-run     # Ver migración completa");
        $this->info("2. php artisan migrate:users --dry-run       # Ver migración de usuarios");
        $this->info("3. php artisan migrate:old-data              # Ejecutar migración");
        $this->info("4. php artisan verify:migration              # Verificar resultado");
    }

    /**
     * Obtener ícono de estado
     */
    protected function getStatusIcon(array $status): string
    {
        if ($status['can_migrate']) {
            return $status['migration_type'] === 'transformation' ? '🔄' : '✅';
        }

        return match($status['migration_type']) {
            'skip_no_target' => '⚠️',
            'skip_no_source' => '➖',
            default => '❌'
        };
    }

    /**
     * Exportar resultados como JSON
     */
    protected function exportJson(array $analysis): void
    {
        $filename = storage_path('app/table_analysis_' . date('Y-m-d_H-i-s') . '.json');
        file_put_contents($filename, json_encode($analysis, JSON_PRETTY_PRINT));

        $this->info("📄 Análisis exportado a: $filename");
        $this->info("📊 Resumen:");

        $migratable = array_filter($analysis['migration_plan'], fn($s) => $s['can_migrate']);
        $totalRecords = array_sum(array_map(fn($s) => is_numeric($s['record_count_old']) ? $s['record_count_old'] : 0, $migratable));

        $this->info("- Tablas migrables: " . count($migratable));
        $this->info("- Total registros: " . number_format($totalRecords));
    }
}
