<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrupoEmpresa;
use App\Models\MiembroGrupoEmpresa;
use App\Models\ReunionCompartidaGrupo;
use App\Models\User;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Carbon\Carbon;

class GrupoEmpresaController extends Controller
{
    // =====================================================
    // CRUD DE GRUPOS
    // =====================================================

    /**
     * Listar grupos de una empresa
     * GET /api/companies/{empresa_id}/groups
     */
    public function index(int $empresaId): JsonResponse
    {
        try {
            // Verificar que la empresa existe
            $empresa = Empresa::find($empresaId);
            if (!$empresa) {
                return response()->json([
                    'message' => 'Empresa no encontrada'
                ], 404);
            }

            $grupos = GrupoEmpresa::with(['miembros' => function ($query) {
                    $query->select('id', 'grupo_id', 'user_id', 'rol', 'created_at');
                }])
                ->deEmpresa($empresaId)
                ->activos()
                ->get()
                ->map(function ($grupo) {
                    // Obtener información de usuarios (miembros)
                    $miembrosConInfo = $grupo->miembros->map(function ($miembro) {
                        $user = User::find($miembro->user_id);
                        return [
                            'id' => $miembro->id,
                            'user_id' => $miembro->user_id,
                            'nombre' => $user->name ?? $user->username ?? null,
                            'rol' => $miembro->rol
                        ];
                    });

                    // Obtener reuniones compartidas
                    $reunionesCompartidas = $grupo->reunionesCompartidas()
                        ->vigentes()
                        ->get()
                        ->map(function ($compartida) {
                            $meeting = DB::table('transcriptions_laravel')
                                ->where('id', $compartida->meeting_id)
                                ->first();
                            $sharedBy = User::find($compartida->shared_by);
                            return [
                                'id' => $compartida->id,
                                'meeting_id' => $compartida->meeting_id,
                                'nombre' => $meeting->meeting_name ?? 'Reunión eliminada',
                                'compartido_por' => $sharedBy->username ?? null
                            ];
                        });

                    // Obtener username del creador
                    $creador = User::find($grupo->created_by);

                    return [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                        'descripcion' => $grupo->descripcion,
                        'empresa_id' => $grupo->empresa_id,
                        'created_by' => $creador->username ?? $grupo->created_by,
                        'created_at' => $grupo->created_at->toIso8601String(),
                        'miembros' => $miembrosConInfo,
                        'miembros_count' => $grupo->miembros->count(),
                        'reuniones_compartidas' => $reunionesCompartidas
                    ];
                });

            return response()->json([
                'groups' => $grupos,
                'total' => $grupos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo grupo
     * POST /api/companies/{empresa_id}/groups
     */
    public function store(Request $request, int $empresaId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:1000',
                'created_by' => 'required|uuid|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que la empresa existe
            $empresa = Empresa::find($empresaId);
            if (!$empresa) {
                return response()->json([
                    'message' => 'Empresa no encontrada'
                ], 404);
            }

            // Verificar que no exista un grupo con el mismo nombre en la empresa
            $existeGrupo = GrupoEmpresa::deEmpresa($empresaId)
                ->where('nombre', $request->nombre)
                ->activos()
                ->exists();

            if ($existeGrupo) {
                return response()->json([
                    'message' => 'Ya existe un grupo con ese nombre en la empresa'
                ], 409);
            }

            $grupo = GrupoEmpresa::create([
                'empresa_id' => $empresaId,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'created_by' => $request->created_by,
                'is_active' => true,
            ]);

            // Agregar al creador como administrador del grupo
            MiembroGrupoEmpresa::create([
                'grupo_id' => $grupo->id,
                'user_id' => $request->created_by,
                'rol' => MiembroGrupoEmpresa::ROL_ADMINISTRADOR,
            ]);

            $creador = User::find($request->created_by);

            return response()->json([
                'message' => 'Grupo creado exitosamente',
                'group' => [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'descripcion' => $grupo->descripcion,
                    'empresa_id' => $grupo->empresa_id,
                    'created_by' => $creador->username ?? $request->created_by,
                    'created_at' => $grupo->created_at->toIso8601String()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de un grupo
     * GET /api/companies/{empresa_id}/groups/{grupo_id}
     */
    public function show(int $empresaId, int $grupoId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::with(['miembros', 'reunionesCompartidas'])
                ->deEmpresa($empresaId)
                ->find($grupoId);

            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // Obtener información completa de miembros
            $miembros = $grupo->miembros->map(function ($miembro) {
                $user = User::find($miembro->user_id);
                return [
                    'id' => $miembro->id,
                    'user_id' => $miembro->user_id,
                    'nombre' => $user->name ?? $user->username ?? null,
                    'rol' => $miembro->rol
                ];
            });

            // Obtener reuniones compartidas con info básica
            $reunionesCompartidas = $grupo->reunionesCompartidas()
                ->vigentes()
                ->get()
                ->map(function ($reunion) {
                    $meeting = DB::table('transcriptions_laravel')
                        ->where('id', $reunion->meeting_id)
                        ->first();
                    
                    $sharedBy = User::find($reunion->shared_by);

                    return [
                        'id' => $reunion->id,
                        'meeting_id' => $reunion->meeting_id,
                        'nombre' => $meeting->meeting_name ?? 'Reunión eliminada',
                        'compartido_por' => $sharedBy->username ?? null
                    ];
                });

            $creador = User::find($grupo->created_by);

            return response()->json([
                'group' => [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'descripcion' => $grupo->descripcion,
                    'empresa_id' => $grupo->empresa_id,
                    'created_by' => $creador->username ?? $grupo->created_by,
                    'created_at' => $grupo->created_at->toIso8601String(),
                    'miembros' => $miembros,
                    'miembros_count' => $miembros->count(),
                    'reuniones_compartidas' => $reunionesCompartidas
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar grupo
     * PATCH /api/companies/{empresa_id}/groups/{grupo_id}
     */
    public function update(Request $request, int $empresaId, int $grupoId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|string|max:255',
                'descripcion' => 'nullable|string|max:1000',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::deEmpresa($empresaId)->find($grupoId);

            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // Si cambia el nombre, verificar que no exista otro con ese nombre
            if ($request->has('nombre') && $request->nombre !== $grupo->nombre) {
                $existeGrupo = GrupoEmpresa::deEmpresa($empresaId)
                    ->where('nombre', $request->nombre)
                    ->where('id', '!=', $grupoId)
                    ->activos()
                    ->exists();

                if ($existeGrupo) {
                    return response()->json([
                        'message' => 'Ya existe otro grupo con ese nombre'
                    ], 409);
                }
            }

            $grupo->update($request->only(['nombre', 'descripcion', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado exitosamente',
                'group' => [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'descripcion' => $grupo->descripcion,
                    'is_active' => $grupo->is_active,
                    'updated_at' => $grupo->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar grupo (soft delete - desactivar)
     * DELETE /api/companies/{empresa_id}/groups/{grupo_id}
     */
    public function destroy(int $empresaId, int $grupoId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::deEmpresa($empresaId)->find($grupoId);

            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // Soft delete - desactivar
            $grupo->update(['is_active' => false]);

            return response()->json([
                'message' => 'Grupo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // GESTIÓN DE MIEMBROS
    // =====================================================

    /**
     * Agregar miembro al grupo
     * POST /api/companies/{empresa_id}/groups/{grupo_id}/members
     */
    public function addMember(Request $request, int $empresaId, int $grupoId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|uuid|exists:users,id',
                'rol' => 'required|in:administrador,colaborador,invitado',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::deEmpresa($empresaId)->activos()->find($grupoId);

            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado o inactivo'
                ], 404);
            }

            // Verificar que el usuario no sea ya miembro
            $existeMiembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('user_id', $request->user_id)
                ->exists();

            if ($existeMiembro) {
                return response()->json([
                    'message' => 'El usuario ya es miembro del grupo'
                ], 409);
            }

            $miembro = MiembroGrupoEmpresa::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'rol' => $request->rol,
            ]);

            $user = User::find($request->user_id);

            return response()->json([
                'message' => 'Miembro añadido exitosamente',
                'member' => [
                    'id' => $miembro->id,
                    'user_id' => $user->id,
                    'nombre' => $user->name ?? $user->username,
                    'rol' => $miembro->rol
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar miembro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar rol de miembro
     * PATCH /api/companies/{empresa_id}/groups/{grupo_id}/members/{user_id}/role
     */
    public function updateMemberRole(Request $request, int $empresaId, int $grupoId, string $userId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'rol' => 'required|in:administrador,colaborador,invitado',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::deEmpresa($empresaId)->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $miembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('user_id', $userId)
                ->first();

            if (!$miembro) {
                return response()->json([
                    'message' => 'Miembro no encontrado en el grupo'
                ], 404);
            }

            // No permitir cambiar rol del creador del grupo
            if ($grupo->created_by === $userId && $request->rol !== 'administrador') {
                return response()->json([
                    'message' => 'No se puede cambiar el rol del creador del grupo'
                ], 403);
            }

            $miembro->update(['rol' => $request->rol]);

            return response()->json([
                'message' => 'Rol actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar miembro del grupo
     * DELETE /api/companies/{empresa_id}/groups/{grupo_id}/members/{user_id}
     */
    public function removeMember(int $empresaId, int $grupoId, string $userId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::deEmpresa($empresaId)->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // No permitir eliminar al creador del grupo
            if ($grupo->created_by === $userId) {
                return response()->json([
                    'message' => 'No se puede eliminar al creador del grupo'
                ], 403);
            }

            $miembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('user_id', $userId)
                ->first();

            if (!$miembro) {
                return response()->json([
                    'message' => 'Miembro no encontrado en el grupo'
                ], 404);
            }

            $miembro->delete();

            return response()->json([
                'message' => 'Miembro eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar miembro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // COMPARTIR REUNIONES CON GRUPO
    // =====================================================

    /**
     * Compartir reunión con grupo
     * POST /api/companies/{empresa_id}/groups/{grupo_id}/share-meeting
     */
    public function shareMeeting(Request $request, int $empresaId, int $grupoId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_id' => 'required|integer',
                'shared_by' => 'required|uuid|exists:users,id',
                'permisos' => 'nullable|array',
                'permisos.ver_audio' => 'nullable|boolean',
                'permisos.ver_transcript' => 'nullable|boolean',
                'permisos.descargar' => 'nullable|boolean',
                'mensaje' => 'nullable|string|max:500',
                'expires_at' => 'nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::deEmpresa($empresaId)->activos()->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado o inactivo'
                ], 404);
            }

            // Verificar que la reunión existe y pertenece al usuario que comparte
            $meeting = DB::table('transcriptions_laravel')
                ->where('id', $request->meeting_id)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'message' => 'Reunión no encontrada'
                ], 404);
            }

            $sharedByUser = User::find($request->shared_by);
            if ($meeting->username !== $sharedByUser->username) {
                return response()->json([
                    'message' => 'Solo el propietario de la reunión puede compartirla'
                ], 403);
            }

            // Verificar que el usuario tenga token de Google (necesario para descargar)
            $token = DB::table('google_tokens')
                ->where('username', $sharedByUser->username)
                ->first();

            if (!$token) {
                return response()->json([
                    'message' => 'El usuario debe conectar su cuenta de Google Drive para compartir reuniones'
                ], 400);
            }

            // Verificar que no esté ya compartida
            $yaCompartida = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->where('meeting_id', $request->meeting_id)
                ->exists();

            if ($yaCompartida) {
                return response()->json([
                    'message' => 'Esta reunión ya está compartida con el grupo'
                ], 409);
            }

            // Crear permisos (usar defaults si no se especifican)
            $permisos = array_merge(
                ReunionCompartidaGrupo::permisosDefault(),
                $request->permisos ?? []
            );

            $compartida = ReunionCompartidaGrupo::create([
                'grupo_id' => $grupoId,
                'meeting_id' => $request->meeting_id,
                'shared_by' => $request->shared_by,
                'permisos' => $permisos,
                'mensaje' => $request->mensaje,
                'expires_at' => $request->expires_at,
            ]);

            return response()->json([
                'message' => 'Reunión compartida exitosamente',
                'shared_meeting' => [
                    'id' => $compartida->id,
                    'meeting_id' => $request->meeting_id,
                    'grupo_id' => $grupoId,
                    'shared_by' => $sharedByUser->username,
                    'permisos' => $permisos,
                    'created_at' => $compartida->created_at->toIso8601String()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al compartir reunión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dejar de compartir reunión con grupo
     * DELETE /api/companies/{empresa_id}/groups/{grupo_id}/shared-meetings/{meeting_id}
     */
    public function unshareMeeting(int $empresaId, int $grupoId, int $meetingId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::deEmpresa($empresaId)->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $compartida = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->where('meeting_id', $meetingId)
                ->first();

            if (!$compartida) {
                return response()->json([
                    'message' => 'La reunión no está compartida con este grupo'
                ], 404);
            }

            $compartida->delete();

            return response()->json([
                'message' => 'Se dejó de compartir la reunión'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al dejar de compartir',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar reuniones compartidas con el grupo
     * GET /api/companies/{empresa_id}/groups/{grupo_id}/shared-meetings
     */
    public function getSharedMeetings(int $empresaId, int $grupoId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::deEmpresa($empresaId)->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $reuniones = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->vigentes()
                ->get()
                ->map(function ($compartida) {
                    $meeting = DB::table('transcriptions_laravel')
                        ->where('id', $compartida->meeting_id)
                        ->first();

                    $sharedBy = User::find($compartida->shared_by);

                    return [
                        'id' => $compartida->id,
                        'meeting_id' => $compartida->meeting_id,
                        'nombre' => $meeting->meeting_name ?? 'Reunión eliminada',
                        'meeting_name' => $meeting->meeting_name ?? 'Reunión eliminada',
                        'compartido_por' => $sharedBy->username ?? null,
                        'fecha_compartido' => $compartida->created_at->toIso8601String(),
                        'permisos' => $compartida->permisos ?? ReunionCompartidaGrupo::permisosDefault()
                    ];
                });

            return response()->json([
                'shared_meetings' => $reuniones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener reuniones compartidas: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // ACCEDER A ARCHIVOS DE REUNIÓN COMPARTIDA
    // =====================================================

    /**
     * Obtener archivos de reunión compartida (usa el token del que compartió)
     * GET /api/companies/{empresa_id}/groups/{grupo_id}/shared-meetings/{meeting_id}/files
     * 
     * Query params:
     * - requester_user_id: UUID del usuario que solicita (debe ser miembro del grupo)
     * - file_type: transcript, audio, o both
     */
    public function getSharedMeetingFiles(Request $request, int $empresaId, int $grupoId, int $meetingId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'requester_user_id' => 'required|uuid|exists:users,id',
                'file_type' => 'required|in:transcript,audio,both',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::deEmpresa($empresaId)->activos()->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado o inactivo'
                ], 404);
            }

            // Verificar que el solicitante es miembro del grupo
            $esMiembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('user_id', $request->requester_user_id)
                ->exists();

            if (!$esMiembro) {
                return response()->json([
                    'message' => 'No eres miembro de este grupo'
                ], 403);
            }

            // Obtener la reunión compartida
            $compartida = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->where('meeting_id', $meetingId)
                ->vigentes()
                ->first();

            if (!$compartida) {
                return response()->json([
                    'message' => 'Reunión no compartida con este grupo o expirada'
                ], 404);
            }

            // Verificar permisos según el tipo de archivo solicitado
            $permisos = $compartida->permisos ?? ReunionCompartidaGrupo::permisosDefault();
            $fileType = $request->file_type;

            if ($fileType === 'audio' || $fileType === 'both') {
                if (!($permisos['ver_audio'] ?? false)) {
                    return response()->json([
                        'message' => 'No tienes permiso para ver el audio'
                    ], 403);
                }
            }

            if ($fileType === 'transcript' || $fileType === 'both') {
                if (!($permisos['ver_transcript'] ?? false)) {
                    return response()->json([
                        'message' => 'No tienes permiso para ver la transcripción'
                    ], 403);
                }
            }

            // Obtener la reunión
            $meeting = DB::table('transcriptions_laravel')
                ->where('id', $meetingId)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'message' => 'Reunión no encontrada'
                ], 404);
            }

            // Obtener el token del usuario que compartió (autorización delegada)
            $sharedByUser = User::find($compartida->shared_by);
            if (!$sharedByUser) {
                return response()->json([
                    'message' => 'Usuario que compartió ya no existe'
                ], 404);
            }

            $token = DB::table('google_tokens')
                ->where('username', $sharedByUser->username)
                ->first();

            if (!$token) {
                return response()->json([
                    'message' => 'El propietario de la reunión debe reconectar Google Drive'
                ], 400);
            }

            // Descargar archivos usando el token del propietario
            $result = $this->downloadFilesWithToken($token, $meeting, $fileType, $compartida);

            return response()->json(array_merge([
                'success' => true,
                'meeting_id' => $meetingId,
                'meeting_name' => $meeting->meeting_name,
                'shared_by' => $sharedByUser->username,
                'permisos' => $permisos,
                'can_download' => $permisos['descargar'] ?? false,
            ], $result));

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener archivos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar archivos usando el token del propietario
     */
    protected function downloadFilesWithToken($token, $meeting, string $fileType, $compartida): array
    {
        // Verificar si el token está expirado y refrescarlo si es necesario
        $accessToken = $token->access_token;
        
        if ($this->isTokenExpired($token)) {
            $refreshedToken = $this->refreshGoogleToken($token);
            if (!$refreshedToken) {
                throw new \Exception('No se pudo refrescar el token de Google');
            }
            $accessToken = $refreshedToken->access_token;
        }

        // Configurar cliente de Google - pasar solo el access_token como string
        $client = new GoogleClient();
        $client->setAccessToken($accessToken);
        $driveService = new GoogleDrive($client);

        $result = [];

        if ($fileType === 'transcript' || $fileType === 'both') {
            if ($meeting->transcript_drive_id) {
                try {
                    $response = $driveService->files->get($meeting->transcript_drive_id, ['alt' => 'media']);
                    $content = $response->getBody()->getContents();
                    
                    $result['transcript'] = [
                        'file_name' => $this->sanitizeFileName($meeting->meeting_name) . '.ju',
                        'file_size_bytes' => strlen($content),
                        'file_size_mb' => round(strlen($content) / 1024 / 1024, 2),
                        'file_content' => base64_encode($content),
                        'encoding' => 'base64'
                    ];
                } catch (\Exception $e) {
                    $result['transcript'] = [
                        'error' => 'Error al descargar transcripción: ' . $e->getMessage()
                    ];
                }
            } else {
                $result['transcript'] = [
                    'error' => 'No hay transcripción disponible'
                ];
            }
        }

        if ($fileType === 'audio' || $fileType === 'both') {
            if ($meeting->audio_drive_id) {
                try {
                    $response = $driveService->files->get($meeting->audio_drive_id, ['alt' => 'media']);
                    $content = $response->getBody()->getContents();
                    
                    $result['audio'] = [
                        'file_name' => $this->sanitizeFileName($meeting->meeting_name) . '.mp3',
                        'file_size_bytes' => strlen($content),
                        'file_size_mb' => round(strlen($content) / 1024 / 1024, 2),
                        'file_content' => base64_encode($content),
                        'encoding' => 'base64'
                    ];
                } catch (\Exception $e) {
                    $result['audio'] = [
                        'error' => 'Error al descargar audio: ' . $e->getMessage()
                    ];
                }
            } else {
                $result['audio'] = [
                    'error' => 'No hay audio disponible'
                ];
            }
        }

        // Calcular tamaño total si es 'both'
        if ($fileType === 'both') {
            $totalBytes = 0;
            if (isset($result['transcript']['file_size_bytes'])) {
                $totalBytes += $result['transcript']['file_size_bytes'];
            }
            if (isset($result['audio']['file_size_bytes'])) {
                $totalBytes += $result['audio']['file_size_bytes'];
            }
            $result['total_size_mb'] = round($totalBytes / 1024 / 1024, 2);
        }

        $result['downloaded_at'] = Carbon::now()->toIso8601String();

        return $result;
    }

    /**
     * Verificar si el token ha expirado
     */
    protected function isTokenExpired($token): bool
    {
        if (!$token->expiry_date) {
            return true;
        }

        try {
            $expiryDate = Carbon::parse($token->expiry_date);
            return Carbon::now()->isAfter($expiryDate->subMinutes(5));
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Refrescar token de Google
     */
    protected function refreshGoogleToken($token)
    {
        try {
            if (empty($token->refresh_token)) {
                return null;
            }

            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->refreshToken($token->refresh_token);
            
            $newToken = $client->getAccessToken();
            
            if (!$newToken || isset($newToken['error'])) {
                return null;
            }

            // Asegurar que el refresh_token se mantiene
            if (empty($newToken['refresh_token']) && !empty($token->refresh_token)) {
                $newToken['refresh_token'] = $token->refresh_token;
            }

            // Actualizar en base de datos
            $expiryDate = Carbon::now()->addSeconds($newToken['expires_in'] ?? 3600);
            
            DB::table('google_tokens')
                ->where('id', $token->id)
                ->update([
                    'access_token' => $newToken['access_token'],
                    'refresh_token' => $newToken['refresh_token'] ?? $token->refresh_token,
                    'expires_in' => $newToken['expires_in'] ?? 3600,
                    'expiry_date' => $expiryDate->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::now()
                ]);

            // Retornar el token actualizado desde la base de datos
            return DB::table('google_tokens')->find($token->id);

        } catch (\Exception $e) {
            \Log::error('Error refreshing Google token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sanitizar nombre de archivo
     */
    protected function sanitizeFileName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-áéíóúñÁÉÍÓÚÑ ]/', '_', $name);
    }

    // =====================================================
    // OBTENER GRUPOS DEL USUARIO
    // =====================================================

    /**
     * Obtener grupos a los que pertenece un usuario
     * GET /api/users/{user_id}/company-groups
     */
    public function getUserGroups(string $userId): JsonResponse
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Obtener grupos donde el usuario es miembro
            $memberships = MiembroGrupoEmpresa::where('user_id', $userId)->get();

            $grupos = $memberships->map(function ($membership) use ($user) {
                $grupo = GrupoEmpresa::with('empresa')->activos()->find($membership->grupo_id);
                
                if (!$grupo) {
                    return null;
                }

                return [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'descripcion' => $grupo->descripcion,
                    'miembros_count' => $grupo->miembros()->count(),
                    'mi_rol' => $membership->rol
                ];
            })->filter()->values();

            return response()->json([
                'groups' => $grupos,
                'total' => $grupos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener grupos del usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // MÉTODOS SIMPLIFICADOS (SIN empresa_id en URL)
    // =====================================================

    /**
     * Agregar miembro al grupo (sin empresa_id en URL)
     * POST /api/groups/{grupo_id}/members
     */
    public function addMemberSimple(Request $request, int $grupoId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|uuid|exists:users,id',
                'rol' => 'nullable|in:administrador,colaborador,invitado',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::activos()->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado o inactivo'
                ], 404);
            }

            // Verificar que el usuario no sea ya miembro
            $existeMiembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('user_id', $request->user_id)
                ->exists();

            if ($existeMiembro) {
                return response()->json([
                    'message' => 'El usuario ya es miembro del grupo'
                ], 409);
            }

            $miembro = MiembroGrupoEmpresa::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'rol' => $request->rol ?? MiembroGrupoEmpresa::ROL_COLABORADOR,
            ]);

            $user = User::find($request->user_id);

            return response()->json([
                'message' => 'Miembro añadido exitosamente',
                'member' => [
                    'id' => $miembro->id,
                    'user_id' => $user->id,
                    'nombre' => $user->name ?? $user->username,
                    'rol' => $miembro->rol
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar miembro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar rol de miembro (sin empresa_id en URL)
     * PUT /api/groups/{grupo_id}/members/{member_id}
     */
    public function updateMemberRoleSimple(Request $request, int $grupoId, int $memberId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'rol' => 'required|in:administrador,colaborador,invitado',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $miembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('id', $memberId)
                ->first();

            if (!$miembro) {
                return response()->json([
                    'message' => 'Miembro no encontrado en el grupo'
                ], 404);
            }

            // No permitir cambiar rol del creador del grupo
            if ($grupo->created_by === $miembro->user_id && $request->rol !== 'administrador') {
                return response()->json([
                    'message' => 'No se puede cambiar el rol del creador del grupo'
                ], 403);
            }

            $miembro->update(['rol' => $request->rol]);

            return response()->json([
                'message' => 'Rol actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar rol: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar miembro del grupo (sin empresa_id en URL)
     * DELETE /api/groups/{grupo_id}/members/{member_id}
     */
    public function removeMemberSimple(int $grupoId, int $memberId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $miembro = MiembroGrupoEmpresa::where('grupo_id', $grupoId)
                ->where('id', $memberId)
                ->first();

            if (!$miembro) {
                return response()->json([
                    'message' => 'Miembro no encontrado en el grupo'
                ], 404);
            }

            // No permitir eliminar al creador del grupo
            if ($grupo->created_by === $miembro->user_id) {
                return response()->json([
                    'message' => 'No se puede eliminar al creador del grupo'
                ], 403);
            }

            $miembro->delete();

            return response()->json([
                'message' => 'Miembro eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar miembro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compartir reunión con grupo (sin empresa_id en URL)
     * POST /api/groups/{grupo_id}/share-meeting
     */
    public function shareMeetingSimple(Request $request, int $grupoId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_id' => 'required|integer',
                'shared_by' => 'required|string', // username
                'permisos' => 'nullable|array',
                'permisos.ver_audio' => 'nullable|boolean',
                'permisos.ver_transcript' => 'nullable|boolean',
                'permisos.descargar' => 'nullable|boolean',
                'mensaje' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grupo = GrupoEmpresa::activos()->find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado o inactivo'
                ], 404);
            }

            // Verificar que la reunión existe y pertenece al usuario que comparte
            $meeting = DB::table('transcriptions_laravel')
                ->where('id', $request->meeting_id)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'message' => 'Reunión no encontrada'
                ], 404);
            }

            // Buscar usuario por username
            $sharedByUser = User::where('username', $request->shared_by)->first();
            if (!$sharedByUser) {
                return response()->json([
                    'message' => 'Usuario que comparte no encontrado'
                ], 404);
            }

            if ($meeting->username !== $sharedByUser->username) {
                return response()->json([
                    'message' => 'Solo el propietario de la reunión puede compartirla'
                ], 403);
            }

            // Verificar que el usuario tenga token de Google (necesario para descargar)
            $token = DB::table('google_tokens')
                ->where('username', $sharedByUser->username)
                ->first();

            if (!$token) {
                return response()->json([
                    'message' => 'El usuario debe conectar su cuenta de Google Drive para compartir reuniones'
                ], 400);
            }

            // Verificar que no esté ya compartida
            $yaCompartida = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->where('meeting_id', $request->meeting_id)
                ->exists();

            if ($yaCompartida) {
                return response()->json([
                    'message' => 'Esta reunión ya está compartida con el grupo'
                ], 409);
            }

            // Crear permisos (usar defaults si no se especifican)
            $permisos = array_merge(
                ReunionCompartidaGrupo::permisosDefault(),
                $request->permisos ?? []
            );

            $compartida = ReunionCompartidaGrupo::create([
                'grupo_id' => $grupoId,
                'meeting_id' => $request->meeting_id,
                'shared_by' => $sharedByUser->id,
                'permisos' => $permisos,
                'mensaje' => $request->mensaje,
            ]);

            return response()->json([
                'message' => 'Reunión compartida exitosamente',
                'shared_meeting' => [
                    'id' => $compartida->id,
                    'meeting_id' => $request->meeting_id,
                    'grupo_id' => $grupoId,
                    'shared_by' => $sharedByUser->username,
                    'permisos' => $permisos,
                    'created_at' => $compartida->created_at->toIso8601String()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al compartir reunión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar reuniones compartidas con el grupo (sin empresa_id en URL)
     * GET /api/groups/{grupo_id}/shared-meetings
     */
    public function getSharedMeetingsSimple(int $grupoId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $reuniones = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->vigentes()
                ->get()
                ->map(function ($compartida) {
                    $meeting = DB::table('transcriptions_laravel')
                        ->where('id', $compartida->meeting_id)
                        ->first();

                    $sharedBy = User::find($compartida->shared_by);

                    return [
                        'id' => $compartida->id,
                        'meeting_id' => $compartida->meeting_id,
                        'nombre' => $meeting->meeting_name ?? 'Reunión eliminada',
                        'meeting_name' => $meeting->meeting_name ?? 'Reunión eliminada',
                        'compartido_por' => $sharedBy->username ?? null,
                        'fecha_compartido' => $compartida->created_at->toIso8601String(),
                        'permisos' => $compartida->permisos ?? ReunionCompartidaGrupo::permisosDefault()
                    ];
                });

            return response()->json([
                'shared_meetings' => $reuniones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener reuniones compartidas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dejar de compartir reunión con grupo (sin empresa_id en URL)
     * DELETE /api/groups/{grupo_id}/shared-meetings/{meeting_id}
     */
    public function unshareMeetingSimple(int $grupoId, int $meetingId): JsonResponse
    {
        try {
            $grupo = GrupoEmpresa::find($grupoId);
            if (!$grupo) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $compartida = ReunionCompartidaGrupo::where('grupo_id', $grupoId)
                ->where('meeting_id', $meetingId)
                ->first();

            if (!$compartida) {
                return response()->json([
                    'message' => 'La reunión no está compartida con este grupo'
                ], 404);
            }

            $compartida->delete();

            return response()->json([
                'message' => 'Se dejó de compartir la reunión'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al dejar de compartir: ' . $e->getMessage()
            ], 500);
        }
    }
}


