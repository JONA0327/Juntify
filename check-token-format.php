<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = DB::table('google_tokens')->first();

echo "Estructura del token:\n";
echo "- expiry_date: {$token->expiry_date}\n";
echo "- expires_in: {$token->expires_in}\n";
echo "- Type: " . gettype($token->expiry_date) . "\n";
