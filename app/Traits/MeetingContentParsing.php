<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

trait MeetingContentParsing
{
    /**
     * Normaliza y extrae datos de reunión desde distintas formas de JSON
     */
    protected function extractMeetingDataFromJson($data): array
    {
        return [
            'summary' => $data['summary'] ?? $data['resumen'] ?? $data['meeting_summary'] ?? 'Resumen no disponible',
            'key_points' => $data['key_points'] ?? $data['keyPoints'] ?? $data['puntos_clave'] ?? $data['main_points'] ?? [],
            'tasks' => $data['tasks'] ?? $data['tareas'] ?? $data['action_items'] ?? [],
            'transcription' => $data['transcription'] ?? $data['transcripcion'] ?? $data['text'] ?? 'Transcripción no disponible',
            'speakers' => $data['speakers'] ?? $data['participantes'] ?? [],
            'segments' => $data['segments'] ?? $data['segmentos'] ?? [],
        ];
    }

    /**
     * Procesa/normaliza segmentos y estructura final para la UI
     */
    protected function processTranscriptData($data): array
    {
        $data = $this->extractMeetingDataFromJson($data);

        $segments = $data['segments'] ?? [];

        if (empty($segments) && !empty($data['transcription']) && is_string($data['transcription'])) {
            $fallbackSegments = $this->parseSegmentsFromTranscriptText($data['transcription']);
            if (!empty($fallbackSegments)) {
                $segments = $fallbackSegments;
            }
        }

        $segments = array_map(function ($segment) {
            if ((!isset($segment['start']) || !isset($segment['end'])) && isset($segment['timestamp'])) {
                if (preg_match('/(\d{2}):(\d{2})(?::(\d{2}))?\s*-\s*(\d{2}):(\d{2})(?::(\d{2}))?/', $segment['timestamp'], $m)) {
                    $segment['start'] = isset($m[3])
                        ? ((int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3])
                        : ((int)$m[1] * 60 + (int)$m[2]);
                    $segment['end'] = isset($m[6])
                        ? ((int)$m[4] * 3600 + (int)$m[5] * 60 + (int)$m[6])
                        : ((int)$m[4] * 60 + (int)$m[5]);
                }
            }
            return $segment;
        }, $segments);

        $transcription = $data['transcription'] ?? '';
        $transcription = is_string($transcription) ? trim($transcription) : '';
        $placeholderTranscriptions = [
            '',
            'Transcripción no disponible',
            'No hay transcripción disponible',
        ];

        $hasSegmentText = false;
        foreach ($segments as $segment) {
            if (isset($segment['text']) && trim((string) $segment['text']) !== '') {
                $hasSegmentText = true;
                break;
            }
        }

        if (in_array($transcription, $placeholderTranscriptions, true) && $hasSegmentText) {
            $transcription = $this->buildTranscriptFromSegments($segments);
        }

        if ($transcription === '') {
            $transcription = 'No hay transcripción disponible';
        }

        return [
            'summary' => $data['summary'] ?? 'No hay resumen disponible',
            'key_points' => $data['key_points'] ?? [],
            'tasks' => $data['tasks'] ?? [],
            'transcription' => $transcription,
            'speakers' => $data['speakers'] ?? [],
            'segments' => $segments,
        ];
    }

