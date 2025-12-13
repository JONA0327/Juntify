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

        if (!$user) {
            \Log::warning("Login attempt with non-existent {$field}: {$credentials['login']}");
            return back()
                ->withErrors(['login' => 'Credenciales inválidas'])
                ->withInput($request->only('login'));
        }

        if (!password_verify($credentials['password'], $user->password)) {
            \Log::warning("Login attempt with wrong password for user: {$user->email}");
            return back()
                ->withErrors(['login' => 'Credenciales inválidas'])
                ->withInput($request->only('login'));
        }

        Auth::login($user);
        $request->session()->regenerate();
        
        \Log::info("User logged in successfully: {$user->email}");
        
        return redirect()->route('profile.show')
             ->with('success', 'Bienvenido, ' . $user->full_name . '!');
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

