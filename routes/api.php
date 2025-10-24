<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\PendingRecordingController;
use App\Http\Controllers\OrganizationActivityController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDriveController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLaravelController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\RecordingChunkController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SharedMeetingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PaymentController;
use App\Services\AudioConversionService;
use App\Exceptions\FfmpegUnavailableException;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/public/organizations', [OrganizationController::class, 'publicIndex']);
    Route::get('/public/organizations/{organization}', [OrganizationController::class, 'publicShow']);
    Route::get('/public/groups', [GroupController::class, 'publicIndex']);
    Route::get('/public/groups/{group}', [GroupController::class, 'publicShow']);
    Route::get('/public/meetings', [MeetingController::class, 'publicIndex']);
    Route::get('/public/meetings/{meeting}', [MeetingController::class, 'publicShow']);
});

Route::middleware('auth')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('payments/mercado-pago')->name('payments.mercado-pago.')->group(function () {
    Route::post('/preferences', [PaymentController::class, 'createPreference'])->name('preferences.create');
    Route::get('/preferences/{externalReference}', [PaymentController::class, 'showPreference'])->name('preferences.show');
    Route::get('/status/{externalReference}', [PaymentController::class, 'status'])->name('status');
    Route::post('/webhook', [PaymentController::class, 'webhook'])->name('webhook');
});

// Endpoint de diagnóstico rápido de sesión
Route::middleware(['web','auth'])->get('/whoami', function(Request $request) {
    $u = $request->user();
    return response()->json([
        'ok' => (bool)$u,
        'id' => $u?->id,
        'role' => $u?->roles,
        'current_organization_id' => $u?->current_organization_id,
        'guard' => config('auth.defaults.guard'),
        'session_cookie_name' => config('session.cookie'),
    ]);
});

// Endpoint de depuración de sesión (sin auth para ver si la cookie llega aunque no haya login)
Route::middleware(['web'])->get('/debug/session-info', function(Request $request) {
    return response()->json([
        'session_id' => session()->getId(),
        'has_session_cookie' => array_key_exists(config('session.cookie'), $_COOKIE ?? []),
        'cookie_names' => array_keys($_COOKIE ?? []),
        'user_id' => optional(auth()->user())->id,
        'is_authenticated' => auth()->check(),
        'expected_cookie' => config('session.cookie'),
        'same_site' => config('session.same_site'),
        'secure' => config('session.secure'),
        'domain_env' => env('SESSION_DOMAIN'),
    ]);
});

Route::middleware(['web'])->post('/debug/request-dump', function(Request $request) {
    return response()->json([
        'method' => $request->method(),
        'cookies_sent' => array_keys($_COOKIE ?? []),
        'raw_cookie_header' => $request->header('cookie'),
        'session_id_server' => session()->getId(),
        'has_session_cookie' => array_key_exists(config('session.cookie'), $_COOKIE ?? []),
        'is_authenticated' => auth()->check(),
        'user_id' => optional(auth()->user())->id,
        'headers_subset' => [
            'origin' => $request->header('origin'),
            'referer' => $request->header('referer'),
            'user-agent' => $request->header('user-agent'),
            'x-requested-with' => $request->header('x-requested-with'),
        ],
        'csrf_meta' => optional($request->session())->token(),
    ]);
});

