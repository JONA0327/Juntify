<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\IntegrantesEmpresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmpresaController extends Controller
{
    /**
     * Mostrar el panel de administración de empresas
     */
    public function index()
    {
        $empresas = Empresa::with('integrantes')->paginate(10);

        // Obtener usuarios con roles founder y enterprise de la BD principal
        $usuariosFounderEnterprise = User::whereIn('roles', ['founder', 'enterprise'])->get();

        return view('admin.empresas.index', compact('empresas', 'usuariosFounderEnterprise'));
    }

    /**
     * Registrar una nueva empresa
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'iduser' => 'required|string|exists:users,id',
            'nombre_empresa' => 'required|string|max:255',
            'es_administrador' => 'boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Verificar que el usuario tenga el rol correcto en la BD principal
        $user = User::find($request->iduser);
        if (!in_array($user->roles, ['founder', 'enterprise'])) {
            return back()->withErrors(['iduser' => 'El usuario debe tener rol founder o enterprise'])->withInput();
        }

        // Verificar que no exista ya una empresa para este usuario
        $empresaExistente = Empresa::where('iduser', $request->iduser)->first();
        if ($empresaExistente) {
            return back()->withErrors(['iduser' => 'Este usuario ya tiene una empresa registrada'])->withInput();
        }

        $empresa = Empresa::create([
            'iduser' => $request->iduser,
            'nombre_empresa' => $request->nombre_empresa,
            'rol' => 'administrador', // Rol automático
            'es_administrador' => true, // Siempre es administrador
        ]);

        return redirect()->route('admin.empresas.index')->with('success', 'Empresa registrada exitosamente');
    }

    /**
     * Mostrar detalles de una empresa específica
     */
    public function show($id)
    {
        $empresa = Empresa::with('integrantes')->findOrFail($id);

        // Obtener información del usuario desde la BD principal
        $usuario = User::find($empresa->iduser);

        return view('admin.empresas.show', compact('empresa', 'usuario'));
    }

    /**
     * Actualizar una empresa
     */
    public function update(Request $request, $id)
    {
        $empresa = Empresa::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre_empresa' => 'required|string|max:255',
            'es_administrador' => 'boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $empresa->update([
            'nombre_empresa' => $request->nombre_empresa,
            'rol' => 'administrador', // Mantener rol automático
            'es_administrador' => true, // Siempre es administrador
        ]);

        return back()->with('success', 'Empresa actualizada exitosamente');
    }

    /**
     * Eliminar una empresa
     */
    public function destroy($id)
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->delete();

        return redirect()->route('admin.empresas.index')->with('success', 'Empresa eliminada exitosamente');
    }

    /**
     * Agregar integrante a una empresa
     */
    public function addIntegrante(Request $request, $empresaId)
    {
        $validator = Validator::make($request->all(), [
            'iduser' => 'required|string|exists:users,id',
            'rol' => 'required|string|max:100',
            'permisos' => 'nullable|array',
            'permisos_text' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Verificar que el usuario no sea ya integrante de esta empresa
        $integranteExistente = IntegrantesEmpresa::where('iduser', $request->iduser)
                                                ->where('empresa_id', $empresaId)
                                                ->first();

        if ($integranteExistente) {
            return back()->withErrors(['iduser' => 'Este usuario ya es integrante de la empresa'])->withInput();
        }

        // Procesar permisos desde el textarea o array
        $permisos = [];
        if ($request->has('permisos') && is_array($request->permisos)) {
            $permisos = $request->permisos;
        } elseif ($request->has('permisos_text') && !empty($request->permisos_text)) {
            $permisos = array_filter(
                array_map('trim', explode("\n", $request->permisos_text)),
                function($permiso) {
                    return !empty($permiso);
                }
            );
        }

        IntegrantesEmpresa::create([
            'iduser' => $request->iduser,
            'empresa_id' => $empresaId,
            'rol' => $request->rol,
            'permisos' => $permisos,
        ]);

        return back()->with('success', 'Integrante agregado exitosamente');
    }

    /**
     * Eliminar integrante de una empresa
     */
    public function removeIntegrante($integranteId)
    {
        $integrante = IntegrantesEmpresa::findOrFail($integranteId);
        $integrante->delete();

        return back()->with('success', 'Integrante eliminado exitosamente');
    }

    /**
     * Actualizar el rol de un usuario y asignar plan automáticamente
     */
    public function updateUserRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'new_role' => 'required|in:free,basic,business,enterprise,founder,bni,developer,superadmin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $newRole = $request->new_role;
        $oldRole = $user->roles;

        // Roles protegidos que no deben cambiar
        $protectedRoles = ['bni', 'developer', 'founder', 'superadmin'];

        // Si es un rol protegido, solo cambiar el rol sin afectar plan ni expiración
        if (in_array($newRole, $protectedRoles)) {
            $user->update([
                'roles' => $newRole,
                'plan' => $newRole,
                'plan_code' => $newRole,
                'plan_expires_at' => null, // Sin expiración para roles protegidos
                'is_role_protected' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => "Rol cambiado a {$newRole} (sin expiración)",
                'user' => $user->fresh()
            ]);
        }

        // Para roles de plan (basic, business, enterprise)
        $planMapping = [
            'free' => ['plan' => 'free', 'plan_code' => 'free', 'expires' => null],
            'basic' => ['plan' => 'basic', 'plan_code' => 'basic', 'expires' => now()->addMonth()],
            'business' => ['plan' => 'business', 'plan_code' => 'business', 'expires' => now()->addMonth()],
            'enterprise' => ['plan' => 'enterprise', 'plan_code' => 'enterprise', 'expires' => now()->addMonth()],
        ];

        if (!array_key_exists($newRole, $planMapping)) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no válido para asignación de plan'
            ], 400);
        }

        $planData = $planMapping[$newRole];

        // Actualizar usuario con nuevo rol y plan
        $user->update([
            'roles' => $newRole,
            'plan' => $planData['plan'],
            'plan_code' => $planData['plan_code'],
            'plan_expires_at' => $planData['expires'],
            'is_role_protected' => false
        ]);

        // Crear registro en UserPlan si no es free
        if ($newRole !== 'free' && $planData['expires']) {
            // Expirar planes activos anteriores
            $user->plans()->where('status', 'active')->update([
                'status' => 'expired',
                'expires_at' => now()
            ]);

            // Crear nuevo plan
            $user->plans()->create([
                'plan_id' => $planData['plan_code'],
                'role' => $newRole,
                'starts_at' => now(),
                'expires_at' => $planData['expires'],
                'status' => 'active',
                'has_unlimited_roles' => false
            ]);
        }

        $message = $newRole === 'free' ?
            "Usuario cambiado a plan {$newRole}" :
            "Usuario cambiado a plan {$newRole} por 1 mes (expira el {$planData['expires']->format('d/m/Y')})";

        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => $user->fresh(),
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'expires_at' => $planData['expires'] ? $planData['expires']->format('Y-m-d H:i:s') : null
        ]);
    }

    /**
     * Buscar usuarios para administración de roles
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'users' => []
            ]);
        }

        $users = User::where(function ($q) use ($query) {
            $q->where('full_name', 'LIKE', "%{$query}%")
              ->orWhere('email', 'LIKE', "%{$query}%")
              ->orWhere('username', 'LIKE', "%{$query}%");
        })
        ->select('id', 'full_name', 'email', 'username', 'roles', 'plan', 'plan_code', 'plan_expires_at', 'is_role_protected')
        ->limit(10)
        ->get();

        return response()->json([
            'success' => true,
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'roles' => $user->roles,
                    'plan' => $user->plan,
                    'plan_code' => $user->plan_code,
                    'plan_expires_at' => $user->plan_expires_at ? $user->plan_expires_at->format('d/m/Y H:i') : null,
                    'is_role_protected' => $user->is_role_protected,
                    'plan_status' => $user->isPlanExpired() ? 'expired' : 'active'
                ];
            })
        ]);
    }
}
