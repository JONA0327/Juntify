<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserApiController extends Controller
{
    /**
     * Obtener lista de usuarios
     */
    public function listUsers(Request $request)
    {
        $query = User::select('id', 'username', 'email');

        // Filtro de búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Excluir usuarios que ya pertenecen a una empresa
        if ($request->has('exclude_empresa_id') && $request->exclude_empresa_id) {
            $empresaId = $request->exclude_empresa_id;
            
            // Obtener IDs de usuarios que ya están en la empresa
            $existingUserIds = DB::connection('juntify_panels')
                ->table('integrantes_empresa')
                ->where('empresa_id', $empresaId)
                ->pluck('iduser')
                ->toArray();
            
            // Excluir esos usuarios
            if (!empty($existingUserIds)) {
                $query->whereNotIn('id', $existingUserIds);
            }
        }

        $users = $query->orderBy('username', 'asc')->get();

        return response()->json([
            'success' => true,
            'users' => $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'name' => $user->username // Usar username como name
                ];
            }),
            'total' => $users->count()
        ]);
    }

    /**
     * Añadir usuario a una empresa
     */
    public function addToCompany(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string',
            'empresa_id' => 'required|integer',
            'rol' => 'required|string|in:admin,miembro,administrador'
        ]);

        // Verificar que el usuario existe
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        // Verificar que la empresa existe
        $empresa = DB::connection('juntify_panels')
            ->table('empresa')
            ->where('id', $request->empresa_id)
            ->first();

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.'
            ], 404);
        }

        // Verificar si ya es integrante
        $existingMember = DB::connection('juntify_panels')
            ->table('integrantes_empresa')
            ->where('iduser', $request->user_id)
            ->where('empresa_id', $request->empresa_id)
            ->first();

        if ($existingMember) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario ya es integrante de esta empresa.'
            ], 409);
        }

        // Insertar nuevo integrante
        $integranteId = DB::connection('juntify_panels')
            ->table('integrantes_empresa')
            ->insertGetId([
                'iduser' => $request->user_id,
                'empresa_id' => $request->empresa_id,
                'rol' => $request->rol,
                'permisos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario añadido a la empresa exitosamente.',
            'integrante' => [
                'id' => $integranteId,
                'user_id' => $request->user_id,
                'empresa_id' => $request->empresa_id,
                'rol' => $request->rol,
                'user' => [
                    'username' => $user->username,
                    'email' => $user->email,
                    'name' => $user->username // Usar username como name
                ],
                'empresa' => [
                    'id' => $empresa->id,
                    'nombre_empresa' => $empresa->nombre_empresa
                ]
            ]
        ], 201);
    }

    /**
     * Obtener contactos de un usuario
     * 
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContacts(string $userId)
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

            // Obtener los contactos del usuario
            $contacts = DB::table('contacts')
                ->where('user_id', $userId)
                ->get();

            $contactsData = [];

            foreach ($contacts as $contact) {
                // Obtener información del contacto desde la tabla users
                $contactUser = User::find($contact->contact_id);
                
                if ($contactUser) {
                    $contactsData[] = [
                        'id' => $contactUser->id,
                        'username' => $contactUser->username,
                        'email' => $contactUser->email,
                        'name' => $contactUser->username,
                        'fecha_agregado' => $contact->created_at
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email
                ],
                'contacts' => $contactsData,
                'total' => count($contactsData)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contactos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
