<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\TaskComment;
use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskCommentController extends Controller
{
    public function index(int $taskId): JsonResponse
    {
        $user = Auth::user();
        // Ensure task belongs to user
        TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();

        $comments = TaskComment::where('task_id', $taskId)
            ->whereNull('parent_id')
            ->select(['id', 'task_id', 'author', 'text', 'parent_id', 'date'])
            ->with(['children' => function ($query) {
                $query->select(['id', 'task_id', 'author', 'text', 'parent_id', 'date']);
            }])
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
            'parent_id' => 'nullable|exists:task_comments,id',
        ]);

        $comment = DB::transaction(function () use ($data, $taskId, $user) {
            $parent = null;
            if (!empty($data['parent_id'])) {
                $parent = TaskComment::where('id', $data['parent_id'])
                    ->where('task_id', $taskId)
                    ->select(['id', 'author'])
                    ->firstOrFail();
            }

            $comment = TaskComment::create([
                'task_id'   => $taskId,
                'author'    => $user->username,
                'text'      => $data['text'],
                'parent_id' => $data['parent_id'] ?? null,
                'date'      => now(),
            ]);

            if ($parent) {
                $parentUser = User::where('username', $parent->author)
                    ->select('id')
                    ->first();
                if ($parentUser && $parentUser->id !== $user->id) {
                    Notification::create([
                        'remitente' => $user->id,
                        'emisor'    => $parentUser->id,
                        'status'    => 'pending',
                        'message'   => 'Tienes una respuesta a tu comentario',
                        'type'      => 'task_comment_reply',
                        'data'      => [
                            'task_id'    => $taskId,
                            'comment_id' => $comment->id,
                        ],
                    ]);
                }
            }

            return $comment;
        });

        return response()->json(['success' => true, 'comment' => $comment]);
    }
}

