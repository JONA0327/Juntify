<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\TranscriptionController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLaravelController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AiAssistantController;


Route::get('/', function () {
    return view('index');
});

// Test route for notifications (remove in production)
Route::get('/test-notifications', function () {
    return view('test-notifications');
})->middleware('auth');

// Test route for audio upload (remove in production)
Route::get('/test-upload', function () {
    return view('test-upload');
})->middleware('auth');

// Test route for MP3 conversion (remove in production)
Route::get('/test-mp3', function () {
    return view('test-mp3');
})->middleware('auth');

// Public test routes for development (remove in production)
// Simplified - remove condition for testing
Route::get('/test-simple', function () {
    return '<h1>Test route works!</h1>';
});

Route::get('/test-mp3-simple', function () {
    return view('test-mp3-simple');
});

Route::get('/test-mp3-nocors', function () {
    return view('test-mp3-nocors');
});

Route::get('/new-meeting-nocors', function () {
    return view('new-meeting-nocors');
});

Route::get('/test-basic', function () {
    return '<!DOCTYPE html>
<html>
<head><title>Basic Test</title></head>
<body><h1>Basic test without any middleware</h1></body>
</html>';
});

Route::get('/debug-headers', function (Request $request) {
    return response()->json([
        'path' => $request->path(),
        'headers' => $request->headers->all(),
        'cors_config' => [
            'debug' => config('app.debug'),
            'skip_coep' => env('SKIP_COEP_IN_DEV', true)
        ]
    ]);
});

