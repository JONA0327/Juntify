<?php

namespace App\Http\Controllers;

use App\Models\TaskComment;
use App\Models\TaskLaravel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskCommentController extends Controller
{
    public function index(int $taskId): JsonResponse
    {
        $user = Auth::user();
        // Ensure task belongs to user
        TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();

        $comments = TaskComment::where('task_id', $taskId)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json(['success' => true, 'comments' => $comments]);
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        $user = Auth::user();
        // Ensure task belongs to user
        TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();

        $data = $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        $comment = TaskComment::create([
            'task_id' => $taskId,
            'author'  => $user->username,
            'text'    => $data['text'],
            'date'    => now(),
        ]);

        return response()->json(['success' => true, 'comment' => $comment]);
    }
}

