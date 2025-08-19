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
use App\Http\Controllers\ContainerController;


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
    // Perfil
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/logout',[LoginController::class, 'logout'])->name('logout');
    Route::get('/reuniones', [MeetingController::class, 'index'])->name('reuniones.index');

    // Rutas de Tareas
    Route::get('/tareas', [TaskController::class, 'index'])->name('tareas.index');
    Route::get('/api/tasks', [TaskController::class, 'getTasks'])->name('api.tasks');
    Route::get('/api/tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show');
    Route::post('/api/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::put('/api/tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update');
    Route::delete('/api/tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');
    Route::post('/api/tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.tasks.complete');

    // Rutas de Contenedores
    Route::get('/api/content-containers', [ContainerController::class, 'getContainers'])->name('api.content-containers');
    Route::post('/api/content-containers', [ContainerController::class, 'store'])->name('api.content-containers.store');
    Route::put('/api/content-containers/{id}', [ContainerController::class, 'update'])->name('api.content-containers.update');
    Route::delete('/api/content-containers/{id}', [ContainerController::class, 'destroy'])->name('api.content-containers.destroy');

    // Rutas API para reuniones
    Route::get('/api/meetings', [MeetingController::class, 'getMeetings'])->name('api.meetings');
    Route::get('/api/shared-meetings', [MeetingController::class, 'getSharedMeetings'])->name('api.shared-meetings');
    Route::get('/api/containers', [MeetingController::class, 'getContainers'])->name('api.containers');
    Route::post('/api/containers', [MeetingController::class, 'storeContainer'])->name('api.containers.store');
    Route::post('/api/containers/{id}/meetings', [MeetingController::class, 'addMeetingToContainer'])->name('api.containers.addMeeting');
    Route::get('/api/containers/{id}/meetings', [MeetingController::class, 'getContainerMeetings'])->name('api.containers.meetings');
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

Route::post('/transcription', [TranscriptionController::class, 'store'])->name('transcription.store');
Route::get('/transcription/{id}', [TranscriptionController::class, 'show'])->name('transcription.show');
Route::post('/analysis', [\App\Http\Controllers\AnalysisController::class, 'analyze'])->name('analysis');

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
});
