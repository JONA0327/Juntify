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

        // Support both bcrypt ($2y$) and bcryptjs ($2a$, $2b$, $2x$, $2y$) formats
        $passwordValid = $this->verifyPassword($credentials['password'], $user->password);
        
        if (!$passwordValid) {
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

    /**
     * Verify password against bcrypt hash (supports $2a$, $2b$, $2x$, $2y$ prefixes)
     * PHP's password_verify() doesn't work with bcryptjs ($2b$) hashes directly,
     * so we normalize the hash prefix to $2y$ which PHP accepts.
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        // If the hash is in bcryptjs format ($2a$, $2b$, $2x$), normalize it to $2y$
        // which PHP's password_verify() can handle
        if (preg_match('/^\$2[aby]\$/', $hash)) {
            $hash = '$2y$' . substr($hash, 4);
        }
        
        return password_verify($password, $hash);
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

