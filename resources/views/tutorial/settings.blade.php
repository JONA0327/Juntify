@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
    <div class="container mx-auto max-w-4xl px-6 py-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Configuración del Tutorial</h1>
            <p class="text-slate-400">Personaliza tu experiencia con el tutorial interactivo de Juntify</p>
        </div>

        <!-- Tutorial Status Card -->
        <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Estado del Tutorial</h2>
                <div id="tutorial-status-badge" class="px-3 py-1 rounded-full text-sm font-medium bg-slate-600 text-slate-200">
                    Cargando...
                </div>
            </div>

            <div id="tutorial-stats" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="text-center p-4 bg-slate-700/50 rounded-lg">
                    <div class="text-2xl font-bold text-sky-400" id="completion-rate">-%</div>
                    <div class="text-sm text-slate-400">Progreso</div>
                </div>
                <div class="text-center p-4 bg-slate-700/50 rounded-lg">
                    <div class="text-2xl font-bold text-green-400" id="completed-sections">-</div>
                    <div class="text-sm text-slate-400">Secciones Completadas</div>
                </div>
                <div class="text-center p-4 bg-slate-700/50 rounded-lg">
                    <div class="text-sm text-slate-300" id="last-seen">-</div>
                    <div class="text-sm text-slate-400">Última Vez</div>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="startTutorial()" class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Iniciar Tutorial
                </button>

                <button onclick="resetTutorial()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Reiniciar Tutorial
                </button>
            </div>
        </div>

        <!-- Preferences Card -->
        <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Preferencias</h2>

            <form id="tutorial-preferences-form" class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-slate-700/30 rounded-lg">
                    <div>
                        <label class="text-slate-200 font-medium">Auto-iniciar tutorial</label>
                        <p class="text-sm text-slate-400">Mostrar el tutorial automáticamente para nuevos usuarios</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="auto-start" class="sr-only peer" />
                        <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-700/30 rounded-lg">
                    <div>
                        <label class="text-slate-200 font-medium">Mostrar botón de ayuda</label>
                        <p class="text-sm text-slate-400">Mostrar el botón flotante para acceder al tutorial</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="show-help-button" class="sr-only peer" checked />
                        <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-700/30 rounded-lg">
                    <div>
                        <label class="text-slate-200 font-medium">Saltar secciones completadas</label>
                        <p class="text-sm text-slate-400">No mostrar pasos ya completados en tutoriales futuros</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="skip-completed" class="sr-only peer" />
                        <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Guardar Preferencias
                    </button>
                </div>
            </form>
        </div>

        <!-- Tutorial Sections Guide -->
        <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Secciones del Tutorial</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-slate-700/30 rounded-lg">
                    <h3 class="font-medium text-white mb-2">📅 Reuniones</h3>
                    <p class="text-sm text-slate-400 mb-3">Aprende a crear, gestionar y organizar tus reuniones</p>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-sky-400 rounded-full"></span>
                        <span class="text-xs text-slate-500">5 pasos</span>
                    </div>
                </div>

                <div class="p-4 bg-slate-700/30 rounded-lg">
                    <h3 class="font-medium text-white mb-2">🤖 Asistente IA</h3>
                    <p class="text-sm text-slate-400 mb-3">Descubre cómo usar el asistente para analizar reuniones</p>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                        <span class="text-xs text-slate-500">3 pasos</span>
                    </div>
                </div>

                <div class="p-4 bg-slate-700/30 rounded-lg">
                    <h3 class="font-medium text-white mb-2">👥 Contactos</h3>
                    <p class="text-sm text-slate-400 mb-3">Gestiona participantes y colaboradores</p>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-yellow-400 rounded-full"></span>
                        <span class="text-xs text-slate-500">2 pasos</span>
                    </div>
                </div>

                <div class="p-4 bg-slate-700/30 rounded-lg">
                    <h3 class="font-medium text-white mb-2">📋 Tareas</h3>
                    <p class="text-sm text-slate-400 mb-3">Organiza y da seguimiento a tareas derivadas de reuniones</p>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-purple-400 rounded-full"></span>
                        <span class="text-xs text-slate-500">3 pasos</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts específicos para la configuración del tutorial -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadTutorialStatus();
    loadPreferences();

    // Form handler para preferencias
    document.getElementById('tutorial-preferences-form').addEventListener('submit', function(e) {
        e.preventDefault();
        savePreferences();
    });
});

function loadTutorialStatus() {
    fetch('{{ route("tutorial.status") }}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const tutorialData = data.data;

                // Actualizar badge de estado
                const statusBadge = document.getElementById('tutorial-status-badge');
                if (tutorialData.completed) {
                    statusBadge.textContent = 'Completado';
                    statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-green-600 text-white';
                } else {
                    statusBadge.textContent = 'En Progreso';
                    statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-sky-600 text-white';
                }

                // Actualizar estadísticas
                const totalSections = 4; // Reuniones, AI, Contactos, Tareas
                const completedCount = tutorialData.completed_sections ? tutorialData.completed_sections.length : 0;
                const completionRate = Math.round((completedCount / totalSections) * 100);

                document.getElementById('completion-rate').textContent = completionRate + '%';
                document.getElementById('completed-sections').textContent = completedCount + '/' + totalSections;

                // Última vez visto
                const lastSeen = tutorialData.last_seen;
                if (lastSeen) {
                    const date = new Date(lastSeen);
                    document.getElementById('last-seen').textContent = date.toLocaleDateString();
                } else {
                    document.getElementById('last-seen').textContent = 'Nunca';
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar estado del tutorial:', error);
        });
}

function loadPreferences() {
    fetch('{{ route("tutorial.status") }}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data.preferences) {
                const prefs = data.data.preferences;

                document.getElementById('auto-start').checked = prefs.auto_start ?? true;
                document.getElementById('show-help-button').checked = prefs.show_help_button ?? true;
                document.getElementById('skip-completed').checked = prefs.skip_completed_sections ?? false;
            }
        })
        .catch(error => {
            console.error('Error al cargar preferencias:', error);
        });
}

function savePreferences() {
    const preferences = {
        auto_start: document.getElementById('auto-start').checked,
        show_help_button: document.getElementById('show-help-button').checked,
        skip_completed_sections: document.getElementById('skip-completed').checked
    };

    fetch('{{ route("tutorial.preferences") }}', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(preferences)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Mostrar notificación de éxito
            showNotification('Preferencias guardadas correctamente', 'success');
        } else {
            showNotification('Error al guardar preferencias', 'error');
        }
    })
    .catch(error => {
        console.error('Error al guardar preferencias:', error);
        showNotification('Error al guardar preferencias', 'error');
    });
}

function showNotification(message, type = 'info') {
    // Crear notificación temporal
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white z-50 ${
        type === 'success' ? 'bg-green-600' :
        type === 'error' ? 'bg-red-600' : 'bg-sky-600'
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endsection
