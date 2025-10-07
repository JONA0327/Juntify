<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Tareas - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <script>
        // Configuración global para scripts de tareas (usada por _tabs-reuniones y otros)
        window.taskLaravel = {
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            apiTasks: '/api/tasks-laravel/tasks',
            apiTasksShow: (id) => `/api/tasks-laravel/tasks/${id}`,
            apiTasksUpdate: (id) => `/api/tasks-laravel/tasks/${id}`,
            apiTasksDestroy: (id) => `/api/tasks-laravel/tasks/${id}`,
            apiExists: '/api/tasks-laravel/exists',
            apiImport: (meetingId) => `/api/tasks-laravel/import/${meetingId}`
        };

        // Helper opcional para mostrar/ocultar el panel de tareas lateral
        window.showTasksPanel = function(show){
            const empty = document.getElementById('tasks-empty');
            const panel = document.getElementById('tasks-panel');
            if (empty) empty.classList.toggle('hidden', !!show);
            if (panel) panel.classList.toggle('hidden', !show);
        };
    </script>

        <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/profile.css',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/audio-processing.css',
        'resources/css/tasks-index.css'
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    @include('partials.global-vars')

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pt-20 lg:pl-24 lg:pt-24 lg:mt-[130px]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                @include('tasks.partials._header')

                <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Vistas de tareas">
                    <button type="button" data-task-view-btn="calendario" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-800/70 text-sm font-medium text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">Calendario</button>
                    <button type="button" data-task-view-btn="tablero" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-900/40 text-sm font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">Tablero</button>
                </div>

                <div class="mt-8 flex flex-col-reverse lg:grid lg:grid-cols-3 lg:gap-8 items-start">

                    <div class="lg:col-span-2 flex flex-col gap-8 w-full mt-8 lg:mt-0">
                        <div x-data="{ open: window.innerWidth >= 1024 }" class="bg-slate-800/50 border border-slate-700/50 rounded-xl" data-task-view-targets="calendario">
                            <button @click="open = !open" class="w-full flex justify-between items-center p-4 lg:hidden">
                                <span class="font-semibold text-lg">Calendario</span>
                                <svg class="w-6 h-6 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="open" x-transition>
                                @include('tasks.partials._calendar-main')
                            </div>
                        </div>
                        <div data-task-view-targets="calendario">
                            @include('tasks.partials._tabs-reuniones')
                        </div>
                        <!-- Kanban simple por reunión -->
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
                                    <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-4 gap-4">
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
                    </div>

                    <aside class="col-span-1 w-full" data-task-view-targets="calendario">
                        @include('tasks.partials._sidebar-details') <div id="tasks-empty" class="info-card p-6 mt-8 text-center text-slate-300">
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


    <script>
        // Extensión Kanban: agrupa tareas por estado y muestra un resumen interactivo
        const csrfToken = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '');
        let currentKanbanTab = 'board';
        let currentKanbanTasks = [];
        let currentTaskMainView = 'calendario';

        window.lastSelectedMeetingId = window.lastSelectedMeetingId || null;
        window.lastSelectedMeetingName = window.lastSelectedMeetingName || null;

        const kanbanBoardElement = document.getElementById('kanban-board');
        if (kanbanBoardElement && !kanbanBoardElement.dataset.hasKanban) {
            kanbanBoardElement.dataset.hasKanban = '0';
        }

        const kanbanContextLabel = document.getElementById('kanban-context-label');
        const kanbanResetBtn = document.getElementById('kanban-reset-btn');
        const kanbanOverdueCount = document.getElementById('kanban-overdue-count');
        const kanbanOverdueList = document.getElementById('kanban-overdue-list');
        const kanbanOverdueEmpty = document.getElementById('kanban-overdue-empty');

        if (kanbanResetBtn) {
            kanbanResetBtn.addEventListener('click', () => {
                window.lastSelectedMeetingId = null;
                window.lastSelectedMeetingName = null;
                if (typeof showTasksPanel === 'function') showTasksPanel(false);
                kanbanReload();
            });
        }

        function updateKanbanContextLabel(hasMeeting) {
            if (!kanbanContextLabel) return;
            if (hasMeeting) {
                const displayName = window.lastSelectedMeetingName
                    ? `"${window.lastSelectedMeetingName}"`
                    : (window.lastSelectedMeetingId ? `#${window.lastSelectedMeetingId}` : 'seleccionada');
                kanbanContextLabel.textContent = `Mostrando tareas de la reunión ${displayName}.`;
                if (kanbanResetBtn) kanbanResetBtn.classList.remove('hidden');
            } else {
                kanbanContextLabel.textContent = 'Mostrando tareas de todas tus reuniones y asignaciones.';
                if (kanbanResetBtn) kanbanResetBtn.classList.add('hidden');
            }
        }

        function kanbanHasData(){
            const board = document.getElementById('kanban-board');
            return !!(board && board.dataset.hasKanban === '1');
        }

        function refreshTaskViewVisibility(){
            document.querySelectorAll('[data-task-view-targets]').forEach(section => {
                const views = (section.dataset.taskViewTargets || '')
                    .split(',')
                    .map(v => v.trim().toLowerCase())
                    .filter(Boolean);
                const matchesView = views.length === 0
                    || views.includes(currentTaskMainView);
                const requiresKanban = section.dataset.taskViewRequiresKanban === '1';
                const shouldShow = matchesView && (!requiresKanban || kanbanHasData());
                section.classList.toggle('hidden', !shouldShow);
            });

            document.querySelectorAll('[data-task-view-btn]').forEach(btn => {
                const isActive = btn.dataset.taskViewBtn === currentTaskMainView;
                if (isActive) {
                    btn.classList.add('bg-slate-800/70', 'text-slate-200');
                    btn.classList.remove('bg-slate-900/40', 'text-slate-300');
                } else {
                    btn.classList.add('bg-slate-900/40', 'text-slate-300');
                    btn.classList.remove('bg-slate-800/70', 'text-slate-200');
                }
            });

            const board = document.getElementById('kanban-board');
            if (board) {
                const visible = kanbanHasData() && currentTaskMainView === 'tablero';
                board.classList.toggle('hidden', !visible);
            }
        }

        function setTaskMainView(view){
            currentTaskMainView = ['calendario', 'tablero'].includes(view) ? view : 'calendario';
            refreshTaskViewVisibility();
        }

        function showKanban(show){
            const board = document.getElementById('kanban-board');
            if (!board) return;
            board.dataset.hasKanban = show ? '1' : '0';
            refreshTaskViewVisibility();
        }

        function kanbanAllowDrop(ev){ ev.preventDefault(); }

        async function kanbanDrop(ev, status){
            ev.preventDefault();
            const allowedStatuses = ['pending', 'in_progress', 'completed', 'approved'];
            if (!allowedStatuses.includes(status)) return;

            const id = ev.dataTransfer.getData('text/plain');
            if (!id) return;

            const task = currentKanbanTasks.find(t => String(t.id) === String(id));
            if (!task) return;

            if (task._overdue) {
                alert('Esta tarea está vencida. Solicita al dueño de la reunión que la reabra antes de continuar.');
                return;
            }

            const progressMap = { pending: 0, in_progress: 50, completed: 100, approved: 100 };
            const desiredProgress = progressMap[status];
            if (typeof desiredProgress !== 'number') return;

            try {
                const res = await fetch(new URL(`/api/tasks-laravel/tasks/${id}`, window.location.origin), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ progreso: desiredProgress })
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    alert(data.message || 'No se pudo actualizar la tarea');
                    return;
                }
                await kanbanReload();
            } catch (e) {
                console.error(e);
                alert('No se pudo actualizar la tarea');
            }
        }

        async function kanbanReload(){
            const hasMeeting = !!window.lastSelectedMeetingId;
            updateKanbanContextLabel(hasMeeting);

            try {
                const url = new URL('/api/tasks-laravel/tasks', window.location.origin);
                if (hasMeeting) {
                    url.searchParams.set('meeting_id', window.lastSelectedMeetingId);
                }

                const res = await fetch(url);
                const json = await res.json();
                if (!json.success) {
                    currentKanbanTasks = [];
                    if (kanbanOverdueList) kanbanOverdueList.innerHTML = '';
                    if (kanbanOverdueCount) kanbanOverdueCount.textContent = '0';
                    if (kanbanOverdueEmpty) kanbanOverdueEmpty.classList.remove('hidden');
                    showKanban(false);
                    return;
                }

                const rawTasks = Array.isArray(json.tasks) ? json.tasks : [];
                const enrichedTasks = rawTasks.map(t => {
                    const progress = typeof t.progreso === 'number' ? t.progreso : 0;
                    const overdue = t.is_overdue === true
                        || (!!t.fecha_limite && progress < 100 && new Date(`${t.fecha_limite}T23:59:59`) < new Date());
                    return { ...t, _progress: progress, _overdue: overdue };
                });

                currentKanbanTasks = enrichedTasks;

                const columns = { pending: [], in_progress: [], completed: [], approved: [] };
                const overdueTasks = [];

                enrichedTasks.forEach(t => {
                    const progress = typeof t._progress === 'number' ? t._progress : 0;
                    const isApproved = t.assignment_status === 'completed';
                    const isCompleted = progress >= 100;

                    if (t._overdue) {
                        overdueTasks.push(t);
                        return;
                    }

                    if (isApproved) {
                        columns.approved.push(t);
                    } else if (isCompleted) {
                        columns.completed.push(t);
                    } else if (progress > 0) {
                        columns.in_progress.push(t);
                    } else {
                        columns.pending.push(t);
                    }
                });

                document.querySelectorAll('#kanban-board .kanban-list').forEach(el => el.innerHTML = '');

                const statusCardClass = {
                    pending: 'kanban-card-pending',
                    in_progress: 'kanban-card-inprogress',
                    completed: 'kanban-card-completed',
                    approved: 'kanban-card-approved'
                };

                Object.entries(columns).forEach(([status, list]) => {
                    const target = document.querySelector(`#kanban-board .kanban-col[data-status="${status}"] .kanban-list`);
                    if (!target) return;

                    list.forEach(t => {
                        const card = document.createElement('div');
                        card.className = 'kanban-card bg-slate-800/60 border border-slate-700 rounded-xl p-3 mb-2 transition-colors';
                        card.dataset.id = t.id;
                        card.dataset.status = status;

                        const extra = statusCardClass[status];
                        if (extra) card.classList.add(extra);

                        card.classList.add('cursor-move');
                        card.draggable = true;
                        card.setAttribute('aria-disabled', 'false');
                        card.addEventListener('dragstart', ev => {
                            ev.dataTransfer.setData('text/plain', String(t.id));
                        });

                        const assigneeName = (t.assigned_user && t.assigned_user.name) || t.asignado || 'Sin asignar';
                        const assignmentText = assignmentStatusLabel(t.assignment_status, !!t.assigned_user_id);
                        const dueLabel = t.fecha_limite ? escapeHtml(t.fecha_limite) : 'Sin fecha límite';
                        const meetingLabel = t.meeting_name ? escapeHtml(t.meeting_name) : 'Sin reunión';

                        card.innerHTML = `
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm text-slate-100 font-semibold leading-snug">${escapeHtml(t.tarea || 'Sin nombre')}</p>
                                <span class="text-[10px] uppercase tracking-wide text-slate-200/80 bg-slate-900/60 border border-slate-700/60 rounded-full px-2 py-0.5">${escapeHtml(t.prioridad || '-')}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-400">
                                <span>${assignmentText}</span>
                                <span class="font-semibold text-slate-200">${pct(t._progress)}%</span>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-400">Responsable: ${escapeHtml(assigneeName)}</div>
                            <div class="mt-1 text-[11px] text-slate-500 flex items-center gap-1">📅 <span>${dueLabel}</span></div>
                            <div class="mt-1 text-[11px] text-slate-500 flex items-center gap-1">🗓 <span>${meetingLabel}</span></div>
                        `;

                        card.addEventListener('dblclick', () => {
                            if (typeof openTaskDetailsModal === 'function') openTaskDetailsModal(t.id);
                        });

                        target.appendChild(card);
                    });
                });

                if (kanbanOverdueCount) kanbanOverdueCount.textContent = String(overdueTasks.length);
                if (kanbanOverdueList) {
                    kanbanOverdueList.innerHTML = '';
                    overdueTasks.forEach(t => {
                        const card = document.createElement('div');
                        card.className = 'kanban-card bg-slate-900/70 border border-rose-500/50 rounded-xl p-3 text-sm text-rose-100 transition-colors';
                        card.classList.add('kanban-card-overdue', 'cursor-not-allowed');
                        card.dataset.id = t.id;
                        card.setAttribute('aria-disabled', 'true');

                        const assigneeName = (t.assigned_user && t.assigned_user.name) || t.asignado || 'Sin asignar';
                        const dueLabel = t.fecha_limite ? escapeHtml(t.fecha_limite) : 'Sin fecha límite';
                        const meetingLabel = t.meeting_name ? escapeHtml(t.meeting_name) : 'Sin reunión';

                        card.innerHTML = `
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-rose-100 leading-snug">${escapeHtml(t.tarea || 'Sin nombre')}</p>
                                <span class="text-[10px] uppercase tracking-wide text-rose-100 bg-rose-500/20 border border-rose-400/40 rounded-full px-2 py-0.5">Vencida</span>
                            </div>
                            <div class="mt-1 text-[11px] text-rose-100/80">Responsable: ${escapeHtml(assigneeName)}</div>
                            <div class="mt-1 text-[11px] text-rose-100/70 flex items-center gap-1">⏰ <span>Venció: ${dueLabel}</span></div>
                            <div class="mt-1 text-[11px] text-rose-100/70 flex items-center gap-1">🗓 <span>${meetingLabel}</span></div>
                            <p class="mt-2 text-[11px] text-rose-200/80 italic">Pide al dueño de la reunión que reabra la tarea para continuar.</p>
                        `;

                        card.addEventListener('dblclick', () => {
                            if (typeof openTaskDetailsModal === 'function') openTaskDetailsModal(t.id);
                        });

                        kanbanOverdueList.appendChild(card);
                    });
                }
                if (kanbanOverdueEmpty) kanbanOverdueEmpty.classList.toggle('hidden', overdueTasks.length > 0);

                updateKanbanSummary(enrichedTasks);
                setKanbanTab(currentKanbanTab);
                showKanban(true);
            } catch (e) {
                console.error('kanban reload', e);
                currentKanbanTasks = [];
                if (kanbanOverdueList) kanbanOverdueList.innerHTML = '';
                if (kanbanOverdueCount) kanbanOverdueCount.textContent = '0';
                if (kanbanOverdueEmpty) kanbanOverdueEmpty.classList.remove('hidden');
                showKanban(false);
            }
        }

        function pct(v){ return typeof v === 'number' ? v : 0; }
        function escapeHtml(s){
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return String(s || '').replace(/[&<>"']/g, c => map[c] || c);
        }

        function assignmentStatusLabel(status, hasAssignee){
            if (!status) {
                return hasAssignee ? 'Asignada' : 'Sin asignar';
            }
            const meta = (typeof assignmentStatusStyles !== 'undefined' && assignmentStatusStyles)
                ? assignmentStatusStyles[status] || null
                : null;
            return meta ? meta.text : status;
        }

        function updateKanbanSummary(tasks){
            const pendingContainer = document.getElementById('summaryPending');
            const inProgressContainer = document.getElementById('summaryInProgress');
            const completedContainer = document.getElementById('summaryCompleted');
            const approvedContainer = document.getElementById('summaryApproved');
            const overdueContainer = document.getElementById('summaryOverdue');
            if (!pendingContainer || !inProgressContainer || !completedContainer || !approvedContainer || !overdueContainer) return;

            const today = new Date();
            today.setHours(23,59,59,999);

            const categories = {
                pending: new Map(),
                in_progress: new Map(),
                completed: new Map(),
                approved: new Map(),
                overdue: new Map(),
            };

            tasks.forEach(t => {
                const progress = typeof t._progress === 'number' ? t._progress : (typeof t.progreso === 'number' ? t.progreso : 0);
                const due = t.fecha_limite ? new Date(`${t.fecha_limite}T23:59:59`) : null;
                const isApproved = t.assignment_status === 'completed';
                const isCompleted = progress >= 100;
                const isOverdue = t._overdue || t.is_overdue === true || (!isCompleted && due && due < today);
                const isInProgress = !isCompleted && !isOverdue && progress > 0;
                const isPending = !isCompleted && !isOverdue && progress <= 0;

                const name = (t.assigned_user && t.assigned_user.name) || t.asignado || 'Sin asignar';
                const key = `${t.assigned_user_id || 'none'}::${name}`;

                const add = (map) => {
                    if (!map.has(key)) {
                        map.set(key, { name, count: 0 });
                    }
                    map.get(key).count += 1;
                };

                if (isOverdue) {
                    add(categories.overdue);
                } else if (isApproved) {
                    add(categories.approved);
                } else if (isCompleted) {
                    add(categories.completed);
                } else if (isInProgress) {
                    add(categories.in_progress);
                } else if (isPending) {
                    add(categories.pending);
                }
            });

            renderSummaryList(pendingContainer, categories.pending, 'Sin pendientes');
            renderSummaryList(inProgressContainer, categories.in_progress, 'Sin tareas en proceso');
            renderSummaryList(completedContainer, categories.completed, 'Sin completadas');
            renderSummaryList(approvedContainer, categories.approved, 'Sin aprobadas');
            renderSummaryList(overdueContainer, categories.overdue, 'Sin vencidas');
        }

        function renderSummaryList(container, map, emptyMessage){
            container.innerHTML = '';
            if (!map || map.size === 0) {
                const li = document.createElement('li');
                li.className = 'text-slate-400 text-sm';
                li.textContent = emptyMessage;
                container.appendChild(li);
                return;
            }

            Array.from(map.values())
                .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
                .forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'flex items-center justify-between bg-slate-800/40 rounded px-2 py-1 text-slate-200';
                    li.innerHTML = `<span>${escapeHtml(item.name)}</span><span class="text-xs text-slate-400">${item.count} tarea${item.count === 1 ? '' : 's'}</span>`;
                    container.appendChild(li);
                });
        }

        function setKanbanTab(tab){
            currentKanbanTab = tab === 'summary' ? 'summary' : 'board';
            const boardView = document.getElementById('kanban-board-view');
            const summaryView = document.getElementById('kanban-summary-view');
            if (boardView) boardView.classList.toggle('hidden', currentKanbanTab !== 'board');
            if (summaryView) summaryView.classList.toggle('hidden', currentKanbanTab !== 'summary');

            document.querySelectorAll('#kanban-board [data-kanban-tab]').forEach(btn => {
                const isActive = btn.dataset.kanbanTab === currentKanbanTab;
                btn.classList.toggle('bg-slate-800/80', isActive);
                btn.classList.toggle('text-white', isActive);
            });
        }

        document.querySelectorAll('#kanban-board [data-kanban-tab]').forEach(btn => {
            btn.addEventListener('click', () => setKanbanTab(btn.dataset.kanbanTab));
        });

        document.querySelectorAll('[data-task-view-btn]').forEach(btn => {
            btn.addEventListener('click', () => setTaskMainView(btn.dataset.taskViewBtn));
        });

        setTaskMainView('calendario');

        const _origLoadTasks = window.loadTasksForMeeting;
        window.loadTasksForMeeting = async function(id, src){
            if (_origLoadTasks) await _origLoadTasks(id, src);
            await kanbanReload();
        };

        kanbanReload();
    </script>

</body>
</html>
