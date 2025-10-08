<?php

$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))));

if (empty($allowedOrigins)) {
    $appUrl = config('app.url');
    if (!empty($appUrl)) {
        $allowedOrigins[] = rtrim($appUrl, '/');
    }
}

$allowedOrigins = array_values(array_unique($allowedOrigins));

$allowedOriginPatterns = array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', '')))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'new-meeting', 'test-mp3-public', 'test-upload-public'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),
];
