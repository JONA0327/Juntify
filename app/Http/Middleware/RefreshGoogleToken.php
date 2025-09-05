<?php

namespace App\Http\Middleware;

use App\Services\GoogleTokenRefreshService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RefreshGoogleToken
{
    protected $tokenService;

    public function __construct(GoogleTokenRefreshService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplicar a usuarios autenticados
        if (Auth::check()) {
            $user = Auth::user();

            // Intentar renovar el token si es necesario
            $this->tokenService->refreshTokenForUser($user);
        }

        return $next($request);
    }
}
