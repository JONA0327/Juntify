<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthValidationController extends Controller
{
    /**
     * Validar usuario y su pertenencia a una empresa
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'nombre_empresa' => 'required|string',
        ]);

        // 1. Buscar usuario por email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'belongs_to_company' => false,
                'message' => 'Usuario no encontrado.'
            ], 401);
        }

        // 2. Verificar contraseña (soportar bcrypt y blowfish)
        $passwordMatches = false;
        
        try {
            // Intentar con Hash::check (bcrypt estándar)
            $passwordMatches = Hash::check($request->password, $user->password);
        } catch (\RuntimeException $e) {
            // Si falla, intentar con password_verify (soporta $2b$)
            $passwordMatches = password_verify($request->password, $user->password);
        }
        
        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'belongs_to_company' => false,
                'message' => 'Contraseña incorrecta.'
            ], 401);
        }

        // 3. Verificar pertenencia a empresa en juntify_panels
        $empresa = DB::connection('juntify_panels')
            ->table('integrantes_empresa')
            ->join('empresa', 'integrantes_empresa.empresa_id', '=', 'empresa.id')
            ->where('integrantes_empresa.iduser', $user->id)
            ->where('empresa.nombre_empresa', $request->nombre_empresa)
            ->select(
                'empresa.id as empresa_id',
                'empresa.nombre_empresa',
                'integrantes_empresa.rol'
            )
            ->first();

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'belongs_to_company' => false,
                'message' => 'El usuario no pertenece a la empresa ' . $request->nombre_empresa . '.'
            ], 403);
        }

        // 4. Usuario válido y pertenece a la empresa
        return response()->json([
            'success' => true,
            'belongs_to_company' => true,
            'message' => 'Autenticación exitosa.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? $user->username,
                'email' => $user->email,
                'username' => $user->username,
            ],
            'company' => [
                'id' => $empresa->empresa_id,
                'nombre' => $empresa->nombre_empresa,
                'rol_usuario' => $empresa->rol
            ]
        ], 200);
    }

    /**
     * Verificar solo si un usuario pertenece a una empresa (sin validar contraseña)
     * Útil para validaciones rápidas cuando ya hay sesión activa
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkCompanyMembership(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string',
            'nombre_empresa' => 'required|string',
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
                'belongs_to_company' => false,
            ], 404);
        }

        // Verificar pertenencia a empresa en juntify_panels
        $empresa = DB::connection('juntify_panels')
            ->table('integrantes_empresa')
            ->join('empresa', 'integrantes_empresa.empresa_id', '=', 'empresa.id')
            ->where('integrantes_empresa.iduser', $user->id)
            ->where('empresa.nombre_empresa', $request->nombre_empresa)
            ->select(
                'empresa.id as empresa_id',
                'empresa.nombre_empresa',
                'integrantes_empresa.rol'
            )
            ->first();

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece a la empresa especificada',
                'belongs_to_company' => false,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'belongs_to_company' => true,
            'company' => [
                'id' => $empresa->empresa_id,
                'nombre' => $empresa->nombre_empresa,
                'rol_usuario' => $empresa->rol,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? $user->username,
                'email' => $user->email,
            ]
        ], 200);
    }
}
