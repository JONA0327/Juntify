<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MeetingDownloadController extends Controller
{
    /**
     * Descargar archivo de reunión (.ju o audio)
     * Juntify maneja el token y descarga desde Google Drive
     *
     * @param Request $request
     * @param int $meetingId
     * @param string $fileType - 'transcript' o 'audio'
     * @return JsonResponse
     */
    public function downloadFile(Request $request, int $meetingId, string $fileType): JsonResponse
    {
        Log::info('MeetingDownloadController::downloadFile called', [
            'meeting_id' => $meetingId,
            'file_type' => $fileType,
            'username' => $request->query('username'),
            'format' => $request->query('format')
        ]);
        
        try {
            // Validar file_type
            if (!in_array($fileType, ['transcript', 'audio', 'both'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de archivo inválido. Usar: transcript, audio o both',
                    'file_type' => $fileType
                ], 400);
            }

            // Obtener username del query
            $username = $request->query('username');
            if (!$username) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro username es requerido'
                ], 400);
            }

            // Buscar la reunión
            $meeting = DB::table('transcriptions_laravel')
                ->where('id', $meetingId)
                ->where('username', $username) // Verificar que pertenece al usuario
                ->first();

            Log::info('Meeting lookup result', [
                'meeting' => $meeting ? 'found' : 'not found',
                'meeting_id' => $meetingId,
                'username' => $username
            ]);

            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión no encontrada o no pertenece al usuario',
                    'meeting_id' => $meetingId,
                    'username' => $username
                ], 404);
            }

            // Obtener el Google Drive ID según el tipo
            $transcriptDriveId = $meeting->transcript_drive_id;
            $audioDriveId = $meeting->audio_drive_id;

            // Validar que los archivos existan según el tipo solicitado
            if ($fileType === 'transcript' && !$transcriptDriveId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo de transcripción no disponible',
                    'file_type' => $fileType
                ], 404);
            }

            if ($fileType === 'audio' && !$audioDriveId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo de audio no disponible',
                    'file_type' => $fileType
                ], 404);
            }

            if ($fileType === 'both' && (!$transcriptDriveId || !$audioDriveId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Uno o más archivos no disponibles',
                    'file_type' => $fileType,
                    'available' => [
                        'transcript' => (bool) $transcriptDriveId,
                        'audio' => (bool) $audioDriveId
                    ]
                ], 404);
            }

            // Determinar formato de respuesta
            $format = $request->query('format', 'base64');

            // Si solo quiere las URLs, retornarlas sin validar token
            if ($format === 'url') {
                if ($fileType === 'both') {
                    return $this->returnBothDownloadUrls($transcriptDriveId, $audioDriveId, $meeting);
                }
                
                $driveId = $fileType === 'transcript' ? $transcriptDriveId : $audioDriveId;
                return $this->returnDownloadUrl($driveId, $meeting, $fileType);
            }

            // Para formatos base64 y stream con 'both', no soportamos stream
            if ($format === 'stream' && $fileType === 'both') {
                return response()->json([
                    'success' => false,
                    'message' => 'El formato stream no soporta descarga de múltiples archivos. Use format=base64 o format=url',
                    'file_type' => $fileType
                ], 400);
            }

            // Para formatos base64 y stream, necesitamos el Google Token
            // Buscar el Google Token del usuario en juntify_new (base de datos principal)
            $googleToken = DB::table('google_tokens')
                ->where('username', $username)
                ->first();

            Log::info('Google token lookup result', [
                'token' => $googleToken ? 'found' : 'not found',
                'username' => $username
            ]);

            if (!$googleToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Token no encontrado para el usuario',
                    'username' => $username,
                    'suggestion' => 'El usuario debe conectar su cuenta de Google Drive'
                ], 404);
            }

            // Verificar si el token está expirado y refrescarlo si es necesario
            $accessToken = $googleToken->access_token;
            
            if ($this->isTokenExpired($googleToken)) {
                $accessToken = $this->refreshGoogleToken($googleToken);
                
                if (!$accessToken) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al refrescar el token de Google',
                        'suggestion' => 'El usuario debe reconectar su cuenta de Google Drive'
                    ], 401);
                }
            }

            // Descargar archivo(s) desde Google Drive
            if ($fileType === 'both') {
                return $this->downloadBothFiles($transcriptDriveId, $audioDriveId, $accessToken, $meeting, $meetingId, $format);
            }

            $driveId = $fileType === 'transcript' ? $transcriptDriveId : $audioDriveId;
            $fileContent = $this->downloadFromGoogleDrive($driveId, $accessToken);

            if (!$fileContent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al descargar archivo desde Google Drive',
                    'drive_id' => $driveId
                ], 500);
            }

            if ($format === 'stream') {
                // Retornar archivo como stream
                return response($fileContent)
                    ->header('Content-Type', 'application/octet-stream')
                    ->header('Content-Disposition', 'attachment; filename="' . $this->getFileName($meeting, $fileType) . '"');
            }

            // Retornar archivo en base64 (default)
            return response()->json([
                'success' => true,
                'meeting_id' => $meetingId,
                'file_type' => $fileType,
                'file_name' => $this->getFileName($meeting, $fileType),
                'file_size_bytes' => strlen($fileContent),
                'file_size_mb' => round(strlen($fileContent) / 1048576, 2),
                'mime_type' => $this->getMimeType($fileType),
                'file_content' => base64_encode($fileContent),
                'encoding' => 'base64',
                'downloaded_at' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al descargar archivo de reunión', [
                'meeting_id' => $meetingId,
                'file_type' => $fileType,
                'username' => $username ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la descarga',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si el token está expirado
     */
    protected function isTokenExpired($googleToken): bool
    {
        if (!$googleToken->expiry_date) {
            return true;
        }

        // expiry_date es un DATETIME string en formato MySQL
        try {
            $expiryDate = Carbon::parse($googleToken->expiry_date);
            return now()->greaterThan($expiryDate);
        } catch (\Exception $e) {
            Log::warning('Error al parsear expiry_date', [
                'expiry_date' => $googleToken->expiry_date,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    /**
     * Refrescar el token de Google
     */
    protected function refreshGoogleToken($googleToken): ?string
    {
        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $googleToken->refresh_token,
                'grant_type' => 'refresh_token'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $newAccessToken = $data['access_token'];
                $expiresIn = $data['expires_in'] ?? 3600;

                // Calcular nuevo expiry_date como DATETIME
                $newExpiryDate = now()->addSeconds($expiresIn);

                // Actualizar token en la base de datos
                DB::table('google_tokens')
                    ->where('id', $googleToken->id)
                    ->update([
                        'access_token' => $newAccessToken,
                        'expiry_date' => $newExpiryDate,
                        'expires_in' => $expiresIn,
                        'updated_at' => now()
                    ]);

                Log::info('Token de Google refrescado exitosamente', [
                    'token_id' => $googleToken->id,
                    'username' => $googleToken->username
                ]);

                return $newAccessToken;
            }

            Log::error('Error al refrescar token de Google', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Excepción al refrescar token de Google', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Descargar archivo desde Google Drive
     */
    protected function downloadFromGoogleDrive(string $driveId, string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(120) // 2 minutos timeout para archivos grandes
                ->get("https://www.googleapis.com/drive/v3/files/{$driveId}", [
                    'alt' => 'media'
                ]);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('Error al descargar desde Google Drive', [
                'drive_id' => $driveId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Excepción al descargar desde Google Drive', [
                'drive_id' => $driveId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Retornar URLs de descarga para ambos archivos
     */
    protected function returnBothDownloadUrls(string $transcriptDriveId, string $audioDriveId, $meeting): JsonResponse
    {
        return response()->json([
            'success' => true,
            'meeting_id' => $meeting->id,
            'file_type' => 'both',
            'transcript' => [
                'file_name' => $this->getFileName($meeting, 'transcript'),
                'download_url' => "https://drive.google.com/uc?export=download&id={$transcriptDriveId}",
                'drive_id' => $transcriptDriveId
            ],
            'audio' => [
                'file_name' => $this->getFileName($meeting, 'audio'),
                'download_url' => "https://drive.google.com/uc?export=download&id={$audioDriveId}",
                'drive_id' => $audioDriveId
            ],
            'note' => 'URLs requieren acceso a Google Drive del usuario'
        ]);
    }

    /**
     * Descargar ambos archivos desde Google Drive
     */
    protected function downloadBothFiles(string $transcriptDriveId, string $audioDriveId, string $accessToken, $meeting, int $meetingId, string $format): JsonResponse
    {
        // Descargar transcripción
        $transcriptContent = $this->downloadFromGoogleDrive($transcriptDriveId, $accessToken);
        if (!$transcriptContent) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar transcripción desde Google Drive',
                'drive_id' => $transcriptDriveId
            ], 500);
        }

        // Descargar audio
        $audioContent = $this->downloadFromGoogleDrive($audioDriveId, $accessToken);
        if (!$audioContent) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar audio desde Google Drive',
                'drive_id' => $audioDriveId
            ], 500);
        }

        // Retornar ambos archivos en base64
        return response()->json([
            'success' => true,
            'meeting_id' => $meetingId,
            'file_type' => 'both',
            'transcript' => [
                'file_name' => $this->getFileName($meeting, 'transcript'),
                'file_size_bytes' => strlen($transcriptContent),
                'file_size_mb' => round(strlen($transcriptContent) / 1048576, 2),
                'mime_type' => $this->getMimeType('transcript'),
                'file_content' => base64_encode($transcriptContent),
                'encoding' => 'base64'
            ],
            'audio' => [
                'file_name' => $this->getFileName($meeting, 'audio'),
                'file_size_bytes' => strlen($audioContent),
                'file_size_mb' => round(strlen($audioContent) / 1048576, 2),
                'mime_type' => $this->getMimeType('audio'),
                'file_content' => base64_encode($audioContent),
                'encoding' => 'base64'
            ],
            'total_size_mb' => round((strlen($transcriptContent) + strlen($audioContent)) / 1048576, 2),
            'downloaded_at' => now()->toIso8601String()
        ]);
    }

    /**
     * Retornar URL de descarga directa
     */
    protected function returnDownloadUrl(string $driveId, $meeting, string $fileType): JsonResponse
    {
        $downloadUrl = "https://drive.google.com/uc?export=download&id={$driveId}";

        return response()->json([
            'success' => true,
            'meeting_id' => $meeting->id,
            'file_type' => $fileType,
            'file_name' => $this->getFileName($meeting, $fileType),
            'download_url' => $downloadUrl,
            'drive_id' => $driveId,
            'note' => 'URL requiere acceso a Google Drive del usuario'
        ]);
    }

    /**
     * Obtener nombre de archivo
     */
    protected function getFileName($meeting, string $fileType): string
    {
        $name = str_replace([' ', '/', '\\'], '_', $meeting->meeting_name);
        $extension = $fileType === 'transcript' ? 'ju' : 'mp3';
        
        return "{$name}_{$meeting->id}.{$extension}";
    }

    /**
     * Obtener MIME type
     */
    protected function getMimeType(string $fileType): string
    {
        return $fileType === 'transcript' 
            ? 'application/octet-stream' 
            : 'audio/mpeg';
    }
}
