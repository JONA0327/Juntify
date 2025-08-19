<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contenedores - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/audio-processing.css',
        'resources/js/containers.js'
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
                            <h1 class="text-4xl font-bold text-white tracking-tight">Contenedores</h1>
                            <p class="text-slate-400 text-lg">Organiza y gestiona tus reuniones en contenedores personalizados</p>
                        </div>

                        <!-- Barra de Búsqueda y Filtros -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- Barra de Búsqueda -->
                            <div class="relative flex-1 max-w-md">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="text" id="search-containers" placeholder="Buscar contenedores..." class="bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 block w-full text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200 shadow-lg shadow-black/10">
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Botones de Acción -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button id="create-container-btn" class="bg-gradient-to-r from-yellow-400 to-amber-400 text-slate-900 px-6 py-3 rounded-xl font-semibold hover:from-yellow-300 hover:to-amber-300 transition-all duration-200 shadow-lg shadow-yellow-400/20 hover:shadow-yellow-400/30 flex items-center gap-3 group">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Crear Contenedor</span>
                        </button>
                    </div>
                </header>

                <!-- Estado de Carga -->
                <div id="loading-state" class="flex justify-center items-center py-20">
                    <div class="flex items-center gap-3 text-slate-400">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-yellow-400"></div>
                        <span>Cargando contenedores...</span>
                    </div>
                </div>

                <!-- Estado Vacío -->
                <div id="empty-state" class="text-center py-20 hidden">
                    <div class="mx-auto max-w-md">
                        <div class="bg-slate-800/30 backdrop-blur-sm rounded-full h-24 w-24 mx-auto mb-6 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-300 mb-3">No hay contenedores</h3>
                        <p class="text-slate-400 mb-6">Crea tu primer contenedor para organizar tus reuniones</p>
                        <button id="create-first-container-btn" class="bg-gradient-to-r from-yellow-400 to-amber-400 text-slate-900 px-6 py-3 rounded-xl font-semibold hover:from-yellow-300 hover:to-amber-300 transition-all duration-200 shadow-lg shadow-yellow-400/20 hover:shadow-yellow-400/30">
                            Crear Primer Contenedor
                        </button>
                    </div>
                </div>

                <!-- Lista de Contenedores -->
                <div id="containers-list" class="space-y-4 hidden">
                    <!-- Los contenedores se cargarán aquí dinámicamente -->
                </div>

            </div>
        </main>
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

    <!-- Notificaciones -->
    <div id="notification-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

</body>
</html>
