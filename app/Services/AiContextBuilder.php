<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Support\Facades\Log;

/**
 * Builds AI context on-demand. Extracts text for documents using ExtractorService
 * and converts images to base64 attachments.
 */
class AiContextBuilder
{
    protected ExtractorService $extractor;
    protected GoogleDriveService $driveService;

    public function __construct(ExtractorService $extractor, GoogleDriveService $driveService)
    {
        $this->extractor = $extractor;
        $this->driveService = $driveService;
    }

    /**
     * Build context for a session: returns ['date'=>..., 'fragments'=>[], 'attachments'=>[]]
     */
    public function build(User $user, AiChatSession $session, array $options = []): array
    {
        $out = [
            'date' => now()->toDateTimeString(),
            'fragments' => [],
            'attachments' => [],
        ];

        $contextData = is_array($session->context_data) ? $session->context_data : (array) $session->context_data;
        $docIds = Arr::get($contextData, 'doc_ids', []);
        if (!is_array($docIds)) { $docIds = []; }

        foreach ($docIds as $docId) {
            try {
                $doc = AiDocument::find($docId);
                if (! $doc) { continue; }

                $mime = (string) ($doc->mime_type ?? 'application/octet-stream');

                // If image, convert to base64 and add to attachments (no OCR)
                if (Str::startsWith($mime, 'image/') || ($doc->document_type === 'image')) {
                    $local = Arr::get($doc->document_metadata ?? [], 'local_path');
                    $data = null;
                    if ($local && Storage::disk('local')->exists($local)) {
                        $full = Storage::disk('local')->path($local);
                        $contents = @file_get_contents($full);
                        if ($contents !== false) {
                            $b64 = base64_encode($contents);
                            $data = 'data:' . $mime . ';base64,' . $b64;
                        }
                    }

                    // Fallback: try downloading from Drive
                    if ($data === null && !empty($doc->drive_file_id)) {
                        try {
                            $contents = $this->driveService->downloadFileContent($doc->drive_file_id);
                            if (!empty($contents)) {
                                $data = 'data:' . $mime . ';base64,' . base64_encode($contents);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('AiContextBuilder: failed to download image from Drive', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
                        }
                    }

                    if ($data) {
                        $out['attachments'][] = [
                            'type' => 'image',
                            'data' => $data,
                            'document_id' => $doc->id,
                            'filename' => $doc->original_filename,
                        ];
                    }

                    continue;
                }

                // Text/Office/PDF: try local path first, then Drive
                $local = Arr::get($doc->document_metadata ?? [], 'local_path');
                $extracted = null;
                if ($local && Storage::disk('local')->exists($local)) {
                    $full = Storage::disk('local')->path($local);
                    $extracted = $this->extractor->extract($full, $mime, $doc->original_filename);
                } else {
                    if (!empty($doc->drive_file_id)) {
                        try {
                            $contents = $this->driveService->downloadFileContent($doc->drive_file_id);
                            if (!empty($contents)) {
                                // write to temp file to allow extractor handlers to run
                                $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_doc_' . uniqid() . '_' . basename($doc->original_filename ?? 'doc');
                                @file_put_contents($tmp, $contents);
                                $extracted = $this->extractor->extract($tmp, $mime, $doc->original_filename);
                                @unlink($tmp);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('AiContextBuilder: failed to download/parse drive document', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
                        }
                    }
                }

                if ($extracted && isset($extracted['text'])) {
                    $out['fragments'][] = [
                        'text' => Str::limit($extracted['text'], 100000),
                        'citation' => 'doc:' . $doc->id,
                        'location' => [
                            'document_id' => $doc->id,
                            'title' => $doc->name,
                        ],
                        'metadata' => $extracted['metadata'] ?? [],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('AiContextBuilder: error processing doc for context', ['doc_id' => $docId, 'error' => $e->getMessage()]);
            }
        }

        // Optionally include meeting .ju transcriptions via MeetingJuCacheService
        if (($session->context_type ?? '') === 'meeting' && !empty($session->context_id)) {
            try {
                $mj = app(MeetingJuCacheService::class);
                $ju = $mj->getTranscriptionForMeeting($session->context_id);
                if ($ju && is_string($ju) && trim($ju) !== '') {
                    $out['fragments'][] = [
                        'text' => Str::limit($ju, 100000),
                        'citation' => 'meeting:' . $session->context_id,
                        'location' => ['meeting_id' => $session->context_id],
                    ];
                }
            } catch (\Throwable $e) {
                // ignore if service not available
            }
        }

        return $out;
    }
}
