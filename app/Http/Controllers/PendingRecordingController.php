<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPendingRecordingsJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
}
