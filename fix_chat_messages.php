<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;

echo "=== SOLUCIONANDO PROBLEMA DE MENSAJES ===\n\n";

$chat = Chat::first();
echo "Chat ID: {$chat->id}\n";
echo "Usuario 1: {$chat->user_one_id}\n";
echo "Usuario 2: {$chat->user_two_id}\n\n";

// Obtener información de los usuarios del chat
$user1 = User::find($chat->user_one_id);
$user2 = User::find($chat->user_two_id);

echo "Usuario 1: {$user1->full_name} ({$user1->email})\n";
echo "Usuario 2: {$user2->full_name} ({$user2->email})\n\n";

// Eliminar mensajes anteriores para limpiar
ChatMessage::where('chat_id', $chat->id)->delete();
echo "Mensajes anteriores eliminados\n\n";

// Crear un mensaje válido del usuario 2 al usuario 1
$message = ChatMessage::create([
    'chat_id' => $chat->id,
    'sender_id' => $user2->id, // Usuario 2 envía mensaje
    'body' => 'Hola! Este es un mensaje de prueba no leído',
    'created_at' => now(),
    'read_at' => null // NO leído
]);

echo "Nuevo mensaje creado:\n";
echo "- ID: {$message->id}\n";
echo "- De: {$user2->full_name}\n";
echo "- Para: {$user1->full_name}\n";
echo "- Contenido: {$message->body}\n";
echo "- Estado: NO LEÍDO\n\n";

echo "Ahora el mensaje debería aparecer en el chat y mostrar indicador rojo\n";
