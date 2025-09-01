<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\TranscriptionController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLaravelController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserApiKeyController;


Route::get('/', function () {
    return view('index');
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

Route::middleware('auth')->group(function () {
    Route::get('/api/user/api-key', [UserApiKeyController::class, 'show']);
    Route::post('/api/user/api-key', [UserApiKeyController::class, 'generate']);
});

Route::middleware(['api-key', 'auth'])->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/logout',[LoginController::class, 'logout'])->name('logout');
    Route::get('/reuniones', [MeetingController::class, 'index'])->name('reuniones.index');
    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::get('/organization/api-guide', function () {
        return view('organization.api-guide');
    })->name('organization.api-guide');

    // Rutas de Organizaciones
    Route::get('/api/organizations', [OrganizationController::class, 'index'])->name('api.organizations.index');
    Route::post('/api/organizations', [OrganizationController::class, 'store'])->name('api.organizations.store');
    Route::post('/api/organizations/{token}/join', [OrganizationController::class, 'join'])->name('api.organizations.join');
    Route::post('/api/organizations/leave', [OrganizationController::class, 'leave'])->name('api.organizations.leave');
    Route::patch('/api/organizations/{organization}', [OrganizationController::class, 'update'])->name('api.organizations.update');
    Route::get('/api/organizations/{organization}', [OrganizationController::class, 'show'])->name('api.organizations.show');
    Route::delete('/api/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('api.organizations.destroy');

    // Rutas de Usuarios
    Route::post('/api/users/check-email', [UserController::class, 'checkEmail'])->name('api.users.check-email');
    Route::get('/api/users/notifications', [UserController::class, 'getNotifications'])->name('api.users.notifications');
    Route::post('/api/users/notifications/{notification}/respond', [UserController::class, 'respondToNotification'])->name('api.users.notifications.respond');

    // Rutas de Grupos
    Route::post('/api/groups', [GroupController::class, 'store'])->name('api.groups.store');
    Route::get('/api/groups/{group}', [GroupController::class, 'show'])->name('api.groups.show');
    Route::patch('/api/groups/{group}', [GroupController::class, 'update'])->name('api.groups.update');
    Route::delete('/api/groups/{group}', [GroupController::class, 'destroy'])->name('api.groups.destroy');
    Route::post('/api/groups/{group}/invite', [GroupController::class, 'invite'])->name('api.groups.invite');
    Route::post('/api/groups/{group}/accept', [GroupController::class, 'accept'])->name('api.groups.accept');
    Route::post('/api/groups/join-code', [GroupController::class, 'joinByCode'])->name('api.groups.join-code');
    Route::get('/groups/{group}/members', [GroupController::class, 'members'])->name('groups.members');
    Route::patch('/api/groups/{group}/members/{user}', [GroupController::class, 'updateMemberRole'])->name('api.groups.members.update');
    Route::delete('/api/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('api.groups.members.destroy');
    Route::get('/api/groups/{group}/containers', [GroupController::class, 'getContainers'])->name('api.groups.containers');

    // Notificaciones
    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::delete('/api/notifications/{notification}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');

    // Rutas de Tareas
    Route::get('/tareas', [TaskController::class, 'index'])->name('tareas.index');
    Route::get('/api/tasks', [TaskController::class, 'getTasks'])->name('api.tasks');
    Route::get('/api/tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show');
    Route::post('/api/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::put('/api/tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update');
    Route::delete('/api/tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');
    Route::post('/api/tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.tasks.complete');

    // Nueva BD de tareas: tasks_laravel
    Route::get('/api/tasks-laravel/meetings', [TaskLaravelController::class, 'meetings'])->name('api.tasks-laravel.meetings');
    Route::post('/api/tasks-laravel/import/{meetingId}', [TaskLaravelController::class, 'importFromJu'])->name('api.tasks-laravel.import');
    Route::post('/api/tasks-laravel/exists', [TaskLaravelController::class, 'exists'])->name('api.tasks-laravel.exists');
    Route::get('/api/tasks-laravel/tasks', [TaskLaravelController::class, 'tasks'])->name('api.tasks-laravel.tasks');
    Route::get('/api/tasks-laravel/tasks/{id}', [TaskLaravelController::class, 'show'])->name('api.tasks-laravel.tasks.show');
    Route::post('/api/tasks-laravel/tasks', [TaskLaravelController::class, 'store'])->name('api.tasks-laravel.tasks.store');
    Route::put('/api/tasks-laravel/tasks/{id}', [TaskLaravelController::class, 'update'])->name('api.tasks-laravel.tasks.update');
    Route::delete('/api/tasks-laravel/tasks/{id}', [TaskLaravelController::class, 'destroy'])->name('api.tasks-laravel.tasks.destroy');
    Route::post('/api/tasks-laravel/tasks/{id}/complete', [TaskLaravelController::class, 'complete'])->name('api.tasks-laravel.tasks.complete');
    Route::get('/api/tasks-laravel/calendar', [TaskLaravelController::class, 'calendar'])->name('api.tasks-laravel.calendar');

    // Comentarios de tareas
    Route::get('/api/tasks-laravel/tasks/{task}/comments', [\App\Http\Controllers\TaskCommentController::class, 'index'])->name('api.tasks-laravel.comments.index');
    Route::post('/api/tasks-laravel/tasks/{task}/comments', [\App\Http\Controllers\TaskCommentController::class, 'store'])->name('api.tasks-laravel.comments.store');

    // Archivos asociados a tareas
    Route::get('/api/tasks-laravel/tasks/{task}/files', [\App\Http\Controllers\TaskAttachmentController::class, 'index'])->name('api.tasks-laravel.files.index');
    Route::post('/api/tasks-laravel/tasks/{task}/files', [\App\Http\Controllers\TaskAttachmentController::class, 'store'])->name('api.tasks-laravel.files.store');
    Route::get('/api/drive/folders', [\App\Http\Controllers\TaskAttachmentController::class, 'folders'])->name('api.drive.folders');
    Route::get('/api/tasks-laravel/files/{file}/download', [\App\Http\Controllers\TaskAttachmentController::class, 'download'])->name('api.tasks-laravel.files.download');

    // Rutas de Contenedores
    Route::get('/api/content-containers', [ContainerController::class, 'getContainers'])->name('api.content-containers');
    Route::get('/api/content-containers/{id}/meetings', [ContainerController::class, 'getContainerMeetings'])->name('api.content-containers.meetings');

    Route::middleware('group.role')->group(function () {
        Route::post('/api/containers', [ContainerController::class, 'store'])->name('api.containers.store');
        Route::patch('/api/containers/{id}', [ContainerController::class, 'update'])->name('api.containers.update');
        Route::delete('/api/containers/{id}', [ContainerController::class, 'destroy'])->name('api.containers.destroy');

        Route::post('/api/content-containers', [ContainerController::class, 'store'])->name('api.content-containers.store');
        Route::post('/api/content-containers/{id}/meetings', [ContainerController::class, 'addMeeting'])->name('api.content-containers.addMeeting');
        Route::delete('/api/content-containers/{container}/meetings/{meeting}', [ContainerController::class, 'removeMeeting'])->name('api.content-containers.meetings.destroy');
    });

    // Rutas API para reuniones
    Route::get('/api/meetings', [MeetingController::class, 'getMeetings'])->name('api.meetings');
    Route::get('/api/shared-meetings', [MeetingController::class, 'getSharedMeetings'])->name('api.shared-meetings');
    Route::get('/api/meetings/{id}', [MeetingController::class, 'show'])->name('api.meetings.show');
    Route::put('/api/meetings/{id}/name', [MeetingController::class, 'updateName'])->name('api.meetings.updateName');
    Route::put('/api/meetings/{id}/segments', [MeetingController::class, 'updateSegments'])->name('api.meetings.updateSegments');
    Route::delete('/api/meetings/{id}', [MeetingController::class, 'destroy'])->name('api.meetings.destroy');
    Route::post('/api/meetings/cleanup', [MeetingController::class, 'cleanupModal'])->name('api.meetings.cleanup');
    Route::post('/api/meetings/{id}/encrypt', [MeetingController::class, 'encryptJu'])
        ->name('api.meetings.encrypt');

    // Rutas de descarga
    Route::get('/api/meetings/{id}/download-ju', [MeetingController::class, 'downloadJuFile'])->name('api.meetings.download-ju');
    Route::get('/api/meetings/{id}/download-audio', [MeetingController::class, 'downloadAudioFile'])->name('api.meetings.download-audio');
    Route::get('/api/meetings/{meeting}/download-report', [MeetingController::class, 'downloadReport'])->name('api.meetings.download-report');
    Route::post('/api/meetings/{id}/download-pdf', [MeetingController::class, 'downloadPdf'])->name('api.meetings.download-pdf');
    Route::post('/api/meetings/{id}/preview-pdf', [MeetingController::class, 'previewPdf'])->name('api.meetings.preview-pdf');
    Route::get('/api/meetings/{meeting}/audio', [MeetingController::class, 'streamAudio'])->name('api.meetings.audio');

    // API para reuniones pendientes
    Route::get('/api/pending-meetings', [MeetingController::class, 'getPendingMeetings']);
    Route::post('/api/pending-meetings/{id}/analyze', [MeetingController::class, 'analyzePendingMeeting']);
    Route::post('/api/pending-meetings/complete', [MeetingController::class, 'completePendingMeeting']);
    Route::get('/api/pending-meetings/{id}/info', [MeetingController::class, 'getPendingProcessingInfo']);
    Route::get('/api/pending-meetings/audio/{tempFileName}', [MeetingController::class, 'getPendingAudioFile']);

    Route::post('/drive/disconnect', [GoogleAuthController::class, 'disconnect'])->name('drive.disconnect');

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
        return view('audio-processing');
    })->name('audio-processing');
});

Route::get('/new-meeting', function () {
    return view('new-meeting');
})->name('new-meeting');

Route::post('/transcription', [TranscriptionController::class, 'store'])
    ->name('transcription.store')
    ->middleware(['api-key', 'auth', 'group.role']);
Route::get('/transcription/{id}', [TranscriptionController::class, 'show'])->name('transcription.show');
Route::post('/analysis', [\App\Http\Controllers\AnalysisController::class, 'analyze'])
    ->name('analysis')
    ->middleware(['api-key', 'auth', 'group.role']);

// Admin routes (solo para roles específicos)
Route::middleware(['api-key', 'auth'])->group(function () {
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
});
