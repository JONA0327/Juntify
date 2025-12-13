<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class BcryptjsUserProvider extends EloquentUserProvider
{
    /**
     * Validate a user against the given credentials.
     * Supports both bcrypt ($2y$) and bcryptjs ($2a$, $2b$, $2x$) formats.
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

    /**
     * Check if a password needs to be rehashed.
     * 
     * IMPORTANT: This prevents automatic rehashing of bcryptjs ($2b$) passwords.
     * We don't want to convert $2b$ hashes to $2y$ hashes in the database,
     * so we always return false to indicate the hash is "fresh enough".
     */
    public function needsRehash(UserContract $user): bool
    {
        $hash = $user->getAuthPassword();
        
        // Never rehash bcryptjs ($2a$, $2b$, $2x$) hashes
        // Only rehash standard bcrypt ($2y$) if rounds changed
        if (preg_match('/^\$2[abx]\$/', $hash)) {
            return false;
        }
        
        // For standard bcrypt, use parent's logic
        return parent::needsRehash($user);
    }
}
