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

    <script>
        window.contactsFeatures = Object.assign(window.contactsFeatures || {}, { showChat: false });
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

    @include('partials.global-vars')

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pt-20 md:pt-24 lg:pl-24 lg:mt-[130px]">
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
                                <p class="text-sm font-semibold text-slate-300">Reuniones mensuales</p>
                                <span id="plan-meetings-count" class="text-xs text-slate-400 bg-slate-700/50 px-2 py-1 rounded-full">—/—</span>
                            </div>
                            <div class="w-full bg-slate-700/50 rounded-full h-2.5 overflow-hidden">
                                <div id="plan-progress-bar" class="progress-bar bg-gradient-to-r from-yellow-400 to-yellow-500 h-2.5 rounded-full shadow-lg shadow-yellow-400/25" style="width: 0%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2" id="plan-remaining-text">Calculando reuniones restantes…</p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <button id="create-container-btn" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-5 py-3 bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl text-slate-200 font-medium hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                <span>Nuevo Contenedor</span>
                            </button>                            <button class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-200 shadow-lg shadow-yellow-400/25 hover:shadow-yellow-400/40 transform hover:scale-105">
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

                    <div id="shared-meetings" class="hidden">
                        <div class="space-y-8">
                            <div>
                                <h2 class="text-xl font-semibold mb-3">Reuniones que otros compartieron conmigo</h2>
                                <div id="incoming-shared-wrapper">
                                    <div class="loading-card"><p>No hay reuniones compartidas</p></div>
                                </div>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold mb-3">Reuniones que yo compartí</h2>
                                <div id="outgoing-shared-wrapper">
                                    <div class="loading-card"><p>No has compartido reuniones</p></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="containers" class="hidden">
                        <div class="loading-card"><p>No tienes contenedores</p></div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    {{-- El resto de tus modales no necesita cambios para esta tarea --}}

    {{-- Modal para compartir reuniones (necesario para que el botón de compartir funcione) --}}
    @include('components.share-modal')

    {{-- Modal Crear/Editar Contenedor --}}
    <div id="container-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center px-4 py-8 bg-black/60 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-slate-900/95 border border-slate-700/60 rounded-2xl shadow-2xl shadow-black/40 overflow-hidden">
            <form id="container-form" class="flex flex-col" autocomplete="off">
                <div class="flex items-start justify-between px-6 pt-6 pb-4 border-b border-slate-700/50">
                    <div>
                        <h3 id="modal-title" class="text-xl font-semibold text-white">Crear Contenedor</h3>
                        <p class="text-sm text-slate-400 mt-1">Organiza tus reuniones en grupos personalizados</p>
                    </div>
                    <button type="button" id="cancel-modal-btn" class="text-slate-400 hover:text-white transition-colors" aria-label="Cerrar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-6">
                    <div>
                        <label for="container-name" class="block text-sm font-medium text-slate-300 mb-2">Nombre <span class="text-red-400">*</span></label>
                        <input id="container-name" name="name" type="text" maxlength="60" required class="w-full rounded-lg bg-slate-800/70 border border-slate-600/50 focus:border-yellow-400/60 focus:ring-yellow-400/40 text-slate-100 placeholder-slate-500 px-4 py-3 text-sm outline-none transition" placeholder="Ej: Reuniones de Ventas" />
                        <p id="error-name" class="mt-2 text-xs text-red-400 hidden"></p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="container-description" class="block text-sm font-medium text-slate-300">Descripción</label>
                            <span id="description-count" class="text-[11px] text-slate-500">0/200</span>
                        </div>
                        <textarea id="container-description" name="description" maxlength="200" rows="4" class="w-full rounded-lg bg-slate-800/70 border border-slate-600/50 focus:border-yellow-400/60 focus:ring-yellow-400/40 text-slate-100 placeholder-slate-500 px-4 py-3 text-sm resize-none outline-none transition" placeholder="Opcional: explica para qué usarás este contenedor"></textarea>
                        <p id="error-description" class="mt-2 text-xs text-red-400 hidden"></p>
                    </div>
                </div>

                <div class="px-6 pb-6 pt-4 border-t border-slate-700/50 flex flex-col sm:flex-row gap-3 sm:justify-end bg-slate-900/60">
                    <button type="button" id="cancel-modal-btn-duplicate" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-slate-800/60 hover:bg-slate-700/60 text-slate-200 text-sm font-medium border border-slate-600/40 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        Cancelar
                    </button>
                    <button type="submit" id="save-container-btn" class="relative w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg bg-gradient-to-r from-yellow-400 to-amber-500 text-slate-900 font-semibold text-sm shadow-lg shadow-yellow-400/25 hover:from-yellow-300 hover:to-amber-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/40 transition">
                        <span id="save-btn-loading" class="hidden animate-spin w-4 h-4 border-2 border-slate-900 border-t-transparent rounded-full"></span>
                        <span id="save-btn-text">Guardar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Helpers simples para errores y contador (usa funciones existentes si ya están en reuniones_v2.js)
        function clearContainerErrors() {
            const e1 = document.getElementById('error-name');
            const e2 = document.getElementById('error-description');
            if (e1) { e1.textContent=''; e1.classList.add('hidden'); }
            if (e2) { e2.textContent=''; e2.classList.add('hidden'); }
        }
        function updateCharacterCount() {
            const ta = document.getElementById('container-description');
            const counter = document.getElementById('description-count');
            if (ta && counter) counter.textContent = `${ta.value.length}/200`;
        }
        // Cerrar con ambos botones (principal y duplicado inferior)
        document.addEventListener('DOMContentLoaded', () => {
            const cancelDup = document.getElementById('cancel-modal-btn-duplicate');
            const cancelMain = document.getElementById('cancel-modal-btn');
            const modal = document.getElementById('container-modal');
            function closeModalLocal(){ modal.classList.add('hidden'); }
            if (cancelDup) cancelDup.addEventListener('click', closeModalLocal);
            if (cancelMain) cancelMain.addEventListener('click', closeModalLocal);
        });
    </script>

    <!-- Modal para opciones bloqueadas por plan -->
    <div class="modal" id="postpone-locked-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="modal-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Opción disponible en planes superiores
                </h2>
            </div>
            <div class="modal-body">
                <p class="modal-description">Esta opción está disponible para los planes: <strong>Negocios</strong> y <strong>Enterprise</strong>.</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeUpgradeModal()" id="close-modal-btn">Cerrar</button>
                <button class="btn btn-primary" onclick="goToPlans()">Cambiar plan</button>
            </div>
        </div>
    </div>

    <script>
        // Variables del usuario para JavaScript
        @auth
            window.userPlanCode = @json(auth()->user()->plan_code ?? 'free');
            window.userId = @json(auth()->user()->id);
            window.userName = @json(auth()->user()->name);
            console.log('👤 Plan del usuario:', window.userPlanCode);
        @else
            window.userPlanCode = 'free';
            window.userId = null;
            window.userName = null;
        @endauth

        // Define fallback handlers only if the global helpers are not available
        if (typeof window.closeUpgradeModal === 'undefined') {
            window.closeUpgradeModal = function() {
                console.log('🔒 Cerrando modal de upgrade...');

                // Intentar múltiples IDs posibles
                const possibleIds = ['postpone-locked-modal', 'upgrade-modal', 'container-modal'];
                let modal = null;

                for (const id of possibleIds) {
                    modal = document.getElementById(id);
                    if (modal) {
                        console.log('🔍 Modal encontrado:', id);
                        break;
                    }
                }

                // También buscar por clase
                if (!modal) {
                    modal = document.querySelector('.modal[style*="display: flex"], .modal.show, .modal.active');
                    if (modal) {
                        console.log('🔍 Modal encontrado por clase/estilo');
                    }
                }

                if (modal) {
                    // Resetear todos los estilos posibles
                    modal.style.setProperty('display', 'none', 'important');
                    modal.style.setProperty('visibility', 'hidden', 'important');
                    modal.style.setProperty('opacity', '0', 'important');
                    modal.classList.remove('show', 'active');

                    // Resetear scroll del body
                    document.body.style.setProperty('overflow', '', 'important');
                    document.body.style.setProperty('overflow-y', '', 'important');

                    console.log('✅ Modal cerrado correctamente');
                } else {
                    console.warn('⚠️ No se encontró ningún modal para cerrar');
                    console.log('🔍 Modales disponibles:', document.querySelectorAll('.modal, [id*="modal"]'));
                }
            };
        }

        if (typeof window.goToPlans === 'undefined') {
            window.goToPlans = function() {
                window.closeUpgradeModal();
                sessionStorage.setItem('navigateToPlans', 'true');
                window.location.href = '/profile';
            };
        }
    </script>

    </body>
</html>
