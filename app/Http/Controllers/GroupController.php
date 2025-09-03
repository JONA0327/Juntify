<?php

namespace App\Http\Controllers;

use App\Mail\GroupInvitation;
use App\Models\Group;
use App\Models\Notification;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\GroupCode;

class GroupController extends Controller
{
    public function publicIndex(Request $request)
    {
        $query = Group::query()
            ->select('id', 'id_organizacion', 'nombre_grupo', 'descripcion', 'miembros');

        if ($request->filled('id_organizacion')) {
            $query->where('id_organizacion', $request->input('id_organizacion'));
        }

        $groups = $query->get()->makeHidden(['created_at', 'updated_at']);

        return response()->json($groups);
    }

    public function publicShow($group)
    {
        $group = Group::query()
            ->select('id', 'id_organizacion', 'nombre_grupo', 'descripcion', 'miembros')
            ->findOrFail($group)
            ->makeHidden(['created_at', 'updated_at']);

        return response()->json($group);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $validated = $request->validate([
            'id_organizacion' => 'required|exists:organizations,id',
            'nombre_grupo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        // Seguridad adicional: solo owner o usuarios con rol colaborador/administrador en la organización pueden crear grupos
        $org = Organization::findOrFail($validated['id_organizacion']);
        $isOwner = $org->admin_id === $user->id;
        $hasOrgPermission = $org->groups()
            ->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id)
                  ->whereIn('group_user.rol', ['colaborador','administrador']);
            })->exists();

        if (!($isOwner || $hasOrgPermission)) {
            abort(403, 'No tienes permisos para crear grupos en esta organización');
        }

    $group = Group::create($validated + ['miembros' => 1]);

        // El creador queda como administrador si es owner de la org, sino como colaborador
        $creatorRole = $isOwner ? 'administrador' : 'colaborador';
    $group->users()->attach($user->id, ['rol' => $creatorRole]);
    // Asegurar pertenencia en la organización y refrescar contador
    $group->organization->users()->syncWithoutDetaching([$user->id => ['rol' => $creatorRole]]);
    $group->organization->refreshMemberCount();

        if (in_array($creatorRole, ['administrador', 'colaborador'])) {
            OrganizationActivity::create([
                'organization_id' => $group->id_organizacion,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'action' => 'group_create',
                'description' => $user->full_name . ' creó el grupo ' . $group->nombre_grupo,
            ]);
        }

        // Generar código de 6 dígitos único para el grupo
        if (!$group->code) {
            do {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (GroupCode::where('code', $code)->exists());
            GroupCode::create([
                'group_id' => $group->id,
                'code' => $code,
            ]);
        }

        return response()->json($group, 201);
    }

    public function show(Group $group)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $membership = $group->users()->where('users.id', $user->id)->first();
            $isOrgOwner = $group->organization && $group->organization->admin_id === $user->id;
            $roleAllowed = $membership && in_array($membership->pivot->rol, ['invitado', 'colaborador', 'administrador']);

            if (!($isOrgOwner || $roleAllowed)) {
                return response()->json(['message' => 'No tienes permisos para ver este grupo'], 403);
            }

            $group->load(['organization', 'users', 'containers' => function($q) {
                $q->where('is_active', true)->orderBy('created_at', 'desc');
            }]);

            $group->current_user_role = $membership ? $membership->pivot->rol : null;
            $group->organization_is_owner = $isOrgOwner;

