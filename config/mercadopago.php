<?php

return [
    'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'), // Server side private token
    'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),     // Client side public key
    'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET', null), // Optional: to validate signatures
    'success_url' => env('APP_URL').'/payment/success',
    'failure_url' => env('APP_URL').'/payment/failure',
    'pending_url' => env('APP_URL').'/payment/pending',
    'auto_approve_window_minutes' => env('MP_AUTO_APPROVE_WINDOW', 30),
];
