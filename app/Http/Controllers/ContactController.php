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
use Illuminate\Support\Facades\Cache;

class ContactController extends Controller
{
    /**
     * Muestra la vista principal de contactos.
     */
    public function index(): View
    {
        return view('contacts.show');
    }

    /**
     * Devuelve la lista de contactos del usuario autenticado
     * junto con los usuarios de su organización actual.
     */
    public function list(Request $request): JsonResponse
    {
        $user = Auth::user();
        $includeRequests = $request->boolean('include_requests');

        $cacheKey = 'contacts_list_v2_' . $user->id;
        try {
        $payload = Cache::remember($cacheKey, 15, function () use ($user) {
            $contacts = Contact::with('contact')
                ->where('user_id', $user->id)
                ->get()
                ->map(function ($c) {
                    return [
                        'id' => $c->contact_id,
                        'contact_record_id' => $c->id,
                        'name' => $c->contact->full_name,
                        'email' => $c->contact->email,
                    ];
                });

            $existingContactIds = Contact::where('user_id', $user->id)->pluck('contact_id')->toArray();
            $userOrgId = $user->current_organization_id;
            $organizationUsers = collect();

            $buildUserRow = function($u){
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'organization_id' => $u->current_organization_id ?: 'Sin organización',
                    'group_name' => $u->group_name ?: 'Sin grupo',
                    'group_role' => $u->group_role ?: null,
                ];
            };

            if (!empty($userOrgId)) {
                $userGroups = DB::table('group_user')
                    ->where('user_id', $user->id)
                    ->pluck('id_grupo');

                $query = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                    ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                    ->where('users.id', '!=', $user->id)
                    ->whereNotIn('users.id', $existingContactIds);

                if ($userGroups->isNotEmpty()) {
                    $query->where(function($q) use ($userOrgId, $userGroups) {
                        $q->where('users.current_organization_id', $userOrgId)
                          ->orWhereIn('group_user.id_grupo', $userGroups);
                    });
                } else {
                    $query->where('users.current_organization_id', $userOrgId);
                }

                $organizationUsers = $query->select(
                        'users.id', 'users.full_name as name', 'users.email', 'users.current_organization_id',
                        'groups.nombre_grupo as group_name', 'group_user.rol as group_role'
                    )
                    ->orderBy('groups.nombre_grupo', 'asc')
                    ->orderBy('users.full_name', 'asc')
                    ->limit(60)
                    ->get()
                    ->map($buildUserRow);
            } else {
                $userGroups = DB::table('group_user')->where('user_id', $user->id)->pluck('id_grupo');
                if ($userGroups->isNotEmpty()) {
                    $organizationUsers = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                        ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                        ->where('users.id', '!=', $user->id)
                        ->whereNotIn('users.id', $existingContactIds)
                        ->whereIn('group_user.id_grupo', $userGroups)
                        ->select('users.id','users.full_name as name','users.email','users.current_organization_id','groups.nombre_grupo as group_name','group_user.rol as group_role')
                        ->orderBy('groups.nombre_grupo', 'asc')
                        ->orderBy('users.full_name', 'asc')
                        ->limit(60)
                        ->get()
                        ->map($buildUserRow);
                } else {
                    $userDomain = substr(strrchr($user->email, '@'), 1);
                    if ($userDomain && !in_array($userDomain, ['gmail.com','hotmail.com','yahoo.com','yahoo.com.mx','outlook.com'])) {
                        $organizationUsers = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                            ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                            ->where('users.email', 'LIKE', "%@{$userDomain}")
                            ->where('users.id', '!=', $user->id)
                            ->whereNotIn('users.id', $existingContactIds)
                            ->select('users.id','users.full_name as name','users.email','users.current_organization_id','groups.nombre_grupo as group_name','group_user.rol as group_role')
                            ->orderBy('groups.nombre_grupo', 'asc')
                            ->orderBy('users.full_name', 'asc')
                            ->limit(40)
                            ->get()
                            ->map($buildUserRow);
                    } else {
                        $organizationUsers = User::leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
                            ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
                            ->where('users.id', '!=', $user->id)
                            ->whereNotIn('users.id', $existingContactIds)
                            ->where(function($q){
                                $q->where('users.current_organization_id','')
                                  ->orWhereNull('users.current_organization_id');
                            })
                            ->select('users.id','users.full_name as name','users.email','users.current_organization_id','groups.nombre_grupo as group_name','group_user.rol as group_role')
                            ->orderByRaw("CASE WHEN users.email LIKE '%@juntify.com' THEN 1 ELSE 2 END")
                            ->orderBy('groups.nombre_grupo', 'asc')
                            ->orderBy('users.full_name', 'asc')
                            ->limit(30)
                            ->get()
                            ->map($buildUserRow);
                    }
                }
            }

            $hasOrganization = !empty($userOrgId);
            $hasGroups = DB::table('group_user')->where('user_id', $user->id)->exists();
            $showOrganizationSection = $hasOrganization || $hasGroups;

            return compact('contacts','organizationUsers','hasOrganization','hasGroups','showOrganizationSection');
        });

        $response = [
            'success' => true,
            'contacts' => $payload['contacts'],
            'users' => $payload['organizationUsers'],
            'has_organization' => $payload['hasOrganization'],
            'has_groups' => $payload['hasGroups'],
            'show_organization_section' => $payload['showOrganizationSection'],
        ];

    if ($includeRequests) {
            $requestsCache = Cache::remember('contact_requests_'.$user->id, 20, function() use ($user) {
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
                return compact('received','sent');
            });
            $response['requests'] = $requestsCache;
        }

        return response()->json($response);
        } catch (\Throwable $e) {
            // Fallback degradado: devolver datos cacheados previos
            $fallback = Cache::get($cacheKey);
            if ($fallback) {
                return response()->json([
                    'success' => true,
                    'contacts' => $fallback['contacts'] ?? [],
                    'users' => $fallback['organizationUsers'] ?? [],
                    'has_organization' => $fallback['hasOrganization'] ?? false,
                    'has_groups' => $fallback['hasGroups'] ?? false,
                    'show_organization_section' => $fallback['showOrganizationSection'] ?? false,
                    'rate_limited' => true,
                    'warning' => 'Servicio degradado: datos cacheados.'
                ], 200);
            }
            return response()->json([
                'success' => false,
                'error' => 'Servicio contactos no disponible',
                'rate_limited' => true
            ], 503);
        }
    }

    /**
     * Devuelve las solicitudes de contacto del usuario autenticado.
     */
    public function requests(): JsonResponse
    {
        $user = Auth::user();
        try {
        $cache = Cache::remember('contact_requests_'.$user->id, 20, function() use ($user) {
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
            return compact('received','sent');
        });
        return response()->json(array_merge(['success'=>true], $cache));
        } catch (\Throwable $e) {
            $fallback = Cache::get('contact_requests_'.$user->id);
            if ($fallback) {
                return response()->json(array_merge(['success'=>true,'rate_limited'=>true,'warning'=>'Servicio degradado: cache.'],$fallback));
            }
            return response()->json(['success'=>false,'error'=>'Servicio solicitudes no disponible','rate_limited'=>true],503);
        }
    }

    /**
     * Envía una solicitud de contacto al usuario especificado.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'email' => 'nullable|email',
            'username' => 'nullable|string',
        ]);

        if (empty($data['email']) && empty($data['username'])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un correo electrónico o nombre de usuario.'
            ], 422);
        }

        $contactUser = null;

        if (! empty($data['email'])) {
            $contactUser = User::where('email', $data['email'])->first();
        }

        if (! $contactUser && ! empty($data['username'])) {
            $contactUser = User::where('username', $data['username'])->first();
        }

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

        // Crear notificación usando tanto campos nuevos como legacy para máxima compatibilidad
        Notification::create([
            'remitente' => $user->id,          // legacy sender
            'emisor' => $contactUser->id,      // legacy receiver
            'user_id' => $contactUser->id,     // nuevo esquema (usuario que recibe)
            'from_user_id' => $user->id,       // nuevo esquema (usuario que envía)
            'type' => 'contact_request',
            'title' => 'Solicitud de contacto',
            'message' => 'Solicitud de contacto',
            'status' => 'pending',
            'data' => [],
            'read' => false,
        ]);

    // Invalidar caché de solicitudes para receptor y emisor para reflejar nueva solicitud inmediatamente
    Cache::forget('contact_requests_'.$contactUser->id);
    Cache::forget('contact_requests_'.$user->id);

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

            // Invalidar caché para ambos usuarios
            Cache::forget('contact_requests_'.$user->id);
            Cache::forget('contact_requests_'.$notification->remitente);
            return response()->json([
                'success' => true,
                'message' => 'Solicitud aceptada',
            ]);
        }

        $notification->delete();
        Cache::forget('contact_requests_'.$user->id);
        Cache::forget('contact_requests_'.$notification->remitente);

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

    /**
     * Cancela una solicitud de contacto enviada.
     */
    public function cancel(Request $request, Notification $notification): JsonResponse
    {
        $user = Auth::user();

        // Verificar que el usuario es quien envió la solicitud
        if ($notification->remitente !== $user->id || $notification->type !== 'contact_request') {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no válida',
            ], 404);
        }

        // Verificar que la solicitud está pendiente
        if ($notification->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud ya fue procesada',
            ], 400);
        }

        $notification->delete();

        // Invalidar caché para ambos usuarios
        Cache::forget('contact_requests_'.$user->id);
        Cache::forget('contact_requests_'.$notification->emisor);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud cancelada correctamente',
        ]);
    }
}