    /**
     * Construye segmentos a partir de una transcripción en formato
     * "[00:00 - 00:10] Nombre: Texto" cuando no vienen en JSON.
     */
    protected function parseSegmentsFromTranscriptText(string $transcription): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $transcription) ?: [];
        $segments = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $pattern = '/^\[(\d{2}):(\d{2})(?::(\d{2}))?\s*-\s*(\d{2}):(\d{2})(?::(\d{2}))?\]\s*(.+?)[：:]-?\s*(.+)$/u';

            if (!preg_match($pattern, $line, $matches)) {
                continue;
            }

            $startSeconds = $this->convertTimestampMatchToSeconds($matches[1], $matches[2], $matches[3] ?? null);
            $endSeconds = $this->convertTimestampMatchToSeconds($matches[4], $matches[5], $matches[6] ?? null);

            $speaker = trim($matches[7]);
            $text = trim($matches[8]);

            $segments[] = [
                'timestamp' => sprintf('%s - %s',
                    $this->formatSegmentTimestamp($startSeconds),
                    $this->formatSegmentTimestamp($endSeconds)
                ),
                'start' => $startSeconds,
                'end' => $endSeconds,
                'speaker' => $speaker,
                'text' => $text,
            ];
        }

        return $segments;
    }

    protected function convertTimestampMatchToSeconds($hoursOrMinutes, $minutesOrSeconds, $seconds = null): int
    {
        $hoursOrMinutes = (int) $hoursOrMinutes;
        $minutesOrSeconds = (int) $minutesOrSeconds;
        $seconds = $seconds !== null ? (int) $seconds : null;

        if ($seconds === null) {
            return ($hoursOrMinutes * 60) + $minutesOrSeconds;
        }

        return ($hoursOrMinutes * 3600) + ($minutesOrSeconds * 60) + $seconds;
    }

    protected function buildTranscriptFromSegments(array $segments): string
    {
        $lines = [];

        foreach ($segments as $segment) {
            $parts = [];

            $timestamp = $segment['timestamp'] ?? null;
            if (!$timestamp) {
                $start = $segment['start'] ?? null;
                $end = $segment['end'] ?? null;
                $formattedRange = $this->formatSegmentRange($start, $end);
                if ($formattedRange !== null) {
                    $timestamp = $formattedRange;
                }
            }
            if (is_string($timestamp) && trim($timestamp) !== '') {
                $parts[] = '[' . trim($timestamp) . ']';
            }

            $speaker = $segment['speaker'] ?? $segment['speaker_name'] ?? null;
            if (is_string($speaker) && trim($speaker) !== '') {
                $parts[] = trim($speaker) . ':';
            }

            $text = $segment['text'] ?? $segment['content'] ?? '';
            $text = is_string($text) ? trim($text) : '';

            if (empty($parts) && $text === '') {
                continue;
            }

            $line = trim(implode(' ', $parts));
            if ($text !== '') {
                $line = $line === '' ? $text : $line . ' ' . $text;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    protected function formatSegmentRange($start, $end): ?string
    {
        $formattedStart = $this->formatSegmentTimestamp($start);
        $formattedEnd = $this->formatSegmentTimestamp($end);

        if ($formattedStart && $formattedEnd) {
            return $formattedStart . ' - ' . $formattedEnd;
        }

        if ($formattedStart) {
            return $formattedStart;
        }

        if ($formattedEnd) {
            return $formattedEnd;
        }

        return null;
    }

    protected function formatSegmentTimestamp($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $seconds = (int) round((float) $value);
        if ($seconds < 0) {
            $seconds = 0;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Intenta desencriptar el contenido de un archivo .ju y devolver datos normalizados
     */
    protected function decryptJuFile($content): array
    {
        try {
            Log::info('decryptJuFile: Starting decryption', [
                'length' => strlen($content),
                'first_50' => substr($content, 0, 50)
            ]);

            // 1) Si el contenido ya es JSON válido (sin encriptar)
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                Log::info('decryptJuFile: Content is already valid JSON (unencrypted)');
                return [
                    'data' => $this->extractMeetingDataFromJson($json_data),
                    'raw' => $json_data,
                    'needs_encryption' => true,
                ];
            }

            // 2) Intentar descifrar string encriptado de Laravel Crypt (base64) usando APP_KEY actual o claves legacy
            if (substr($content, 0, 3) === 'eyJ') {
                Log::info('decryptJuFile: Attempting to decrypt Laravel Crypt format');
                $legacyKeys = array_filter(array_map('trim', explode(',', (string) env('LEGACY_APP_KEYS', ''))));
                $triedKeys = [];
                $decrypted = null;

                // Helper closure para desencriptar con una clave concreta
                $attemptDecrypt = function(string $key) use (&$content) {
                    $prevKey = config('app.key');
                    // Ajustar key temporalmente
                    config(['app.key' => $key]);
                    try {
                        return Crypt::decryptString($content);
                    } finally {
                        // Restaurar clave original
                        config(['app.key' => $prevKey]);
                    }
                };

                // Primero con la clave actual
                try {
                    $decrypted = Crypt::decryptString($content);
                    $triedKeys[] = 'current';
                    Log::info('decryptJuFile: Direct decryption successful');
                } catch (\Exception $e) {
                    $triedKeys[] = 'current(failed:' . $e->getMessage() . ')';
                    Log::warning('decryptJuFile: Direct decryption failed', ['error' => $e->getMessage()]);
                    // Intentar con legacy keys si error fue MAC invalid o similar
                    if (!empty($legacyKeys)) {
                        foreach ($legacyKeys as $idx => $legacyKey) {
                            try {
                                $legacyDecrypted = $attemptDecrypt($legacyKey);
                                if ($legacyDecrypted) {
                                    $decrypted = $legacyDecrypted;
                                    $triedKeys[] = 'legacy[' . $idx . ']';
                                    Log::info('decryptJuFile: Decryption successful with legacy key', ['legacy_index' => $idx]);
                                    break;
                                }
                            } catch (\Exception $eL) {
                                $triedKeys[] = 'legacy[' . $idx . '](failed:' . $eL->getMessage() . ')';
                                Log::warning('decryptJuFile: Legacy key failed', ['index' => $idx, 'error' => $eL->getMessage()]);
                            }
                        }
                    }
                }

                if ($decrypted !== null) {
                    $json_data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('decryptJuFile: JSON parsing after decryption successful', [
                            'keys' => array_keys($json_data),
                            'attempts' => $triedKeys,
                        ]);
                        return [
                            'data' => $this->extractMeetingDataFromJson($json_data),
                            'raw' => $json_data,
                            'needs_encryption' => false,
                        ];
                    } else {
                        Log::warning('decryptJuFile: JSON decode failed after decryption', [
                            'attempts' => $triedKeys,
                            'error' => json_last_error_msg(),
                        ]);
                    }
                }
            }

            // 3) Intentar desencriptar formato JSON {"iv":"...","value":"..."}
            if (str_contains($content, '"iv"') && str_contains($content, '"value"')) {
                Log::info('decryptJuFile: Detected Laravel Crypt JSON format');
                try {
                    $decrypted = Crypt::decrypt($content);
                    Log::info('decryptJuFile: Laravel Crypt JSON decryption successful');

                    $json_data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('decryptJuFile: JSON parsing after Laravel Crypt decryption successful', ['keys' => array_keys($json_data)]);
                        return [
                            'data' => $this->extractMeetingDataFromJson($json_data),
                            'raw' => $json_data,
                            'needs_encryption' => false,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('decryptJuFile: Laravel Crypt JSON decryption failed', ['error' => $e->getMessage()]);
                }
            }

            Log::warning('decryptJuFile: Using default data - all decryption methods failed');
            return [
                'data' => $this->getDefaultMeetingData(),
                'raw' => null,
                'needs_encryption' => false,
            ];

        } catch (\Exception $e) {
            Log::error('decryptJuFile: General exception', ['error' => $e->getMessage()]);
            return [
                'data' => $this->getDefaultMeetingData(),
                'raw' => null,
                'needs_encryption' => false,
            ];
        }
    }

    protected function getDefaultMeetingData(): array
    {
        return [
            'summary' => 'Resumen no disponible - Los archivos están encriptados y necesitan ser procesados.',
            'key_points' => [
                'Archivo encontrado en Google Drive',
                'Formato de encriptación detectado',
                'Procesamiento en desarrollo'
            ],
            'tasks' => [
                'Verificar método de encriptación utilizado',
                'Implementar desencriptación correcta'
            ],
            'transcription' => 'La transcripción está encriptada y será procesada en breve. Mientras tanto, puedes descargar el archivo original desde Google Drive.',
            'speakers' => ['Sistema'],
            'segments' => [
                [
                    'speaker' => 'Sistema',
                    'text' => 'El contenido de esta reunión está siendo procesado. El archivo se descargó correctamente desde Google Drive pero requiere desencriptación.',
                    'timestamp' => '00:00'
                ]
            ]
        ];
    }

    /**
     * Port of MeetingController::parseTaskFromParts, made reusable for imports.
     * Returns associative array keys: name, description, assigned, start, end, progress
     */
    protected function parseTaskFromPartsForDb(array $parts, array $base = []): array
    {
        $result = array_merge([
            'name' => 'Sin nombre',
            'description' => '',
            'assigned' => 'Sin asignar',
            'start' => 'Sin asignar',
            'end' => 'Sin asignar',
            'progress' => '0%'
        ], $base);

        $parts = array_values(array_map(function($p){ return trim((string)$p); }, array_filter($parts, function($p){ return $p !== null && trim((string)$p) !== ''; })));
        $expanded = [];
        foreach ($parts as $p) {
            $p = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', $p);
            $sub = array_map('trim', array_filter(explode(',', $p), function($x){ return $x !== ''; }));
            if (empty($sub)) { $expanded[] = $p; } else { foreach ($sub as $s) { $expanded[] = $s; } }
        }
        $parts = $expanded; $n = count($parts);
        if ($n === 0) { return $result; }

        if ($n === 1) {
            $single = $parts[0];
            if (preg_match('/^\s*([^:\-–]+?)\s*[:\-–]\s*(.+)$/u', $single, $m)) {
                $idToken = trim($m[1]);
                $desc = trim($m[2]);
                $result['name'] = $idToken !== '' ? rtrim($idToken, ",;:") : 'Sin nombre';
                $result['description'] = $desc;
                return $result;
            }
        }

        $norm = array_map(function($p){ return rtrim($p, " ,;:."); }, $parts);
        $progressIdx = -1;
        for ($i = $n - 1; $i >= 0; $i--) {
            if (preg_match('/^(100|[0-9]{1,2})\s*%[.,]?$/', $norm[$i])) { $progressIdx = $i; break; }
        }
        if ($progressIdx >= 0) {
            $result['progress'] = rtrim($parts[$progressIdx], " ,;:.");
            array_splice($parts, $progressIdx, 1);
            array_splice($norm, $progressIdx, 1);
            $n = count($parts);
        }

        $dateIdxs = [];
        for ($i = 0; $i < $n; $i++) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $norm[$i])) { $dateIdxs[] = $i; continue; }
            // dd/mm/yyyy -> normalize to yyyy-mm-dd and mark
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $norm[$i], $dm)) {
                $parts[$i] = $dm[3] . '-' . $dm[2] . '-' . $dm[1];
                $norm[$i] = $parts[$i];
                $dateIdxs[] = $i; continue;
            }
        }

        $startIdx = -1; $endIdx = -1; $lastDateIdx = -1;
        if (count($dateIdxs) >= 2) {
            $startIdx = $dateIdxs[0]; $endIdx = $dateIdxs[1];
            $result['start'] = $parts[$startIdx];
            $result['end'] = $parts[$endIdx];
            $lastDateIdx = max($dateIdxs);
        } elseif (count($dateIdxs) === 1) {
            $startIdx = $dateIdxs[0];
            $result['start'] = $parts[$startIdx];
            $lastDateIdx = $startIdx;
        }

        $looksLikeName = function($s) {
            $s = trim($s);
            if ($s === '') return false;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', rtrim($s, " ,;:."))) return false;
            if (preg_match('/^(task[_-]?\d+|tarea[_-]?\d+)$/i', $s)) return false;
            return (bool)preg_match('/[A-Za-zÁÉÍÓÚÑáéíóúñ]/u', $s);
        };

        $assignedIdx = -1;
        if ($lastDateIdx >= 0) {
            if ($lastDateIdx - 1 >= 0 && $looksLikeName($parts[$lastDateIdx - 1])) { $assignedIdx = $lastDateIdx - 1; }
            elseif ($lastDateIdx + 1 < $n && $looksLikeName($parts[$lastDateIdx + 1])) { $assignedIdx = $lastDateIdx + 1; }
        }
        if ($assignedIdx >= 0) { $result['assigned'] = $parts[$assignedIdx]; }

        if ($lastDateIdx < 0 && $assignedIdx < 0 && $n > 1) {
            $idToken = $parts[0] ?? null;
            $baseId = $idToken !== null ? rtrim(trim($idToken), ",;:") : '';
            $result['name'] = $baseId !== '' ? $baseId : 'Sin nombre';
            $descTokens = array_slice($parts, 1);
            $result['description'] = trim(implode(', ', $descTokens));
            return $result;
        }

        $idToken = $parts[0] ?? null;
        $titleStart = $idToken !== null ? 1 : 0;
        $limitIdx = $n - 1;
        if ($assignedIdx >= 0) { $limitIdx = min($limitIdx, $assignedIdx - 1); }
        if ($lastDateIdx >= 0) { $limitIdx = min($limitIdx, $lastDateIdx - 1); }
        $titleTokens = [];
        if ($limitIdx >= $titleStart) { for ($i = $titleStart; $i <= $limitIdx; $i++) { $titleTokens[] = $parts[$i]; } }
        $title = trim(implode(', ', $titleTokens));
        $baseId = $idToken !== null ? rtrim(trim($idToken), ",;:") : '';
        if ($baseId !== '' && preg_match('/^(task|tarea)[_-]?\d+$/i', $baseId)) { $name = $baseId; }
        else if ($title !== '') { $name = trim(($baseId !== '' ? $baseId . ', ' : '') . $title); }
        else { $name = $baseId !== '' ? $baseId : 'Sin nombre'; }
        $result['name'] = $name;

        $descTokens = [];
        $dateIdxSet = array_flip($dateIdxs);
        for ($i = 1; $i < $n; $i++) {
            if ($i === $assignedIdx) continue;
            if (isset($dateIdxSet[$i])) continue;
            $tok = rtrim($parts[$i], " ,;:.");
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tok)) continue;
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $tok)) continue;
            $low = mb_strtolower(rtrim($parts[$i], " ,;:."), 'UTF-8');
            if (in_array($low, ['no asignado','sin asignar'], true)) continue;
            $descTokens[] = $parts[$i];
        }
        $desc = trim(implode(', ', $descTokens));
        $result['description'] = $desc;

        return $result;
    }

    /**
     * Flexible task parser that mirrors PDF logic and returns fields mapped for DB: tarea, descripcion, fecha_inicio, fecha_limite, progreso.
     */
    protected function parseRawTaskForDb($raw): array
    {
        $base = [
            'name' => 'Sin nombre',
            'description' => '',
            'assigned' => 'Sin asignar',
            'start' => 'Sin asignar',
            'end' => 'Sin asignar',
            'progress' => '0%'
        ];

        $parse = function($rawTask) use ($base) {
            if (is_array($rawTask)) {
                $isAssoc = array_keys($rawTask) !== range(0, count($rawTask) - 1);
                if ($isAssoc) {
                    $id = $rawTask['id']
                        ?? $rawTask['name']
                        ?? $rawTask['title']
                        ?? $rawTask['tarea']
                        ?? null;
                    $title = $rawTask['title']
                        ?? $rawTask['name']
                        ?? $rawTask['tarea']
                        ?? $rawTask['text']
                        ?? null;
                    $desc = $rawTask['description']
                        ?? $rawTask['desc']
                        ?? $rawTask['descripcion']
                        ?? $rawTask['context']
                        ?? '';
                    $assigned = $rawTask['assigned']
                        ?? $rawTask['assigned_to']
                        ?? $rawTask['owner']
                        ?? $rawTask['responsable']
                        ?? $rawTask['assignee']
                        ?? 'Sin asignar';
                    $start = $rawTask['start']
                        ?? $rawTask['start_date']
                        ?? $rawTask['fecha_inicio']
                        ?? $rawTask['dueDate']
                        ?? 'Sin asignar';
                    $end = $rawTask['end']
                        ?? $rawTask['due']
                        ?? $rawTask['due_date']
                        ?? $rawTask['fecha_fin']
                        ?? $rawTask['fecha_limite']
                        ?? 'Sin asignar';
                    $rawProgress = $rawTask['progress'] ?? $rawTask['progreso'] ?? null;
                    $progress = isset($rawProgress)
                        ? (is_numeric($rawProgress) ? ($rawProgress . '%') : $rawProgress)
                        : '0%';

                    $parsedFromId = null;
                    if ($title === null || trim((string)$title) === '') {
                        $idText = is_string($id) ? $id : '';
                        if ($idText !== '') {
                            $norm = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', $idText);
                            $idParts = array_map('trim', array_filter(explode(',', $norm), function($p){ return $p !== ''; }));
                            if (!empty($idParts)) { $parsedFromId = $this->parseTaskFromPartsForDb($idParts, $base); }
                        }
                    }

                    if ($parsedFromId) {
                        $name = $parsedFromId['name'] ?? ($id ?? 'Sin nombre');
                        $descFromId = $parsedFromId['description'] ?? '';
                        $finalDesc = is_string($desc) && trim($desc) !== '' ? $desc : $descFromId;
                    } else {
                        $baseId = $id !== null ? rtrim(trim((string)$id), ",;:") : '';
                        if ($title !== null && trim((string)$title) !== '') {
                            $name = trim(($baseId !== '' ? $baseId . ', ' : '') . $title);
                        } else {
                            $name = $baseId !== '' ? $baseId : 'Sin nombre';
                        }
                        $finalDesc = is_string($desc) ? $desc : (is_array($desc) ? implode(', ', $desc) : strval($desc));
                    }

                    $res = $base;
                    $res['name'] = (string) $name;
                    $res['description'] = (string) $finalDesc;
                    $res['assigned'] = (string) ($assigned ?: 'Sin asignar');
                    $res['start'] = (string) ($start ?: 'Sin asignar');
                    $res['end'] = (string) ($end ?: 'Sin asignar');
                    $res['progress'] = (string) ($progress ?: '0%');
                    return $res;
                } else {
                    $parts = array_values(array_map('trim', array_filter($rawTask, function($p){ return $p !== null && $p !== ''; })));
                    return $this->parseTaskFromPartsForDb($parts, $base);
                }
            }

            $text = is_string($rawTask) ? $rawTask : strval($rawTask);
            $text = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', $text);
            $parts = array_map('trim', array_filter(explode(',', $text), function($p){ return $p !== ''; }));
            if (empty($parts)) {
                $base['description'] = $text; return $base;
            }
            return $this->parseTaskFromPartsForDb($parts, $base);
        };

        $t = $parse($raw);

        // Improve name parsing when it's like: "task 004: ..." or "tarea 12 - ..."
        if (is_string($t['name']) && is_string($t['description'])) {
            $nameLower = mb_strtolower(trim($t['name']), 'UTF-8');
            if ($nameLower === 'task' || $nameLower === 'tarea') {
                if (preg_match('/^\s*(\d{1,6})(?:\s*[:\-–]\s*)?(.*)$/u', trim($t['description']), $mFix)) {
                    $t['name'] = trim($t['name']) . '_' . $mFix[1];
                    $t['description'] = trim($mFix[2]);
                }
            }
        }
        // Clean description similar to PDF
        $cleanDesc = function($desc) {
            if (!is_string($desc) || trim($desc) === '') return $desc;
            $s = ' ' . $desc . ' ';
            $s = preg_replace('/[,\s]+(no asignado|sin asignar)[\s,\.]+/iu', ', ', $s);
            // Remove ISO dates and dd/mm/yyyy dates in description
            $s = preg_replace('/[,\s]+\d{4}-\d{2}-\d{2}[\s,\.]+/', ', ', $s);
            $s = preg_replace('/[,\s]+\d{2}\/\d{2}\/\d{4}[\s,\.]+/', ', ', $s);
            $s = preg_replace('/[,\s]+[^,]{1,80}?,\s*\d{4}-\d{2}-\d{2}[\s,\.]+/u', ', ', $s);
            $s = preg_replace('/[,\s]+[^,]{1,80}?,\s*\d{2}\/\d{2}\/\d{4}[\s,\.]+/u', ', ', $s);
            $s = preg_replace('/\s*,\s*,+/', ', ', $s);
            $s = preg_replace('/\s{2,}/', ' ', $s);
            $s = preg_replace('/\s*,\s*$/', '', trim($s));
            $s = preg_replace('/^,\s*/', '', $s);
            return trim($s);
        };

        if (is_string($t['description']) && trim($t['description']) !== '') {
            $descTmp = ' ' . $t['description'] . ' ';
            if (($t['assigned'] === 'Sin asignar' || $t['assigned'] === '' || $t['assigned'] === null) &&
                preg_match('/,\s*([^,\n]{2,80}?)\s*,\s*(\d{4}-\d{2}-\d{2})\s*,/u', $descTmp, $m)) {
                $candidate = trim($m[1]);
                if (mb_strtolower($candidate, 'UTF-8') !== 'no asignado' && mb_strtolower($candidate, 'UTF-8') !== 'sin asignar') {
                    $t['assigned'] = $candidate;
                }
                if ($t['start'] === 'Sin asignar' || empty($t['start'])) { $t['start'] = $m[2]; }
                $descTmp = preg_replace('/,\s*' . preg_quote($m[1], '/') . '\s*,\s*' . preg_quote($m[2], '/') . '\s*,/u', ', ', $descTmp, 1);
            } else if (($t['start'] === 'Sin asignar' || empty($t['start'])) &&
                preg_match('/,\s*(no asignado|sin asignar)\s*,\s*(\d{4}-\d{2}-\d{2})\s*,/iu', $descTmp, $m2)) {
                $t['start'] = $m2[2];
                $descTmp = preg_replace('/,\s*' . $m2[1] . '\s*,\s*' . preg_quote($m2[2], '/') . '\s*,/iu', ', ', $descTmp, 1);
            }
            $t['description'] = trim($descTmp);
        }
        $t['description'] = $cleanDesc($t['description']);

        // Extract optional priority and time from raw or description
        $priority = null; $timeStr = null;
        if (is_array($raw)) {
            $priorityRaw = $raw['prioridad'] ?? $raw['priority'] ?? $raw['nivel'] ?? $raw['importance'] ?? null;
            if (is_string($priorityRaw)) {
                $pl = mb_strtolower(trim($priorityRaw), 'UTF-8');
                if (in_array($pl, ['alta','high','alta prioridad','muy alta'], true)) $priority = 'alta';
                elseif (in_array($pl, ['media','medium','intermedia'], true)) $priority = 'media';
                elseif (in_array($pl, ['baja','low','muy baja'], true)) $priority = 'baja';
            } elseif (is_numeric($priorityRaw)) {
                $n = intval($priorityRaw);
                $priority = $n >= 3 ? 'alta' : ($n == 2 ? 'media' : 'baja');
            }

            $timeRaw = $raw['hora'] ?? $raw['hora_limite'] ?? $raw['time'] ?? $raw['due_time'] ?? $raw['hour'] ?? null;
            if (is_string($timeRaw)) { $timeStr = trim($timeRaw); }
        }
        // Try to find time within start/end or description when not provided
        $scanText = '';
        if (!$timeStr) {
            if (is_string($t['end'])) { $scanText .= ' ' . $t['end']; }
            if (is_string($t['description'])) { $scanText .= ' ' . $t['description']; }
            if (preg_match('/\b(\d{1,2}):(\d{2})(?::\d{2})?\s*([ap]m)?\b/i', $scanText, $tm)) {
                $hh = intval($tm[1]); $mm = intval($tm[2]); $ampm = isset($tm[3]) ? strtolower($tm[3]) : '';
                if ($ampm === 'pm' && $hh < 12) $hh += 12; if ($ampm === 'am' && $hh == 12) $hh = 0;
                $timeStr = sprintf('%02d:%02d', max(0, min(23, $hh)), max(0, min(59, $mm)));
            }
        }

        // Also try to infer priority from description text
        if ($priority === null && is_string($t['description']) && $t['description'] !== '') {
            $dl = mb_strtolower($t['description'], 'UTF-8');
            if (preg_match('/\b(prioridad|priority)\s*(alta|high)\b/u', $dl)) $priority = 'alta';
            elseif (preg_match('/\b(prioridad|priority)\s*(media|medium)\b/u', $dl)) $priority = 'media';
            elseif (preg_match('/\b(prioridad|priority)\s*(baja|low)\b/u', $dl)) $priority = 'baja';
        }

        // Map to DB fields
        $progressInt = 0;
        if (is_string($t['progress']) && preg_match('/^(\d{1,3})%$/', trim($t['progress']), $pm)) {
            $progressInt = max(0, min(100, intval($pm[1])));
        } elseif (is_numeric($t['progress'])) {
            $progressInt = max(0, min(100, intval($t['progress'])));
        }

        // Normalize possible dd/mm/yyyy for start/end
        $start = $t['start']; $end = $t['end'];
        if (is_string($start) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $start, $dm)) {
            $start = $dm[3] . '-' . $dm[2] . '-' . $dm[1];
        }
        if (is_string($end) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $end, $dm2)) {
            $end = $dm2[3] . '-' . $dm2[2] . '-' . $dm2[1];
        }

        return [
            'tarea' => rtrim(trim((string)$t['name']), ',;:'),
            'descripcion' => is_string($t['description']) ? trim($t['description']) : '',
            'fecha_inicio' => (is_string($start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) ? $start : null,
            'fecha_limite' => (is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) ? $end : null,
            'hora_limite' => (is_string($timeStr) && preg_match('/^\d{2}:\d{2}$/', $timeStr)) ? $timeStr : null,
            'prioridad' => $priority,
            'asignado' => (string)($t['assigned'] ?? ''),
            'progreso' => $progressInt,
        ];
    }
}
