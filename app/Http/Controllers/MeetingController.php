<?php

namespace App\Http\Controllers;

use App\Models\TranscriptionLaravel;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MeetingController extends Controller
{
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Muestra la página principal de reuniones.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        return view('reuniones');
    }

    /**
     * Obtiene todas las reuniones del usuario autenticado
     */
    public function getMeetings(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            $meetings = TranscriptionLaravel::where('username', $user->username)
                ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
            // Carpeta real desde Drive (subcarpeta o raíz)
            'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
            'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                    ];
                });

            return response()->json([
                'success' => true,
                'meetings' => $meetings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar reuniones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los detalles completos de una reunión específica
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            // Descargar el archivo .ju (transcripción)
            $transcriptContent = $this->downloadFromDrive($meeting->transcript_drive_id);
            $transcriptData = $this->decryptJuFile($transcriptContent);

            // Descargar el archivo de audio
            $audioContent = $this->downloadFromDrive($meeting->audio_drive_id);

            // Generar nombre de archivo temporal basado en el nombre de la reunión
            $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meeting->meeting_name);
            $audioFileName = $sanitizedName . '_' . $id . '.mp3';
            $audioPath = $this->storeTemporaryFile($audioContent, $audioFileName);

            // Procesar los datos de la transcripción
            $processedData = $this->processTranscriptData($transcriptData);

            return response()->json([
                'success' => true,
                'meeting' => [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                    'audio_path' => $audioPath,
                    'summary' => $processedData['summary'],
                    'key_points' => $processedData['key_points'],
                    'tasks' => $processedData['tasks'],
                    'transcription' => $processedData['transcription'],
                    'speakers' => $processedData['speakers'] ?? [],
                    'segments' => $processedData['segments'] ?? [],
                    // Carpeta real desde Drive
                    'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                    'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la reunión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza el nombre de una reunión
     */
    public function updateName(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'meeting_name' => 'required|string|max:255'
            ]);

            $user = Auth::user();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            $meeting->meeting_name = $request->meeting_name;
            $meeting->save();

            return response()->json([
                'success' => true,
                'message' => 'Nombre actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el nombre: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina una reunión
     */
    public function delete($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            // Eliminar archivos de Google Drive
            try {
                if ($meeting->transcript_drive_id) {
                    $this->googleDriveService->deleteFile($meeting->transcript_drive_id);
                }
            } catch (\Exception $e) {
                Log::warning('Error al eliminar archivo de transcripción de Drive', [
                    'file_id' => $meeting->transcript_drive_id,
                    'error' => $e->getMessage()
                ]);
            }

            try {
                if ($meeting->audio_drive_id) {
                    $this->googleDriveService->deleteFile($meeting->audio_drive_id);
                }
            } catch (\Exception $e) {
                Log::warning('Error al eliminar archivo de audio de Drive', [
                    'file_id' => $meeting->audio_drive_id,
                    'error' => $e->getMessage()
                ]);
            }

            // Eliminar registro de la base de datos
            $meeting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reunión eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la reunión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configura el token de Google Drive para el usuario
     */
    private function setGoogleDriveToken($user)
    {
        $googleToken = $user->googleToken;
        if (!$googleToken) {
            throw new \Exception('No se encontró token de Google para el usuario');
        }

        Log::info('setGoogleDriveToken: Setting token', [
            'username' => $user->username,
            'has_access_token' => !empty($googleToken->access_token),
            'has_refresh_token' => !empty($googleToken->refresh_token),
            'expiry_date' => $googleToken->expiry_date
        ]);

        $this->googleDriveService->setAccessToken($googleToken->access_token);

        // Verificar si Google Client marca el token como expirado
        if ($this->googleDriveService->getClient()->isAccessTokenExpired()) {
            Log::info('setGoogleDriveToken: Google Client says token is expired, refreshing');
            try {
                $newTokens = $this->googleDriveService->refreshToken($googleToken->refresh_token);
                $googleToken->update([
                    'access_token' => $newTokens['access_token'],
                    // Guardar como datetime en BD
                    'expiry_date' => now()->addSeconds($newTokens['expires_in'] ?? 3600)
                ]);
                Log::info('setGoogleDriveToken: Token refreshed successfully', [
                    'new_expiry' => now()->addSeconds($newTokens['expires_in'] ?? 3600)
                ]);
            } catch (\Exception $e) {
                Log::error('setGoogleDriveToken: Error refreshing token', [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
    }    private function downloadFromDrive($fileId)
    {
        return $this->googleDriveService->downloadFileContent($fileId);
    }

    private function decryptJuFile($content): array
    {
        try {
            Log::info('decryptJuFile: Starting decryption', [
                'length' => strlen($content),
                'first_50' => substr($content, 0, 50)
            ]);

            // Primer intento: ver si el contenido ya es JSON válido (sin encriptar)
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                Log::info('decryptJuFile: Content is already valid JSON (unencrypted)');
                return $this->extractMeetingDataFromJson($json_data);
            }

            // Segundo intento: ver si es un string encriptado directo de Laravel Crypt
            if (substr($content, 0, 3) === 'eyJ') {
                Log::info('decryptJuFile: Attempting to decrypt Laravel Crypt format');
                try {
                    // Intentar desencriptar directamente el string base64
                    $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($content);
                    Log::info('decryptJuFile: Direct decryption successful');

                    $json_data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('decryptJuFile: JSON parsing after decryption successful', ['keys' => array_keys($json_data)]);
                        return $this->extractMeetingDataFromJson($json_data);
                    }
                } catch (\Exception $e) {
                    Log::warning('decryptJuFile: Direct decryption failed', ['error' => $e->getMessage()]);
                }
            }

            // Tercer intento: ver si el contenido contiene el formato {"iv":"...","value":"..."} de Laravel
            if (str_contains($content, '"iv"') && str_contains($content, '"value"')) {
                Log::info('decryptJuFile: Detected Laravel Crypt JSON format');
                try {
                    // El contenido ya contiene el formato JSON de Laravel Crypt, desencriptar directamente
                    $decrypted = \Illuminate\Support\Facades\Crypt::decrypt($content);
                    Log::info('decryptJuFile: Laravel Crypt JSON decryption successful');

                    $json_data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('decryptJuFile: JSON parsing after Laravel Crypt decryption successful', ['keys' => array_keys($json_data)]);
                        return $this->extractMeetingDataFromJson($json_data);
                    }
                } catch (\Exception $e) {
                    Log::error('decryptJuFile: Laravel Crypt JSON decryption failed', ['error' => $e->getMessage()]);
                }
            }

            // Fallback: usar datos por defecto
            Log::warning('decryptJuFile: Using default data - all decryption methods failed');
            return $this->getDefaultMeetingData();

        } catch (\Exception $e) {
            Log::error('decryptJuFile: General exception', ['error' => $e->getMessage()]);
            return $this->getDefaultMeetingData();
        }
    }

    private function getDefaultMeetingData(): array
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

    private function extractMeetingDataFromJson($data): array
    {
        // Intentar extraer datos de diferentes estructuras JSON posibles
        return [
            'summary' => $data['summary'] ?? $data['resumen'] ?? $data['meeting_summary'] ?? 'Resumen no disponible',
            'key_points' => $data['key_points'] ?? $data['keyPoints'] ?? $data['puntos_clave'] ?? $data['main_points'] ?? [],
            'tasks' => $data['tasks'] ?? $data['tareas'] ?? $data['action_items'] ?? [],
            'transcription' => $data['transcription'] ?? $data['transcripcion'] ?? $data['text'] ?? 'Transcripción no disponible',
            'speakers' => $data['speakers'] ?? $data['participantes'] ?? [],
            'segments' => $data['segments'] ?? $data['segmentos'] ?? []
        ];
    }

    private function processTranscriptData($data): array
    {
        return [
            'summary' => $data['summary'] ?? 'No hay resumen disponible',
            'key_points' => $data['key_points'] ?? [],
            'tasks' => $data['tasks'] ?? [],
            'transcription' => $data['transcription'] ?? 'No hay transcripción disponible',
            'speakers' => $data['speakers'] ?? [],
            'segments' => $data['segments'] ?? []
        ];
    }

    private function storeTemporaryFile($content, $filename): string
    {
        $path = 'temp/' . $filename;
        Storage::disk('public')->put($path, $content);
        return asset('storage/' . $path);
    }

    private function getFolderName($fileId): string
    {
        try {
            if (empty($fileId)) {
                return 'Sin especificar';
            }

            // Obtener información del archivo para saber en qué carpeta está
            $file = $this->googleDriveService->getFileInfo($fileId);

            if ($file->getParents()) {
                $parentId = $file->getParents()[0];
                $parent = $this->googleDriveService->getFileInfo($parentId);
                return $parent->getName() ?: 'Carpeta sin nombre';
            }

            return 'Carpeta raíz';
        } catch (\Exception $e) {
            Log::warning('getFolderName: Error getting folder name (first attempt)', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);

            // Si es problema de API Key o autenticación, devolver el nombre por defecto basado en la estructura conocida
            if (str_contains($e->getMessage(), 'API key') ||
                str_contains($e->getMessage(), 'PERMISSION_DENIED') ||
                str_contains($e->getMessage(), 'unauthorized') ||
                str_contains($e->getMessage(), 'forbidden')) {

                // Basado en la estructura que descubrimos: Juntify Recordings → Transcripciones/Audios
                // Como el error indica problemas de autenticación, devolvemos nombres genéricos útiles
                return 'Juntify Recordings'; // Nombre genérico ya que sabemos la estructura
            }

            return 'Error al obtener carpeta';
        }
    }

    /**
     * Obtiene el nombre de la carpeta de grabaciones desde la BD
     */
    private function getRecordingsFolderName(string $username): string
    {
        try {
            // Buscar token del usuario para obtener recordings_folder_id
            $token = GoogleToken::where('username', $username)->first();
            if (!$token || empty($token->recordings_folder_id)) {
                return 'Grabaciones';
            }

            // Buscar en la tabla folders el nombre de esa carpeta
            $folder = Folder::where('google_id', $token->recordings_folder_id)->first();
            if ($folder && !empty($folder->name)) {
                return $folder->name;
            }

            return 'Grabaciones';
        } catch (\Exception $e) {
            Log::warning('getRecordingsFolderName: error fetching folder name', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return 'Grabaciones';
        }
    }

    /**
     * Limpia archivos temporales del modal
     */
    public function cleanupModal(): JsonResponse
    {
        try {
            // Limpiar archivos temporales más antiguos de 1 hora
            $tempPath = storage_path('app/public/temp');
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                $oneHourAgo = time() - 3600;

                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $oneHourAgo) {
                        unlink($file);
                    }
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('cleanupModal error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Descarga el archivo .ju de una reunión
     */
    public function downloadJuFile($id)
    {
        try {
            $user = Auth::user();
            $this->setGoogleDriveToken($user);

            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            if (empty($meeting->transcript_drive_id)) {
                return response()->json(['error' => 'No se encontró archivo .ju para esta reunión'], 404);
            }

            // Descargar el contenido del archivo
            $content = $this->googleDriveService->downloadFileContent($meeting->transcript_drive_id);

            // Crear nombre de archivo
            $filename = 'meeting_' . $meeting->id . '.ju';

            return response($content)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('Error downloading .ju file', [
                'meeting_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al descargar archivo .ju'], 500);
        }
    }

    /**
     * Descarga el archivo de audio de una reunión
     */
    public function downloadAudioFile($id)
    {
        try {
            $user = Auth::user();
            $this->setGoogleDriveToken($user);

            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            if (empty($meeting->audio_drive_id)) {
                return response()->json(['error' => 'No se encontró archivo de audio para esta reunión'], 404);
            }

            // Descargar el contenido del archivo
            $content = $this->googleDriveService->downloadFileContent($meeting->audio_drive_id);

            // Crear nombre de archivo (asumiendo que es mp3, pero podría ser otro formato)
            $filename = 'meeting_' . $meeting->id . '_audio.mp3';

            return response($content)
                ->header('Content-Type', 'audio/mpeg')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('Error downloading audio file', [
                'meeting_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al descargar archivo de audio'], 500);
        }
    }

    private function updateDriveFileName($fileId, $newName)
    {
        return $this->googleDriveService->updateFileName($fileId, $newName);
    }
}
