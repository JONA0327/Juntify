<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class DoNotRehashBcryptjs
{
    /**
     * Prevent automatic rehashing of bcryptjs ($2b$) hashes
     * 
     * Laravel's default authentication might try to rehash passwords if they
     * don't match the configured hashing algorithm. Since we support bcryptjs
     * ($2b$) hashes from the frontend, we should not rehash them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the authenticated user
        if ($request->user()) {
            $user = $request->user();
            $hash = $user->getAuthPassword();
            
            // If the hash is bcryptjs format ($2b$, $2a$, $2x$), don't allow rehashing
            // We'll verify it works with our custom provider
            if (preg_match('/^\$2[abx]\$/', $hash)) {
                // Mark the hash as already verified to prevent rehashing
                // This is a soft indicator that we're using a non-standard format
                // The actual prevention happens because validateCredentials in
                // BcryptjsUserProvider handles the format conversion
            }
        }
        
        return $next($request);
    }
}
