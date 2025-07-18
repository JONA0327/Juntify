<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // Muestra la vista del formulario de login
    public function showLoginForm()
    {
        // Ajusta la ruta de la vista si tu login.blade.php está en otra carpeta
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $user = User::where($field, $credentials['login'])->first();

        if ($user && password_verify($credentials['password'], $user->password)) {
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->route('profile.show')
                 ->with('success', 'Bienvenido, ' . $user->full_name . '!');
        }

        return back()->withErrors(['auth' => 'Credenciales inválidas']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')
            ->with('success', 'Has cerrado sesión correctamente.');
    }
}

