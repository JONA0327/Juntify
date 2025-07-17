<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Mostrar el formulario de registro.
     */
    public function show()
    {
        return view('auth.register');
    }

    /**
     * Procesar el registro de un nuevo usuario.
     * Generamos el UUID, guardamos el hash recibido (bcryptjs)
     * y redirigimos a la misma ruta con una flag en sesión.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'username'              => 'required|string|max:50|unique:users,username',
            'full_name'             => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string',
            'password_confirmation' => 'required|string|same:password',
            'role'                  => 'nullable|string|max:200',
        ]);

        $user = User::create([
            'id'         => (string) Str::uuid(),
            'username'   => $data['username'],
            'full_name'  => $data['full_name'],
            'email'      => $data['email'],
            'password'   => $data['password'],           // hash de bcryptjs
            'roles'      => $data['role'] ?? 'free',      // guardamos string
        ]);

        auth()->login($user);

        // Volvemos a la misma ruta para disparar el modal
        return redirect()
            ->route('register')
            ->with('registered', true);
    }
}
