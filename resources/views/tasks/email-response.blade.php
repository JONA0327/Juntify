@extends('layouts.app')

@section('title', 'Respuesta de Tarea')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">
                @if(isset($success) && $success)
                    ✅ Tarea {{ $actionText }}
                @elseif(isset($alreadyResponded) && $alreadyResponded)
                    ⚠️ Ya respondiste esta tarea
                @elseif(isset($needsAuth) && $needsAuth)
                    🔐 Confirmar {{ $actionText }} tarea
                @else
                    📋 Gestión de Tarea
                @endif
            </h1>
            <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto"></div>
        </div>

        <div class="bg-white/10 backdrop-blur-md rounded-xl shadow-xl border border-white/20 p-6 mb-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-white mb-4">📝 {{ $task->tarea }}</h2>

                @if($task->descripcion)
                    <div class="bg-slate-800/50 rounded-lg p-4 mb-4">
                        <h3 class="text-sm font-medium text-slate-300 mb-2">Descripción:</h3>
                        <p class="text-slate-200">{{ $task->descripcion }}</p>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    @if($task->fecha_limite)
                        <div class="flex items-center text-slate-300">
                            <span class="mr-2">📅</span>
                            <strong class="mr-2">Fecha límite:</strong>
                            {{ $task->fecha_limite->format('d/m/Y') }}
                        </div>
                    @endif

                    @if($task->hora_limite)
                        <div class="flex items-center text-slate-300">
                            <span class="mr-2">⏰</span>
                            <strong class="mr-2">Hora límite:</strong>
                            {{ $task->hora_limite }}
                        </div>
                    @endif

                    <div class="flex items-center text-slate-300">
                        <span class="mr-2">📊</span>
                        <strong class="mr-2">Prioridad:</strong>
                        @if($task->prioridad === 'alta')
                            <span class="text-red-400">🔴 Alta</span>
                        @elseif($task->prioridad === 'media')
                            <span class="text-yellow-400">🟡 Media</span>
                        @else
                            <span class="text-green-400">🟢 Baja</span>
                        @endif
                    </div>

                    @if($task->meeting)
                        <div class="flex items-center text-slate-300">
                            <span class="mr-2">🎯</span>
                            <strong class="mr-2">Reunión:</strong>
                            {{ $task->meeting->meeting_name ?: 'Sin nombre' }}
                        </div>
                    @endif
                </div>
            </div>

            @if(isset($success) && $success)
                <!-- Éxito -->
                <div class="bg-green-600/20 border border-green-400/30 rounded-lg p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-3">✅</span>
                        <h3 class="text-lg font-semibold text-green-300">¡Perfecto!</h3>
                    </div>
                    <p class="text-green-200">{{ $message }}</p>
                </div>

                <div class="text-center">
                    <a href="{{ route('tareas.index') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                        📋 Ir a mis Tareas
                    </a>
                </div>

            @elseif(isset($alreadyResponded) && $alreadyResponded)
                <!-- Ya respondió -->
                <div class="bg-yellow-600/20 border border-yellow-400/30 rounded-lg p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-3">⚠️</span>
                        <h3 class="text-lg font-semibold text-yellow-300">Ya respondiste esta tarea</h3>
                    </div>
                    <p class="text-yellow-200">
                        Estado actual:
                        @if($currentStatus === 'accepted')
                            <span class="text-green-400 font-semibold">✅ Aceptada</span>
                        @elseif($currentStatus === 'rejected')
                            <span class="text-red-400 font-semibold">❌ Rechazada</span>
                        @else
                            <span class="text-slate-400 font-semibold">{{ $currentStatus }}</span>
                        @endif
                    </p>
                </div>

                <div class="text-center">
                    <a href="{{ route('tareas.index') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                        📋 Ver mis Tareas
                    </a>
                </div>

            @elseif(isset($needsAuth) && $needsAuth)
                <!-- Necesita autenticación -->
                <div class="bg-blue-600/20 border border-blue-400/30 rounded-lg p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-3">🔐</span>
                        <h3 class="text-lg font-semibold text-blue-300">Autenticación Requerida</h3>
                    </div>
                    <p class="text-blue-200">Para {{ $actionText }} esta tarea, necesitas iniciar sesión en Juntify.</p>
                </div>

                <div class="space-y-4">
                    <div class="text-center">
                        <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            🔑 Iniciar Sesión
                        </a>
                    </div>

                    <div class="text-center">
                        <p class="text-slate-400 text-sm">¿No tienes cuenta?</p>
                        <a href="{{ route('register') }}" class="text-blue-400 hover:text-blue-300 text-sm underline">
                            Regístrate en Juntify
                        </a>
                    </div>
                </div>

            @else
                <!-- Formulario de confirmación -->
                <div class="bg-slate-800/50 border border-slate-600 rounded-lg p-4 mb-4">
                    <h3 class="text-lg font-semibold text-white mb-2">
                        @if($action === 'accept')
                            ✅ ¿Confirmas que quieres aceptar esta tarea?
                        @else
                            ❌ ¿Confirmas que quieres rechazar esta tarea?
                        @endif
                    </h3>

                    @if($action === 'reject')
                        <p class="text-slate-300 text-sm mb-4">
                            Opcionalmente puedes especificar un motivo para el rechazo:
                        </p>
                        <textarea id="rejectReason" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 resize-none"
                                  rows="3" placeholder="Motivo del rechazo (opcional)..."></textarea>
                    @endif
                </div>

                <div class="flex justify-center gap-4">
                    @if($action === 'accept')
                        <button onclick="confirmResponse('accept')" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            ✅ Sí, Aceptar Tarea
                        </button>
                    @else
                        <button onclick="confirmResponse('reject')" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                            ❌ Sí, Rechazar Tarea
                        </button>
                    @endif

                    <a href="{{ route('tareas.index') }}" class="px-6 py-3 bg-slate-600 hover:bg-slate-700 text-white font-medium rounded-lg transition-colors">
                        🔙 Cancelar
                    </a>
                </div>
            @endif
        </div>

        <div class="text-center text-slate-400 text-sm">
            <p>🚀 Powered by <strong>Juntify</strong> - Tu plataforma de gestión de tareas</p>
        </div>
    </div>
</div>

@if(!isset($success) && !isset($alreadyResponded) && !isset($needsAuth))
    @push('scripts')
        @vite('resources/js/tasks/email-response.js')
    @endpush
@endif
@endsection
