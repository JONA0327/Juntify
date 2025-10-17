<?php

namespace App\Http\Controllers;

use App\Models\SharedMeeting;
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
use App\Services\GoogleServiceAccount;

class SharedMeetingController extends Controller
{
    use \App\Traits\MeetingContentParsing;

    // ------------------------------------------------------------------
    // NUEVAS FUNCIONALIDADES (Outgoing Shares)
    // getOutgoingShares(): Lista reuniones que el usuario actual ha compartido
    // revokeOutgoingShare(id): Revoca (elimina) el share, quitando acceso al receptor
    // Estas funciones permiten al usuario gestionar y retirar accesos previamente otorgados.
    // ------------------------------------------------------------------
    /**
     * List shares initiated by the authenticated user (outgoing shares).
     * Includes pending and accepted so user can see invitations and revoke them.
     */
    public function getOutgoingShares(): JsonResponse
    {
        try {
            if (!Schema::hasTable('shared_meetings')) {
                return response()->json([
                    'success' => true,
                    'shares' => [],
                    'message' => 'Funcionalidad no disponible todavía'
                ]);
            }

            $sharesQuery = SharedMeeting::with(['meeting', 'temporaryMeeting', 'sharedWithUser'])
                ->where('shared_by', Auth::id())
                ->orderByDesc('shared_at')
                ->get();

            $shares = $sharesQuery->map(function($share) {
                // Get the appropriate meeting based on type
                if ($share->meeting_type === 'temporary') {
                    $meeting = $share->temporaryMeeting;
                    $meetingIdPrefix = 'temp-';
                } else {
                    $meeting = $share->meeting;
                    $meetingIdPrefix = '';
                }

                // Skip if the meeting doesn't exist (deleted or expired)
                if (!$meeting) {
                    return null;
                }

                $with = $share->sharedWithUser;
                $sharedWithName = $with?->full_name ?? $with?->username ?? $with?->name ?? 'Usuario';
                return [
                    'id' => $share->id,
                    'meeting_id' => $meetingIdPrefix . $share->meeting_id,
                    'title' => $meeting->meeting_name ?? 'Reunión',
                    'status' => $share->status,
                    'meeting_type' => $share->meeting_type,
                    'shared_with' => [
                        'id' => $with?->id,
                        'name' => $sharedWithName,
                        'email' => $with?->email ?? ''
                    ],
                    'shared_at' => $share->shared_at,
                    'responded_at' => $share->responded_at,
                    'expires_at' => $share->meeting_type === 'temporary' ? $meeting->expires_at : null,
                    'is_legacy' => $share->meeting_type === 'regular',
                ];
            })->filter(); // Remove null entries (deleted meetings)

            return response()->json([
                'success' => true,
                'shares' => $shares,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error getOutgoingShares: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar reuniones compartidas por ti'
            ], 500);
        }
    }

