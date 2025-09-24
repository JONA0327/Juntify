<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\TaskComment;
use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TaskCommentController extends Controller
{
    public function index(int $taskId): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($taskId);
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para ver esta tarea');
        }

        $comments = TaskComment::where('task_id', $taskId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('date', 'asc')
            ->get();

        $userMap = $this->resolveAuthorsMap($comments);
        $formatted = $comments->map(fn (TaskComment $comment) => $this->transformComment($comment, $userMap))->values();

        return response()->json(['success' => true, 'comments' => $formatted]);
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($taskId);
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para comentar esta tarea');
        }

        $data = $request->validate([
            'text' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:task_comments,id',
        ]);

        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = TaskComment::where('id', $data['parent_id'])
                ->where('task_id', $taskId)
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
            $parentUser = User::where('username', $parent->author)->first();
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

        $comment->setRelation('children', collect());
        $userMap = $this->resolveAuthorsMap(collect([$comment]));

        return response()->json([
            'success' => true,
            'comment' => $this->transformComment($comment, $userMap),
        ]);
    }

    private function resolveAuthorsMap(Collection $comments): Collection
    {
        $usernames = $this->collectAuthorUsernames($comments)
            ->filter()
            ->unique()
            ->values();

        if ($usernames->isEmpty()) {
            return collect();
        }

        return User::whereIn('username', $usernames)->get()->keyBy('username');
    }

    private function collectAuthorUsernames(Collection $comments): Collection
    {
        $accumulator = collect();

        foreach ($comments as $comment) {
            if (!empty($comment->author)) {
                $accumulator->push($comment->author);
            }

            if ($comment->relationLoaded('children') && $comment->children instanceof Collection && $comment->children->isNotEmpty()) {
                $accumulator = $accumulator->merge($this->collectAuthorUsernames($comment->children));
            }
        }

        return $accumulator;
    }

    private function transformComment(TaskComment $comment, Collection $userMap): array
    {
        $children = [];
        if ($comment->relationLoaded('children') && $comment->children instanceof Collection && $comment->children->isNotEmpty()) {
            $children = $comment->children
                ->map(fn (TaskComment $child) => $this->transformComment($child, $userMap))
                ->values()
                ->all();
        }

        return [
            'id' => $comment->id,
            'text' => $comment->text,
            'user' => $this->displayNameFor($comment->author, $userMap),
            'author_username' => $comment->author,
            'created_at' => optional($comment->date)->toIso8601String(),
            'children' => $children,
        ];
    }

    private function displayNameFor(?string $username, Collection $userMap): string
    {
        if (empty($username)) {
            return 'Usuario';
        }

        $user = $userMap->get($username);
        if ($user) {
            return $user->full_name ?: ($user->email ?: $username);
        }

        return $username;
    }
}

