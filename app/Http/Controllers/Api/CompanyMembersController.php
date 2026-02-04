<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class CompanyMembersController extends Controller
{
    /**
     * Obtener lista de miembros de una empresa
     * 
     * @param Request $request
     * @param int $empresaId
     * @return JsonResponse
     */
    public function getMembers(Request $request, int $empresaId): JsonResponse
    {
        try {
            // Verificar que la empresa existe
            $empresa = DB::connection('juntify_panels')
                ->table('empresa')
                ->where('id', $empresaId)
                ->first();

            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada',
                    'empresa_id' => $empresaId
                ], 404);
            }

            $includeOwner = filter_var(
                $request->query('include_owner', 'true'), 
                FILTER_VALIDATE_BOOLEAN
            );
            
            $members = [];
            $processedUserIds = []; // Para evitar duplicados

            // 1. Obtener el dueño/usuario principal si se solicita
            if ($includeOwner && $empresa->iduser) {
                $owner = User::find($empresa->iduser);

                if ($owner) {
                    $members[] = [
                        'id' => $owner->id,
                        'username' => $owner->username,
                        'email' => $owner->email,
                        'name' => $owner->username, // users table doesn't have 'name' field
                        'is_owner' => true,
                        'rol' => $empresa->rol,
                        'fecha_agregado' => $empresa->created_at
                    ];
                    $processedUserIds[] = $owner->id; // Marcar como procesado
                }
            }

            // 2. Obtener integrantes de la tabla integrantes_empresa
            $integrantes = DB::connection('juntify_panels')
                ->table('integrantes_empresa')
                ->where('empresa_id', $empresaId)
                ->get();

            foreach ($integrantes as $integrante) {
                // Evitar duplicados: si el usuario ya fue procesado como owner, saltarlo
                if (in_array($integrante->iduser, $processedUserIds)) {
                    continue;
                }
                
                // Buscar información del usuario en la BD principal
                $user = User::find($integrante->iduser);
                
                if ($user) {
                    $members[] = [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'name' => $user->username,
                        'is_owner' => false,
                        'rol' => $integrante->rol,
                        'fecha_agregado' => $integrante->created_at
                    ];
                    $processedUserIds[] = $user->id;
                }
            }

            // 3. Calcular estadísticas
            $totalMembers = count($members);
            $admins = count(array_filter($members, fn($m) => 
                $m['is_owner'] || in_array($m['rol'], ['admin', 'administrador'])
            ));

            return response()->json([
                'success' => true,
                'empresa' => [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre_empresa,
                    'usuario_principal' => $empresa->iduser,
                    'rol_empresa' => $empresa->rol
                ],
                'members' => $members,
                'total' => $totalMembers,
                'stats' => [
                    'total_members' => $totalMembers,
                    'admins' => $admins,
                    'members' => $totalMembers - $admins,
                    'active' => $totalMembers,
                    'inactive' => 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener miembros de la empresa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el rol de un miembro de la empresa
     * 
     * @param Request $request
     * @param int $empresaId
     * @param string $userId
     * @return JsonResponse
     */
    public function updateMemberRole(Request $request, int $empresaId, string $userId): JsonResponse
    {
        try {
            $request->validate([
                'rol' => 'required|string|in:admin,miembro,administrador'
            ]);

            // Verificar que la empresa existe
            $empresa = DB::connection('juntify_panels')
                ->table('empresa')
                ->where('id', $empresaId)
                ->first();

            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada',
                    'empresa_id' => $empresaId
                ], 404);
            }

            // Verificar que el usuario existe
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'user_id' => $userId
                ], 404);
            }

            // No permitir cambiar el rol del dueño
            if ($empresa->iduser === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el rol del dueño de la empresa'
                ], 403);
            }

            // Verificar que el usuario es integrante de la empresa
            $integrante = DB::connection('juntify_panels')
                ->table('integrantes_empresa')
                ->where('empresa_id', $empresaId)
                ->where('iduser', $userId)
                ->first();

            if (!$integrante) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es integrante de esta empresa'
                ], 404);
            }

            // Actualizar el rol
            DB::connection('juntify_panels')
                ->table('integrantes_empresa')
                ->where('empresa_id', $empresaId)
                ->where('iduser', $userId)
                ->update([
                    'rol' => $request->rol,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Rol actualizado exitosamente',
                'data' => [
                    'empresa_id' => $empresaId,
                    'user_id' => $userId,
                    'username' => $user->username,
                    'email' => $user->email,
                    'nuevo_rol' => $request->rol
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un miembro de la empresa
     * 
     * @param int $empresaId
     * @param string $userId
     * @return JsonResponse
     */
    public function removeMember(int $empresaId, string $userId): JsonResponse
    {
        try {
            // Verificar que la empresa existe
            $empresa = DB::connection('juntify_panels')
                ->table('empresa')
                ->where('id', $empresaId)
                ->first();

            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada',
                    'empresa_id' => $empresaId
                ], 404);
            }

            // Verificar que el usuario existe
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'user_id' => $userId
                ], 404);
            }

            // No permitir eliminar al dueño
            if ($empresa->iduser === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar al dueño de la empresa'
                ], 403);
            }

            // Verificar que el usuario es integrante de la empresa
            $integrante = DB::connection('juntify_panels')
                ->table('integrantes_empresa')
                ->where('empresa_id', $empresaId)
                ->where('iduser', $userId)
                ->first();

            if (!$integrante) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es integrante de esta empresa'
                ], 404);
            }

            // Eliminar el integrante
            DB::connection('juntify_panels')
                ->table('integrantes_empresa')
                ->where('empresa_id', $empresaId)
                ->where('iduser', $userId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Miembro eliminado exitosamente',
                'data' => [
                    'empresa_id' => $empresaId,
                    'user_id' => $userId,
                    'username' => $user->username,
                    'email' => $user->email
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el miembro',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
