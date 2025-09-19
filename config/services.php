<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_OAUTH_REDIRECT_URI'),
        'api_key' => env('GOOGLE_API_KEY'),
        'service_account_json' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL'),
        'pending_folder_id' => env('GOOGLE_PENDING_FOLDER_ID'),
    ],

    'assemblyai' => [
        'api_key' => env('ASSEMBLYAI_API_KEY'),
        'verify_ssl' => env('ASSEMBLYAI_VERIFY_SSL', true),
        'timeout' => env('ASSEMBLYAI_TIMEOUT', 300),
        'connect_timeout' => env('ASSEMBLYAI_CONNECT_TIMEOUT', 60),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        // Modelos configurables
        // chat_model: modelo para conversaciones del asistente
        // embedding_model: modelo para embeddings semánticos
        'chat_model' => env('AI_ASSISTANT_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],
];
