<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class BcryptjsUserProvider extends EloquentUserProvider
{
    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(UserContract $user, array $credentials): bool
    {
        $plain = $credentials['password'];
        $hash = $user->getAuthPassword();

        if (!$plain || !$hash) {
            return false;
        }

        // Support both bcrypt ($2y$) and bcryptjs ($2a$, $2b$, $2x$, $2y$) formats
        // Normalize bcryptjs hashes to $2y$ format that PHP's password_verify() can handle
        if (preg_match('/^\$2[aby]\$/', $hash)) {
            $hash = '$2y$' . substr($hash, 4);
        }

        return password_verify($plain, $hash);
    }
}
