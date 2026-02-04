<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SharedMeeting;
use App\Models\TranscriptionLaravel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;

class UserMeetingsController extends Controller
{
    /**
     * Obtener reuniones de un usuario
     * 
     * @param Request $request
     * @param string $userId
     * @return JsonResponse
     */
    public function getUserMeetings(Request $request, string $userId): JsonResponse
    {
        try {
            // Verificar que el usuario existe
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'user_id' => $userId
                ], 404);
            }

            // Parámetros de consulta
            $limit = min($request->query('limit', 100), 500); // Max 500
            $offset = $request->query('offset', 0);
            $orderBy = $request->query('order_by', 'created_at');
            $orderDir = in_array(strtolower($request->query('order_dir', 'desc')), ['asc', 'desc']) 
                ? $request->query('order_dir', 'desc') 
                : 'desc';

            // Validar campo de orden
            $allowedOrderFields = ['created_at', 'meeting_name', 'id'];
            if (!in_array($orderBy, $allowedOrderFields)) {
                $orderBy = 'created_at';
            }

            // Obtener reuniones del usuario por username
            $query = DB::table('transcriptions_laravel')
                ->where('username', $user->username);

            // Total de reuniones
            $total = $query->count();

            // Obtener reuniones con paginación
            $meetings = $query
                ->orderBy($orderBy, $orderDir)
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($meeting) use ($user) {
                    $meetingData = [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'username' => $meeting->username,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'status' => 'completed',
                        'duration_minutes' => null,
                        'created_at' => $meeting->created_at,
                        'updated_at' => $meeting->updated_at,
                    ];

                    // Descargar archivos y agregarlos en base64
                    try {
                        $files = $this->downloadMeetingFiles($user->username, $meeting->transcript_drive_id, $meeting->audio_drive_id, $meeting);
                        $meetingData = array_merge($meetingData, $files);
                    } catch (\Exception $e) {
                        // Si falla la descarga, agregar error pero no romper la lista
                        $meetingData['files_error'] = 'Error al descargar archivos: ' . $e->getMessage();
                    }

                    return $meetingData;
                });

            // Calcular estadísticas
            $now = Carbon::now();
            $thisWeekStart = $now->copy()->startOfWeek();
            $thisMonthStart = $now->copy()->startOfMonth();

            $allMeetings = DB::table('transcriptions_laravel')
                ->where('username', $user->username)
                ->select('id', 'created_at')
                ->get();

            $stats = [
                'total_meetings' => $total,
                'this_week' => $allMeetings->filter(function ($m) use ($thisWeekStart) {
                    return Carbon::parse($m->created_at)->gte($thisWeekStart);
                })->count(),
                'this_month' => $allMeetings->filter(function ($m) use ($thisMonthStart) {
                    return Carbon::parse($m->created_at)->gte($thisMonthStart);
                })->count(),
                'total_duration_minutes' => 0,
            ];

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ],
                'meetings' => $meetings,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total,
                ],
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reuniones del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener grupos de reuniones del usuario
     * 
     * @param Request $request
     * @param string $userId
     * @return JsonResponse
     */
    public function getUserMeetingGroups(Request $request, string $userId): JsonResponse
    {
        try {
            // Verificar que el usuario existe
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'user_id' => $userId
                ], 404);
            }

            $includeMembers = filter_var(
                $request->query('include_members', 'true'), 
                FILTER_VALIDATE_BOOLEAN
            );
            $includeMeetingsCount = filter_var(
                $request->query('include_meetings_count', 'true'), 
                FILTER_VALIDATE_BOOLEAN
            );

            // Obtener grupos donde el usuario es dueño
            $ownedGroups = DB::table('meeting_groups')
                ->where('owner_id', $userId)
                ->get();

            // Obtener grupos donde el usuario es miembro
            $memberGroups = DB::table('meeting_group_user')
                ->join('meeting_groups', 'meeting_group_user.meeting_group_id', '=', 'meeting_groups.id')
                ->where('meeting_group_user.user_id', $userId)
                ->where('meeting_groups.owner_id', '!=', $userId)
                ->select('meeting_groups.*')
                ->get();

            // Combinar grupos
            $allGroups = $ownedGroups->concat($memberGroups);

            $groups = $allGroups->map(function ($group) use ($user, $includeMembers, $includeMeetingsCount) {
                $groupData = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'owner_id' => $group->owner_id,
                    'is_owner' => $group->owner_id === $user->id,
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                ];

                // Incluir conteo de miembros
                $membersCount = DB::table('meeting_group_user')
                    ->where('meeting_group_id', $group->id)
                    ->count();
                $groupData['members_count'] = $membersCount + 1; // +1 por el owner

                // Incluir lista de miembros
                if ($includeMembers) {
                    $members = DB::table('meeting_group_user as mgu')
                        ->join('users as u', 'mgu.user_id', '=', 'u.id')
                        ->where('mgu.meeting_group_id', $group->id)
                        ->select('u.id', 'u.username', 'u.email', 'mgu.created_at as added_at')
                        ->get();

                    // Agregar al owner a la lista
                    $owner = User::find($group->owner_id);

                    if ($owner) {
                        $members->prepend((object)[
                            'id' => $owner->id,
                            'username' => $owner->username,
                            'email' => $owner->email,
                            'added_at' => $group->created_at,
                        ]);
                    }

                    $groupData['members'] = $members->toArray();
                } else {
                    $groupData['members'] = [];
                }

                // Incluir conteo de reuniones (si existe tabla de relación)
                if ($includeMeetingsCount) {
                    try {
                        $meetingsCount = DB::table('meeting_group_meeting')
                            ->where('meeting_group_id', $group->id)
                            ->count();
                        $groupData['meetings_count'] = $meetingsCount;
                    } catch (\Exception $e) {
                        $groupData['meetings_count'] = 0;
                    }
                }

                return $groupData;
            });

            $ownedCount = $groups->where('is_owner', true)->count();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
                'groups' => $groups->values(),
                'total' => $groups->count(),
                'stats' => [
                    'total_groups' => $groups->count(),
                    'owned_groups' => $ownedCount,
                    'member_groups' => $groups->count() - $ownedCount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una reunión específica
     * 
     * @param int $meetingId
     * @return JsonResponse
     */
    public function getMeetingDetails(int $meetingId): JsonResponse
    {
        try {
            // Obtener reunión
            $meeting = DB::table('transcriptions_laravel')
                ->where('id', $meetingId)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión no encontrada',
                    'meeting_id' => $meetingId
                ], 404);
            }

            // Obtener usuario
            $user = User::where('username', $meeting->username)->first();

            // Obtener grupos compartidos (si existe la tabla)
            $sharedWithGroups = [];
            try {
                $sharedWithGroups = DB::table('meeting_group_meeting as mgm')
                    ->join('meeting_groups as mg', 'mgm.meeting_group_id', '=', 'mg.id')
                    ->where('mgm.meeting_id', $meetingId)
                    ->select(
                        'mg.id as group_id',
                        'mg.name as group_name',
                        'mgm.created_at as shared_at'
                    )
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // Tabla no existe, continuar sin grupos compartidos
            }

            $response = [
                'success' => true,
                'meeting' => [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'username' => $meeting->username,
                    'user' => $user ? [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                    ] : null,
                    'transcript_drive_id' => $meeting->transcript_drive_id,
                    'audio_drive_id' => $meeting->audio_drive_id,
                    'transcript_download_url' => $meeting->transcript_download_url ?? null,
                    'audio_download_url' => $meeting->audio_download_url ?? null,
                    'status' => 'completed',
                    'duration_minutes' => null,
                    'created_at' => $meeting->created_at,
                    'updated_at' => $meeting->updated_at,
                    'shared_with_groups' => $sharedWithGroups,
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalles de la reunión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar archivos de reunión desde Google Drive
     */
    protected function downloadMeetingFiles(string $username, ?string $transcriptDriveId, ?string $audioDriveId, $meeting): array
    {
        $result = [];

        // Obtener token de Google
        $googleToken = DB::table('google_tokens')
            ->where('username', $username)
            ->first();

        if (!$googleToken) {
            throw new \Exception('Token de Google Drive no encontrado para el usuario');
        }

        // Verificar si el token está expirado y refrescarlo
        if ($this->isTokenExpired($googleToken)) {
            $googleToken = $this->refreshGoogleToken($googleToken);
        }

        $accessToken = $googleToken->access_token;

        // Descargar transcript si existe
        if ($transcriptDriveId) {
            try {
                $transcriptContent = $this->downloadFromGoogleDrive($transcriptDriveId, $accessToken);
                $transcriptSize = strlen($transcriptContent);
                
                $result['transcript'] = [
                    'file_name' => $meeting->meeting_name . '.ju',
                    'file_size_bytes' => $transcriptSize,
                    'file_size_mb' => round($transcriptSize / 1048576, 2),
                    'file_content' => base64_encode($transcriptContent),
                    'encoding' => 'base64',
                ];
            } catch (\Exception $e) {
                $result['transcript_error'] = $e->getMessage();
            }
        }

        // Descargar audio si existe
        if ($audioDriveId) {
            try {
                $audioContent = $this->downloadFromGoogleDrive($audioDriveId, $accessToken);
                $audioSize = strlen($audioContent);
                
                $result['audio'] = [
                    'file_name' => $meeting->meeting_name . '.mp3',
                    'file_size_bytes' => $audioSize,
                    'file_size_mb' => round($audioSize / 1048576, 2),
                    'file_content' => base64_encode($audioContent),
                    'encoding' => 'base64',
                ];
            } catch (\Exception $e) {
                $result['audio_error'] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Descargar archivo desde Google Drive
     */
    protected function downloadFromGoogleDrive(string $fileId, string $accessToken): string
    {
        $client = new GoogleClient();
        $client->setAccessToken($accessToken);

        $driveService = new GoogleDrive($client);
        
        try {
            $response = $driveService->files->get($fileId, ['alt' => 'media']);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            throw new \Exception('Error al descargar desde Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Verificar si el token de Google está expirado
     */
    protected function isTokenExpired($googleToken): bool
    {
        $expiryDate = Carbon::parse($googleToken->expiry_date);
        return now()->greaterThan($expiryDate);
    }

    /**
     * Refrescar token de Google Drive
     */
    protected function refreshGoogleToken($googleToken)
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->refreshToken($googleToken->refresh_token);

        $newToken = $client->getAccessToken();

        if (!$newToken || !isset($newToken['access_token'])) {
            throw new \Exception('No se pudo refrescar el token de Google Drive');
        }

        // Actualizar token en la base de datos
        DB::table('google_tokens')
            ->where('id', $googleToken->id)
            ->update([
                'access_token' => $newToken['access_token'],
                'expiry_date' => Carbon::now()->addSeconds($newToken['expires_in']),
                'updated_at' => now(),
            ]);

        // Retornar el token actualizado
        return DB::table('google_tokens')->find($googleToken->id);
    }

    /**
     * Obtener todas las reuniones accesibles para el usuario
     * Incluye: propias, compartidas directamente y compartidas en grupos
     * 
     * @param Request $request
     * @param string $userId
     * @return JsonResponse
     */
    public function getAllAccessibleMeetings(Request $request, string $userId): JsonResponse
    {
        try {
            // Verificar que el usuario existe
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'user_id' => $userId
                ], 404);
            }

            // Parámetros de consulta
            $limit = min($request->query('limit', 100), 500);
            $offset = $request->query('offset', 0);
            $includeShared = filter_var($request->query('include_shared', 'true'), FILTER_VALIDATE_BOOLEAN);
            $includeGroups = filter_var($request->query('include_groups', 'true'), FILTER_VALIDATE_BOOLEAN);

            $allMeetings = collect();
            $stats = [
                'own_meetings' => 0,
                'shared_meetings' => 0,
                'group_meetings' => 0,
            ];

            // 1. Reuniones propias del usuario
            $ownMeetings = TranscriptionLaravel::where('username', $user->username)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'username' => $meeting->username,
                        'created_at' => $meeting->created_at,
                        'updated_at' => $meeting->updated_at,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'source' => 'own',
                        'shared_by' => null,
                        'access_type' => 'owner',
                    ];
                });

            $allMeetings = $allMeetings->concat($ownMeetings);
            $stats['own_meetings'] = $ownMeetings->count();

            // 2. Reuniones compartidas directamente (shared_meetings)
            if ($includeShared) {
                $sharedMeetings = SharedMeeting::with(['meeting', 'sharedBy'])
                    ->where('shared_with', $userId)
                    ->where('status', 'accepted')
                    ->where('meeting_type', 'regular')
                    ->get()
                    ->filter(fn($shared) => $shared->meeting !== null)
                    ->map(function ($shared) {
                        $meeting = $shared->meeting;
                        $sharedBy = $shared->sharedBy;
                        
                        return [
                            'id' => $meeting->id,
                            'meeting_name' => $meeting->meeting_name,
                            'username' => $meeting->username,
                            'created_at' => $meeting->created_at,
                            'updated_at' => $meeting->updated_at,
                            'transcript_drive_id' => $meeting->transcript_drive_id,
                            'audio_drive_id' => $meeting->audio_drive_id,
                            'source' => 'shared_direct',
                            'shared_by' => [
                                'id' => $sharedBy?->id,
                                'name' => $sharedBy?->full_name ?? $sharedBy?->username ?? 'Usuario',
                                'email' => $sharedBy?->email,
                            ],
                            'shared_at' => $shared->shared_at,
                            'access_type' => 'shared',
                        ];
                    });

                $allMeetings = $allMeetings->concat($sharedMeetings);
                $stats['shared_meetings'] = $sharedMeetings->count();
            }

            // 3. Reuniones en contenedores de grupos donde el usuario es miembro
            if ($includeGroups) {
                $groupMeetingIds = DB::table('meeting_content_relations')
                    ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
                    ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
                    ->leftJoin('group_user', function ($join) use ($userId) {
                        $join->on('groups.id', '=', 'group_user.id_grupo')
                             ->where('group_user.user_id', '=', $userId);
                    })
                    ->leftJoin('organizations', 'groups.id_organizacion', '=', 'organizations.id')
                    ->where('meeting_content_containers.is_active', true)
                    ->where(function ($query) use ($userId) {
                        $query->whereNotNull('group_user.user_id') // Es miembro del grupo
                              ->orWhere('organizations.admin_id', $userId); // Es admin de la organización
                    })
                    ->select('meeting_content_relations.meeting_id')
                    ->distinct()
                    ->pluck('meeting_id');

                if ($groupMeetingIds->isNotEmpty()) {
                    $groupMeetings = TranscriptionLaravel::whereIn('id', $groupMeetingIds)
                        ->where('username', '!=', $user->username) // Excluir las propias
                        ->get()
                        ->map(function ($meeting) {
                            // Obtener info del contenedor
                            $container = DB::table('meeting_content_relations')
                                ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
                                ->where('meeting_content_relations.meeting_id', $meeting->id)
                                ->select('meeting_content_containers.id', 'meeting_content_containers.name', 'meeting_content_containers.group_id')
                                ->first();

                            $groupInfo = null;
                            if ($container && $container->group_id) {
                                $group = DB::table('groups')->where('id', $container->group_id)->first();
                                $groupInfo = [
                                    'id' => $group->id ?? null,
                                    'name' => $group->nombre_grupo ?? 'Grupo',
                                ];
                            }

                            return [
                                'id' => $meeting->id,
                                'meeting_name' => $meeting->meeting_name,
                                'username' => $meeting->username,
                                'created_at' => $meeting->created_at,
                                'updated_at' => $meeting->updated_at,
                                'transcript_drive_id' => $meeting->transcript_drive_id,
                                'audio_drive_id' => $meeting->audio_drive_id,
                                'source' => 'group_container',
                                'shared_by' => $groupInfo,
                                'container' => [
                                    'id' => $container->id ?? null,
                                    'name' => $container->name ?? 'Contenedor',
                                ],
                                'access_type' => 'group',
                            ];
                        });

                    $allMeetings = $allMeetings->concat($groupMeetings);
                    $stats['group_meetings'] = $groupMeetings->count();
                }
            }

            // Eliminar duplicados (por ID)
            $allMeetings = $allMeetings->unique('id');

            // Ordenar por fecha
            $allMeetings = $allMeetings->sortByDesc('created_at')->values();

            // Aplicar paginación
            $total = $allMeetings->count();
            $paginatedMeetings = $allMeetings->slice($offset, $limit)->values();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ],
                'meetings' => $paginatedMeetings,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total,
                ],
                'stats' => array_merge($stats, [
                    'total_accessible' => $total,
                ]),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reuniones accesibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