// Endpoint de diagnóstico: convierte un archivo subido a OGG (Opus)
// Requiere sesión para evitar abuso. Usar desde Postman/Frontend para probar conversión en el servidor.
Route::middleware(['web', 'auth'])->post('/debug/convert-audio-to-ogg', function(Request $request) {
    try {
        $request->validate([
            'audioFile' => 'required|file|mimetypes:audio/mpeg,audio/mp3,audio/webm,video/webm,audio/ogg,audio/wav,audio/x-wav,audio/wave,audio/mp4,video/mp4,audio/aac,audio/x-aac,audio/m4a,audio/x-m4a,audio/flac,audio/x-flac,audio/amr,audio/3gpp,audio/3gpp2'
        ]);

        $file = $request->file('audioFile');
        $maxBytes = 200 * 1024 * 1024; // 200 MB
        if ($file->getSize() > $maxBytes) {
            return response()->json([
                'code' => 'PAYLOAD_TOO_LARGE',
                'message' => 'Archivo demasiado grande (máx. 200 MB)'
            ], 413);
        }

        $path = $file->getRealPath();
        $mime = $file->getMimeType();
        $ext  = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: null;

        /** @var AudioConversionService $svc */
        $svc = app(AudioConversionService::class);
        $res = $svc->convertToOgg($path, $mime, $ext);

        $download = (string) $request->query('download', '0');
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.ogg';

        if ($download === '1') {
            return response()->download($res['path'], $filename, [
                'Content-Type' => 'audio/ogg'
            ])->deleteFileAfterSend(true);
        }

        $size = is_file($res['path']) ? filesize($res['path']) : null;
        // Si no se descargará, limpiamos el archivo temporal para no dejar basura
        if ($res['was_converted'] && is_file($res['path'])) {
            @unlink($res['path']);
        }

        return response()->json([
            'ok' => true,
            'was_converted' => $res['was_converted'],
            'output_mime' => $res['mime_type'],
            'output_size' => $size,
        ]);
    } catch (FfmpegUnavailableException $e) {
        return response()->json([
            'code' => 'FFMPEG_UNAVAILABLE',
            'message' => 'FFmpeg/ffprobe no están disponibles en el servidor. Instálalos para habilitar la conversión a OGG.',
        ], 500);
    } catch (\Throwable $e) {
        return response()->json([
            'code' => 'OGG_CONVERSION_FAILED',
            'message' => 'Falló la conversión a OGG: ' . $e->getMessage(),
        ], 500);
    }
});

// Endpoint para ver límites efectivos de carga (útil ante 413). Requiere sesión.
Route::middleware(['web', 'auth'])->get('/debug/upload-limits', function() {
    // Estos valores vienen de PHP ini; el límite real también puede estar en el servidor/proxy (nginx/apache)
    $val = function($key) {
        $v = ini_get($key);
        return $v === false ? null : $v;
    };
    return response()->json([
        'post_max_size' => $val('post_max_size'),
        'upload_max_filesize' => $val('upload_max_filesize'),
        'memory_limit' => $val('memory_limit'),
        'max_input_vars' => $val('max_input_vars'),
        'max_execution_time' => $val('max_execution_time'),
        'note' => 'Si usas Nginx/Apache/Proxy, revisa client_max_body_size (Nginx) o LimitRequestBody (Apache).',
    ]);
});

// Endpoint de prueba para verificar que API devuelve JSON
Route::get('/test', function () {
    return response()->json([
        'message' => 'API funcionando correctamente',
        'timestamp' => now(),
        'status' => 'success'
    ]);
});

// Endpoint para crear tokens de API (requiere autenticación web)
Route::middleware('auth')->post('/create-token', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $token = $request->user()->createToken($request->name);

    return response()->json([
        'token' => $token->plainTextToken,
        'name' => $request->name,
        'created_at' => now()
    ]);
});

Route::get('/analyzers', [AnalyzerController::class, 'list']);
Route::post('/drive/save-results', [DriveController::class, 'saveResults']);

Route::post('/drive/upload-pending-audio', [DriveController::class, 'uploadPendingAudio'])
    ->middleware(['web', 'auth']);

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/pending-recordings/{pendingRecording}', [PendingRecordingController::class, 'show']);
    Route::post('/recordings/chunk', [RecordingChunkController::class, 'storeChunk']);
    Route::post('/recordings/concat', [RecordingChunkController::class, 'concatChunks']);
});


Route::get('/organization-activities', [OrganizationActivityController::class, 'index'])
    ->middleware(['web', 'auth']);

