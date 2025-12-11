<?php
// Container files upload & listing (moved inside PHP tag to avoid accidental output)
use App\Http\Controllers\ContainerFileController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\TranscriptionController;
use App\Http\Controllers\TranscriptionTempController;
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
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\PlanManagementController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\TutorialController;
use App\Models\Analyzer;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;

// Rutas de archivos de contenedor (deben ir antes de otros grupos pero dentro de PHP)
Route::middleware(['web'])->group(function () {
    Route::get('/containers/{container}/files/{file}', [ContainerFileController::class, 'download'])->name('containers.files.download');
    Route::get('/containers/{container}/files/{file}/debug', [ContainerFileController::class, 'debugDownload'])->name('containers.files.debug');

    // Rutas públicas para responder a asignaciones de tareas por email
    Route::get('/tasks/{task}/respond/{action}', [TaskLaravelController::class, 'respondByEmail'])
        ->name('tasks.respond')
        ->where('action', 'accept|reject');
});


Route::get('/', function () {
    return view('index');
});

Route::view('/condiciones-de-uso', 'legal.terms')->name('legal.terms');
Route::view('/politica-de-privacidad', 'legal.privacy')->name('legal.privacy');

// Rutas de Auth
Route::get('/login',    [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login',   [LoginController::class, 'login']);
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register',[RegisterController::class, 'register']);

// Recuperación de contraseña
Route::get('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'showForgotForm'])->name('password.forgot');
Route::post('/forgot-password/send-code', [\App\Http\Controllers\PasswordResetController::class, 'sendCode'])->name('password.sendCode');
Route::post('/forgot-password/verify-code', [\App\Http\Controllers\PasswordResetController::class, 'verifyCode'])->name('password.verifyCode');
Route::post('/forgot-password/reset', [\App\Http\Controllers\PasswordResetController::class, 'resetPassword'])->name('password.reset');

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
    Route::get('/profile/payment/{payment}/receipt', [ProfileController::class, 'downloadReceipt'])
        ->name('profile.payment.receipt');

    Route::post('/logout',[LoginController::class, 'logout'])->name('logout');

    // Eliminar cuenta (confirmación por modal)
    Route::delete('/account/delete', [\App\Http\Controllers\DeleteAccountController::class, 'destroy'])
        ->name('account.delete');

    // Dashboard - redirige a reuniones
    Route::get('/dashboard', function () {
        return redirect()->route('reuniones.index');
    })->name('dashboard');



    // Reuniones - con refresh automático de token
    Route::get('/reuniones', [MeetingController::class, 'index'])
        ->middleware('google.refresh')
        ->name('reuniones.index');

    // Reuniones BNI (Almacenamiento Temporal)
    Route::get('/reuniones-bni', [TranscriptionTempController::class, 'webIndex'])
        ->name('reuniones.bni.index');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');

    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    // Ruta web alternativa para crear organización (fallback a /api/organizations)
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store.web');
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

    // Rutas POST para manejo de carpetas (crear carpeta manual está deshabilitado; se crea automáticamente tras conectar)
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
    Route::post('/drive/save-results/chunked/init', [DriveController::class, 'initChunkedAudioSave']);
    Route::post('/drive/save-results/chunked/upload', [DriveController::class, 'uploadChunkedAudioSave']);
    Route::post('/drive/save-results/chunked/finalize', [DriveController::class, 'finalizeChunkedAudioSave']);

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

// Página dedicada a la experiencia "Nueva Reunión" (grabación o carga de audio)
Route::get('/new-meeting', function () {
    $user = auth()->user();
    return view('new-meeting', [
        'userRole' => $user->roles ?? 'free',
        'organizationId' => $user->current_organization_id ?? null,
        'organizationName' => optional($user?->organization)->nombre_organizacion,
    ]);
})->name('new-meeting')->middleware('cors.ffmpeg');

// Rutas para subida por chunks (archivos grandes)
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
        $analyzerCount = Analyzer::count();
        $systemAnalyzerCount = Analyzer::where('is_system', 1)->count();
        $latestAnalyzerUpdate = Analyzer::max('updated_at');

        $userCount = User::count();
        $newUsersToday = User::whereDate('created_at', now()->toDateString())->count();

        $planCount = Plan::count();
        $activePlanCount = Plan::where('is_active', true)->count();
        $latestPlanUpdate = Plan::max('updated_at');

        return view('admin.dashboard', [
            'analyzerCount' => $analyzerCount,
            'systemAnalyzerCount' => $systemAnalyzerCount,
            'latestAnalyzerUpdate' => $latestAnalyzerUpdate
                ? Carbon::parse($latestAnalyzerUpdate)->diffForHumans()
                : 'Sin datos',
            'userCount' => $userCount,
            'newUsersToday' => $newUsersToday,
            'planCount' => $planCount,
            'activePlanCount' => $activePlanCount,
            'latestPlanUpdate' => $latestPlanUpdate
                ? Carbon::parse($latestPlanUpdate)->diffForHumans()
                : 'Sin datos',
        ]);
    })->name('admin.dashboard');

    Route::get('/admin/analyzers', [\App\Http\Controllers\AnalyzerController::class, 'index'])
        ->name('admin.analyzers');

    Route::get('/admin/analyzers/list', [\App\Http\Controllers\AnalyzerController::class, 'list']);
    Route::get('/admin/analyzers/{analyzer}', [\App\Http\Controllers\AnalyzerController::class, 'show']);
    Route::post('/admin/analyzers', [\App\Http\Controllers\AnalyzerController::class, 'store']);
    Route::put('/admin/analyzers/{analyzer}', [\App\Http\Controllers\AnalyzerController::class, 'update']);
    Route::delete('/admin/analyzers/{analyzer}', [\App\Http\Controllers\AnalyzerController::class, 'destroy']);

    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users');
    Route::get('/admin/users/list', [UserManagementController::class, 'list']);
    Route::put('/admin/users/{user}/role', [UserManagementController::class, 'updateRole']);
    Route::post('/admin/users/{user}/block', [UserManagementController::class, 'block']);
    Route::post('/admin/users/{user}/unblock', [UserManagementController::class, 'unblock']);
    Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy']);

    Route::get('/admin/panels', [PanelController::class, 'index'])->name('admin.panels');
    Route::get('/admin/panels/list', [PanelController::class, 'list']);
    Route::get('/admin/panels/eligible-admins', [PanelController::class, 'eligibleAdmins']);
    Route::post('/admin/panels', [PanelController::class, 'store']);

    Route::get('/admin/plans/manage', [PlanManagementController::class, 'index'])->name('admin.plans');
    Route::get('/admin/plans/list', [PlanManagementController::class, 'list']);
    Route::post('/admin/plans', [PlanManagementController::class, 'store']);

    // Rutas para administración de empresas
    Route::get('/admin/empresas', [\App\Http\Controllers\Admin\EmpresaController::class, 'index'])->name('admin.empresas.index');
    Route::post('/admin/empresas', [\App\Http\Controllers\Admin\EmpresaController::class, 'store'])->name('admin.empresas.store');
    Route::get('/admin/empresas/{empresa}', [\App\Http\Controllers\Admin\EmpresaController::class, 'show'])->name('admin.empresas.show');
    Route::put('/admin/empresas/{empresa}', [\App\Http\Controllers\Admin\EmpresaController::class, 'update'])->name('admin.empresas.update');
    Route::delete('/admin/empresas/{empresa}', [\App\Http\Controllers\Admin\EmpresaController::class, 'destroy'])->name('admin.empresas.destroy');
    Route::post('/admin/empresas/{empresa}/integrantes', [\App\Http\Controllers\Admin\EmpresaController::class, 'addIntegrante'])->name('admin.empresas.add-integrante');
    Route::delete('/admin/empresas/integrantes/{integrante}', [\App\Http\Controllers\Admin\EmpresaController::class, 'removeIntegrante'])->name('admin.empresas.remove-integrante');
    Route::post('/admin/update-user-role', [\App\Http\Controllers\Admin\EmpresaController::class, 'updateUserRole'])->name('admin.update-user-role');
    Route::get('/admin/search-users', [\App\Http\Controllers\Admin\EmpresaController::class, 'searchUsers'])->name('admin.search-users');

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
        Route::get('/tasks-laravel/assignable-users', [TaskLaravelController::class, 'assignableUsers'])->name('api.tasks-laravel.assignable-users');
        // Asignaciones y respuestas
        Route::post('/tasks-laravel/tasks/{id}/assign', [TaskLaravelController::class, 'assign'])->name('api.tasks-laravel.tasks.assign');
        Route::post('/tasks-laravel/tasks/{id}/cancel-assignment', [TaskLaravelController::class, 'cancelAssignment'])->name('api.tasks-laravel.tasks.cancel-assignment');
        Route::post('/tasks-laravel/tasks/{id}/respond', [TaskLaravelController::class, 'respond'])->name('api.tasks-laravel.tasks.respond');
        Route::post('/tasks-laravel/tasks/{id}/reactivate', [TaskLaravelController::class, 'reactivate'])->name('api.tasks-laravel.tasks.reactivate');
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
            Route::delete('/sessions/{id}', [AiAssistantController::class, 'deleteSession'])->name('api.ai-assistant.sessions.delete');
            Route::patch('/sessions/{id}', [AiAssistantController::class, 'updateSession'])->name('api.ai-assistant.sessions.update');
            Route::get('/sessions/{id}/messages', [AiAssistantController::class, 'getMessages'])->name('api.ai-assistant.sessions.messages');
            Route::post('/sessions/{id}/messages', [AiAssistantController::class, 'sendMessage'])->name('api.ai-assistant.sessions.send-message');

            // Datos para contexto
            Route::get('/containers', [AiAssistantController::class, 'getContainers'])->name('api.ai-assistant.containers');
            Route::get('/meetings', [AiAssistantController::class, 'getMeetings'])->name('api.ai-assistant.meetings');
            Route::get('/contact-chats', [AiAssistantController::class, 'getContactChats'])->name('api.ai-assistant.contact-chats');

            // Gestión de documentos
            Route::get('/documents', [AiAssistantController::class, 'getDocuments'])->name('api.ai-assistant.documents');
            Route::post('/documents/upload', [AiAssistantController::class, 'uploadDocument'])->name('api.ai-assistant.documents.upload');

            // Límites del plan
            Route::get('/limits', [AiAssistantController::class, 'getLimits'])->name('api.ai-assistant.limits');
            Route::post('/documents/wait', [AiAssistantController::class, 'waitDocuments'])->name('api.ai-assistant.documents.wait');
            // Listado directo desde la carpeta de Drive "Documentos"
            Route::get('/documents/drive', [AiAssistantController::class, 'listDriveDocuments'])->name('api.ai-assistant.documents.drive');
            Route::post('/documents/drive/attach', [AiAssistantController::class, 'attachDriveDocuments'])->name('api.ai-assistant.documents.drive.attach');

            // Generación de PDF de resumen
            Route::post('/sessions/{id}/summary-pdf', [AiAssistantController::class, 'generateSummaryPdf'])->name('api.ai-assistant.sessions.summary-pdf');
            Route::post('/containers/{containerId}/preload', [AiAssistantController::class, 'preloadContainer'])->name('api.ai-assistant.containers.preload');
            // Pre-cargar .ju de una reunión específica
            Route::post('/meetings/{id}/preload', [AiAssistantController::class, 'preloadMeeting'])->name('api.ai-assistant.meetings.preload');
            // Obtener detalles específicos de una reunión (resumen, puntos clave, tareas, transcripción)
            Route::get('/meeting/{id}/details', [AiAssistantController::class, 'getMeetingDetails'])->name('api.ai-assistant.meeting.details');
            // Importar tareas de todas las reuniones del contenedor (garantiza .ju descargado/cacheado)
            Route::post('/containers/{containerId}/import-tasks', [AiAssistantController::class, 'importContainerTasks'])->name('api.ai-assistant.containers.import-tasks');
            // Diagnóstico por contenedor
            Route::get('/containers/{containerId}/diagnostics', [AiAssistantController::class, 'containerDiagnostics'])->name('api.ai-assistant.containers.diagnostics');

            // Límites del plan del usuario
            Route::get('/limits', [AiAssistantController::class, 'getLimits'])->name('api.ai-assistant.limits');

            // Debug temporal - remover después
            Route::post('/debug/meeting-context', [AiAssistantController::class, 'debugMeetingContext'])->name('api.ai-assistant.debug.meeting-context');
        });
    });

    // Ruta principal del asistente IA
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai-assistant');
});

