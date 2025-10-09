<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isBlocked') && $user->isBlocked()) {
            $message = 'Tu cuenta está bloqueada temporalmente.';
            if ($user->blocked_permanent) {
                $message = 'Tu cuenta ha sido bloqueada de forma permanente.';
            } elseif ($user->blockingEndsAt()) {
                $message = 'Tu cuenta está bloqueada hasta ' . $user->blockingEndsAt()->timezone(config('app.timezone'))->format('d/m/Y H:i');
            }

            if ($user->blocked_reason) {
                $message .= ' Motivo: ' . $user->blocked_reason;
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'reason' => $user->blocked_reason,
                    'permanent' => (bool) $user->blocked_permanent,
                    'blocked_until' => optional($user->blockingEndsAt())->toIso8601String(),
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => $message,
            ]);
        }

        return $next($request);
    }
}