    /**
     * Revoke an outgoing share (only creator can revoke). Deletes the record so recipient loses access.
     */
    public function revokeOutgoingShare($id): JsonResponse
    {
        try {
            $share = SharedMeeting::where('id', $id)
                ->where('shared_by', Auth::id())
                ->first();

            if (!$share) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compartido no encontrado'
                ], 404);
            }

            $share->delete();

            return response()->json([
                'success' => true,
                'message' => 'Acceso revocado'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error revokeOutgoingShare: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al revocar acceso'
            ], 500);
        }
    }
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
            ->whereHas('meeting')
            ->firstOrFail();

        $meetingId = $shared->meeting_id;
        $legacy = $shared->meeting;

        if (!$legacy) {
            return response()->json([
                'success' => false,
                'message' => 'Reunión no encontrada',
            ], 404);
        }

        $juLink = null;
        if (!empty($legacy->transcript_drive_id)) {
            $juLink = 'https://drive.google.com/uc?export=download&id=' . $legacy->transcript_drive_id;
        }

        $audioId = !empty($legacy->audio_drive_id) ? $legacy->audio_drive_id : null;
        $audioLink = $audioId ? 'https://drive.google.com/uc?export=download&id=' . $audioId : null;

        return response()->json([
            'success' => true,
            'meeting_id' => $meetingId,
            'is_legacy' => true,
            'ju_link' => $juLink,
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
        ]);

        try {
            DB::beginTransaction();

            // Obtener contactos válidos asociados al usuario actual
            $validContactIds = Contact::where('user_id', Auth::id())
                ->whereIn('contact_id', $request->contact_ids)
                ->pluck('contact_id')
                ->toArray();

            // Check if it's a temporary meeting
            $meetingId = $request->meeting_id;
            $isTemporary = str_starts_with((string)$meetingId, 'temp-');
            $actualMeetingId = $isTemporary ? str_replace('temp-', '', $meetingId) : $meetingId;
            $meetingType = $isTemporary ? 'temporary' : 'regular';

            if ($isTemporary) {
                $meeting = \App\Models\TranscriptionTemp::where('id', $actualMeetingId)
                    ->where('user_id', Auth::id())
                    ->first();
            } else {
                $meeting = TranscriptionLaravel::find($actualMeetingId);
            }

            if (!$meeting) {
                throw new \RuntimeException('Meeting not found');
            }

            // Get meeting title based on type
            if ($isTemporary) {
                $meetingTitle = $meeting->title ?? 'Reunión temporal';
            } else {
                $meetingTitle = $meeting->meeting_name ?? 'Reunión';
            }

            $sharedWith = [];

            foreach ($validContactIds as $contactId) {
                // Verificar si ya se compartió con este contacto
                $existingShare = SharedMeeting::where('meeting_id', $actualMeetingId)
                    ->where('meeting_type', $meetingType)
                    ->where('shared_with', $contactId)
                    ->first();

                if ($existingShare) {
                    // Si fue rechazado anteriormente, eliminarlo para permitir un nuevo envío
                    if ($existingShare->status === 'rejected') {
                        try { $existingShare->delete(); } catch (\Throwable $e) { /* ignore */ }
                    } else {
                        // pending o accepted: no volvemos a crear
                        continue;
                    }
                }

                // Crear el registro de reunión compartida
                $sharedMeeting = SharedMeeting::create([
                    'meeting_id' => $actualMeetingId,
                    'meeting_type' => $meetingType,
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
                        'meeting_id' => $actualMeetingId,
                        'meeting_type' => $meetingType,
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
            $sharedMeeting = SharedMeeting::with(['meeting', 'temporaryMeeting', 'sharedBy'])
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

            // Get the appropriate meeting based on type
            if ($sharedMeeting->meeting_type === 'temporary') {
                $meeting = $sharedMeeting->temporaryMeeting;
            } else {
                $meeting = $sharedMeeting->meeting;
            }

            if (!$meeting) {
                return response()->json(['error' => 'La reunión ya no está disponible'], 404);
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

            // Actualizar o preparar para borrar si rechazo
            if ($status === 'accepted') {
                $sharedMeeting->update([
                    'status' => $status,
                    'responded_at' => now()
                ]);
                Log::info('respondToInvitation:updatedStatus', [
                    'id' => $sharedMeeting->id,
                    'status' => $status
                ]);
            } else {
                // Guardar responded_at antes de notificar, y luego borrar
                try {
                    $sharedMeeting->update([
                        'status' => $status,
                        'responded_at' => now()
                    ]);
                } catch (\Throwable $e) { /* ignore */ }
            }

            // If accepted, ensure Drive permissions are in place for the recipient (only for regular meetings)
            if ($status === 'accepted' && $sharedMeeting->meeting_type === 'regular') {
                try {
                    $this->grantDriveAccessForShare($sharedMeeting);
                } catch (\Throwable $e) {
                    Log::warning('respondToInvitation: grantDriveAccess failed', [
                        'shared_meeting_id' => $sharedMeeting->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $meetingTitle = $meeting->meeting_name ?? 'Reunión';

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
                    'meeting_type' => $sharedMeeting->meeting_type,
                    'shared_meeting_id' => $sharedMeeting->id,
                    'action' => $status,
                    'responded_by_name' => $responderName
                ]),
                'read' => false
            ]);
            try { Cache::forget('notifications_user_' . $sharedMeeting->shared_by); } catch (\Throwable $e) { /* ignore */ }

            // Si fue rechazado, eliminar el registro para permitir futuros re-envíos
            if ($status === 'rejected') {
                try {
                    $sharedMeeting->delete();
                    Log::info('respondToInvitation:deletedRejectedShare', ['id' => $sharedMeeting->id]);
                } catch (\Throwable $e) {
                    Log::warning('respondToInvitation:deleteRejectedFailed', ['id' => $sharedMeeting->id, 'error' => $e->getMessage()]);
                }
            }
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

            $sharedMeetingsQuery = SharedMeeting::with(['meeting', 'temporaryMeeting', 'sharedBy'])
                ->where('shared_with', Auth::id())
                ->where('status', 'accepted')
                ->orderBy('shared_at', 'desc')
                ->get();

            $sharedMeetings = $sharedMeetingsQuery->map(function ($shared) {
                // Get the appropriate meeting based on type
                if ($shared->meeting_type === 'temporary') {
                    $meeting = $shared->temporaryMeeting;
                    $meetingIdPrefix = 'temp-';
                } else {
                    $meeting = $shared->meeting;
                    $meetingIdPrefix = '';
                }

                // Skip if the meeting doesn't exist (deleted or expired)
                if (!$meeting) {
                    return null;
                }

                $sharedBy = $shared->sharedBy;
                $sharedByName = $sharedBy?->full_name
                    ?? $sharedBy?->username
                    ?? $sharedBy?->name
                    ?? 'Usuario';

                // Get meeting title based on type
                if ($shared->meeting_type === 'temporary') {
                    $meetingTitle = $meeting->title ?? 'Reunión temporal';
                    $audioPath = $meeting->audio_path ? "/api/transcriptions-temp/{$meeting->id}/audio" : null;
                } else {
                    $meetingTitle = $meeting->meeting_name ?? 'Reunión compartida';
                    $audioPath = $meeting->audio_path ?? null;
                }

                return [
                    'id' => $shared->id,
                    'meeting_id' => $meetingIdPrefix . $shared->meeting_id,
                    'title' => $meetingTitle,
                    'date' => $meeting->created_at ? $meeting->created_at->format('d/m/Y H:i') : '',
                    'duration' => null,
                    'summary' => null,
                    'audio_path' => $audioPath,
                    'shared_by' => [
                        'id' => $sharedBy?->id,
                        'name' => $sharedByName,
                        'email' => $sharedBy?->email ?? ''
                    ],
                    'shared_at' => $shared->shared_at,
                    'message' => $shared->message,
                    'meeting_type' => $shared->meeting_type,
                    'audio_drive_id' => $shared->meeting_type === 'regular' ? $meeting->audio_drive_id : null,
                    'transcript_drive_id' => $shared->meeting_type === 'regular' ? $meeting->transcript_drive_id : null,
                    'expires_at' => $shared->meeting_type === 'temporary' ? $meeting->expires_at : null,
                ];
            })->filter(); // Remove null entries (deleted meetings)

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
     * Show a shared meeting (both regular and temporary)
     */
    public function showSharedMeeting($sharedId): JsonResponse
    {
        try {
            $shared = SharedMeeting::with(['meeting.tasks', 'temporaryMeeting', 'sharedBy'])
                ->where('id', $sharedId)
                ->where('shared_with', Auth::id())
                ->where('status', 'accepted')
                ->first();

            if (!$shared) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión compartida no encontrada'
                ], 404);
            }

            // Get the appropriate meeting based on type
            if ($shared->meeting_type === 'temporary') {
                $meeting = $shared->temporaryMeeting;
                $meetingIdPrefix = 'temp-';
            } else {
                $meeting = $shared->meeting;
                $meetingIdPrefix = '';
            }

            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'La reunión ya no está disponible'
                ], 404);
            }

            $sharedBy = $shared->sharedBy;
            $sharedByName = $sharedBy?->full_name ?? $sharedBy?->username ?? $sharedBy?->name ?? 'Usuario';

            // For temporary meetings, load complete data from .ju file
            if ($shared->meeting_type === 'temporary') {
                // Load complete meeting data from .ju file
                $meetingData = [];
                if ($meeting->transcription_path && \Storage::disk('local')->exists($meeting->transcription_path)) {
                    try {
                        $encryptedContent = \Storage::disk('local')->get($meeting->transcription_path);
                        // Decrypt the .ju file content using the proper method
                        $result = $this->decryptJuFile($encryptedContent);
                        $meetingData = $result['data'] ?? [];
                    } catch (\Exception $e) {
                        \Log::warning('Could not load/decrypt .ju file for temporary meeting: ' . $e->getMessage());
                        $meetingData = [];
                    }
                }

                return response()->json([
                    'success' => true,
                    'meeting' => [
                        'id' => $meetingIdPrefix . $shared->meeting_id,
                        'meeting_name' => $meeting->title ?? '',
                        'meeting_date' => $meeting->created_at ? $meeting->created_at->format('d/m/Y H:i') : '',
                        'summary' => $meetingData['summary'] ?? ($meeting->metadata['summary'] ?? ''),
                        'key_points' => $meetingData['key_points'] ?? ($meeting->metadata['key_points'] ?? []),
                        'transcription' => $meetingData['transcription'] ?? ($meeting->metadata['transcription'] ?? ''),
                        'segments' => $meetingData['segments'] ?? ($meeting->metadata['segments'] ?? []),
                        'tasks' => is_string($meeting->tasks) ? json_decode($meeting->tasks, true) : $meeting->tasks,
                        'meeting_type' => $shared->meeting_type,
                        'expires_at' => $meeting->expires_at,
                        'audio_path' => $meeting->audio_path ? "/api/transcriptions-temp/{$meeting->id}/audio" : null,
                        'shared_by' => [
                            'name' => $sharedByName,
                            'email' => $sharedBy?->email ?? ''
                        ],
                        'shared_at' => $shared->shared_at,
                        'message' => $shared->message,
                    ],
                ]);
            } else {
                // For regular meetings - try to load content from .ju file if available
                $meetingData = [];

                // First ensure the recipient has access to Drive files
                try {
                    $this->grantDriveAccessForShare($shared);
                } catch (\Exception $e) {
                    \Log::warning('Could not grant Drive access for shared meeting: ' . $e->getMessage());
                }

                // Now try to download and decrypt the .ju file from Drive
                if (!empty($meeting->transcript_drive_id)) {
                    try {
                        // First try without impersonation (in case recipient already has access)
                        $sa = app(\App\Services\GoogleServiceAccount::class);
                        $driveService = $sa->getDrive();

                        try {
                            $fileContent = $driveService->files->get($meeting->transcript_drive_id, ['alt' => 'media']);
                            $encryptedContent = $fileContent->getBody()->getContents();
                        } catch (\Exception $directAccessError) {
                            \Log::info("Direct access failed, trying with impersonation: " . $directAccessError->getMessage());

                            // If direct access fails, try impersonating the sharer
                            if ($sharedBy && $sharedBy->email) {
                                try {
                                    $sa->impersonate($sharedBy->email);
                                    $driveService = $sa->getDrive();
                                    $fileContent = $driveService->files->get($meeting->transcript_drive_id, ['alt' => 'media']);
                                    $encryptedContent = $fileContent->getBody()->getContents();
                                } catch (\Throwable $impersonationError) {
                                    \Log::warning('Impersonation also failed: ' . $impersonationError->getMessage());
                                    throw $impersonationError;
                                }
                            } else {
                                throw $directAccessError;
                            }
                        }

                        // Decrypt the .ju file content
                        $result = $this->decryptJuFile($encryptedContent);
                        $meetingData = $result['data'] ?? [];

                        \Log::info("Successfully loaded .ju file from Drive for meeting {$meeting->id}");
                    } catch (\Exception $e) {
                        \Log::warning('Could not load/decrypt .ju file from Drive for regular meeting: ' . $e->getMessage());
                        $meetingData = [];
                    }
                }

                // Load tasks from database if not in .ju file or if .ju file has empty tasks
                $tasksFromDb = [];
                $tasksFromJu = $meetingData['tasks'] ?? null;

                // Use database tasks if .ju file has no tasks or empty array
                if (empty($tasksFromJu) || (is_array($tasksFromJu) && count($tasksFromJu) === 0)) {
                    $tasksFromDb = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)
                        ->where('username', $meeting->username) // Use meeting owner's username
                        ->get()
                        ->toArray();
                }

                return response()->json([
                    'success' => true,
                    'meeting' => [
                        'id' => $meetingIdPrefix . $shared->meeting_id,
                        'meeting_name' => $meeting->meeting_name ?? '',
                        'meeting_date' => $meeting->created_at ? $meeting->created_at->format('d/m/Y H:i') : '',
                        // Use data from .ju file if available, otherwise fallback to database
                        'summary' => $meetingData['summary'] ?? ($meeting->summary ?? ''),
                        'key_points' => $meetingData['key_points'] ?? ($meeting->key_points ?? []),
                        'transcription' => $meetingData['transcription'] ?? ($meeting->transcription ?? ''),
                        'segments' => $meetingData['segments'] ?? [],
                        'tasks' => !empty($tasksFromDb) ? $tasksFromDb : $tasksFromJu,
                        'meeting_type' => $shared->meeting_type,
                        'expires_at' => null,
                        'audio_path' => $meeting->audio_path ?? null,
                        'shared_by' => [
                            'name' => $sharedByName,
                            'email' => $sharedBy?->email ?? ''
                        ],
                        'shared_at' => $shared->shared_at,
                        'message' => $shared->message,
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error showing shared meeting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la reunión compartida'
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

            $legacy = TranscriptionLaravel::find($sharedMeeting->meeting_id);
            if (!$legacy) {
                return;
            }

            $recipientEmail = $recipient->email;

            /** @var GoogleServiceAccount $sa */
            $sa = app(GoogleServiceAccount::class);
            // Impersonate sharer when available to ensure permission on their files
            if ($sharer && $sharer->email) {
                try { $sa->impersonate($sharer->email); } catch (\Throwable $e) { /* continue without impersonation */ }
            }

            if (!empty($legacy->transcript_drive_id) && $this->driveFileExists($sa, $legacy->transcript_drive_id)) {
                $this->shareDriveItem($sa, $legacy->transcript_drive_id, $recipientEmail, 'grantDriveAccess: share .ju failed');
            }

            // Apply same robust logic for AUDIO as for transcript
            // 1) If we have audio_drive_id and it exists, share it
            if (!empty($legacy->audio_drive_id) && $this->driveFileExists($sa, $legacy->audio_drive_id)) {
                $this->shareDriveItem($sa, $legacy->audio_drive_id, $recipientEmail, 'grantDriveAccess: share audio failed');
            } else {
                // 2) If missing/invalid, try to extract ID from audio_download_url
                $maybeId = null;
                if (!empty($legacy->audio_download_url)) {
                    $maybeId = $this->extractDriveIdFromUrl((string) $legacy->audio_download_url);
                }
                if ($maybeId) {
                    try {
                        if ($this->driveFileExists($sa, $maybeId)) {
                            $this->shareDriveItem($sa, $maybeId, $recipientEmail, 'grantDriveAccess: share audio (from url) failed');
                            // Optionally persist normalized id for future operations
                            try {
                                if (empty($legacy->audio_drive_id)) {
                                    $legacy->forceFill(['audio_drive_id' => $maybeId]);
                                    $legacy->save();
                                }
                            } catch (\Throwable $e) {
                                Log::warning('grantDriveAccess: failed to persist extracted audio_drive_id', [
                                    'meeting_id' => $legacy->id,
                                    'maybe_id' => $maybeId,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('grantDriveAccess: audio share from URL failed', [
                            'meeting_id' => $legacy->id,
                            'maybe_id' => $maybeId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
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

    /**
     * Extrae un ID de archivo de Google Drive desde una URL (formatos de file/d/{id} y ?id=)
     */
    private function extractDriveIdFromUrl(string $url): ?string
    {
        try {
            // Patterns comunes: /file/d/{id}/, uc?id=, open?id=
            if (preg_match('#/file/d/([a-zA-Z0-9_-]{10,})#', $url, $m)) {
                return $m[1];
            }
            $parts = parse_url($url);
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $q);
                if (!empty($q['id']) && is_string($q['id'])) {
                    return $q['id'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

}