Route::middleware(['web', 'auth'])->group(function () {
    // Rutas de Organizaciones
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('api.organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('api.organizations.store');
    Route::post('/organizations/{token}/join', [OrganizationController::class, 'join'])->name('api.organizations.join');
    Route::post('/organizations/leave', [OrganizationController::class, 'leave'])->name('api.organizations.leave');
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->name('api.organizations.update');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('api.organizations.show');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('api.organizations.destroy');

    // Rutas de Google Drive para organizaciones (control de permisos en el controlador)
    Route::post('/organizations/{organization}/drive/root-folder', [OrganizationDriveController::class, 'createRootFolder'])->name('api.organizations.drive.root-folder');
    Route::post('/organizations/{organization}/drive/subfolders', [OrganizationDriveController::class, 'createSubfolder'])->name('api.organizations.drive.subfolders.store');
    Route::get('/organizations/{organization}/drive/subfolders', [OrganizationDriveController::class, 'listSubfolders'])->name('api.organizations.drive.subfolders.index');
    Route::get('/organizations/{organization}/drive/status', [OrganizationDriveController::class, 'status'])->name('api.organizations.drive.status');
    Route::patch('/organizations/{organization}/drive/subfolders/{subfolder}', [OrganizationDriveController::class, 'renameSubfolder'])->name('api.organizations.drive.subfolders.update');
    Route::delete('/organizations/{organization}/drive/subfolders/{subfolder}', [OrganizationDriveController::class, 'deleteSubfolder'])->name('api.organizations.drive.subfolders.destroy');
});

    // Rutas de Usuarios
    Route::post('/users/check-email', [UserController::class, 'checkEmail'])->name('api.users.check-email');
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/users/notifications', [UserController::class, 'getNotifications'])->name('api.users.notifications');
        Route::post('/users/notifications/{notification}/respond', [UserController::class, 'respondToNotification'])->name('api.users.notifications.respond');
    });

    // Rutas de Grupos
    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('/groups', [GroupController::class, 'store'])->name('api.groups.store');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->name('api.groups.show');
        Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('api.groups.update');
        Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('api.groups.destroy');
        Route::post('/groups/{group}/invite', [GroupController::class, 'invite'])->name('api.groups.invite');
    Route::get('/groups/{group}/invitable-contacts', [GroupController::class, 'invitableContacts'])->name('api.groups.invitable-contacts');
        Route::post('/groups/{group}/accept', [GroupController::class, 'accept'])->name('api.groups.accept');
        Route::post('/groups/join-code', [GroupController::class, 'joinByCode'])->name('api.groups.join-code');
        Route::get('/groups/{group}/members', [GroupController::class, 'members'])->name('groups.members');
        Route::patch('/groups/{group}/members/{user}', [GroupController::class, 'updateMemberRole'])->name('api.groups.members.update');
        Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('api.groups.members.destroy');
        Route::get('/groups/{group}/containers', [GroupController::class, 'getContainers'])->name('api.groups.containers');
    });

    // Rutas de Transcripciones Temporales (Plan FREE)
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/transcriptions-temp', [App\Http\Controllers\TranscriptionTempController::class, 'index'])->name('api.transcriptions-temp.index');
        Route::post('/transcriptions-temp', [App\Http\Controllers\TranscriptionTempController::class, 'store'])->name('api.transcriptions-temp.store');
        Route::get('/transcriptions-temp/{transcription}', [App\Http\Controllers\TranscriptionTempController::class, 'show'])->name('api.transcriptions-temp.show');
        Route::get('/transcriptions-temp/{transcription}/audio', [App\Http\Controllers\TranscriptionTempController::class, 'streamAudio'])->name('api.transcriptions-temp.audio');
        Route::put('/transcriptions-temp/{transcription}/name', [App\Http\Controllers\TranscriptionTempController::class, 'updateName'])->name('api.transcriptions-temp.name.update');
        Route::patch('/transcriptions-temp/{transcription}/tasks', [App\Http\Controllers\TranscriptionTempController::class, 'updateTasks'])->name('api.transcriptions-temp.tasks.update');
        Route::post('/transcriptions-temp/{transcription}/analyze-tasks', [App\Http\Controllers\TranscriptionTempController::class, 'analyzeAndGenerateTasks'])->name('api.transcriptions-temp.analyze-tasks');
        Route::delete('/transcriptions-temp/{transcription}', [App\Http\Controllers\TranscriptionTempController::class, 'destroy'])->name('api.transcriptions-temp.destroy');
        Route::post('/transcriptions-temp/clean-expired', [App\Http\Controllers\TranscriptionTempController::class, 'cleanExpired'])->name('api.transcriptions-temp.clean-expired');
    });

    // (Eliminado grupo duplicado de notificaciones para permitir route:cache)

    // Rutas de Tareas (tabla tasks tradicional)
    Route::middleware(['auth:web'])->group(function () {
        Route::get('/tasks', [TaskController::class, 'getTasks'])->name('api.tasks');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show');
        Route::post('/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');
        Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.tasks.complete');
    });

    // Contenedores (requieren sesión web + auth + verificación de rol de grupo)
    Route::middleware(['web', 'auth', 'group.role'])->group(function () {
        Route::post('/containers', [ContainerController::class, 'store'])->name('api.containers.store');
        Route::patch('/containers/{id}', [ContainerController::class, 'update'])->name('api.containers.update');
        Route::delete('/containers/{id}', [ContainerController::class, 'destroy'])->name('api.containers.destroy');
    });

    // Rutas API para reuniones y contenedores
    Route::middleware(['web', 'auth'])->group(function () {
        // Rutas de Contenedores - movidas fuera del middleware restrictivo
        Route::post('/content-containers', [ContainerController::class, 'store'])->name('api.content-containers.store');
        Route::put('/content-containers/{id}', [ContainerController::class, 'update'])->name('api.content-containers.update');
        Route::delete('/content-containers/{id}', [ContainerController::class, 'destroy'])->name('api.content-containers.destroy');
        Route::post('/content-containers/{id}/meetings', [ContainerController::class, 'addMeeting'])->name('api.content-containers.addMeeting');
        Route::delete('/content-containers/{container}/meetings/{meeting}', [ContainerController::class, 'removeMeeting'])->name('api.content-containers.meetings.destroy');

        Route::get('/content-containers', [ContainerController::class, 'getContainers'])->name('api.content-containers');
        Route::get('/content-containers/{id}/meetings', [ContainerController::class, 'getContainerMeetings'])->name('api.content-containers.meetings');

        // Rutas de reuniones
        Route::get('/meetings', [MeetingController::class, 'getMeetings'])->name('api.meetings');
        Route::get('/meetings/{id}', [MeetingController::class, 'show'])->name('api.meetings.show');
        Route::put('/meetings/{id}/name', [MeetingController::class, 'updateName'])->name('api.meetings.updateName');
        Route::put('/meetings/{id}/segments', [MeetingController::class, 'updateSegments'])->name('api.meetings.updateSegments');
        Route::delete('/meetings/{id}', [MeetingController::class, 'destroy'])->name('api.meetings.destroy');
        Route::post('/meetings/cleanup', [MeetingController::class, 'cleanupModal'])->name('api.meetings.cleanup');
        Route::post('/meetings/{id}/encrypt', [MeetingController::class, 'encryptJu'])->name('api.meetings.encrypt');

        // Rutas de descarga
        Route::get('/meetings/{id}/download-ju', [MeetingController::class, 'downloadJuFile'])->name('api.meetings.download-ju');
        Route::get('/meetings/{id}/download-audio', [MeetingController::class, 'downloadAudioFile'])->name('api.meetings.download-audio');
        Route::get('/meetings/{meeting}/download-report', [MeetingController::class, 'downloadReport'])->name('api.meetings.download-report');
          Route::post('/meetings/{id}/download-pdf', [MeetingController::class, 'downloadPdf'])->name('api.meetings.download-pdf');
          Route::post('/meetings/{id}/preview-pdf', [MeetingController::class, 'previewPdf'])->name('api.meetings.preview-pdf');
          Route::get('/meetings/{meeting}/audio', [MeetingController::class, 'streamAudio'])->name('api.meetings.audio');

          // Contactos
          Route::get('/contacts', [ContactController::class, 'list'])->name('api.contacts.index');
          Route::get('/contacts/requests', [ContactController::class, 'requests'])->name('api.contacts.requests');
          Route::post('/contacts', [ContactController::class, 'store'])->name('api.contacts.store');
          Route::post('/contacts/requests/{notification}/respond', [ContactController::class, 'respond'])->name('api.contacts.requests.respond');
          Route::delete('/contacts/requests/{notification}/cancel', [ContactController::class, 'cancel'])->name('api.contacts.requests.cancel');
          Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('api.contacts.destroy');
          Route::post('/users/search', [ContactController::class, 'searchUsers'])->name('api.users.search');


          // API para reuniones compartidas
          Route::get('/shared-meetings/contacts', [SharedMeetingController::class, 'getContactsForSharing'])->name('api.shared-meetings.contacts');
          Route::post('/shared-meetings/share', [SharedMeetingController::class, 'shareMeeting'])->name('api.shared-meetings.share');
          Route::post('/shared-meetings/respond', [SharedMeetingController::class, 'respondToInvitation'])->name('api.shared-meetings.respond');
          // Versioned endpoint to avoid clashing with legacy shared meetings list
          Route::get('/shared-meetings/v2', [SharedMeetingController::class, 'getSharedMeetings'])->name('api.shared-meetings.v2');
          Route::get('/shared-meetings/show/{id}', [SharedMeetingController::class, 'showSharedMeeting'])->name('api.shared-meetings.show');
          // Outgoing shares (those I have shared)
          Route::get('/shared-meetings/outgoing', [SharedMeetingController::class, 'getOutgoingShares'])->name('api.shared-meetings.outgoing');
          Route::delete('/shared-meetings/outgoing/{id}', [SharedMeetingController::class, 'revokeOutgoingShare'])->name('api.shared-meetings.outgoing.revoke');
          Route::post('/shared-meetings/resolve-drive-links', [SharedMeetingController::class, 'resolveDriveLinks'])->name('api.shared-meetings.resolve');
          Route::delete('/shared-meetings/{id}', [SharedMeetingController::class, 'unlink'])->name('api.shared-meetings.unlink');

          // API para notificaciones (unificado)
          Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
          Route::post('/notifications', [NotificationController::class, 'store'])->name('api.notifications.store');
          Route::put('/notifications/{notification}', [NotificationController::class, 'update'])->name('api.notifications.update');
          Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('api.notifications.unread');
          Route::get('/notifications/count', [NotificationController::class, 'getUnreadCount'])->name('api.notifications.count');
          Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
          Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
          Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('api.notifications.clear-all');
          // Usar {notification} para binding automático
          Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');

          // Documentos organizacionales por grupo / contenedor
          Route::get('/organizations/{organization}/documents/groups', [\App\Http\Controllers\OrganizationDocumentsController::class, 'listGroups']);
          Route::post('/organizations/{organization}/documents/groups/{group}/ensure', [\App\Http\Controllers\OrganizationDocumentsController::class, 'ensureGroupFolder']);
          Route::get('/organizations/{organization}/documents/groups/{group}/containers', [\App\Http\Controllers\OrganizationDocumentsController::class, 'listContainers']);
          Route::post('/organizations/{organization}/documents/groups/{group}/containers/{container}/ensure', [\App\Http\Controllers\OrganizationDocumentsController::class, 'ensureContainerFolder']);
          Route::get('/organizations/{organization}/documents/groups/{group}/containers/{container}/files', [\App\Http\Controllers\OrganizationDocumentsController::class, 'listContainerFiles']);
          Route::post('/organizations/{organization}/documents/groups/{group}/containers/{container}/upload', [\App\Http\Controllers\OrganizationDocumentsController::class, 'uploadToContainer'])->middleware('upload.max150');
          Route::delete('/organizations/{organization}/documents/groups/{group}/containers/{container}/delete', [\App\Http\Controllers\OrganizationDocumentsController::class, 'deleteDocument']);

          // API para reuniones pendientes
          Route::get('/pending-meetings', [MeetingController::class, 'getPendingMeetings']);
        Route::post('/pending-meetings/{id}/analyze', [MeetingController::class, 'analyzePendingMeeting']);
        Route::post('/pending-meetings/complete', [MeetingController::class, 'completePendingMeeting']);
        Route::get('/pending-meetings/{id}/info', [MeetingController::class, 'getPendingProcessingInfo']);
        Route::get('/pending-meetings/audio/{tempFileName}', [MeetingController::class, 'getPendingAudioFile']);

                // Plan limits
                Route::get('/plan/limits', [PlanController::class, 'limits'])->name('api.plan.limits');
    });

