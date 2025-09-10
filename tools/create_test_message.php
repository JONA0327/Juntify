<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;

try {
    $chat = Chat::first();

    if (!$chat) {
        echo "No hay chats disponibles\n";
        exit;
    }

    echo "Chat encontrado: {$chat->id}\n";
    echo "Usuario 1: {$chat->user_one_id}\n";
    echo "Usuario 2: {$chat->user_two_id}\n";

    // Obtener el otro usuario que no esté en este chat
    $otherUser = User::whereNotIn('id', [$chat->user_one_id, $chat->user_two_id])->first();

    if (!$otherUser) {
        // Si no hay otro usuario, usar uno de los usuarios del chat
        $otherUser = User::find($chat->user_two_id);
        echo "Usando usuario del chat: {$otherUser->id}\n";
    } else {
        echo "Usando otro usuario: {$otherUser->id}\n";
    }

    // Crear mensaje no leído
    $message = ChatMessage::create([
        'chat_id' => $chat->id,
        'sender_id' => $otherUser->id,
        'body' => 'Mensaje de prueba no leído',
        'created_at' => now(),
        'read_at' => null // Explícitamente no leído
    ]);

    echo "Mensaje creado con ID: {$message->id}\n";
    echo "Mensaje no leído para mostrar indicador\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
