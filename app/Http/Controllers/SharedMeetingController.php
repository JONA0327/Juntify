<?php

namespace App\Http\Controllers;

use App\Models\SharedMeeting;
use App\Models\Meeting;
use App\Models\TranscriptionLaravel;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;

class SharedMeetingController extends Controller
{
    public function resolveDriveLinks(Request $request): JsonResponse
    {
        $request->validate([
            'shared_meeting_id' => 'required|exists:shared_meetings,id'
        ]);

        $user = Auth::user();
        $shared = SharedMeeting::with(['meeting','sharedBy'])
            ->where('id', $request->shared_meeting_id)
            ->where('shared_with', $user->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        $meetingId = $shared->meeting_id;
        $sharerEmail = $shared->sharedBy?->email;

        // Prefer user token; if missing, use SA impersonating sharer
        $drive = app(GoogleDriveService::class);
        $useServiceAccount = false;
        try {
            // We only need Drive API for link discovery if we must search; direct IDs can be normalized without token
            if (Auth::user()->googleToken) {
                $token = $user->googleToken;
                $drive->setAccessToken($token->access_token ? json_decode($token->access_token, true) ?: ['access_token'=>$token->access_token] : []);
            } else {
                throw new \RuntimeException('no_user_token');
            }
        } catch (\Throwable $e) {
            $useServiceAccount = true;
        }

        $juLink = null; $audioLink = null; $audioId = null;

        // Legacy
        $legacy = TranscriptionLaravel::find($meetingId);
        if ($legacy) {
            // .ju: if we have transcript_drive_id, convert to direct link
            if (!empty($legacy->transcript_drive_id)) {
                $fileId = $legacy->transcript_drive_id;
                $juLink = 'https://drive.google.com/uc?export=download&id=' . $fileId;
            }

            // Audio: prefer stored file id; else search inside folder id
            if (!empty($legacy->audio_drive_id)) {
                // If it's a folder id, we need to search for the matching audio file
                if ($useServiceAccount) {
                    /** @var GoogleServiceAccount $sa */
                    $sa = app(GoogleServiceAccount::class);
                    if ($sharerEmail) { $sa->impersonate($sharerEmail); }
                    try {
                        // Try to treat audio_drive_id as file id first
                        $audioId = $legacy->audio_drive_id;
                        $audioLink = $sa->getFileLink($audioId);
                    } catch (\Throwable $e) {
                        // Fallback: not a file-id, try to find inside folder using SA
                        try {
                            $resp = $sa->getDrive()->files->listFiles([
                                'q' => sprintf("'%s' in parents and trashed=false", $legacy->audio_drive_id),
                                'fields' => 'files(id,name,webContentLink)',
                                'supportsAllDrives' => true,
                            ]);
                            $files = $resp->getFiles();
                            foreach ($files as $file) {
                                $name = $file->getName();
                                if (preg_match('/^' . preg_quote($legacy->meeting_name, '/') . '/i', $name) || preg_match('/^' . preg_quote((string)$legacy->id, '/') . '/i', $name)) {
                                    $audioId = $file->getId();
                                    $audioLink = $file->getWebContentLink();
                                    break;
                                }
                            }
                        } catch (\Throwable $e2) { /* ignore */ }
                    }
                } else {
                    try {
                        // Try direct file id first
                        $audioId = $legacy->audio_drive_id;
                        $audioLink = $drive->getWebContentLink($audioId);
                        if (!$audioLink) {
                            // Search in folder by name/id
                            $found = $drive->findAudioInFolder(
                                $legacy->audio_drive_id,
                                $legacy->meeting_name,
                                (string)$legacy->id
                            );
                            if ($found) {
                                $audioId = $found['fileId'];
                                $audioLink = $found['downloadUrl'] ?? $drive->getWebContentLink($audioId) ?: $drive->getFileLink($audioId);
                            }
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                }
            }

            return response()->json([
                'success' => true,
                'meeting_id' => $meetingId,
                'is_legacy' => true,
                'ju_link' => $juLink,
                'audio_link' => $audioLink,
                'audio_file_id' => $audioId,
            ]);
        }

        // Modern meeting
        $modern = Meeting::findOrFail($meetingId);
        $folderId = $modern->recordings_folder_id;
        if ($folderId) {
            if ($useServiceAccount) {
                /** @var GoogleServiceAccount $sa */
                $sa = app(GoogleServiceAccount::class);
                if ($sharerEmail) { $sa->impersonate($sharerEmail); }
                try {
                    $resp = $sa->getDrive()->files->listFiles([
                        'q' => sprintf("'%s' in parents and trashed=false", $folderId),
                        'fields' => 'files(id,name,webContentLink)',
                        'supportsAllDrives' => true,
                    ]);
                    $files = $resp->getFiles();
                    foreach ($files as $file) {
                        $name = $file->getName();
                        if (preg_match('/^' . preg_quote($modern->title, '/') . '/i', $name) || preg_match('/^' . preg_quote((string)$modern->id, '/') . '/i', $name)) {
                            $audioId = $file->getId();
                            $audioLink = $file->getWebContentLink();
                            break;
                        }
                    }
                } catch (\Throwable $e) { /* ignore */ }
            } else {
                $found = $drive->findAudioInFolder($folderId, $modern->title, (string)$modern->id);
                if ($found) {
                    $audioId = $found['fileId'];
                    $audioLink = $found['downloadUrl'] ?? $drive->getWebContentLink($audioId) ?: $drive->getFileLink($audioId);
                } else {
                    $files = $drive->searchFiles($modern->title, $folderId);
                    $first = $files[0] ?? null;
                    if ($first) {
                        $audioId = $first->getId();
                        $audioLink = $drive->getWebContentLink($audioId) ?: $drive->getFileLink($audioId);
                    }
                }
            }
        }

        // No .ju in modern DB; summary/segments live in DB
        return response()->json([
            'success' => true,
            'meeting_id' => $meetingId,
            'is_legacy' => false,
            'ju_link' => null,
            'audio_link' => $audioLink,
            'audio_file_id' => $audioId,
        ]);
    }
    /**
     * Obtener contactos del usuario para compartir reunión
     */
    public function getContactsForSharing(): JsonResponse
    {
        try {
            $contacts = collect();

            if (Schema::hasTable('contacts')) {
                $contacts = Contact::where('user_id', Auth::id())
                    ->with(['contact' => function ($query) {
                        $query->select('id', 'full_name', 'username', 'email');
                    }])
                    ->get()
                    ->filter(function ($contact) {
                        return $contact->contact; // Solo incluir si el usuario contacto existe
                    })
                    ->map(function ($contact) {
                        return [
                            'id' => $contact->contact_id,
                            'name' => $contact->contact->full_name ?? $contact->contact->username ?? 'Usuario',
                            'email' => $contact->contact->email ?? '',
                            'avatar' => strtoupper(substr($contact->contact->full_name ?? $contact->contact->username ?? 'U', 0, 1))
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'contacts' => $contacts->values() // Asegurar que sea un array indexado numéricamente
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting contacts for sharing: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar contactos',
                'contacts' => []
            ], 500);
        }
    }

    /**
     * Compartir una reunión con contactos
     */
    public function shareMeeting(Request $request): JsonResponse
    {
        $request->validate([
            'meeting_id' => 'required',
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'exists:users,id',
            'message' => 'nullable|string|max:500',
            'meeting_title' => 'nullable|string|max:255', // título enviado por el front para validar colisiones
        ]);

        try {
            DB::beginTransaction();

            // Obtener contactos válidos asociados al usuario actual
            $validContactIds = Contact::where('user_id', Auth::id())
                ->whereIn('contact_id', $request->contact_ids)
                ->pluck('contact_id')
                ->toArray();

            // Cargar potencialmente ambos (puede haber colisión de IDs)
            $legacyMeeting = TranscriptionLaravel::find($request->meeting_id);
            $meeting = Meeting::find($request->meeting_id);

            if (!$legacyMeeting && !$meeting) {
                throw new \RuntimeException('Meeting not found');
            }

            $providedTitle = $request->meeting_title;
            $usingLegacy = false;

            if ($legacyMeeting && $meeting) {
                // Si hay colisión de ID, usamos heurística basada en el título proporcionado.
                if ($providedTitle) {
                    $p = mb_strtolower(trim($providedTitle));
                    $legacyName = mb_strtolower(trim($legacyMeeting->meeting_name ?? ''));
                    $modernName = mb_strtolower(trim($meeting->title ?? ''));

                    $matchesLegacy = $legacyName !== '' && $p === $legacyName;
                    $matchesModern = $modernName !== '' && $p === $modernName;

                    if ($matchesLegacy && !$matchesModern) {
                        $usingLegacy = true; // coincide sólo con legacy
                    } elseif (!$matchesLegacy && $matchesModern) {
                        $usingLegacy = false; // coincide sólo con modern
                    } elseif ($matchesLegacy && $matchesModern) {
                        // Coincide con ambos, preferimos legacy (mantener compatibilidad histórica)
                        $usingLegacy = true;
                    } else {
                        // No coincide con ninguno: fallback a modern para evitar devolver contenido legacy incorrecto
                        $usingLegacy = false;
                    }
                } else {
                    // Sin título proporcionado, mantenemos preferencia legacy para compatibilidad
                    $usingLegacy = true;
                }
            } else {
                // Sólo uno existe
                $usingLegacy = (bool)$legacyMeeting;
            }

            if ($usingLegacy) {
                $meetingTitle = $legacyMeeting->meeting_name ?? 'Reunión';
                $meeting = null; // aseguramos que el resto del flujo use legacyTitle
            } else {
                $meetingTitle = $meeting?->title ?? $legacyMeeting?->meeting_name ?? 'Reunión';
            }
            $sharedWith = [];

            foreach ($validContactIds as $contactId) {
                // Verificar si ya se compartió con este contacto
                $existingShare = SharedMeeting::where('meeting_id', $request->meeting_id)
                    ->where('shared_with', $contactId)
                    ->first();

                if ($existingShare) {
                    continue;
                }

                // Crear el registro de reunión compartida
                $sharedMeeting = SharedMeeting::create([
                    'meeting_id' => $request->meeting_id,
                    'shared_by' => Auth::id(),
                    'shared_with' => $contactId,
                    'message' => $request->message,
                    'status' => 'pending',
                    'shared_at' => now()
                ]);

                // Crear notificación usando el esquema moderno
                $currentUser = Auth::user();
                $currentUserName = $currentUser->full_name ?? $currentUser->username ?? 'Usuario';

                // Crear notificación con duplicación de campos para compatibilidad (user_id/emisor, from_user_id/remitente)
                $notification = Notification::create([
                    'user_id' => $contactId,
                    'emisor' => $contactId,
                    'from_user_id' => Auth::id(),
                    'remitente' => Auth::id(),
                    'type' => 'meeting_share_request',
                    'title' => 'Nueva reunión compartida',
                    'message' => $currentUserName . ' ha compartido la reunión "' . $meetingTitle . '" contigo.',
                    'data' => json_encode([
                        'meeting_id' => $meeting?->id ?? $legacyMeeting?->id,
                        'shared_meeting_id' => $sharedMeeting->id,
                        'meeting_title' => $meetingTitle,
                        'shared_by_name' => $currentUserName,
                        'custom_message' => $request->message
                    ]),
                    'read' => false,
                    'status' => 'pending'
                ]);

                // Invalidate notifications cache for recipient for immediate visibility
                try { Cache::forget('notifications_user_' . $contactId); } catch (\Throwable $e) { /* ignore */ }

                $contactUser = User::find($contactId);
                if ($contactUser) {
                    $sharedWith[] = $contactUser->full_name ?? $contactUser->username ?? 'Usuario';
                }

                // Proactively grant Drive permissions to recipient (reader)
                try {
                    $this->grantDriveAccessForShare($sharedMeeting);
                } catch (\Throwable $e) {
                    Log::warning('shareMeeting: grantDriveAccess failed', [
                        'shared_meeting_id' => $sharedMeeting->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reunión compartida exitosamente',
                'shared_with' => $sharedWith
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error sharing meeting: ' . $e->getMessage());
            return response()->json(['error' => 'Error al compartir la reunión'], 500);
        }
    }

    /**
     * Responder a una invitación de reunión compartida
     */
    public function respondToInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'shared_meeting_id' => 'required|exists:shared_meetings,id',
            'action' => 'required|in:accept,reject',
            'notification_id' => 'nullable'
        ]);

    try {
            Log::info('respondToInvitation:start', [
                'user_id' => Auth::id(),
                'shared_meeting_id' => $request->shared_meeting_id,
                'action' => $request->action
            ]);
            // Buscar la invitación por id y usuario, sin forzar estado inicialmente
            $sharedMeeting = SharedMeeting::with(['meeting', 'sharedBy'])
                ->where('id', $request->shared_meeting_id)
                ->where('shared_with', Auth::id())
                ->first();

            if (!$sharedMeeting) {
                Log::warning('respondToInvitation:notFoundForUser', [
                    'shared_meeting_id' => $request->shared_meeting_id,
                    'user_id' => Auth::id()
                ]);
                return response()->json(['error' => 'Invitación no encontrada'], 404);
            }

            Log::info('respondToInvitation:loadedSharedMeeting', [
                'id' => $sharedMeeting->id,
                'meeting_id' => $sharedMeeting->meeting_id,
                'shared_by' => $sharedMeeting->shared_by,
                'shared_with' => $sharedMeeting->shared_with,
                'status' => $sharedMeeting->status
            ]);

            $status = $request->action === 'accept' ? 'accepted' : 'rejected';

            // Si ya fue respondida previamente, no marcar error; limpiar notificación y devolver éxito idempotente
            if ($sharedMeeting->status !== 'pending') {
                Log::info('respondToInvitation:alreadyResponded', [
                    'current_status' => $sharedMeeting->status,
                ]);
                // Intentar limpiar notificación de invitación igualmente
                $this->deleteInviteNotification($request->notification_id, $sharedMeeting);
                return response()->json([
                    'success' => true,
                    'message' => 'La invitación ya fue respondida previamente.',
                    'status' => $sharedMeeting->status
                ]);
            }

            // Actualizar estado cuando es pending
            $sharedMeeting->update([
                'status' => $status,
                'responded_at' => now()
            ]);
            Log::info('respondToInvitation:updatedStatus', [
                'id' => $sharedMeeting->id,
                'status' => $status
            ]);

            // If accepted, ensure Drive permissions are in place for the recipient
            if ($status === 'accepted') {
                try {
                    $this->grantDriveAccessForShare($sharedMeeting);
                } catch (\Throwable $e) {
                    Log::warning('respondToInvitation: grantDriveAccess failed', [
                        'shared_meeting_id' => $sharedMeeting->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Obtener título priorizando siempre legacy para evitar colisión de IDs con meetings
            try {
                $legacy = TranscriptionLaravel::find($sharedMeeting->meeting_id);
            } catch (\Throwable $e) { $legacy = null; }
            $meetingTitle = $legacy?->meeting_name
                ?? $sharedMeeting->meeting?->title
                ?? 'Reunión';

            // Eliminar la notificación de invitación al responder (aceptar/rechazar)
            $this->deleteInviteNotification($request->notification_id, $sharedMeeting);

            // Crear notificación para quien compartió
            $actionText = $status === 'accepted' ? 'aceptó' : 'rechazó';
            $currentUser = Auth::user();
            $responderName = $currentUser->full_name ?? $currentUser->username ?? 'Usuario';
            $responseNotification = Notification::create([
                'user_id' => $sharedMeeting->shared_by,
                'emisor' => $sharedMeeting->shared_by,
                'from_user_id' => Auth::id(),
                'remitente' => Auth::id(),
                'type' => 'meeting_share_response',
                'title' => 'Respuesta a reunión compartida',
                'message' => $responderName . ' ' . $actionText . ' la reunión "' . $meetingTitle . '".',
                'data' => json_encode([
                    'meeting_id' => $sharedMeeting->meeting_id,
                    'shared_meeting_id' => $sharedMeeting->id,
                    'action' => $status,
                    'responded_by_name' => $responderName
                ]),
                'read' => false
            ]);
            try { Cache::forget('notifications_user_' . $sharedMeeting->shared_by); } catch (\Throwable $e) { /* ignore */ }
            Log::info('respondToInvitation:createdResponseNotification');

            return response()->json([
                'success' => true,
                'message' => $status === 'accepted'
                    ? 'Reunión aceptada. Ahora aparecerá en tu lista de reuniones compartidas.'
                    : 'Reunión rechazada.',
                'status' => $status
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Invitación no encontrada'], 404);
        } catch (QueryException $e) {
            Log::error('DB error responding to meeting invitation: ' . $e->getMessage());
            return response()->json(['error' => 'Error en base de datos al procesar la respuesta'], 500);
        } catch (\Throwable $e) {
            Log::error('Error responding to meeting invitation: ' . $e->getMessage());
            return response()->json(['error' => 'Error al procesar la respuesta'], 500);
        }
    }

    /**
     * Borra la notificación de invitación a reunión compartida. Idempotente.
     */
    private function deleteInviteNotification($notificationId, SharedMeeting $sharedMeeting): void
    {
        try {
            $deleted = 0;
            $userIdColumn = Schema::hasColumn('notifications', 'user_id')
                ? 'user_id'
                : (Schema::hasColumn('notifications', 'emisor') ? 'emisor' : 'user_id');

            if (!empty($notificationId)) {
                $deleted = Notification::where('id', $notificationId)
                    ->where($userIdColumn, Auth::id())
                    ->where('type', 'meeting_share_request')
                    ->delete();
                Log::info('respondToInvitation:deletedInviteById', [
                    'notification_id' => $notificationId,
                    'deleted_count' => $deleted
                ]);
            }

            if ($deleted === 0) {
                $deleted = Notification::where($userIdColumn, Auth::id())
                    ->where('type', 'meeting_share_request')
                    ->where(function ($q) use ($sharedMeeting) {
                        $id = $sharedMeeting->id;
                        $q->where('data', 'like', '%"shared_meeting_id":'.$id.'%')
                          ->orWhere('data', 'like', '%"shared_meeting_id":"'.$id.'"%');
                    })
                    ->delete();
                Log::info('respondToInvitation:deletedInviteNotificationLike', [
                    'deleted_count' => $deleted
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('respondToInvitation:deleteInviteNotificationFailed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener reuniones compartidas del usuario actual
     */
    public function getSharedMeetings(): JsonResponse
    {
        try {
            // Si la tabla/columna aún no existe en este entorno, devolver vacío en lugar de 500
            if (!Schema::hasTable('shared_meetings') || !Schema::hasColumn('shared_meetings', 'shared_with')) {
                return response()->json([
                    'success' => true,
                    'meetings' => [],
                    'message' => 'Reuniones compartidas no disponibles aún en este entorno',
                ]);
            }

            $sharedMeetingsQuery = SharedMeeting::with(['meeting', 'sharedBy'])
                ->where('shared_with', Auth::id())
                ->where('status', 'accepted')
                ->orderBy('shared_at', 'desc')
                ->get();

            // Pre-cargar transcripciones legacy para evitar N+1
            $legacyMap = TranscriptionLaravel::whereIn('id', $sharedMeetingsQuery->pluck('meeting_id'))
                ->get()
                ->keyBy('id');

            $sharedMeetings = $sharedMeetingsQuery->map(function ($shared) use ($legacyMap) {
                // Intentar primero legacy
                $legacy = $legacyMap->get($shared->meeting_id);
                $meeting = $legacy ? null : $shared->meeting; // sólo usar relación Meeting si no hay legacy

                $sharedBy = $shared->sharedBy;
                $sharedByName = $sharedBy?->full_name
                    ?? $sharedBy?->username
                    ?? $sharedBy?->name
                    ?? 'Usuario';

                return [
                    'id' => $shared->id,
                    'meeting_id' => $shared->meeting_id,
                    'title' => $legacy?->meeting_name ?? $meeting?->title ?? 'Reunión compartida',
                    'date' => $legacy?->created_at ?? $meeting?->date,
                    'duration' => $meeting?->duration ?? null,
                    'summary' => $meeting?->summary ?? null,
                    'shared_by' => [
                        'id' => $sharedBy?->id,
                        'name' => $sharedByName,
                        'email' => $sharedBy?->email ?? ''
                    ],
                    'shared_at' => $shared->shared_at,
                    'message' => $shared->message,
                    // Para legacy exponemos audio_drive_id en el mismo campo usado por el front
                    'recordings_folder_id' => $legacy?->audio_drive_id ?? $meeting?->recordings_folder_id,
                ];
            });

            return response()->json([
                'success' => true,
                'meetings' => $sharedMeetings,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting shared meetings: ' . $e->getMessage());
            return response()->json(['error' => 'Error al cargar reuniones compartidas'], 500);
        }
    }

    /**
     * Dejar de ver una reunión compartida (el receptor elimina el vínculo compartido en su lista)
     */
    public function unlink($id): JsonResponse
    {
        try {
            // Solo el receptor puede desvincular y no afecta a la reunión original
            $deleted = SharedMeeting::where('id', $id)
                ->where('shared_with', Auth::id())
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión compartida no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Reunión eliminada de tus compartidas'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error unlinking shared meeting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar de compartidas'
            ], 500);
        }
    }

    /**
     * Grants Drive access to the recipient user for the shared meeting assets (.ju and audio).
     * Uses Service Account impersonating the sharer if necessary.
     */
    private function grantDriveAccessForShare(SharedMeeting $sharedMeeting): void
    {
        try {
            $recipient = User::find($sharedMeeting->shared_with);
            $sharer = $sharedMeeting->sharedBy; // might be null in old data
            if (!$recipient || !$recipient->email) {
                return;
            }

            $recipientEmail = $recipient->email;

            // Determine legacy vs modern
            $legacy = TranscriptionLaravel::find($sharedMeeting->meeting_id);
            $modern = $legacy ? null : Meeting::find($sharedMeeting->meeting_id);

            /** @var GoogleServiceAccount $sa */
            $sa = app(GoogleServiceAccount::class);
            // Impersonate sharer when available to ensure permission on their files
            if ($sharer && $sharer->email) {
                try { $sa->impersonate($sharer->email); } catch (\Throwable $e) { /* continue without impersonation */ }
            }

            // Share .ju for legacy, validating Drive ID first
            if ($legacy) {
                if (!empty($legacy->transcript_drive_id) && $this->driveFileExists($sa, $legacy->transcript_drive_id)) {
                    $this->shareDriveItem($sa, $legacy->transcript_drive_id, $recipientEmail, 'grantDriveAccess: share .ju failed');
                }
                // Share audio: could be file or folder id
                if (!empty($legacy->audio_drive_id)) {
                    $this->shareDriveItem($sa, $legacy->audio_drive_id, $recipientEmail, 'grantDriveAccess: share audio failed');
                }
                return;
            }

            // Modern meeting: share recordings folder if present
            if ($modern && !empty($modern->recordings_folder_id)) {
                $this->shareDriveItem($sa, $modern->recordings_folder_id, $recipientEmail, 'grantDriveAccess: share recordings folder failed');
            }
        } catch (\Throwable $e) {
            Log::warning('grantDriveAccessForShare failed', [ 'error' => $e->getMessage() ]);
            throw $e;
        }
    }

    private function driveFileExists(GoogleServiceAccount $sa, string $fileId): bool
    {
        try {
            $sa->getDrive()->files->get($fileId, ['fields' => 'id', 'supportsAllDrives' => true]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('grantDriveAccess: invalid drive id', ['file_id' => $fileId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function shareDriveItem(GoogleServiceAccount $sa, string $itemId, string $email, string $context): void
    {
        try {
            $sa->shareItem($itemId, $email, 'reader');
            $permissions = $sa->getDrive()->permissions->listPermissions($itemId, ['supportsAllDrives' => true]);
            $granted = false;
            foreach ($permissions->getPermissions() ?? [] as $perm) {
                if (method_exists($perm, 'getEmailAddress') && $perm->getEmailAddress() === $email) {
                    $granted = true;
                    break;
                }
            }
            if (!$granted) {
                Log::error('grantDriveAccess: permission not granted', ['item_id' => $itemId, 'recipient' => $email]);
            }
        } catch (\Throwable $e) {
            Log::error($context, ['item_id' => $itemId, 'recipient' => $email, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

}
