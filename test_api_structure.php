<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chat;
use App\Models\ChatMessage;

echo "=== ESTRUCTURA DE RESPUESTA API ===\n\n";

$chat = Chat::first();
$messages = $chat->messages()->with('sender')->orderBy('created_at')->get();

echo "Estructura de mensajes que devuelve la API:\n";
echo json_encode($messages->toArray(), JSON_PRETTY_PRINT);