// Rutas de Auth
Route::get('/login',    [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login',   [LoginController::class, 'login']);
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register',[RegisterController::class, 'register']);

// Google OAuth routes
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
Route::get('/google/reauth', [GoogleAuthController::class, 'redirect'])->name('google.reauth');

Route::middleware(['auth'])->group(function () {
    // Perfil - con refresh automático de token
    Route::get('/profile', [ProfileController::class, 'show'])
        ->middleware('google.refresh')
        ->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::post('/logout',[LoginController::class, 'logout'])->name('logout');

    // Dashboard - redirige a reuniones
    Route::get('/dashboard', function () {
        return redirect()->route('reuniones.index');
    })->name('dashboard');

    // Reuniones - con refresh automático de token
    Route::get('/reuniones', [MeetingController::class, 'index'])
        ->middleware('google.refresh')
        ->name('reuniones.index');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/{chat}', [ChatController::class, 'showView'])->name('chats.show');

    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    // Vista de configuración de Drive por organización (sin afectar perfil)
    Route::get('/organizations/{organization}/drive', [OrganizationController::class, 'driveSettings'])
        ->name('organizations.drive');

    // Rutas de Tareas
    Route::get('/tareas', [TaskController::class, 'index'])->name('tareas.index');

    Route::post('/drive/disconnect', [GoogleAuthController::class, 'disconnect'])->name('drive.disconnect');
    Route::post('/drive/disconnect-organization', [GoogleAuthController::class, 'disconnectOrganization'])->name('drive.disconnect.organization');

    // Endpoint para verificar estado de conexión Google
    Route::get('/api/google/connection-status', function(App\Services\GoogleTokenRefreshService $tokenService) {
        $user = Auth::user();
        $status = $tokenService->checkConnectionStatus($user);
        return response()->json($status);
    })->name('api.google.status');

    // Rutas POST para manejo de carpetas
    Route::post('/drive/main-folder',     [DriveController::class, 'createMainFolder'])
         ->name('drive.createMainFolder');
    Route::post('/drive/set-main-folder', [DriveController::class, 'setMainFolder'])
         ->name('drive.setMainFolder');
    Route::post('/drive/subfolder',       [DriveController::class, 'createSubfolder'])
         ->name('drive.createSubfolder');
    Route::delete('/drive/subfolder/{id}', [DriveController::class, 'deleteSubfolder'])
         ->name('drive.deleteSubfolder');
    Route::get('/drive/sync-subfolders', [DriveController::class, 'syncDriveSubfolders'])
         ->name('drive.syncSubfolders');
    Route::get('/drive/status', [DriveController::class, 'status'])
         ->name('drive.status');
    Route::post('/drive/save-results', [DriveController::class, 'saveResults']);

    Route::post('/calendar/event', [\App\Http\Controllers\CalendarController::class, 'createEvent'])
         ->name('calendar.createEvent');

    Route::get('/audio-processing', function () {
        $user = auth()->user();
        return view('audio-processing', [
            'userRole' => $user->roles ?? 'free',
            'organizationId' => $user->current_organization_id ?? null
        ]);
    })->name('audio-processing');
});

Route::get('/new-meeting', function () {
    $user = auth()->user();
    return view('new-meeting', [
        'userRole' => $user->roles ?? 'free',
        'organizationId' => $user->current_organization_id ?? null
    ]);
})->name('new-meeting')->middleware('cors.ffmpeg');

// Test routes with FFmpeg middleware
Route::get('/test-mp3-public', function () {
    return view('test-mp3');
})->middleware('cors.ffmpeg');

Route::get('/test-upload-public', function () {
    return view('test-upload');
})->middleware('cors.ffmpeg');

Route::post('/transcription', [TranscriptionController::class, 'store'])
    ->name('transcription.store')
    ->middleware(['auth', 'group.role']);

// Rutas para subida por chunks (archivos grandes)
Route::post('/transcription/chunked/init', [TranscriptionController::class, 'initChunkedUpload'])
    ->name('transcription.chunked.init')
    ->middleware(['auth', 'group.role']);
Route::post('/transcription/chunked/upload', [TranscriptionController::class, 'uploadChunk'])
    ->name('transcription.chunked.upload')
    ->middleware(['auth', 'group.role']);
Route::post('/transcription/chunked/finalize', [TranscriptionController::class, 'finalizeChunkedUpload'])
    ->name('transcription.chunked.finalize')
    ->middleware(['auth', 'group.role']);

Route::get('/transcription/{id}', [TranscriptionController::class, 'show'])->name('transcription.show');
Route::get('/transcription/check/{id}', [TranscriptionController::class, 'checkTranscription'])->name('transcription.check');
Route::post('/analysis', [\App\Http\Controllers\AnalysisController::class, 'analyze'])
    ->name('analysis')
    ->middleware(['auth', 'group.role']);

// Admin routes (solo para roles específicos)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin', function () {
        $user = auth()->user();
        if (!in_array($user->roles, ['superadmin', 'developer'])) {
            abort(403, 'No tienes permisos para acceder al panel administrativo');
        }
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/admin/analyzers', [\App\Http\Controllers\AnalyzerController::class, 'index'])
        ->name('admin.analyzers');

    Route::get('/admin/analyzers/list', [\App\Http\Controllers\AnalyzerController::class, 'list']);
    Route::get('/admin/analyzers/{analyzer}', [\App\Http\Controllers\AnalyzerController::class, 'show']);
    Route::post('/admin/analyzers', [\App\Http\Controllers\AnalyzerController::class, 'store']);
    Route::put('/admin/analyzers/{analyzer}', [\App\Http\Controllers\AnalyzerController::class, 'update']);
    Route::delete('/admin/analyzers/{analyzer}', [\App\Http\Controllers\AnalyzerController::class, 'destroy']);

    Route::post('/admin/pending-recordings/process', [\App\Http\Controllers\PendingRecordingController::class, 'process'])
        ->name('admin.pending-recordings.process');

    // Ruta temporal para depurar datos pending
    Route::get('/debug-pending', function() {
        $user = \Illuminate\Support\Facades\Auth::user();
        $folders = \App\Models\PendingFolder::all();
        $recordings = \App\Models\PendingRecording::all();

        return response()->json([
            'current_user' => $user ? $user->username : 'No autenticado',
            'folders' => $folders,
            'recordings' => $recordings
        ]);
    });

    // Vista de prueba para reuniones pendientes
    Route::get('/test-pending', function() {
        return view('test-pending');
    });

    // Vista de prueba para limpieza de memoria de audio
    Route::get('/test-memory-cleanup', function() {
        return view('test-memory-cleanup');
    })->name('test.memory-cleanup');

    // Rutas de tasks-laravel (como API pero en web para mantener sesión)
    Route::prefix('api')->group(function () {
        Route::get('/tasks-laravel/meetings', [TaskLaravelController::class, 'meetings'])->name('api.tasks-laravel.meetings');
        Route::post('/tasks-laravel/import/{meetingId}', [TaskLaravelController::class, 'importFromJu'])->name('api.tasks-laravel.import');
        Route::post('/tasks-laravel/exists', [TaskLaravelController::class, 'exists'])->name('api.tasks-laravel.exists');
        Route::get('/tasks-laravel/tasks', [TaskLaravelController::class, 'tasks'])->name('api.tasks-laravel.tasks');
        Route::get('/tasks-laravel/tasks/{id}', [TaskLaravelController::class, 'show'])->name('api.tasks-laravel.tasks.show');
        Route::post('/tasks-laravel/tasks', [TaskLaravelController::class, 'store'])->name('api.tasks-laravel.tasks.store');
        Route::put('/tasks-laravel/tasks/{id}', [TaskLaravelController::class, 'update'])->name('api.tasks-laravel.tasks.update');
        Route::delete('/tasks-laravel/tasks/{id}', [TaskLaravelController::class, 'destroy'])->name('api.tasks-laravel.tasks.destroy');
        Route::post('/tasks-laravel/tasks/{id}/complete', [TaskLaravelController::class, 'complete'])->name('api.tasks-laravel.tasks.complete');
        Route::get('/tasks-laravel/calendar', [TaskLaravelController::class, 'calendar'])->name('api.tasks-laravel.calendar');

        // Comentarios de tareas
        Route::get('/tasks-laravel/tasks/{task}/comments', [TaskCommentController::class, 'index'])->name('api.tasks-laravel.comments.index');
        Route::post('/tasks-laravel/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('api.tasks-laravel.comments.store');

        // Archivos asociados a tareas
        Route::get('/tasks-laravel/tasks/{task}/files', [TaskAttachmentController::class, 'index'])->name('api.tasks-laravel.files.index');
        Route::post('/tasks-laravel/tasks/{task}/files', [TaskAttachmentController::class, 'store'])->name('api.tasks-laravel.files.store');
        Route::get('/drive/folders', [TaskAttachmentController::class, 'folders'])->name('api.drive.folders');
        Route::get('/tasks-laravel/files/{file}/download', [TaskAttachmentController::class, 'download'])->name('api.tasks-laravel.files.download');

        // Rutas del Asistente IA
        Route::prefix('ai-assistant')->group(function () {
            // Sesiones de chat
            Route::get('/sessions', [AiAssistantController::class, 'getSessions'])->name('api.ai-assistant.sessions');
            Route::post('/sessions', [AiAssistantController::class, 'createSession'])->name('api.ai-assistant.sessions.create');
            Route::get('/sessions/{id}/messages', [AiAssistantController::class, 'getMessages'])->name('api.ai-assistant.sessions.messages');
            Route::post('/sessions/{id}/messages', [AiAssistantController::class, 'sendMessage'])->name('api.ai-assistant.sessions.send-message');

            // Datos para contexto
            Route::get('/containers', [AiAssistantController::class, 'getContainers'])->name('api.ai-assistant.containers');
            Route::get('/meetings', [AiAssistantController::class, 'getMeetings'])->name('api.ai-assistant.meetings');
            Route::get('/contact-chats', [AiAssistantController::class, 'getContactChats'])->name('api.ai-assistant.contact-chats');

            // Gestión de documentos
            Route::get('/documents', [AiAssistantController::class, 'getDocuments'])->name('api.ai-assistant.documents');
            Route::post('/documents/upload', [AiAssistantController::class, 'uploadDocument'])->name('api.ai-assistant.documents.upload');
        });
    });

    // Ruta principal del asistente IA
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai-assistant');

    // Ruta de prueba para el modal de contexto
    Route::get('/test-modal-debug', function () {
        return view('test_modal_debug');
    })->name('test-modal-debug');
});
