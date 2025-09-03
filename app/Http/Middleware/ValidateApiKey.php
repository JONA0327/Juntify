<?php

namespace App\Http\Middleware;

use App\Models\UserApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');

        if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $apiKey = trim($matches[1]);
            $key = UserApiKey::where('api_key', $apiKey)->first();

            if ($key && $key->user) {
                // Setear el usuario para esta request (sin sesión)
                Auth::setUser($key->user);
            }
        }

        return $next($request);
    }
}
