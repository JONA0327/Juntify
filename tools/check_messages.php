<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;

echo "=== VERIFICACIÓN DE MENSAJES ===\n\n";

echo "Chats en DB:\n";
Chat::all()->each(function($chat) {
    echo "- Chat ID: {$chat->id}, Usuario 1: {$chat->user_one_id}, Usuario 2: {$chat->user_two_id}\n";
});

echo "\nMensajes en DB:\n";
ChatMessage::with('chat', 'sender')->get()->each(function($message) {
    echo "- Mensaje ID: {$message->id}\n";
    echo "  Chat ID: {$message->chat_id}\n";
    echo "  Sender ID: {$message->sender_id}\n";
    echo "  Sender Name: " . ($message->sender ? $message->sender->full_name : 'N/A') . "\n";
    echo "  Body: {$message->body}\n";
    echo "  Created: {$message->created_at}\n";
    echo "  Read: " . ($message->read_at ? 'Sí' : 'No') . "\n";
    echo "  ---\n";
});

echo "\nUsuarios en DB:\n";
User::select('id', 'full_name', 'email')->get()->each(function($user) {
    echo "- {$user->id}: {$user->full_name} ({$user->email})\n";
});