// Rutas de suscripciones y pagos
Route::middleware(['auth'])->group(function () {
    // Planes y suscripciones
    Route::get('/subscription/plans', [SubscriptionPaymentController::class, 'index'])->name('subscription.plans');
    Route::post('/subscription/create-preference', [SubscriptionPaymentController::class, 'createPreference'])->name('subscription.create-preference');

    // Ruta de test temporal
    Route::get('/test-create-preference', function () {
        return view('test-create-preference');
    })->middleware('auth');

    // Debug route para verificar planes
    Route::get('/debug-plans', function () {
        $plans = App\Models\Plan::where('is_active', true)->orderBy('price')->get();
        return view('debug-plans', compact('plans'));
    });

    // Ruta de prueba para MercadoPago
    Route::get('/mercadopago-test', function () {
        return view('mercadopago-test');
    })->middleware('auth');

    // Debug para el flujo de profile payment
    Route::get('/debug-profile-payment', function () {
        return view('debug-profile-payment');
    })->middleware('auth');

    // Simulador de flujo de pago
    Route::get('/simulate-payment-flow', function () {
        return view('simulate-payment-flow');
    })->middleware('auth');

    // Sistema de prueba de pagos
    Route::get('/payment-test', [App\Http\Controllers\PaymentTestController::class, 'selectPlan'])->middleware('auth')->name('payment-test.select');
    Route::post('/payment-test/simulate', [App\Http\Controllers\PaymentTestController::class, 'simulateSuccess'])->middleware('auth')->name('payment-test.simulate');

    // Estados de pago
    Route::get('/payment/success', [SubscriptionPaymentController::class, 'success'])->name('payment.success');
    Route::get('/payment/failure', [SubscriptionPaymentController::class, 'failure'])->name('payment.failure');
    Route::get('/payment/pending', [SubscriptionPaymentController::class, 'pending'])->name('payment.pending');

    // Simulación de pago exitoso (desarrollo)
    Route::get('/payment/simulate-success', [SubscriptionPaymentController::class, 'simulateSuccess'])->name('payment.simulate-success');

    // API para verificar estado
    Route::post('/payment/check-status', [SubscriptionPaymentController::class, 'checkPaymentStatus'])->name('payment.check-status');

    // Historial de pagos
    Route::get('/subscription/history', [SubscriptionPaymentController::class, 'history'])->name('subscription.history');
});

// Webhook de MercadoPago (sin middleware auth)
Route::post('/webhook/mercadopago', [SubscriptionPaymentController::class, 'webhook'])->name('payment.webhook');


