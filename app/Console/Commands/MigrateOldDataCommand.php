<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MigrateOldDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:old-data
                            {--table= : Migrar solo una tabla específica}
                            {--dry-run : Solo mostrar qué se migraría sin ejecutar}
                            {--batch-size=1000 : Tamaño del batch para migración masiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra datos de la base de datos antigua local a la nueva base de datos de producción';

    /**
     * Mapeo de tablas entre BD antigua y nueva
     */
    protected $tableMappings = [
        // Usuarios y Permisos
        'users' => 'users',
        'permissions' => 'permissions',
        'password_reset_tokens' => 'password_reset_tokens',
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
        'meeting_content_relations' => 'meeting_content_relations',
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
        'organization_google_tokens' => 'organization_google_tokens',
        'organization_folders' => 'organization_folders',
        'organization_subfolders' => 'organization_subfolders',
        'organization_group_folders' => 'organization_group_folders',
        'organization_container_folders' => 'organization_container_folders',
        'container_files' => 'container_files',
        'pending_folders' => 'pending_folders',

        // Planes y Pagos
        'plans' => 'plans',
        'user_subscriptions' => 'user_subscriptions',
        'payments' => 'payments',
        'plan_limits' => 'plan_limits',

        // Métricas y Límites
        'monthly_meeting_usage' => 'monthly_meeting_usage',
        'ai_daily_usage' => 'ai_daily_usage',
        'limits' => 'limits',

        // AI
        'analyzers' => 'analyzers',
    ];

    /**
     * Tablas que requieren transformación especial
     */
    protected $specialTransformations = [
        'chats' => 'conversations',
        'ai_chat_sessions' => 'conversations',
        'chat_messages' => 'conversation_messages',
        'ai_chat_messages' => 'conversation_messages',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Iniciando migración de datos de BD antigua a BD nueva");

        // Verificar conexiones
        if (!$this->checkConnections()) {
            return 1;
        }

        $table = $this->option('table');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($table) {
            $this->migrateTable($table, $dryRun, $batchSize);
        } else {
            $this->migrateAllTables($dryRun, $batchSize);
        }

        $this->info("✅ Migración completada exitosamente!");
        return 0;
    }

    /**
     * Verificar que las conexiones a ambas BD funcionen
     */
    protected function checkConnections(): bool
    {
        try {
            // Probar conexión a BD antigua
            $oldDbTables = DB::connection('juntify_old_local')->select('SHOW TABLES');
            $this->info("✅ Conexión a BD antigua OK - " . count($oldDbTables) . " tablas encontradas");

            // Probar conexión a BD nueva
            $newDbTables = DB::connection('mysql')->select('SHOW TABLES');
            $this->info("✅ Conexión a BD nueva OK - " . count($newDbTables) . " tablas encontradas");

            return true;
        } catch (Exception $e) {
            $this->error("❌ Error de conexión: " . $e->getMessage());
            $this->error("Verifica las variables de entorno para OLD_LOCAL_DB_*");
            return false;
        }
    }

    /**
     * Migrar todas las tablas mapeadas
     */
    protected function migrateAllTables(bool $dryRun, int $batchSize): void
    {
        // Primero migrar tablas directas
        foreach ($this->tableMappings as $oldTable => $newTable) {
            $this->info("\n🔄 Procesando tabla: $oldTable -> $newTable");
            $this->migrateTable($oldTable, $dryRun, $batchSize);
        }

        // Luego migrar tablas con transformaciones especiales
        $this->info("\n🔀 Procesando transformaciones especiales...");
        $this->migrateConversationsFromChats($dryRun, $batchSize);
        $this->migrateConversationsFromAiSessions($dryRun, $batchSize);
        $this->migrateChatMessages($dryRun, $batchSize);
        $this->migrateAiMessages($dryRun, $batchSize);
    }

    /**
     * Migrar una tabla específica
     */
    protected function migrateTable(string $oldTableName, bool $dryRun, int $batchSize): void
    {
        try {
            $newTableName = $this->tableMappings[$oldTableName] ?? $oldTableName;

            // Verificar que la tabla existe en BD antigua
            if (!$this->tableExists('juntify_old_local', $oldTableName)) {
                $this->warn("⚠️  Tabla '$oldTableName' no existe en BD antigua");
                return;
            }

            // Verificar que la tabla existe en BD nueva
            if (!$this->tableExists('mysql', $newTableName)) {
                $this->warn("⚠️  Tabla '$newTableName' no existe en BD nueva");
                return;
            }

            // Contar registros en BD antigua
            $totalRecords = DB::connection('juntify_old_local')->table($oldTableName)->count();
            $this->info("📊 Registros a migrar: $totalRecords");

            if ($dryRun) {
                $this->info("🔍 [DRY-RUN] Se migrarían $totalRecords registros de $oldTableName a $newTableName");
                return;
            }

            if ($totalRecords == 0) {
                $this->info("📝 Tabla vacía, saltando...");
                return;
            }

            // Migrar en batches
            $migrated = 0;
            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            DB::connection('juntify_old_local')
                ->table($oldTableName)
                ->orderBy('id')
                ->chunk($batchSize, function ($records) use ($newTableName, &$migrated, $progressBar) {
                    $data = $records->map(function ($record) {
                        return $this->transformRecord((array) $record);
                    })->toArray();

                    // Insertar en BD nueva
                    DB::connection('mysql')->table($newTableName)->insert($data);

                    $migrated += count($data);
                    $progressBar->advance(count($data));
                });

            $progressBar->finish();
            $this->info("\n✅ Migrados $migrated registros de $oldTableName -> $newTableName");

        } catch (Exception $e) {
            $this->error("\n❌ Error migrando tabla $oldTableName: " . $e->getMessage());
            Log::error("Migration error for table $oldTableName", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Verificar si una tabla existe en una conexión
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
     * Transformar un registro antes de insertarlo
     * Aquí puedes añadir lógica específica de transformación
     */
    protected function transformRecord(array $record): array
    {
        // Transformaciones comunes

        // Convertir timestamps si es necesario
        if (isset($record['created_at']) && !is_null($record['created_at'])) {
            $record['created_at'] = date('Y-m-d H:i:s', strtotime($record['created_at']));
        }

        if (isset($record['updated_at']) && !is_null($record['updated_at'])) {
            $record['updated_at'] = date('Y-m-d H:i:s', strtotime($record['updated_at']));
        }

        // Limpiar campos nulos problemáticos
        foreach ($record as $key => $value) {
            if ($value === '' || $value === 'NULL') {
                $record[$key] = null;
            }
        }

        return $record;
    }

    /**
     * Migrar chats de usuarios a tabla conversations
     */
    protected function migrateConversationsFromChats(bool $dryRun, int $batchSize): void
    {
        if (!$this->tableExists('juntify_old_local', 'chats')) {
            $this->warn("⚠️  Tabla 'chats' no existe en BD antigua, omitiendo...");
            return;
        }

        try {
            $totalRecords = DB::connection('juntify_old_local')->table('chats')->count();
            $this->info("📊 Chats de usuarios a migrar: $totalRecords");

            if ($dryRun) {
                $this->info("🔍 [DRY-RUN] Se migrarían $totalRecords chats a conversations");
                return;
            }

            if ($totalRecords == 0) return;

            $migrated = 0;
            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            DB::connection('juntify_old_local')
                ->table('chats')
                ->orderBy('id')
                ->chunk($batchSize, function ($records) use (&$migrated, $progressBar) {
                    $data = $records->map(function ($record) {
                        return [
                            'id' => $record->id,
                            'type' => 'chat',
                            'user_one_id' => $record->user_one_id ?? null,
                            'user_two_id' => $record->user_two_id ?? null,
                            'is_active' => true,
                            'last_activity' => $record->updated_at ?? $record->created_at ?? now(),
                            'created_at' => $record->created_at ?? now(),
                            'updated_at' => $record->updated_at ?? now(),
                        ];
                    })->toArray();

                    DB::connection('mysql')->table('conversations')->insertOrIgnore($data);
                    $migrated += count($data);
                    $progressBar->advance(count($data));
                });

            $progressBar->finish();
            $this->info("\n✅ Migrados $migrated chats -> conversations");

        } catch (Exception $e) {
            $this->error("\n❌ Error migrando chats: " . $e->getMessage());
        }
    }

    /**
     * Migrar sesiones de IA a tabla conversations
     */
    protected function migrateConversationsFromAiSessions(bool $dryRun, int $batchSize): void
    {
        if (!$this->tableExists('juntify_old_local', 'ai_chat_sessions')) {
            $this->warn("⚠️  Tabla 'ai_chat_sessions' no existe en BD antigua, omitiendo...");
            return;
        }

        try {
            $totalRecords = DB::connection('juntify_old_local')->table('ai_chat_sessions')->count();
            $this->info("📊 Sesiones de IA a migrar: $totalRecords");

            if ($dryRun) {
                $this->info("🔍 [DRY-RUN] Se migrarían $totalRecords sesiones de IA a conversations");
                return;
            }

            if ($totalRecords == 0) return;

            // Obtener el ID máximo de conversations para evitar colisiones
            $maxId = DB::connection('mysql')->table('conversations')->max('id') ?? 0;

            $migrated = 0;
            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            DB::connection('juntify_old_local')
                ->table('ai_chat_sessions')
                ->orderBy('id')
                ->chunk($batchSize, function ($records) use (&$migrated, $progressBar, &$maxId) {
                    $data = $records->map(function ($record) use (&$maxId) {
                        $maxId++;
                        return [
                            'id' => $maxId,
                            'type' => 'ai_assistant',
                            'username' => $record->username ?? null,
                            'title' => $record->title ?? null,
                            'context_data' => isset($record->context_data) ? json_encode($record->context_data) : null,
                            'is_active' => $record->is_active ?? true,
                            'last_activity' => $record->last_activity ?? $record->updated_at ?? $record->created_at ?? now(),
                            'created_at' => $record->created_at ?? now(),
                            'updated_at' => $record->updated_at ?? now(),
                        ];
                    })->toArray();

                    DB::connection('mysql')->table('conversations')->insertOrIgnore($data);
                    $migrated += count($data);
                    $progressBar->advance(count($data));
                });

            $progressBar->finish();
            $this->info("\n✅ Migrados $migrated sesiones de IA -> conversations");

        } catch (Exception $e) {
            $this->error("\n❌ Error migrando sesiones de IA: " . $e->getMessage());
        }
    }

    /**
     * Migrar mensajes de chat de usuarios
     */
    protected function migrateChatMessages(bool $dryRun, int $batchSize): void
    {
        if (!$this->tableExists('juntify_old_local', 'chat_messages')) {
            $this->warn("⚠️  Tabla 'chat_messages' no existe en BD antigua, omitiendo...");
            return;
        }

        try {
            $totalRecords = DB::connection('juntify_old_local')->table('chat_messages')->count();
            $this->info("📊 Mensajes de chat a migrar: $totalRecords");

            if ($dryRun) {
                $this->info("🔍 [DRY-RUN] Se migrarían $totalRecords mensajes de chat a conversation_messages");
                return;
            }

            if ($totalRecords == 0) return;

            $migrated = 0;
            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            DB::connection('juntify_old_local')
                ->table('chat_messages')
                ->orderBy('id')
                ->chunk($batchSize, function ($records) use (&$migrated, $progressBar) {
                    $data = $records->map(function ($record) {
                        return [
                            'conversation_id' => $record->chat_id,
                            'role' => 'user',
                            'sender_id' => $record->sender_id ?? null,
                            'content' => $record->body ?? $record->message ?? $record->content,
                            'body' => $record->body ?? null,
                            'attachments' => isset($record->attachments) ? json_encode($record->attachments) : null,
                            'read_at' => $record->read_at ?? null,
                            'is_hidden' => $record->is_hidden ?? false,
                            'legacy_chat_message_id' => $record->id,
                            'created_at' => $record->created_at ?? now(),
                            'updated_at' => $record->updated_at ?? now(),
                        ];
                    })->toArray();

                    DB::connection('mysql')->table('conversation_messages')->insertOrIgnore($data);
                    $migrated += count($data);
                    $progressBar->advance(count($data));
                });

            $progressBar->finish();
            $this->info("\n✅ Migrados $migrated mensajes de chat -> conversation_messages");

        } catch (Exception $e) {
            $this->error("\n❌ Error migrando mensajes de chat: " . $e->getMessage());
        }
    }

    /**
     * Migrar mensajes de IA
     */
    protected function migrateAiMessages(bool $dryRun, int $batchSize): void
    {
        if (!$this->tableExists('juntify_old_local', 'ai_chat_messages')) {
            $this->warn("⚠️  Tabla 'ai_chat_messages' no existe en BD antigua, omitiendo...");
            return;
        }

        try {
            $totalRecords = DB::connection('juntify_old_local')->table('ai_chat_messages')->count();
            $this->info("📊 Mensajes de IA a migrar: $totalRecords");

            if ($dryRun) {
                $this->info("🔍 [DRY-RUN] Se migrarían $totalRecords mensajes de IA a conversation_messages");
                return;
            }

            if ($totalRecords == 0) return;

            $migrated = 0;
            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            // Crear mapeo de session_id a conversation_id
            $sessionMapping = $this->getSessionToConversationMapping();

            DB::connection('juntify_old_local')
                ->table('ai_chat_messages')
                ->orderBy('id')
                ->chunk($batchSize, function ($records) use (&$migrated, $progressBar, $sessionMapping) {
                    $data = $records->map(function ($record) use ($sessionMapping) {
                        $conversationId = $sessionMapping[$record->session_id] ?? $record->session_id;

                        return [
                            'conversation_id' => $conversationId,
                            'role' => $record->role ?? 'assistant',
                            'content' => $record->content ?? $record->message,
                            'metadata' => isset($record->metadata) ? json_encode($record->metadata) : null,
                            'legacy_ai_message_id' => $record->id,
                            'created_at' => $record->created_at ?? now(),
                            'updated_at' => $record->updated_at ?? now(),
                        ];
                    })->toArray();

                    DB::connection('mysql')->table('conversation_messages')->insertOrIgnore($data);
                    $migrated += count($data);
                    $progressBar->advance(count($data));
                });

            $progressBar->finish();
            $this->info("\n✅ Migrados $migrated mensajes de IA -> conversation_messages");

        } catch (Exception $e) {
            $this->error("\n❌ Error migrando mensajes de IA: " . $e->getMessage());
        }
    }

    /**
     * Obtener mapeo de session_id a conversation_id para mensajes de IA
     */
    protected function getSessionToConversationMapping(): array
    {
        try {
            // Buscar conversaciones que fueron creadas desde ai_chat_sessions
            $mapping = [];

            // Si las IDs fueron conservadas, usar directamente
            if ($this->tableExists('juntify_old_local', 'ai_chat_sessions')) {
                $sessions = DB::connection('juntify_old_local')->table('ai_chat_sessions')->select('id')->get();
                foreach ($sessions as $session) {
                    $mapping[$session->id] = $session->id;
                }
            }

            return $mapping;
        } catch (Exception $e) {
            $this->warn("No se pudo crear mapeo de sesiones: " . $e->getMessage());
            return [];
        }
    }
}
