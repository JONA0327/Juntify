<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPendingRecordingsJob;
use App\Models\PendingRecording;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class PendingRecordingController extends Controller
{
    public function process(Request $request): Response
    {
        $user = $request->user();
        if (!in_array($user->roles, ['superadmin', 'developer'])) {
            abort(403, 'No tienes permisos para disparar este proceso');
        }

        ProcessPendingRecordingsJob::dispatch();

        return response()->noContent();
    }

    public function show(Request $request, PendingRecording $pendingRecording): JsonResponse
    {
        if ($pendingRecording->username !== $request->user()->username) {
            abort(403);
        }

        return response()->json([
            'id'            => $pendingRecording->id,
            'status'        => $pendingRecording->status,
            'error_message' => $pendingRecording->error_message,
        ]);
    }
}
