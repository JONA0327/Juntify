<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Reuniones - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <script>
        window.userRole = @json($userRole);
        window.currentOrganizationId = @json($organizationId);
    </script>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js', 'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav') <main class="w-full pt-20 md:pt-24 lg:pl-24 lg:mt-[130px]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <header class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6 pb-12 fade-in">
                    <div class="flex-1 space-y-6">
                        <div class="space-y-3">
                            <h1 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">Reuniones</h1>
                            <p class="text-slate-400 text-lg">Gestiona y organiza todas tus reuniones</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="relative flex-1 w-full">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="text" placeholder="Buscar en reuniones..." class="bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 block w-full text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200 shadow-lg shadow-black/10">
                            </div>

                            <button class="bg-slate-800/50 backdrop-blur-custom text-slate-200 px-4 py-3 rounded-xl flex items-center justify-center sm:justify-start gap-3 hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 border border-slate-700/50 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span class="font-medium">Fecha</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col items-stretch lg:items-end gap-6 fade-in stagger-1 w-full lg:w-auto">
                        <div class="w-full lg:w-80 bg-gradient-to-br from-slate-800/50 to-slate-800/30 backdrop-blur-custom border border-slate-700/50 rounded-xl p-5 shadow-lg shadow-black/10">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-sm font-semibold text-slate-300">Análisis mensuales</p>
                                <span class="text-xs text-slate-400 bg-slate-700/50 px-2 py-1 rounded-full">45/100</span>
                            </div>
                            <div class="w-full bg-slate-700/50 rounded-full h-2.5 overflow-hidden">
                                <div class="progress-bar bg-gradient-to-r from-yellow-400 to-yellow-500 h-2.5 rounded-full shadow-lg shadow-yellow-400/25" style="width: 45%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">55 análisis restantes este mes</p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <button id="create-container-btn" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-5 py-3 bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl text-slate-200 font-medium hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                <span>Nuevo Contenedor</span>
                            </button>

                            <button class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-200 shadow-lg shadow-yellow-400/25 hover:shadow-yellow-400/40 transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Reuniones Pendientes</span>
                            </button>
                        </div>
                    </div>
                </header>

                <nav class="mb-6 overflow-x-auto">
                    <ul class="flex gap-3 whitespace-nowrap">
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
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                @forelse ($meetings as $m)
                                    <x-meeting-card :id="$m['id']"
                                        :meeting-name="$m['meeting_name']"
                                        :created-at="$m['created_at']"
                                        :audio-folder="$m['audio_folder'] ?? ''"
                                        :transcript-folder="$m['transcript_folder'] ?? ''" />
                                @empty
                                    <div class="loading-card md:col-span-2 xl:col-span-3"><p>No tienes reuniones</p></div>
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

                    <div id="shared-meetings" class="hidden space-y-6">
                        <div id="incoming-shared-wrapper" class="loading-card">
                            <div class="loading-spinner"></div>
                            <p>Cargando reuniones compartidas contigo...</p>
                        </div>

                        <div id="outgoing-shared-wrapper" class="loading-card">
                            <div class="loading-spinner"></div>
                            <p>Cargando reuniones que has compartido...</p>
                        </div>
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

    {{-- Modal global para compartir reuniones --}}
    <x-share-modal />

    {{-- El resto de tus modales no necesita cambios para esta tarea --}}

    <div id="fullPreviewModal"
        class="fixed inset-0 z-[9999] hidden flex items-center justify-center bg-slate-950/80 backdrop-blur-sm px-4 py-8">
        <div class="relative w-full max-w-screen-xl h-full max-h-[95vh] bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl overflow-hidden">
            <button id="closeFullPreview" type="button"
                class="absolute top-4 right-4 inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-800/70 border border-slate-700/70 text-slate-300 hover:bg-slate-700/70 hover:text-white transition">
                <span class="sr-only">Cerrar vista previa</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
            <iframe id="fullPreviewFrame" class="w-full h-full" title="Vista previa de la reunión" frameborder="0"></iframe>
        </div>
    </div>

    <div id="container-modal"
        class="fixed inset-0 z-[9998] hidden flex items-center justify-center bg-slate-950/80 backdrop-blur-sm px-4 py-8">
        <div class="relative w-full max-w-xl bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl p-6">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 id="modal-title" class="text-xl font-semibold text-white">Crear Contenedor</h2>
                    <p class="text-sm text-slate-400 mt-1">Organiza tus reuniones en contenedores personalizados.</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-white transition-colors"
                    onclick="closeContainerModal()">
                    <span class="sr-only">Cerrar modal</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="container-form" class="space-y-5">
                <div>
                    <label for="container-name" class="block text-sm font-medium text-slate-300 mb-2">Nombre del
                        contenedor</label>
                    <input id="container-name" name="name" type="text"
                        class="w-full px-4 py-3 bg-slate-900/70 border border-slate-700/60 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"
                        placeholder="Ej. Ventas Q1">
                    <span id="name-error" class="hidden mt-2 text-sm text-red-400"></span>
                </div>

                <div>
                    <label for="container-description" class="block text-sm font-medium text-slate-300 mb-2">Descripción</label>
                    <textarea id="container-description" name="description" rows="4"
                        class="w-full px-4 py-3 bg-slate-900/70 border border-slate-700/60 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"
                        placeholder="Describe el propósito del contenedor"></textarea>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span id="description-count">0</span>
                        <span>Máximo recomendado: 300 caracteres</span>
                    </div>
                    <span id="description-error" class="hidden mt-2 text-sm text-red-400"></span>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="cancel-modal-btn"
                        class="px-4 py-2 rounded-xl border border-slate-700/60 bg-slate-900/70 text-slate-300 hover:bg-slate-800/70 transition-colors"
                        onclick="closeContainerModal()">
                        Cancelar
                    </button>
                    <button type="submit" id="save-container-btn"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold shadow-lg shadow-yellow-400/25 hover:from-yellow-500 hover:to-yellow-600 transition-all">
                        <span id="save-btn-text">Guardar</span>
                        <svg id="save-btn-loading" class="hidden h-5 w-5 text-slate-900 animate-spin" viewBox="0 0 24 24"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V2.5a9.5 9.5 0 00-9.5 9.5H4zm2 5.291A7.962 7.962 0 014 12H2.5c0 3.042 1.135 5.824 3 7.938L6 17.291z" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="container-meetings-modal"
        class="fixed inset-0 z-[9998] hidden flex items-center justify-center bg-slate-950/80 backdrop-blur-sm px-4 py-8">
        <div
            class="relative w-full max-w-5xl bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between gap-4 p-6 border-b border-slate-700/50">
                <div>
                    <h2 id="container-meetings-title" class="text-2xl font-semibold text-white">Reuniones del Contenedor</h2>
                    <p id="container-meetings-subtitle" class="text-sm text-slate-400 mt-1"></p>
                </div>
                <button type="button" class="text-slate-400 hover:text-white transition-colors"
                    onclick="closeContainerMeetingsModal()">
                    <span class="sr-only">Cerrar modal</span>
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <div id="container-meetings-loading" class="flex flex-col items-center justify-center gap-4 py-16">
                    <div class="loading-spinner"></div>
                    <p class="text-slate-300">Cargando reuniones...</p>
                </div>

                <div id="container-meetings-list" class="hidden space-y-4"></div>

                <div id="container-meetings-empty" class="hidden text-center py-16">
                    <div
                        class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-slate-800/30">
                        <svg class="h-12 w-12 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-300 mb-2">Aún no hay reuniones</h3>
                    <p class="text-slate-400 max-w-md mx-auto">Agrega reuniones a este contenedor para verlas en esta
                        lista.</p>
                </div>

                <div id="container-meetings-error" class="hidden text-center py-16 space-y-4">
                    <div class="mx-auto w-16 h-16 flex items-center justify-center rounded-full bg-red-500/10 text-red-400">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <p id="container-meetings-error-message" class="text-slate-300"></p>
                    <div>
                        <button type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-700/60 bg-slate-900/70 text-slate-300 hover:bg-slate-800/70 transition-colors"
                            onclick="retryLoadContainerMeetings()">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0A8.003 8.003 0 014.582 15m15.837 0H15" />
                            </svg>
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </body>
</html>
