<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Reuniones - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Global Variables -->
    <script>
        window.userRole = @json($userRole);
        window.currentOrganizationId = @json($organizationId);
    </script>

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js', 'resources/css/new-meeting.css','resources/css/index.css',
        'resources/css/reuniones_v2.css', /* Nuevo archivo de estilos */
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'   /* Nuevo archivo de script */
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <!-- Contenedor Centrado -->
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Header -->
                <header class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8 pb-12 fade-in">
                    <!-- Columna Izquierda: Título y Filtros -->
                    <div class="flex-1 space-y-6">
                        <div class="space-y-3">
                            <h1 class="text-4xl font-bold text-white tracking-tight">Reuniones</h1>
                            <p class="text-slate-400 text-lg">Gestiona y organiza todas tus reuniones de forma eficiente</p>
                        </div>

                        <!-- Barra de Búsqueda y Filtros -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- Barra de Búsqueda -->
                            <div class="relative flex-1 max-w-md">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="text" placeholder="Buscar en reuniones..." class="bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 block w-full text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200 shadow-lg shadow-black/10">
                            </div>

                            <!-- Botón de Filtro de Fecha -->
                            <button class="bg-slate-800/50 backdrop-blur-custom text-slate-200 px-4 py-3 rounded-xl flex items-center gap-3 hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 border border-slate-700/50 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span class="font-medium">Fecha</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Columna Derecha: Análisis y Botones de Acción -->
                    <div class="flex flex-col items-end gap-6 fade-in stagger-1">
                        <!-- Análisis Restantes -->
                        <div class="w-full sm:w-80 bg-gradient-to-br from-slate-800/50 to-slate-800/30 backdrop-blur-custom border border-slate-700/50 rounded-xl p-5 shadow-lg shadow-black/10">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-sm font-semibold text-slate-300">Análisis mensuales</p>
                                <span class="text-xs text-slate-400 bg-slate-700/50 px-2 py-1 rounded-full">45/100</span>
                            </div>
                            <div class="w-full bg-slate-700/50 rounded-full h-2.5 overflow-hidden">
                                <div class="progress-bar bg-gradient-to-r from-yellow-400 to-yellow-500 h-2.5 rounded-full shadow-lg shadow-yellow-400/25" style="width: 45%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">55 análisis restantes este mes</p>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center gap-3">
                            <button id="create-container-btn" class="inline-flex items-center gap-3 px-5 py-3 bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl text-slate-200 font-medium hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                <span>Nuevo Contenedor</span>
                            </button>

                            <button class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-200 shadow-lg shadow-yellow-400/25 hover:shadow-yellow-400/40 transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Reuniones Pendientes</span>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Sistema de Reuniones -->
                <nav class="mb-6">
                    <ul class="flex gap-3">
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="my-meetings">Mis reuniones</button>
                        </li>
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="shared-meetings">Reuniones compartidas</button>
                        </li>
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="containers">Contenedores</button>
                        </li>
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="contacts">Contactos</button>
                        </li>
                    </ul>
                </nav>

                <div class="fade-in stagger-2" id="meetings-container">
                    <div id="my-meetings" class="hidden">
                        @isset($meetings)
                            <div class="meetings-grid">
                                @forelse ($meetings as $m)
                                    <x-meeting-card :id="$m['id']"
                                        :meeting-name="$m['meeting_name']"
                                        :created-at="$m['created_at']"
                                        :audio-folder="$m['audio_folder'] ?? ''"
                                        :transcript-folder="$m['transcript_folder'] ?? ''" />
                                @empty
                                    <div class="loading-card"><p>No tienes reuniones</p></div>
                                @endforelse
                            </div>
                        @else
                            {{-- Fallback: el contenido se cargará dinámicamente por JS --}}
                            <div class="loading-card">
                                <div class="loading-spinner"></div>
                                <p>Cargando reuniones...</p>
                            </div>
                        @endisset
                    </div>

                    <div id="shared-meetings" class="hidden">
                        <div class="loading-card"><p>No hay reuniones compartidas</p></div>
                    </div>

                    <div id="containers" class="hidden">
                        <div class="loading-card"><p>No tienes contenedores</p></div>
                    </div>
                    <div id="contacts" class="hidden">
                        @include('contacts.index')
                    </div>
                </div>

            </div>
        </main>
    </div>

    {{-- Modal para compartir reunión (included once at the end as a Blade component) --}}

    <!-- Modal para cambiar hablante -->
    <div class="modal" id="change-speaker-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <x-icon name="user" class="modal-icon" />
                    Cambiar Hablante
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Ingresa el nuevo nombre para este hablante específico.
                </p>
                <div class="form-group">
                    <label class="form-label">Nombre del hablante</label>
                    <input type="text" class="modal-input" id="speaker-name-input" placeholder="Ej: María González">
                    <div class="input-hint">Este cambio solo afectará a este segmento de la transcripción.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeChangeSpeakerModal()">Cancelar</button>
                <button class="btn btn-primary" id="confirm-speaker-change" onclick="confirmSpeakerChange()">
                    <x-icon name="check" class="btn-icon" />
                    <span class="sr-only">Cambiar Hablante</span>
                    Cambiar Hablante
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para cambiar hablantes globalmente -->
    <div class="modal" id="change-global-speaker-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <x-icon name="users" class="modal-icon" />
                    Cambiar Hablante Globalmente
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Cambia el nombre de este hablante en toda la transcripción.
                </p>
                <div class="form-group">
                    <label class="form-label">Hablante actual</label>
                    <input type="text" class="modal-input" id="current-speaker-name" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Nuevo nombre</label>
                    <input type="text" class="modal-input" id="global-speaker-name-input" placeholder="Ej: María González">
                    <div class="input-hint">Este cambio afectará a todos los segmentos de este hablante.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeGlobalSpeakerModal()">Cancelar</button>
                <button class="btn btn-primary" id="confirm-global-speaker-change" onclick="confirmGlobalSpeakerChange()">
                    <x-icon name="check" class="btn-icon" />
                    <span class="sr-only">Cambiar Globalmente</span>
                    Cambiar Globalmente
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Contenedor -->
    <div id="container-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <!-- Header del Modal -->
            <div class="bg-gradient-to-r from-slate-800 to-slate-700 px-6 py-4 border-b border-slate-600">
                <h3 id="modal-title" class="text-xl font-bold text-white">Crear Contenedor</h3>
            </div>

            <!-- Contenido del Modal -->
            <div class="p-6 space-y-6">
                <form id="container-form">
                    <!-- Campo Nombre -->
                    <div class="space-y-2">
                        <label for="container-name" class="block text-sm font-medium text-slate-300">
                            Nombre del contenedor
                        </label>
                        <input
                            type="text"
                            id="container-name"
                            name="name"
                            placeholder="Ej: Reuniones Q1 2025"
                            class="bg-slate-700/50 border border-slate-600 rounded-xl py-3 px-4 block w-full text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200"
                            required
                            maxlength="255"
                        >
                        <div id="name-error" class="text-red-400 text-sm hidden"></div>
                    </div>

                    <!-- Campo Descripción -->
                    <div class="space-y-2">
                        <label for="container-description" class="block text-sm font-medium text-slate-300">
                            Descripción (opcional)
                        </label>
                        <textarea
                            id="container-description"
                            name="description"
                            placeholder="Describe el propósito de este contenedor..."
                            rows="3"
                            class="bg-slate-700/50 border border-slate-600 rounded-xl py-3 px-4 block w-full text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200 resize-none"
                            maxlength="1000"
                        ></textarea>
                        <div class="text-xs text-slate-400 text-right">
                            <span id="description-count">0</span>/1000 caracteres
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer del Modal -->
            <div class="bg-slate-900/50 px-6 py-4 flex justify-end gap-3 border-t border-slate-600">
                <button
                    id="cancel-modal-btn"
                    type="button"
                    class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-6 py-2.5 rounded-xl font-medium transition-all duration-200 border border-slate-600"
                >
                    Cancelar
                </button>
                <button
                    id="save-container-btn"
                    type="submit"
                    class="bg-gradient-to-r from-yellow-400 to-amber-400 hover:from-yellow-300 hover:to-amber-300 text-slate-900 px-6 py-2.5 rounded-xl font-semibold transition-all duration-200 shadow-lg shadow-yellow-400/20 hover:shadow-yellow-400/30"
                >
                    <span id="save-btn-text">Guardar</span>
                    <svg id="save-btn-loading" class="animate-spin h-4 w-4 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Reuniones del Contenedor -->
    <div id="container-meetings-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 backdrop-blur-sm">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-slate-900 rounded-2xl shadow-2xl border border-slate-700/50 w-full max-w-4xl max-h-[80vh] overflow-hidden">
                <!-- Header del Modal -->
                <div class="bg-gradient-to-r from-slate-800/50 to-slate-700/50 px-6 py-4 border-b border-slate-700/50">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 id="container-meetings-title" class="text-xl font-semibold text-white">Reuniones del Contenedor</h3>
                            <p id="container-meetings-subtitle" class="text-slate-400 text-sm"></p>
                        </div>
                        <button onclick="closeContainerMeetingsModal()" class="p-2 text-slate-400 hover:text-white hover:bg-slate-700/50 rounded-lg transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Contenido del Modal -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <!-- Loading State -->
                    <div id="container-meetings-loading" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-400"></div>
                        <p class="text-slate-400 mt-2">Cargando reuniones...</p>
                    </div>

                    <!-- Lista de Reuniones -->
                    <div id="container-meetings-list" class="hidden space-y-4">
                        <!-- Las reuniones se cargarán aquí dinámicamente -->
                    </div>

                    <!-- Empty State -->
                    <div id="container-meetings-empty" class="hidden text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-slate-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 7a2 2 0 012-2h10a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <h3 class="text-lg font-medium text-slate-400 mb-2">Sin reuniones</h3>
                        <p class="text-slate-500">Este contenedor aún no tiene reuniones asignadas.</p>
                    </div>

                    <!-- Error State -->
                    <div id="container-meetings-error" class="hidden text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-red-400 mb-2">Error al cargar reuniones</h3>
                        <p id="container-meetings-error-message" class="text-slate-400 mb-4"></p>
                        <button onclick="retryLoadContainerMeetings()" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-black font-medium rounded-lg transition-colors">
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="download-meeting" maxWidth="md">
        <div class="p-6 space-y-4 download-modal">
            <h2 class="text-xl font-semibold">Descargar reunión</h2>

            <div class="space-y-2">
                <label class="modal-option">
                    <input type="checkbox" value="summary" class="download-option">
                    <span>Resumen</span>
                </label>
                <label class="modal-option">
                    <input type="checkbox" value="key_points" class="download-option">
                    <span>Puntos Claves</span>
                </label>
                <label class="modal-option">
                    <input type="checkbox" value="transcription" class="download-option">
                    <span>Transcripción</span>
                </label>
                <label class="modal-option">
                    <input type="checkbox" value="tasks" class="download-option">
                    <span>Tareas</span>
                </label>
            </div>

            <!-- Vista previa PDF -->
            <div class="mt-4">
                <button class="preview-pdf w-full px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg transition-colors">Vista previa</button>
                <div id="preview-container" class="mt-3 hidden border border-slate-700 rounded overflow-hidden" style="height: 420px;">
                    <iframe id="preview-frame" title="Vista previa PDF" class="w-full h-full bg-white"></iframe>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button class="btn-cancel" x-on:click="$dispatch('close-modal','download-meeting')">Cancelar</button>
                <button class="confirm-download btn-primary">Descargar</button>
            </div>
        </div>
    </x-modal>

    <!-- Modal de Vista Previa (full-screen) -->
    <div id="fullPreviewModal" class="fixed inset-0 z-[10000] hidden">
        <div class="absolute inset-0 bg-black/70"></div>
        <div class="absolute inset-0 flex flex-col p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-white text-lg font-semibold">Vista previa del documento</h3>
                <button id="closeFullPreview" class="text-white/90 hover:text-white px-3 py-1 rounded bg-slate-700/60">Cerrar</button>
            </div>
            <div class="flex-1 bg-white rounded-md overflow-hidden">
                <iframe id="fullPreviewFrame" class="w-full h-full" title="Vista previa PDF"></iframe>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const closeBtn = document.getElementById('closeFullPreview');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    const modal = document.getElementById('fullPreviewModal');
                    const frame = document.getElementById('fullPreviewFrame');
                    if (frame) frame.src = 'about:blank';
                    if (modal) modal.classList.add('hidden');
                });
            }
        });
    </script>

    <!-- Modal para compartir reunión -->


</body>
</html>
