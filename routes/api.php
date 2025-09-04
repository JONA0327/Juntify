<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\PendingRecordingController;
use App\Http\Controllers\OrganizationActivityController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLaravelController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\MeetingController;

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

Route::get('/pending-recordings/{pendingRecording}', [PendingRecordingController::class, 'show']);


Route::get('/organization-activities', [OrganizationActivityController::class, 'index'])
    ->middleware(['web', 'auth']);


    // Rutas de Organizaciones
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('api.organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('api.organizations.store');
    Route::post('/organizations/{token}/join', [OrganizationController::class, 'join'])->name('api.organizations.join');
    Route::post('/organizations/leave', [OrganizationController::class, 'leave'])->name('api.organizations.leave');
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->name('api.organizations.update');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('api.organizations.show');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('api.organizations.destroy');

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
        Route::post('/groups/{group}/accept', [GroupController::class, 'accept'])->name('api.groups.accept');
        Route::post('/groups/join-code', [GroupController::class, 'joinByCode'])->name('api.groups.join-code');
        Route::get('/groups/{group}/members', [GroupController::class, 'members'])->name('groups.members');
        Route::patch('/groups/{group}/members/{user}', [GroupController::class, 'updateMemberRole'])->name('api.groups.members.update');
        Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('api.groups.members.destroy');
        Route::get('/groups/{group}/containers', [GroupController::class, 'getContainers'])->name('api.groups.containers');
    });

    // Notificaciones
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');
    });

    // Rutas de Tareas
    Route::get('/tasks', [TaskController::class, 'getTasks'])->name('api.tasks');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show');
    Route::post('/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.tasks.complete');

    // Nueva BD de tareas: tasks_laravel
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

    // Rutas de Contenedores
    Route::get('/content-containers', [ContainerController::class, 'getContainers'])->name('api.content-containers');
    Route::get('/content-containers/{id}/meetings', [ContainerController::class, 'getContainerMeetings'])->name('api.content-containers.meetings');

    Route::middleware('group.role')->group(function () {
        Route::post('/containers', [ContainerController::class, 'store'])->name('api.containers.store');
        Route::patch('/containers/{id}', [ContainerController::class, 'update'])->name('api.containers.update');
        Route::delete('/containers/{id}', [ContainerController::class, 'destroy'])->name('api.containers.destroy');

        Route::post('/content-containers', [ContainerController::class, 'store'])->name('api.content-containers.store');
        Route::post('/content-containers/{id}/meetings', [ContainerController::class, 'addMeeting'])->name('api.content-containers.addMeeting');
        Route::delete('/content-containers/{container}/meetings/{meeting}', [ContainerController::class, 'removeMeeting'])->name('api.content-containers.meetings.destroy');
    });

    // Rutas API para reuniones
    Route::get('/meetings', [MeetingController::class, 'getMeetings'])->name('api.meetings');
    Route::get('/shared-meetings', [MeetingController::class, 'getSharedMeetings'])->name('api.shared-meetings');
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

    // API para reuniones pendientes
    Route::get('/pending-meetings', [MeetingController::class, 'getPendingMeetings']);
    Route::post('/pending-meetings/{id}/analyze', [MeetingController::class, 'analyzePendingMeeting']);
    Route::post('/pending-meetings/complete', [MeetingController::class, 'completePendingMeeting']);
    Route::get('/pending-meetings/{id}/info', [MeetingController::class, 'getPendingProcessingInfo']);
    Route::get('/pending-meetings/audio/{tempFileName}', [MeetingController::class, 'getPendingAudioFile']);

