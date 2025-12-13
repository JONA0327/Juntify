<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\ErroresSistema;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $loginField = $this->input('login');

        // Determinar si el login es email o username
        $credentials = [];
        if (filter_var($loginField, FILTER_VALIDATE_EMAIL)) {
            $credentials = ['email' => $loginField, 'password' => $this->input('password')];
        } else {
            $credentials = ['username' => $loginField, 'password' => $this->input('password')];
        }

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Verificar si el usuario está en la lista de backup de contraseñas
            // Buscar por email o username
            $emailToCheck = $credentials['email'] ?? null;
            if (!$emailToCheck && isset($credentials['username'])) {
                // Si se intentó con username, buscar el email del usuario
                $user = User::where('username', $credentials['username'])->first();
                $emailToCheck = $user ? $user->email : $credentials['username'];
            }

            if ($emailToCheck && ErroresSistema::needsPasswordUpdate($emailToCheck)) {
                // Lanzar excepción especial para usuarios con errores en el sistema
                throw ValidationException::withMessages([
                    'login' => 'password_update_required',
                ]);
            }

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        // Verificar si el usuario está bloqueado
        /** @var User|null $authenticatedUser */
        $authenticatedUser = Auth::user();
        if ($authenticatedUser instanceof User && $authenticatedUser->isBlocked()) {
            Auth::guard('web')->logout(); // Cerrar sesión inmediatamente

            $message = 'Tu cuenta está bloqueada.';

            if ($authenticatedUser->blocked_permanent) {
                $message = 'Tu cuenta ha sido bloqueada de forma permanente.';
            } elseif ($authenticatedUser->blockingEndsAt()) {
                $blockEndDate = $authenticatedUser->blockingEndsAt()
                    ->setTimezone(config('app.timezone', 'UTC'))
                    ->format('d/m/Y \a \l\a\s H:i');

                $message = "Tu cuenta está bloqueada hasta el {$blockEndDate}.";
            }

            // Agregar motivo si está disponible
            if (!empty($authenticatedUser->blocked_reason)) {
                $message .= " Motivo: {$authenticatedUser->blocked_reason}";
            }

            throw ValidationException::withMessages([
                'login' => $message,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
