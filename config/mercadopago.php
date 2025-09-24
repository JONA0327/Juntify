<?php

return [
    'access_token' => env('MP_ACCESS_TOKEN'), // Server side private token
    'public_key' => env('MP_PUBLIC_KEY'),     // Client side public key
    'webhook_secret' => env('MP_WEBHOOK_SECRET', null), // Optional: to validate signatures
    'success_url' => env('MP_SUCCESS_URL', env('APP_URL').'/billing/success'),
    'failure_url' => env('MP_FAILURE_URL', env('APP_URL').'/billing/failure'),
    'pending_url' => env('MP_PENDING_URL', env('APP_URL').'/billing/pending'),
    'auto_approve_window_minutes' => env('MP_AUTO_APPROVE_WINDOW', 30),
];
