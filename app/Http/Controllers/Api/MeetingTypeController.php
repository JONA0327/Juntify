<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MeetingTypeController extends Controller
{
    /**
     * Obtener el tipo de una reunión: personal, organizational, shared
     * 
     * GET /api/meetings/{meeting_id}/type
     * 
     * @param Request $request
     * @param int $meetingId
     * @return JsonResponse
     */
    public function getMeetingType(Request $request, int $meetingId): JsonResponse
    {
        try {
            // Verificar que la reunión existe
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

            // Determinar el tipo de reunión
            $typeInfo = $this->determineMeetingType($meetingId, $meeting->username);

            return response()->json([
                'success' => true,
                'meeting_id' => $meetingId,
                'meeting_name' => $meeting->meeting_name,
                'owner_username' => $meeting->username,
                'type' => $typeInfo['type'],
                'type_label' => $typeInfo['label'],
                'type_color' => $typeInfo['color'],
                'details' => $typeInfo['details']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipo de reunión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de múltiples reuniones en batch
     * 
     * POST /api/meetings/types
     * Body: { "meeting_ids": [1, 2, 3] }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMeetingTypes(Request $request): JsonResponse
    {
        try {
            $meetingIds = $request->input('meeting_ids', []);

            if (empty($meetingIds) || !is_array($meetingIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requiere un array de meeting_ids'
                ], 400);
            }

            // Limitar a 100 reuniones por request
            $meetingIds = array_slice($meetingIds, 0, 100);

            // Obtener reuniones
            $meetings = DB::table('transcriptions_laravel')
                ->whereIn('id', $meetingIds)
                ->get()
                ->keyBy('id');

            $results = [];
            foreach ($meetingIds as $meetingId) {
                $meeting = $meetings->get($meetingId);
                
                if (!$meeting) {
                    $results[$meetingId] = [
                        'success' => false,
                        'message' => 'Reunión no encontrada'
                    ];
                    continue;
                }

                $typeInfo = $this->determineMeetingType($meetingId, $meeting->username);
                
                $results[$meetingId] = [
                    'success' => true,
                    'meeting_name' => $meeting->meeting_name,
                    'owner_username' => $meeting->username,
                    'type' => $typeInfo['type'],
                    'type_label' => $typeInfo['label'],
                    'type_color' => $typeInfo['color'],
                    'details' => $typeInfo['details']
                ];
            }

            return response()->json([
                'success' => true,
                'meetings' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de reuniones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determinar el tipo de reunión
     * 
     * Prioridad:
     * 1. Si está en un contenedor organizacional (con group_id) -> organizational
     * 2. Si está compartida (en shared_meetings) -> shared
     * 3. Si está en contenedor personal (sin group_id) -> personal
     * 4. Si no está en ningún contenedor -> personal
     * 
     * @param int $meetingId
     * @param string $ownerUsername
     * @return array
     */
    protected function determineMeetingType(int $meetingId, string $ownerUsername): array
    {
        // Verificar si está en un contenedor organizacional (con group_id)
        $organizationalContainer = DB::table('meeting_content_relations')
            ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
            ->leftJoin('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
            ->leftJoin('organizations', 'groups.id_organizacion', '=', 'organizations.id')
            ->where('meeting_content_relations.meeting_id', $meetingId)
            ->whereNotNull('meeting_content_containers.group_id')
            ->where('meeting_content_containers.is_active', true)
            ->select(
                'meeting_content_containers.id as container_id',
                'meeting_content_containers.name as container_name',
                'groups.id as group_id',
                'groups.nombre_grupo as group_name',
                'organizations.id as organization_id',
                'organizations.nombre_organizacion as organization_name'
            )
            ->first();

        if ($organizationalContainer) {
            return [
                'type' => 'organizational',
                'label' => 'Organizacional',
                'color' => '#3B82F6', // blue-500
                'details' => [
                    'container_id' => $organizationalContainer->container_id,
                    'container_name' => $organizationalContainer->container_name,
                    'group_id' => $organizationalContainer->group_id,
                    'group_name' => $organizationalContainer->group_name,
                    'organization_id' => $organizationalContainer->organization_id,
                    'organization_name' => $organizationalContainer->organization_name
                ]
            ];
        }

        // Verificar si está compartida (alguien la compartió O fue compartida con alguien)
        $sharedMeeting = DB::table('shared_meetings')
            ->where('meeting_id', $meetingId)
            ->where('status', 'accepted')
            ->select('id', 'shared_by', 'shared_with', 'shared_at')
            ->first();

        if ($sharedMeeting) {
            // Obtener nombres de usuarios involucrados
            $sharedByUser = DB::table('users')
                ->where('id', $sharedMeeting->shared_by)
                ->select('username', 'name')
                ->first();
            
            $sharedWithUser = DB::table('users')
                ->where('id', $sharedMeeting->shared_with)
                ->select('username', 'name')
                ->first();

            return [
                'type' => 'shared',
                'label' => 'Compartida',
                'color' => '#10B981', // green-500
                'details' => [
                    'shared_by' => [
                        'username' => $sharedByUser->username ?? null,
                        'name' => $sharedByUser->name ?? null
                    ],
                    'shared_with' => [
                        'username' => $sharedWithUser->username ?? null,
                        'name' => $sharedWithUser->name ?? null
                    ],
                    'shared_at' => $sharedMeeting->shared_at
                ]
            ];
        }

        // Verificar si está en un contenedor personal (sin group_id)
        $personalContainer = DB::table('meeting_content_relations')
            ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
            ->where('meeting_content_relations.meeting_id', $meetingId)
            ->whereNull('meeting_content_containers.group_id')
            ->where('meeting_content_containers.is_active', true)
            ->select(
                'meeting_content_containers.id as container_id',
                'meeting_content_containers.name as container_name',
                'meeting_content_containers.username as container_owner'
            )
            ->first();

        if ($personalContainer) {
            return [
                'type' => 'personal',
                'label' => 'Personal',
                'color' => '#8B5CF6', // violet-500
                'details' => [
                    'container_id' => $personalContainer->container_id,
                    'container_name' => $personalContainer->container_name,
                    'is_in_container' => true
                ]
            ];
        }

        // No está en ningún contenedor -> Personal sin contenedor
        return [
            'type' => 'personal',
            'label' => 'Personal',
            'color' => '#8B5CF6', // violet-500
            'details' => [
                'is_in_container' => false,
                'message' => 'Reunión no asignada a ningún contenedor'
            ]
        ];
    }
}
