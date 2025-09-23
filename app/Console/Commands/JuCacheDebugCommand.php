<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TranscriptionLaravel;
use App\Services\MeetingJuCacheService;
use Throwable;

class JuCacheDebugCommand extends Command
{
    protected $signature = 'ju:cache-debug {meeting_id} {--raw}';
    protected $description = 'Descarga el .ju, lo desencripta, intenta cachearlo y muestra info detallada / stack traces';

    public function handle(): int
    {
        $id = (int)$this->argument('meeting_id');
        $meeting = TranscriptionLaravel::find($id);
        if (!$meeting) {
            $this->error("Reunión $id no encontrada");
            return 1;
        }

        $this->info("Meeting #$id: {$meeting->meeting_name} (drive_id={$meeting->transcript_drive_id})");

        try {
            // Usar el mismo trait que el controlador
            $controller = app(\App\Http\Controllers\AiAssistantController::class);
            $ref = new \ReflectionClass($controller);
            $m = $ref->getMethod('tryDownloadJuContent');
            $m->setAccessible(true);
            $content = $m->invoke($controller, $meeting);
        } catch (Throwable $e) {
            $this->error('Fallo descarga: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());
            return 1;
        }

        if (!is_string($content) || $content === '') {
            $this->warn('Contenido vacío / no descargado');
            return 1;
        }
        $this->info('Bytes descargados: ' . strlen($content));
        $this->line('Preview base64 first 60: ' . substr($content,0,60));

        try {
            $trait = app(\App\Http\Controllers\AiAssistantController::class); // reutiliza decryptJuFile
            $refT = new \ReflectionClass($trait);
            $dm = $refT->getMethod('decryptJuFile');
            $dm->setAccessible(true);
            $parsed = $dm->invoke($trait, $content);
            $data = $parsed['data'] ?? [];
            $raw = $parsed['raw'] ?? null;
            $this->info('Llaves data: ' . implode(',', array_keys($data)));
            $this->info('Segmentos: ' . (is_countable($data['segments'] ?? null) ? count($data['segments']) : 0));
        } catch (Throwable $e) {
            $this->error('Error al desencriptar: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());
            return 1;
        }

        try {
            /** @var MeetingJuCacheService $cache */
            $cache = app(MeetingJuCacheService::class);
            $ok = $cache->setCachedParsed($id, $data, (string)$meeting->transcript_drive_id, $raw);
            $this->info('Resultado cache DB: ' . ($ok ? 'OK' : 'FAIL'));
        } catch (Throwable $e) {
            $this->error('Excepción cache DB: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());
            $this->line(substr($e->getTraceAsString(),0,1000));
        }

        return 0;
    }
}
