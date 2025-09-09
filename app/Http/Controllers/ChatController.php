<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $chats = Chat::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with('messages')
            ->get();

        return response()->json($chats);
    }

    public function show(Chat $chat): JsonResponse
    {
        $userId = Auth::id();
        abort_unless($chat->user_one_id === $userId || $chat->user_two_id === $userId, 403);

        $messages = $chat->messages()->with('sender')->orderBy('created_at')->get();

        return response()->json($messages);
    }

    public function store(Request $request, Chat $chat): JsonResponse
    {
        $user = Auth::user();
        abort_unless($chat->user_one_id === $user->id || $chat->user_two_id === $user->id, 403);

        $data = $request->validate([
            'body' => 'nullable|string',
            'file' => 'nullable|file',
            'voice' => 'nullable|file',
        ]);

        $filePath = $request->file('file') ? $request->file('file')->store('chat_files') : null;
        $voicePath = $request->file('voice') ? $request->file('voice')->store('chat_files') : null;

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'] ?? null,
            'file_path' => $filePath,
            'voice_path' => $voicePath,
            'created_at' => now(),
        ]);

        return response()->json($message->load('sender'));
    }
}
