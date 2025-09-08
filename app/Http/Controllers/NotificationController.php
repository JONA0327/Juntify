<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return response()->json([], 401);
        }

        $user = auth()->user();
        $notifications = Notification::where('emisor', $user->id)
            ->with(['remitente:id,username,full_name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    public function store(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth()->user();

        $request->validate([
            'type' => 'required|string',
            'message' => 'required|string',
            'data' => 'nullable|array'
        ]);

        $notification = Notification::create([
            'remitente' => $user->id,
            'emisor' => $user->id,
            'type' => $request->type,
            'message' => $request->message,
            'data' => $request->data ?? [],
            'status' => 'active'
        ]);

        return response()->json($notification, 201);
    }

    public function update(Request $request, Notification $notification)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        if ($notification->emisor !== $user->id) {
            abort(403);
        }

        $request->validate([
            'message' => 'sometimes|required|string',
            'data' => 'sometimes|nullable|array',
            'status' => 'sometimes|required|string'
        ]);

        $notification->update($request->only(['message', 'data', 'status']));

        return response()->json($notification);
    }

    public function destroy(Notification $notification)
    {
        if (!auth()->check()) {
            return response()->json([], 401);
        }

        $user = auth()->user();
        if ($notification->emisor !== $user->id) {
            abort(403);
        }
        $notification->delete();

        return response()->json(['deleted' => true]);
    }
}
