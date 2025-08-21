<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $notifications = Notification::where('emisor', $user->id)
            ->with(['remitente:id,username'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    public function destroy(Notification $notification)
    {
        $user = auth()->user();
        if ($notification->emisor !== $user->id) {
            abort(403);
        }
        $notification->delete();

        return response()->json(['deleted' => true]);
    }
}
