<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Muestra la vista principal de contactos.
     */
    public function index(): View
    {
        return view('contacts.index');
    }

    /**
     * Devuelve la lista de contactos del usuario autenticado
     * junto con los usuarios de su organización actual.
     */
    public function list(): JsonResponse
    {
        $user = Auth::user();

        $contacts = Contact::with('contact')
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->contact_id, // ID del usuario contacto, no del registro Contact
                    'contact_record_id' => $c->id, // ID del registro Contact para operaciones de eliminación
                    'name' => $c->contact->full_name,
                    'email' => $c->contact->email,
                ];
            });

        // Obtener IDs de usuarios que ya son contactos para excluirlos
        $existingContactIds = Contact::where('user_id', $user->id)->pluck('contact_id')->toArray();

        // Determinar la organización del usuario actual
        $userOrgId = $user->current_organization_id;

        // Obtener todos los usuarios de la misma organización con información de grupos
        $organizationUsers = collect();

        if (!empty($userOrgId)) {
            // Si el usuario tiene organización válida, mostrar:
            // 1. Usuarios de la misma organización
            // 2. Usuarios que estén en los mismos grupos (aunque no tengan la misma organización)

            // Obtener los grupos del usuario actual
            $userGroups = DB::table('group_user')
                ->where('user_id', $user->id)
                ->pluck('id_grupo');

            $query = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                ->where('users.id', '!=', $user->id)
                ->whereNotIn('users.id', $existingContactIds);

            if ($userGroups->isNotEmpty()) {
                // Mostrar usuarios de la misma organización O de los mismos grupos
                $query->where(function($q) use ($userOrgId, $userGroups) {
                    $q->where('users.current_organization_id', $userOrgId)
                      ->orWhereIn('group_user.id_grupo', $userGroups);
                });
            } else {
                // Si no tiene grupos, solo mostrar usuarios de la misma organización
                $query->where('users.current_organization_id', $userOrgId);
            }

            $organizationUsers = $query
                ->select(
                    'users.id',
                    'users.full_name as name',
                    'users.email',
                    'users.current_organization_id',
                    'groups.nombre_grupo as group_name',
                    'group_user.rol as group_role'
                )
                ->orderBy('groups.nombre_grupo', 'asc')
                ->orderBy('users.full_name', 'asc')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'organization_id' => $u->current_organization_id,
                        'group_name' => $u->group_name ?: 'Sin grupo',
                        'group_role' => $u->group_role ?: null,
                    ];
                });
        } else {
            // Si no tiene organización, buscar usuarios en los mismos grupos
            $userGroups = DB::table('group_user')
                ->where('user_id', $user->id)
                ->pluck('id_grupo');

            if ($userGroups->isNotEmpty()) {
                // Mostrar usuarios que estén en los mismos grupos
                $organizationUsers = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                    ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                    ->where('users.id', '!=', $user->id)
                    ->whereNotIn('users.id', $existingContactIds)
                    ->whereIn('group_user.id_grupo', $userGroups)
                    ->select(
                        'users.id',
                        'users.full_name as name',
                        'users.email',
                        'users.current_organization_id',
                        'groups.nombre_grupo as group_name',
                        'group_user.rol as group_role'
                    )
                    ->orderBy('groups.nombre_grupo', 'asc')
                    ->orderBy('users.full_name', 'asc')
                    ->get()
                    ->map(function ($u) {
                        return [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                            'organization_id' => $u->current_organization_id ?: 'Sin organización',
                            'group_name' => $u->group_name ?: 'Sin grupo',
                            'group_role' => $u->group_role ?: null,
                        ];
                    });
            } else {
                // Si no pertenece a ningún grupo, usar la lógica anterior basada en dominio
                $userDomain = substr(strrchr($user->email, "@"), 1);

                if ($userDomain && !in_array($userDomain, ['gmail.com', 'hotmail.com', 'yahoo.com', 'yahoo.com.mx', 'outlook.com'])) {
                    // Para dominios corporativos, mostrar usuarios del mismo dominio con grupos
                    $organizationUsers = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                        ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                        ->where('users.email', 'LIKE', "%@{$userDomain}")
                        ->where('users.id', '!=', $user->id)
                        ->whereNotIn('users.id', $existingContactIds)
                        ->select(
                            'users.id',
                            'users.full_name as name',
                            'users.email',
                            'users.current_organization_id',
                            'groups.nombre_grupo as group_name',
                            'group_user.rol as group_role'
                        )
                        ->orderBy('groups.nombre_grupo', 'asc')
                        ->orderBy('users.full_name', 'asc')
                        ->limit(20)
                        ->get()
                        ->map(function ($u) {
                            return [
                                'id' => $u->id,
                                'name' => $u->name,
                                'email' => $u->email,
                                'organization_id' => $u->current_organization_id,
                                'group_name' => $u->group_name ?: 'Sin grupo',
                                'group_role' => $u->group_role ?: null,
                            ];
                        });
                } else {
                    // Para dominios genéricos, mostrar usuarios de la misma "organización" (sin organización) con grupos
                    $organizationUsers = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                        ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                        ->where('users.id', '!=', $user->id)
                        ->whereNotIn('users.id', $existingContactIds)
                        ->where(function($query) {
                            $query->where('users.current_organization_id', '')
                                  ->orWhereNull('users.current_organization_id');
                        })
                        ->select(
                            'users.id',
                            'users.full_name as name',
                            'users.email',
                            'users.current_organization_id',
                            'groups.nombre_grupo as group_name',
                            'group_user.rol as group_role'
                        )
                        ->orderByRaw("CASE WHEN users.email LIKE '%@juntify.com' THEN 1 ELSE 2 END")
                        ->orderBy('groups.nombre_grupo', 'asc')
                        ->orderBy('users.full_name', 'asc')
                        ->limit(15)
                        ->get()
                        ->map(function ($u) {
                            return [
                                'id' => $u->id,
                                'name' => $u->name,
                                'email' => $u->email,
                                'organization_id' => $u->current_organization_id ?: 'Sin organización',
                                'group_name' => $u->group_name ?: 'Sin grupo',
                                'group_role' => $u->group_role ?: null,
                            ];
                        });
                }
            }
        }

        // Determinar si el usuario tiene organización o está en grupos
        $hasOrganization = !empty($userOrgId);
        $hasGroups = DB::table('group_user')->where('user_id', $user->id)->exists();
        $showOrganizationSection = $hasOrganization || $hasGroups;

        return response()->json([
            'success' => true,
            'contacts' => $contacts,
            'users' => $organizationUsers,
            'has_organization' => $hasOrganization,
            'has_groups' => $hasGroups,
            'show_organization_section' => $showOrganizationSection,
        ]);
    }

    /**
     * Devuelve las solicitudes de contacto del usuario autenticado.
     */
    public function requests(): JsonResponse
    {
        $user = Auth::user();

        $received = Notification::where('emisor', $user->id)
            ->where('type', 'contact_request')
            ->where('status', 'pending')
            ->with('sender')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'sender' => [
                        'id' => $notification->sender->id,
                        'name' => $notification->sender->full_name,
                        'email' => $notification->sender->email,
                    ],
                    'message' => $notification->message,
                    'created_at' => $notification->created_at,
                ];
            });

        $sent = Notification::where('remitente', $user->id)
            ->where('type', 'contact_request')
            ->where('status', 'pending')
            ->with('receiver')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'receiver' => [
                        'id' => $notification->receiver->id,
                        'name' => $notification->receiver->full_name,
                        'email' => $notification->receiver->email,
                    ],
                    'message' => $notification->message,
                    'created_at' => $notification->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'received' => $received,
            'sent' => $sent,
        ]);
    }

    /**
     * Envía una solicitud de contacto al usuario especificado.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'email' => 'nullable|email',
        ]);

        if (empty($data['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un correo electrónico.'
            ], 422);
        }

        $contactUser = User::where('email', $data['email'])->first();

        if (! $contactUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        $exists = Contact::where('user_id', $user->id)
            ->where('contact_id', $contactUser->id)
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'El contacto ya existe.'
            ], 409);
        }

        Notification::create([
            'remitente' => $user->id,
            'emisor' => $contactUser->id,
            'type' => 'contact_request',
            'message' => 'Solicitud de contacto',
            'status' => 'pending',
            'data' => [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud enviada'
        ]);
    }

    /**
     * Responde a una solicitud de contacto.
     */
    public function respond(Request $request, Notification $notification): JsonResponse
    {
        $user = Auth::user();

        if ($notification->emisor !== $user->id || $notification->type !== 'contact_request') {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no válida',
            ], 404);
        }

        $data = $request->validate([
            'action' => 'required|in:accept,reject',
        ]);

        if ($data['action'] === 'accept') {
            DB::transaction(function () use ($user, $notification) {
                Contact::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'contact_id' => $notification->remitente,
                ]);

                Contact::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $notification->remitente,
                    'contact_id' => $user->id,
                ]);

                $notification->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Solicitud aceptada',
            ]);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud rechazada',
        ]);
    }

    /**
     * Elimina un contacto del usuario autenticado.
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $user = Auth::user();
        if ($contact->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }
        DB::transaction(function () use ($contact) {
            Contact::where('user_id', $contact->contact_id)
                ->where('contact_id', $contact->user_id)
                ->delete();

            $contact->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Contacto eliminado'
        ]);
    }

    /**
     * Busca usuarios por email o nombre de usuario.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'query' => 'required|string|min:3',
        ]);

        $query = $data['query'];

        // Buscar usuarios que coincidan con el email o nombre
        $users = User::where(function ($q) use ($query) {
                $q->where('email', 'LIKE', "%{$query}%")
                  ->orWhere('full_name', 'LIKE', "%{$query}%")
                  ->orWhere('username', 'LIKE', "%{$query}%");
            })
            ->where('id', '!=', $user->id) // Excluir al usuario actual
            ->limit(10) // Limitar resultados
            ->get(['id', 'full_name as name', 'email', 'username'])
            ->map(function ($foundUser) {
                return [
                    'id' => $foundUser->id,
                    'name' => $foundUser->name,
                    'email' => $foundUser->email,
                    'username' => $foundUser->username,
                    'exists_in_juntify' => true,
                ];
            });

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }
}

