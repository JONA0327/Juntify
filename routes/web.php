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


Route::middleware(['api-key', 'auth'])->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/logout',[LoginController::class, 'logout'])->name('logout');
    Route::get('/reuniones', [MeetingController::class, 'index'])->name('reuniones.index');
    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::get('/organization/api-guide', function () {
        return view('organization.api-guide');
    })->name('organization.api-guide');

    // Rutas de Tareas
    Route::get('/tareas', [TaskController::class, 'index'])->name('tareas.index');

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
