<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserBlockedMail;
use App\Mail\UserRoleChangedMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    protected function authorizeAdmin(Request $request): User
    {
        $admin = $request->user();

        if (! $admin || ! in_array($admin->roles, ['superadmin', 'developer'])) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        return $admin;
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.users');
    }

    public function list(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $users = User::query()
            ->with('blockedBy')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => $this->transformUser($user));

        // Definir todos los roles disponibles del sistema
        $allRoles = [
            'free',
            'basic',
            'business',
            'enterprise',
            'founder',
            'bni',
            'developer',
            'superadmin'
        ];

        return response()->json([
            'users' => $users,
            'available_roles' => $allRoles
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $data = $request->validate([
            'role' => ['required', 'string', 'max:200'],
        ]);

        // Prevenir cambio de rol en usuarios superadmin y developer
        if (in_array($user->roles, ['superadmin', 'developer'])) {
            return response()->json([
                'message' => 'No se puede cambiar el rol de usuarios con roles protegidos (superadmin, developer).'
            ], 403);
        }

        $oldRole = $user->roles;
        
        // Configurar expiración y protección según el tipo de rol
        $protectedRoles = ['bni', 'developer', 'founder', 'superadmin'];
        $planExpiresAt = null;
        $isProtected = false;
        
        if (in_array($data['role'], $protectedRoles)) {
            $planExpiresAt = null;
            $isProtected = true;
        } elseif ($data['role'] === 'free') {
            $planExpiresAt = null;
            $isProtected = false;
        } else {
            // Para basic, business, enterprise
            $planExpiresAt = now()->addMonth();
            $isProtected = false;
        }
        
        // Primero desactivar la protección para evitar el trigger de la base de datos
        if ($user->is_role_protected) {
            $user->updateQuietly(['is_role_protected' => false]);
        }
        
        // Ahora actualizar todos los campos incluyendo el rol
        $user->updateQuietly([
            'roles' => $data['role'],
            'plan' => $data['role'],
            'plan_code' => $data['role'],
            'plan_expires_at' => $planExpiresAt,
            'is_role_protected' => $isProtected
        ]);
        
        $user = $user->fresh(['blockedBy']);

        return response()->json($this->transformUser($user));
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        if ($admin->id === $user->id) {
            return response()->json([
                'message' => 'No puedes bloquear tu propia cuenta.',
            ], 422);
        }

        $data = $request->validate([
            'duration' => ['required', Rule::in(['1_day', '1_week', '1_month', 'permanent'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $blockedUntil = match ($data['duration']) {
            '1_day' => Carbon::now()->addDay(),
            '1_week' => Carbon::now()->addWeek(),
            '1_month' => Carbon::now()->addMonth(),
            default => null,
        };

        $user->blocked_at = Carbon::now();
        $user->blocked_reason = $data['reason'];
        $user->blocked_permanent = $data['duration'] === 'permanent';
        $user->blocked_until = $user->blocked_permanent ? null : $blockedUntil;
        $user->blocked_by = $admin->id;
        $user->save();
        $user = $user->fresh(['blockedBy']);

        Mail::to($user->email)->send(new UserBlockedMail(
            $user,
            $admin,
            $data['reason'],
            $user->blocked_permanent,
            $user->blockingEndsAt()
        ));

        return response()->json($this->transformUser($user));
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user->blocked_at = null;
        $user->blocked_reason = null;
        $user->blocked_permanent = false;
        $user->blocked_until = null;
        $user->blocked_by = null;
        $user->save();

        $user = $user->fresh(['blockedBy']);

        return response()->json($this->transformUser($user));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        if (! $user->blocked_permanent) {
            return response()->json([
                'message' => 'Solo puedes eliminar usuarios con bloqueo permanente.',
            ], 422);
        }

        if (in_array($user->roles, ['superadmin', 'developer'])) {
            return response()->json([
                'message' => 'No puedes eliminar cuentas críticas del sistema.',
            ], 422);
        }

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta desde el panel administrativo.',
            ], 422);
        }

        $user->delete();

        return response()->json(['deleted' => true]);
    }

    protected function transformUser(User $user): array
    {
        $blockedUntil = $user->blockingEndsAt();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'roles' => $user->roles,
            'organization' => $user->organization,
            'created_at' => optional($user->created_at)->toIso8601String(),
            'updated_at' => optional($user->updated_at)->toIso8601String(),
            'blocked_at' => optional($user->blocked_at)->toIso8601String(),
            'blocked' => $user->isBlocked(),
            'blocked_permanent' => (bool) $user->blocked_permanent,
            'blocked_reason' => $user->blocked_reason,
            'blocked_until' => $blockedUntil?->toIso8601String(),
            'blocked_until_human' => $blockedUntil && $blockedUntil->isFuture() ? $blockedUntil->diffForHumans() : null,
            'blocked_by' => $user->blocked_by,
            'blocked_by_name' => optional($user->blockedBy)->full_name ?? optional($user->blockedBy)->username,
        ];
    }
}
