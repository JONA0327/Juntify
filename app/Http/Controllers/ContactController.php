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
                    'id' => $c->id,
                    'name' => $c->contact->name,
                    'email' => $c->contact->email,
                ];
            });

        $users = User::where('current_organization_id', $user->current_organization_id)
            ->where('id', '!=', $user->id)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'contacts' => $contacts,
            'users' => $users,
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
            'username' => 'nullable|string',
        ]);

        if (empty($data['email']) && empty($data['username'])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un correo o nombre de usuario.'
            ], 422);
        }

        if (!empty($data['email'])) {
            $contactUser = User::where('email', $data['email'])->first();
        } else {
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
}

