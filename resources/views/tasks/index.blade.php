<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Tareas - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/profile.css',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/audio-processing.css',
        'resources/css/tasks-index.css',
        'resources/css/mobile-navigation.css',
        'resources/js/tasks/index.js'
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased" data-is-business-plan="{{ ($isBusinessPlan ?? false) ? '1' : '0' }}">

    @include('partials.global-vars')

    <div class="flex">

        @include('partials.navbar')


        <main class="w-full pt-20 lg:pl-24 lg:pt-24 lg:mt-[130px]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                @include('tasks.partials._header')

                <!-- Filtro por Reunión -->
                <div id="meetingFilterContainer" class="mt-6 bg-slate-800/30 border border-slate-700/40 rounded-xl p-4 transition-all duration-300" data-task-view-targets="tablero">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="text-sm font-medium text-slate-200">📋 Filtrar tareas por reunión</h3>
                                <span id="activeFilterBadge" class="hidden px-2 py-1 bg-blue-600/20 border border-blue-400/30 rounded-full text-xs text-blue-300 font-medium">
                                    🔍 Filtrando
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <select id="meetingFilter" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[250px]">
                                    <option value="">🌐 Todas las reuniones</option>
                                </select>
                                <button id="clearMeetingFilter" class="px-3 py-2 bg-slate-600 hover:bg-slate-500 text-slate-200 rounded-lg text-sm transition-colors hidden flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Limpiar
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span id="filterStats" class="text-slate-400">Mostrando todas las tareas</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Vistas de tareas">
                    <button type="button" data-task-view-btn="calendario" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-800/70 text-sm font-medium text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">Calendario</button>
                    @if(!($isBusinessPlan ?? false))
                        <button type="button" data-task-view-btn="tablero" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-900/40 text-sm font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">Tablero</button>
                    @endif
                </div>

                <div id="tasks-layout" class="mt-8 flex flex-col-reverse lg:grid lg:grid-cols-3 lg:gap-8 items-start">

                    <div id="tasks-main-column" class="lg:col-span-2 flex flex-col gap-8 w-full mt-8 lg:mt-0">
                        <div x-data="{ open: window.innerWidth >= 1024 }" class="bg-slate-800/50 border border-slate-700/50 rounded-xl" data-task-view-targets="calendario">

                            <div x-show="open" x-transition>
                                @include('tasks.partials._calendar-main')
                            </div>
                        </div>
                        <div data-task-view-targets="calendario">
                            @include('tasks.partials._tabs-reuniones')
                        </div>
                        <!-- Kanban simple por reunión -->
                        @if(!($isBusinessPlan ?? false))
                            <div id="kanban-board" class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 hidden" data-task-view-targets="tablero" data-task-view-requires-kanban="1">
                                <div class="flex flex-col gap-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-100">Tablero Kanban</h3>
                                        <p class="text-slate-400 text-sm">Gestiona el avance de tus tareas arrastrándolas entre columnas.</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                            <span id="kanban-context-label">Mostrando tareas de todas tus reuniones y asignaciones.</span>
                                            <button type="button" id="kanban-reset-btn" class="hidden text-blue-300 hover:text-blue-200 font-medium underline-offset-2 hover:underline transition-colors">Ver todas las reuniones</button>
                                        </div>
                                    </div>
                                    <div class="inline-flex rounded-lg border border-slate-700/60 bg-slate-900/60 overflow-hidden">
                                        <button type="button" class="kanban-tab-btn px-3 py-1.5 text-sm text-slate-300 bg-slate-800/80" data-kanban-tab="board">Tablero</button>
                                        <button type="button" class="kanban-tab-btn px-3 py-1.5 text-sm text-slate-300" data-kanban-tab="summary">Resumen</button>
                                    </div>
                                </div>

                                <div id="kanban-board-view" class="flex flex-col gap-5">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div class="kanban-col" data-status="pending">
                                            <div class="kanban-col-header px-3 py-2 rounded border border-yellow-400/40 bg-gradient-to-r from-yellow-500/20 to-yellow-500/0 text-sm font-semibold text-yellow-200 uppercase tracking-wide">Pendientes</div>
                                            <div class="kanban-list min-h-[220px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'pending')"></div>
                                        </div>
                                        <div class="kanban-col" data-status="in_progress">
                                            <div class="kanban-col-header px-3 py-2 rounded border border-orange-400/40 bg-gradient-to-r from-orange-500/20 to-orange-500/0 text-sm font-semibold text-orange-200 uppercase tracking-wide">En proceso</div>
                                            <div class="kanban-list min-h-[220px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'in_progress')"></div>
                                        </div>
                                        <div class="kanban-col" data-status="completed">
                                            <div class="kanban-col-header px-3 py-2 rounded border border-blue-400/40 bg-gradient-to-r from-blue-500/20 to-blue-500/0 text-sm font-semibold text-blue-200 uppercase tracking-wide">Completadas</div>
                                            <div class="kanban-list min-h-[220px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'completed')"></div>
                                        </div>
                                        <div class="kanban-col" data-status="approved">
                                            <div class="kanban-col-header px-3 py-2 rounded border border-emerald-400/50 bg-gradient-to-r from-emerald-500/20 to-emerald-500/0 text-sm font-semibold text-emerald-200 uppercase tracking-wide">Aprobadas</div>
                                            <div class="kanban-list min-h-[220px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'approved')"></div>
                                        </div>
                                    </div>

                                    <div class="bg-slate-900/50 border border-rose-500/40 rounded-xl p-4 shadow-inner">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                            <div>
                                                <h4 class="text-base font-semibold text-rose-200">Tareas vencidas</h4>
                                                <p class="text-xs text-rose-100/80">No podrás completarlas hasta que el dueño de la reunión reabra la tarea con una nueva fecha.</p>
                                            </div>
                                            <span id="kanban-overdue-count" class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-rose-500/20 text-rose-100 text-xs font-semibold">0</span>
                                        </div>
                                        <div id="kanban-overdue-list" class="mt-4 flex flex-col gap-3"></div>
                                        <div id="kanban-overdue-empty" class="mt-4 text-sm text-slate-400 border border-dashed border-rose-400/30 rounded-lg px-4 py-6 text-center">Sin tareas vencidas 🎉</div>
                                    </div>

                                    <div class="bg-slate-900/50 border border-amber-400/40 rounded-xl p-4 shadow-inner">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                            <div>
                                                <h4 class="text-base font-semibold text-amber-200">Tareas sin fecha</h4>
                                                <p class="text-xs text-amber-100/80">Asigna una fecha y un responsable para que puedan avanzar.</p>
                                            </div>
                                            <span id="kanban-undated-count" class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-amber-500/20 text-amber-100 text-xs font-semibold">0</span>
                                        </div>
                                        <div id="kanban-undated-list" class="mt-4 flex flex-col gap-3"></div>
                                        <div id="kanban-undated-empty" class="mt-4 text-sm text-slate-400 border border-dashed border-amber-400/30 rounded-lg px-4 py-6 text-center">Todas las tareas tienen fecha 🎉</div>
                                    </div>
                                </div>

                                <div id="kanban-summary-view" class="hidden">
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-yellow-300">Pendientes</h4>
                                            <ul id="summaryPending" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-orange-300">En proceso</h4>
                                            <ul id="summaryInProgress" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-blue-300">Completadas</h4>
                                            <ul id="summaryCompleted" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-300">Aprobadas</h4>
                                            <ul id="summaryApproved" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3 md:col-span-2 xl:col-span-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-rose-300">Vencidas</h4>
                                            <ul id="summaryOverdue" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Sidebar para vista Calendario -->
                    <aside class="col-span-1 w-full" data-task-view-targets="calendario">
                        @include('tasks.partials._sidebar-details')

                        <div id="tasks-empty" class="info-card p-6 mt-8 text-center text-slate-300">
                            <div class="text-xl font-semibold mb-2">Tareas de la reunión</div>
                            <div class="text-blue-400">Selecciona una conversación</div>
                        </div>

                        <div id="tasks-panel" class="info-card p-4 mt-8 hidden">
                            <div class="flex flex-col sm:flex-row items-center justify-between mb-4 gap-4">
                                <h3 class="card-title text-xl">Tareas</h3>
                                <button onclick="openTaskModal()" class="btn btn-primary w-full sm:w-auto">+ Agregar tarea</button>
                            </div>
                            <div id="tasks-sidebar-stats" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 gap-3 mb-4 text-sm">
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-total" class="text-lg font-bold text-blue-400">0</div><div class="text-slate-400">Total</div></div>
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-pending" class="text-lg font-bold text-yellow-400">0</div><div class="text-slate-400">Pendientes</div></div>
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-inprogress" class="text-lg font-bold text-orange-400">0</div><div class="text-slate-400">En progreso</div></div>
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-completed" class="text-lg font-bold text-green-400">0</div><div class="text-slate-400">Completadas</div></div>
                            </div>
                            <div id="tasks-sidebar-list" class="flex flex-col gap-3"></div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    @include('tasks.partials._modal-task')
    @include('tasks.partials._task-details-modal')


    

    <!-- Navegación móvil -->
    @include('partials.mobile-bottom-nav')

</body>
</html>