            return response()->json($group);
        } catch (\Throwable $e) {
            Log::error('Error fetching group', [
                'group_id' => $group->id ?? null,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error interno al cargar el grupo'], 500);
        }
    }

    public function update(Request $request, Group $group)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $validated = $request->validate([
            'nombre_grupo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $group->update($validated);

        $membership = $group->users()->where('users.id', $user->id)->first();
        $userRole = $membership ? $membership->pivot->rol : null;

        if (in_array($userRole, ['administrador', 'colaborador'])) {
            OrganizationActivity::create([
                'organization_id' => $group->id_organizacion,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'action' => 'group_update',
                'description' => $user->full_name . ' actualizó el grupo ' . $group->nombre_grupo,
            ]);
        }

        return response()->json($group->fresh());
    }

    public function joinByCode(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $groupCode = GroupCode::where('code', $validated['code'])->first();
        if (!$groupCode) {
            return response()->json(['message' => 'Código inválido'], 404);
        }

        $group = $groupCode->group()->with('organization')->first();

        $belongsToAnotherOrg = DB::table('groups')
            ->join('group_user', 'groups.id', '=', 'group_user.id_grupo')
            ->where('group_user.user_id', $user->id)
            ->where('groups.id_organizacion', '<>', $group->id_organizacion)
            ->exists();

        if ($belongsToAnotherOrg) {
            return response()->json([
                'message' => 'Ya perteneces a otra organización'
            ], 409);
        }

        if (!$group->users()->where('users.id', $user->id)->exists()) {
            // Añadir al grupo y asegurar pertenencia en la organización
            $group->users()->attach($user->id, ['rol' => 'invitado']);
            $group->update(['miembros' => $group->users()->count()]);

            OrganizationActivity::create([
                'organization_id' => $group->id_organizacion,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'target_user_id' => $user->id,
                'action' => 'join_group',
                'description' => $user->full_name . ' se unió al grupo ' . $group->nombre_grupo,
            ]);
        }

        $organization = $group->organization;
        // Garantizar registro en pivot organization_user
        $organization->users()->syncWithoutDetaching([$user->id => ['rol' => 'invitado']]);
        $organization->refreshMemberCount();

        return response()->json([
            'organization' => $organization->fresh(),
            'group' => $group->fresh()
        ]);
    }

    public function invite(Request $request, Group $group)
    {
        try {
            Log::info('Group invitation attempt', [
                'group_id' => $group->id,
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            // Verificar permisos del usuario
            $user = auth()->user();
            if (!$user) {
                Log::error('Unauthorized invitation attempt - no user');
                return response()->json(['message' => 'No autorizado'], 401);
            }

            // Verificar rol del usuario en el grupo: solo colaborador o administrador pueden invitar
            $membership = $group->users()->where('users.id', $user->id)->first();
            if (!$membership) {
                Log::error('Unauthorized invitation attempt - user not in group', [
                    'user_id' => $user->id,
                    'group_id' => $group->id
                ]);
                return response()->json(['message' => 'No tienes permisos para invitar a este grupo'], 403);
            }

            $userRole = $membership->pivot->rol ?? null;
            if (!in_array($userRole, ['colaborador', 'administrador'])) {
                Log::warning('Invitation denied due to insufficient role', [
                    'user_id' => $user->id,
                    'group_id' => $group->id,
                    'user_role' => $userRole
                ]);
                return response()->json(['message' => 'Solo colaboradores o administradores pueden invitar'], 403);
            }

            $validated = $request->validate([
                'email' => 'required|email',
                'send_notification' => 'boolean',
                'role' => 'string|in:invitado,colaborador,administrador'
            ]);

            $targetUser = User::where('email', $validated['email'])->first();
            $sendNotification = $validated['send_notification'] ?? false;

            // Verificar si el usuario ya está en el grupo
            if ($targetUser && $group->users()->where('users.id', $targetUser->id)->exists()) {
                return response()->json([
                    'message' => 'El usuario ya es miembro de este grupo'
                ], 400);
            }

            // Validación: si el usuario objetivo ya pertenece a otra organización distinta, bloquear
            if ($targetUser) {
                $belongsToAnotherOrg = DB::table('groups')
                    ->join('group_user', 'groups.id', '=', 'group_user.id_grupo')
                    ->where('group_user.user_id', $targetUser->id)
                    ->where('groups.id_organizacion', '<>', $group->id_organizacion)
                    ->exists();
                if ($belongsToAnotherOrg) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario ya pertenece a otra organización y no puede ser invitado hasta que salga de la actual.'
                    ], 409);
                }
            }

            if ($sendNotification && $targetUser) {
                // Usuario existe en Juntify - enviar notificación interna
                Notification::create([
                    'remitente' => auth()->id(),
                    'emisor' => $targetUser->id,
                    'status' => 'pending',
                    'message' => "Has sido invitado al grupo {$group->nombre_grupo}",
                    'type' => 'group_invitation',
                    'data' => json_encode([
                        'group_id' => $group->id,
                        'role' => $validated['role'] ?? 'invitado'
                    ])
                ]);

                Log::info('Group invitation notification sent', [
                    'target_user_id' => $targetUser->id,
                    'group_id' => $group->id
                ]);

                OrganizationActivity::create([
                    'organization_id' => $group->id_organizacion,
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'target_user_id' => $targetUser->id,
                    'action' => 'invite_user',
                    'description' => $user->full_name . ' invitó al correo ' . $validated['email'],
                ]);

                return response()->json([
                    'success' => true,
                    'type' => 'notification',
                    'message' => 'Notificación enviada al usuario de Juntify'
                ]);
            } else {
                // Usuario no existe o se forzó email - enviar por correo
                $code = Str::uuid()->toString();

                Log::info('Group invitation email prepared', [
                    'email' => $validated['email'],
                    'group_id' => $group->id,
                    'code' => $code
                ]);

                // Aquí puedes implementar el envío de email
                // Mail::to($validated['email'])->send(new GroupInvitation($code, $group->id));

                OrganizationActivity::create([
                    'organization_id' => $group->id_organizacion,
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'target_user_id' => $targetUser?->id,
                    'action' => 'invite_user',
                    'description' => $user->full_name . ' invitó al correo ' . $validated['email'],
                ]);

                return response()->json([
                    'success' => true,
                    'type' => 'email',
                    'message' => 'Invitación enviada por correo electrónico'
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in group invitation', [
                'errors' => $e->errors(),
                'group_id' => $group->id
            ]);
            return response()->json([
                'message' => 'Datos de invitación inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Unexpected error in group invitation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'group_id' => $group->id
            ]);
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function accept(Group $group)
    {
        $user = auth()->user();

    // Añadir al grupo y asegurar pertenencia en la organización
    $group->users()->syncWithoutDetaching([$user->id => ['rol' => 'invitado']]);
    $group->update(['miembros' => $group->users()->count()]);
    $group->organization->users()->syncWithoutDetaching([$user->id => ['rol' => 'invitado']]);
    $group->organization->refreshMemberCount();

        Notification::where('emisor', $user->id)
            ->where('type', 'group_invitation')
            ->delete();

        OrganizationActivity::create([
            'organization_id' => $group->id_organizacion,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'target_user_id' => $user->id,
            'action' => 'join_group',
            'description' => $user->full_name . ' se unió al grupo ' . $group->nombre_grupo,
        ]);

        $group->load('users');

        return response()->json(['joined' => true, 'group' => $group]);
    }

    public function members(Group $group)
    {
        $group->load('users');

        return view('group.members', compact('group'));
    }

    public function updateMemberRole(Request $request, Group $group, User $user)
    {
        // Sólo owner de organización o administradores del grupo pueden cambiar roles
        $actor = auth()->user();
        $org = $group->organization;
        $isOwner = $org->admin_id === $actor->id;
        $actorMembership = $group->users()->where('users.id', $actor->id)->first();
        $actorRole = $actorMembership ? $actorMembership->pivot->rol : null;
        if (!($isOwner || $actorRole === 'administrador')) {
            return response()->json(['message' => 'No tienes permisos para editar roles'], 403);
        }

        $validated = $request->validate([
            'rol' => 'required|in:invitado,colaborador,administrador',
        ]);

        $group->users()->updateExistingPivot($user->id, ['rol' => $validated['rol']]);

        return response()->json(['role_updated' => true]);
    }

    public function removeMember(Group $group, User $user)
    {
        try {
            // Sólo owner de organización o administradores del grupo pueden quitar miembros
            $actor = auth()->user();
            $org = $group->organization;
            $isOwner = $org->admin_id === $actor->id;
            $actorMembership = $group->users()->where('users.id', $actor->id)->first();
            $actorRole = $actorMembership ? $actorMembership->pivot->rol : null;

            if ($actorRole && !in_array($actorRole, Group::ROLES, true)) {
                return response()->json(['message' => 'Rol del usuario no válido'], 400);
            }

            if (!($isOwner || $actorRole === Group::ROLE_ADMINISTRADOR)) {
                return response()->json(['message' => 'No tienes permisos para quitar miembros'], 403);
            }

            // Quitar del grupo
            $group->users()->detach($user->id);
            $group->update(['miembros' => $group->users()->count()]);

            // Si ya no pertenece a ningún otro grupo de la organización, quitarlo de la organización
            $stillInAnyGroup = Group::where('id_organizacion', $org->id)
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })->exists();
            if (!$stillInAnyGroup) {
                $org->users()->detach($user->id);
            }
            $org->refreshMemberCount();

            OrganizationActivity::create([
                'organization_id' => $group->id_organizacion,
                'group_id' => $group->id,
                'user_id' => $actor->id,
                'target_user_id' => $user->id,
                'action' => 'remove_member',
                'description' => $actor->full_name . ' expulsó a ' . $user->full_name . ' del grupo ' . $group->nombre_grupo,
            ]);

            return response()->json(['removed' => true, 'message' => 'Miembro removido correctamente']);
        } catch (\Throwable $e) {
            Log::error('Error removing member', [
                'group_id' => $group->id ?? null,
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error interno al remover el miembro'], 500);
        }
    }

    public function destroy(Group $group)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        try {
            $organization = $group->organization;
            // Sólo owner o miembros con rol colaborador/administrador pueden eliminar el grupo
            $isOwner = $organization->admin_id === $user->id;
            $actorMembership = $group->users()->where('users.id', $user->id)->first();
            $actorRole = $actorMembership ? $actorMembership->pivot->rol : null;
            if (!($isOwner || in_array($actorRole, ['colaborador','administrador']))) {
                return response()->json(['message' => 'No tienes permisos para eliminar este grupo'], 403);
            }

            $groupName = $group->nombre_grupo;
            $groupId = $group->id;
            $organizationId = $group->id_organizacion;

            DB::transaction(function () use ($group, $organization, $groupName, $groupId, $organizationId, $user, $isOwner, $actorRole) {
                $group->delete();
                $organization->refreshMemberCount();

                if ($isOwner || in_array($actorRole, ['colaborador', 'administrador'])) {
                    OrganizationActivity::create([
                        'organization_id' => $organizationId,
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'action' => 'group_delete',
                        'description' => $user->full_name . ' eliminó el grupo ' . $groupName,
                    ]);
                }
            });

            return response()->json(['deleted' => true]);
        } catch (\Throwable $e) {
            Log::error('Error deleting group', [
                'group_id' => $group->id,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error interno al eliminar el grupo'], 500);
        }
    }

    public function getContainers(Group $group)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $membership = $group->users()->where('users.id', $user->id)->first();
        $isOrgOwner = optional($group->organization)->admin_id === $user->id;
        $roleAllowed = $membership && in_array($membership->pivot->rol, ['invitado', 'colaborador', 'administrador']);
        if (!($isOrgOwner || $roleAllowed)) {
            abort(403, 'No tienes acceso a este grupo');
        }

        $containers = $group->containers()
            ->with('group')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($container) {
                return [
                    'id' => $container->id,
                    'name' => $container->name,
                    'description' => $container->description,
                    'created_at' => $container->created_at->format('d/m/Y H:i'),
                    'meetings_count' => $container->meetingRelations()->count(),
                    'is_company' => true,
                    'group_name' => $container->group->nombre_grupo ?? null,
                ];
            });

        return response()->json([
            'containers' => $containers
        ]);
    }
}
