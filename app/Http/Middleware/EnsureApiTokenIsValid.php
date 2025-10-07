<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $this->resolveToken($request);

        if (!$plainToken) {
            return $this->unauthorizedResponse('Se requiere un token de acceso válido.');
        }

        $token = ApiToken::with('user')
            ->where('token_hash', ApiToken::hashToken($plainToken))
            ->first();

        if (!$token || !$token->user) {
            return $this->unauthorizedResponse('El token proporcionado no es válido.');
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();

            return $this->unauthorizedResponse('El token ha expirado, genera uno nuevo.');
        }

        if (!$request->user()) {
            Auth::setUser($token->user);
        }

        $request->setUserResolver(static fn () => $token->user);
        $request->attributes->set('apiToken', $token);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    protected function resolveToken(Request $request): ?string
    {
        if ($token = $request->bearerToken()) {
            return $token;
        }

        if ($headerToken = $request->header('X-API-Token')) {
            return trim($headerToken);
        }

        $queryToken = $request->query('api_token');

        return $queryToken ? trim($queryToken) : null;
    }

    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => 'API_TOKEN_INVALID',
        ], 401);
    }
}
