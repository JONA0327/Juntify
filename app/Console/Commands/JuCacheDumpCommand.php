<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiMeetingJuCache;
use App\Models\TranscriptionLaravel;
use Throwable;

class JuCacheDumpCommand extends Command
{
    protected $signature = 'ju:cache-dump {meeting_ids* : IDs de reuniones} {--raw : Mostrar si existe RAW completo} {--full : Imprime JSON completo (cuidado con tamaño)}';
    protected $description = 'Muestra (desencriptado) lo que está guardado en ai_meeting_ju_caches para verificar si hay datos vacíos o incompletos.';

    public function handle(): int
    {
        $ids = $this->argument('meeting_ids');
        $showRaw = $this->option('raw');
        $full = $this->option('full');
        $any = false;

        foreach ($ids as $id) {
            $id = (int)$id;
            /** @var AiMeetingJuCache|null $row */
            $row = AiMeetingJuCache::where('meeting_id', $id)->first();
            $meeting = TranscriptionLaravel::find($id);
            $title = $meeting?->meeting_name ?? '(sin nombre)';
            if (!$row) {
                $this->warn("[ID $id] SIN REGISTRO en ai_meeting_ju_caches (".$title.")");
                continue;
            }
            $any = true;
            try {
                $data = $row->data; // mutator ya desencripta
                $raw = $showRaw ? $row->raw_data : null;
                $summary = is_array($data) ? ($data['summary'] ?? null) : null;
                $kp = is_array($data) ? ($data['key_points'] ?? []) : [];
                $segments = is_array($data) ? ($data['segments'] ?? []) : [];
                $tasks = is_array($data) ? ($data['tasks'] ?? []) : [];

                $this->line(str_repeat('-', 80));
                $this->info("Reunión #$id: $title");
                $this->line('Drive ID: ' . ($row->transcript_drive_id ?? '(null)'));
                $this->line('Checksum: ' . ($row->checksum ?? '(null)') . ' | Size: ' . ($row->size_bytes ?? '0'));
                if ($showRaw) {
                    $this->line('RAW checksum: ' . ($row->raw_checksum ?? '(null)') . ' | RAW size: ' . ($row->raw_size_bytes ?? '0'));
                }
                $this->line('Summary length: ' . (is_string($summary) ? strlen($summary) : 0));
                $this->line('Key Points: ' . (is_countable($kp) ? count($kp) : 0));
                $this->line('Segments: ' . (is_countable($segments) ? count($segments) : 0));
                $this->line('Tasks: ' . (is_countable($tasks) ? count($tasks) : 0));

                if ($summary) {
                    $this->line('Resumen (primeros 280 chars): ' . substr($summary, 0, 280));
                } else {
                    $this->warn('Resumen vacío / no disponible');
                }

                if (!$full) {
                    $this->line('Use --full para ver JSON completo, --raw para incluir raw.');
                } else {
                    $this->line('JSON normalizado completo:');
                    $this->line(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                    if ($showRaw) {
                        $this->line('JSON RAW completo:');
                        $this->line(json_encode($raw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                    }
                }
            } catch (Throwable $e) {
                $this->error("[ID $id] Error al desencriptar: " . $e->getMessage());
                $this->line($e->getFile() . ':' . $e->getLine());
            }
        }

        if (!$any) {
            $this->warn('No se encontró ningún registro para los IDs indicados.');
        }
        return 0;
    }
}
