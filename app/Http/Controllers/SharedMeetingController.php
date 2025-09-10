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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SharedMeetingController extends Controller
{
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

            $meeting = Meeting::findOrFail($request->meeting_id);
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

                Notification::create([
                    'user_id' => $contactId,
                    'from_user_id' => Auth::id(),
                    'type' => 'meeting_share_request',
                    'title' => 'Nueva reunión compartida',
                    'message' => $currentUserName . ' ha compartido la reunión "' . $meeting->title . '" contigo.',
                    'data' => json_encode([
                        'meeting_id' => $meeting->id,
                        'shared_meeting_id' => $sharedMeeting->id,
                        'meeting_title' => $meeting->title,
                        'shared_by_name' => $currentUserName,
                        'custom_message' => $request->message
                    ]),
                    'read' => false,
                    'status' => 'pending'
                ]);

                $contactUser = User::find($contactId);
                if ($contactUser) {
                    $sharedWith[] = $contactUser->full_name ?? $contactUser->username ?? 'Usuario';
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
            'action' => 'required|in:accept,reject'
        ]);

        try {
            $sharedMeeting = SharedMeeting::with(['meeting', 'sharedBy'])
                ->where('id', $request->shared_meeting_id)
                ->where('shared_with', Auth::id())
                ->where('status', 'pending')
                ->firstOrFail();

            $status = $request->action === 'accept' ? 'accepted' : 'rejected';

            $sharedMeeting->update([
                'status' => $status,
                'responded_at' => now()
            ]);

            // Marcar la notificación como leída
            Notification::where('user_id', Auth::id())
                ->where('type', 'meeting_share_request')
                ->whereJsonContains('data->shared_meeting_id', $sharedMeeting->id)
                ->update(['read' => true, 'read_at' => now()]);

            // Crear notificación para quien compartió
            $actionText = $status === 'accepted' ? 'aceptó' : 'rechazó';
            Notification::create([
                'user_id' => $sharedMeeting->shared_by,
                'from_user_id' => Auth::id(),
                'type' => 'meeting_share_response',
                'title' => 'Respuesta a reunión compartida',
                'message' => Auth::user()->name . ' ' . $actionText . ' la reunión "' . $sharedMeeting->meeting->title . '".',
                'data' => json_encode([
                    'meeting_id' => $sharedMeeting->meeting_id,
                    'shared_meeting_id' => $sharedMeeting->id,
                    'action' => $status,
                    'responded_by_name' => Auth::user()->name
                ]),
                'read' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => $status === 'accepted'
                    ? 'Reunión aceptada. Ahora aparecerá en tu lista de reuniones compartidas.'
                    : 'Reunión rechazada.',
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Error responding to meeting invitation: ' . $e->getMessage());
            return response()->json(['error' => 'Error al procesar la respuesta'], 500);
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

            $sharedMeetings = SharedMeeting::with(['meeting', 'sharedBy'])
                ->where('shared_with', Auth::id())
                ->where('status', 'accepted')
                ->orderBy('shared_at', 'desc')
                ->get()
                ->map(function ($shared) {
                    // Relación principal (meetings)
                    $meeting = $shared->meeting;

                    // Fallback para datos antiguos (transcriptions_laravel)
                    $legacy = null;
                    if (!$meeting) {
                        $legacy = TranscriptionLaravel::find($shared->meeting_id);
                    }

                    // Datos del usuario que compartió, tolerante a nulos
                    $sharedBy = $shared->sharedBy;
                    $sharedByName = $sharedBy?->full_name
                        ?? $sharedBy?->username
                        ?? $sharedBy?->name
                        ?? 'Usuario';

                    return [
                        'id' => $shared->id,
                        'meeting_id' => $shared->meeting_id,
                        'title' => $meeting?->title ?? $legacy?->meeting_name ?? 'Reunión compartida',
                        'date' => $meeting?->date ?? $legacy?->created_at,
                        'duration' => $meeting?->duration ?? null,
                        'summary' => $meeting?->summary ?? null,
                        'shared_by' => [
                            'id' => $sharedBy?->id,
                            'name' => $sharedByName,
                            'email' => $sharedBy?->email ?? ''
                        ],
                        'shared_at' => $shared->shared_at,
                        'message' => $shared->message,
                        'recordings_folder_id' => $meeting?->recordings_folder_id ?? $legacy?->audio_drive_id
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
     * Obtener reuniones que el usuario ha compartido
     */
    public function getMeetingsSharedByUser(): JsonResponse
    {
        try {
            $sharedMeetings = SharedMeeting::with(['meeting', 'sharedWith'])
                ->where('shared_by', Auth::id())
                ->orderBy('shared_at', 'desc')
                ->get()
                ->groupBy('meeting_id')
                ->map(function ($shares) {
                    $firstShare = $shares->first();
                    return [
                        'meeting_id' => $firstShare->meeting_id,
                        'title' => $firstShare->meeting->title,
                        'date' => $firstShare->meeting->date,
                        'shared_with_count' => $shares->count(),
                        'shared_with' => $shares->map(function ($share) {
                            return [
                                'id' => $share->shared_with,
                                'name' => $share->sharedWith->name,
                                'status' => $share->status,
                                'responded_at' => $share->responded_at
                            ];
                        }),
                        'shared_at' => $firstShare->shared_at
                    ];
                })
                ->values();

            return response()->json($sharedMeetings);
        } catch (\Exception $e) {
            Log::error('Error getting meetings shared by user: ' . $e->getMessage());
            return response()->json(['error' => 'Error al cargar reuniones compartidas por ti'], 500);
        }
    }
}
