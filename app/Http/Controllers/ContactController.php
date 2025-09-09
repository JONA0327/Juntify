<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
     * Crea un nuevo contacto para el usuario autenticado.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'email' => 'nullable|email',
            'name' => 'nullable|string',
        ]);

        if (empty($data['email']) && empty($data['name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un correo o nombre.'
            ], 422);
        }

        $contactUser = User::query()
            ->when(!empty($data['email']), fn($q) => $q->where('email', $data['email']))
            ->when(empty($data['email']) && !empty($data['name']), fn($q) => $q->where('name', $data['name']))
            ->first();

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

        $contact = Contact::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'contact_id' => $contactUser->id,
        ]);

        return response()->json([
            'success' => true,
            'contact' => [
                'id' => $contact->id,
                'name' => $contactUser->name,
                'email' => $contactUser->email,
            ]
        ], 201);
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

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contacto eliminado'
        ]);
    }
}

