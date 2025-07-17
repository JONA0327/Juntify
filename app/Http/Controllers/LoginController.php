<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {

        $credentials = $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',  // aquí PUT plaintext
        ]);

        // Buscamos el usuario por username o email
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $user = User::where($field, $credentials['login'])->first();

        if ($user && password_verify($credentials['password'], $user->password)) {
            // Si coincide el plaintext con el hash de bcryptjs
            Auth::login($user);
            $request->session()->regenerate();
            // Redirect to the profile edit page after successful login
            return redirect()->route('profile.show')
                 ->with('success', 'Bienvenido, ' . $user->full_name . '!');
        }

        return back()->withErrors(['auth' => 'Credenciales inválidas']);
    }
      public function logout(Request $request)
    {
        Auth::logout();                         // cierra la sesión
        $request->session()->invalidate();      // invalida todos los datos de sesión
        $request->session()->regenerateToken(); // genera un nuevo CSRF token

       return redirect('/')->with('success', 'Has cerrado sesión correctamente.');// redirige a la página de inicio
    }
}
