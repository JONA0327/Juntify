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
                    <button type="button" data-task-view-btn="todo" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-800/70 text-sm font-medium text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">Todo</button>
                    <button type="button" data-task-view-btn="calendario" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-900/40 text-sm font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">Calendario</button>
                    <button type="button" data-task-view-btn="tablero" class="task-view-tab-btn px-4 py-2 rounded-lg border border-slate-700 bg-slate-900/40 text-sm font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">Tablero</button>
                </div>

                <div class="mt-8 flex flex-col-reverse lg:grid lg:grid-cols-3 lg:gap-8 items-start">

                    <div class="lg:col-span-2 flex flex-col gap-8 w-full mt-8 lg:mt-0">
                        <div x-data="{ open: window.innerWidth >= 1024 }" class="bg-slate-800/50 border border-slate-700/50 rounded-xl" data-task-view-targets="todo,calendario">
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
                        <div data-task-view-targets="todo,calendario">
                            @include('tasks.partials._tabs-reuniones')
                        </div>
                        <!-- Kanban simple por reunión -->
                        <div id="kanban-board" class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hidden" data-task-view-targets="todo,tablero" data-task-view-requires-kanban="1">
                            <div class="flex flex-col gap-3">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold">Kanban</h3>
                                        <p class="text-slate-400 text-sm">Arrastra tareas entre columnas para actualizar progreso</p>
                                    </div>
                                    <div class="inline-flex rounded-lg border border-slate-700/60 bg-slate-900/60 overflow-hidden">
                                        <button type="button" class="kanban-tab-btn px-3 py-1.5 text-sm text-slate-300 bg-slate-800/80" data-kanban-tab="board">Tablero</button>
                                        <button type="button" class="kanban-tab-btn px-3 py-1.5 text-sm text-slate-300" data-kanban-tab="summary">Resumen</button>
                                    </div>
                                </div>
                                <div id="kanban-board-view" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                                    <div class="kanban-col" data-status="pending">
                                        <div class="px-3 py-2 bg-slate-900/60 rounded border border-slate-700/50 font-medium mb-2">Pendientes</div>
                                        <div class="kanban-list min-h-[200px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'pending')"></div>
                                    </div>
                                    <div class="kanban-col" data-status="in_progress">
                                        <div class="px-3 py-2 bg-slate-900/60 rounded border border-slate-700/50 font-medium mb-2">En progreso</div>
                                        <div class="kanban-list min-h-[200px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'in_progress')"></div>
                                    </div>
                                    <div class="kanban-col" data-status="completed">
                                        <div class="px-3 py-2 bg-slate-900/60 rounded border border-slate-700/50 font-medium mb-2">Completadas</div>
                                        <div class="kanban-list min-h-[200px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'completed')"></div>
                                    </div>
                                    <div class="kanban-col" data-status="overdue">
                                        <div class="px-3 py-2 bg-slate-900/60 rounded border border-slate-700/50 font-medium mb-2 text-rose-300">Vencidas</div>
                                        <div class="kanban-list min-h-[200px] p-2 bg-slate-900/30 rounded border border-slate-700/30" ondragover="kanbanAllowDrop(event)" ondrop="kanbanDrop(event, 'overdue')"></div>
                                    </div>
                                </div>
                                <div id="kanban-summary-view" class="hidden">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-yellow-300">Pendientes</h4>
                                            <ul id="summaryPending" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-rose-300">Vencidas</h4>
                                            <ul id="summaryOverdue" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                        <div class="bg-slate-900/50 border border-slate-700/40 rounded-lg p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-300">Completados</h4>
                                            <ul id="summaryCompleted" class="mt-2 space-y-1 text-sm text-slate-200"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="col-span-1 w-full" data-task-view-targets="todo,calendario">
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
        // Extensión Kanban: actualiza progreso al arrastrar y muestra resumen por responsable
        const csrfToken = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '');
        let currentKanbanTab = 'board';
        let currentKanbanTasks = [];
        let currentTaskMainView = 'todo';

        const kanbanBoardElement = document.getElementById('kanban-board');
        if (kanbanBoardElement && !kanbanBoardElement.dataset.hasKanban) {
            kanbanBoardElement.dataset.hasKanban = '0';
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
                    || views.includes(currentTaskMainView)
                    || (views.includes('todo') && currentTaskMainView === 'todo');
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
                const visible = kanbanHasData() && (currentTaskMainView === 'todo' || currentTaskMainView === 'tablero');
                board.classList.toggle('hidden', !visible);
            }
        }

        function setTaskMainView(view){
            currentTaskMainView = ['todo', 'calendario', 'tablero'].includes(view) ? view : 'todo';
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
            const id = ev.dataTransfer.getData('text/plain');
            if (!id || status === 'overdue') {
                return;
            }

            const task = currentKanbanTasks.find(t => String(t.id) === String(id));
            if (!task) {
                return;
            }

            if (task._overdue && status !== 'completed') {
                alert('Esta tarea está vencida. El dueño debe reprogramar la fecha antes de continuar.');
                return;
            }

            const prog = status === 'completed' ? 100 : (status === 'in_progress' ? 50 : 0);
            try {
                const res = await fetch(new URL(`/api/tasks-laravel/tasks/${id}`, window.location.origin), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ progreso: prog })
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
            if (!window.lastSelectedMeetingId) {
                currentKanbanTasks = [];
                showKanban(false);
                return;
            }

            try {
                const url = new URL('/api/tasks-laravel/tasks', window.location.origin);
                url.searchParams.set('meeting_id', window.lastSelectedMeetingId);
                const res = await fetch(url);
                const json = await res.json();
                if (!json.success) {
                    currentKanbanTasks = [];
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

                const cols = { pending: [], in_progress: [], completed: [], overdue: [] };

                enrichedTasks.forEach(t => {
                    const completed = t._progress >= 100;
                    const status = t._overdue
                        ? 'overdue'
                        : (completed ? 'completed' : (t._progress > 0 ? 'in_progress' : 'pending'));
                    cols[status].push(t);
                });

                document.querySelectorAll('#kanban-board .kanban-list').forEach(el => el.innerHTML = '');

                Object.entries(cols).forEach(([st, list]) => {
                    const target = document.querySelector(`#kanban-board .kanban-col[data-status="${st}"] .kanban-list`);
                    if (!target) return;
                    list.forEach(t => {
                        const card = document.createElement('div');
                        card.className = 'kanban-card bg-slate-800/60 border border-slate-700 rounded p-3 mb-2 transition-colors';
                        if (t.assignment_status === 'pending') {
                            card.classList.add('kanban-card-pending');
                        }
                        if (t._overdue) {
                            card.classList.add('kanban-card-overdue', 'cursor-not-allowed');
                            card.draggable = false;
                            card.setAttribute('aria-disabled', 'true');
                            card.title = 'Tarea vencida. Espera a que el dueño reprograma la fecha.';
                        } else {
                            card.classList.add('cursor-move');
                            card.draggable = true;
                            card.setAttribute('aria-disabled', 'false');
                        }
                        card.dataset.id = t.id;
                        card.addEventListener('dragstart', ev => {
                            ev.dataTransfer.setData('text/plain', String(t.id));
                        });
                        const assigneeName = (t.assigned_user && t.assigned_user.name) || t.asignado || 'Sin asignar';
                        const assignmentText = assignmentStatusLabel(t.assignment_status, !!t.assigned_user_id);
                        const dueLabel = t.fecha_limite ? `Vence: ${escapeHtml(t.fecha_limite)}` : 'Sin fecha límite';
                        card.innerHTML = `
                            <div class="text-sm text-slate-200 font-semibold">${escapeHtml(t.tarea || 'Sin nombre')}</div>
                            <div class="text-[11px] text-slate-400 flex justify-between gap-2">
                                <span>${escapeHtml(t.prioridad || '-')} • ${pct(t._progress)}%</span>
                                <span>${assignmentText}</span>
                            </div>
                            <div class="text-[11px] text-slate-400">Responsable: ${escapeHtml(assigneeName)}</div>
                            <div class="text-[11px] text-slate-500">${dueLabel}</div>
                            <div class="text-[11px] text-slate-500">Reunión: ${escapeHtml(t.meeting_name || 'Sin reunión')}</div>
                        `;
                        card.addEventListener('dblclick', () => {
                            if (typeof openTaskDetailsModal === 'function') openTaskDetailsModal(t.id);
                        });
                        target.appendChild(card);
                    });
                });

                updateKanbanSummary(enrichedTasks);
                setKanbanTab(currentKanbanTab);
                showKanban(enrichedTasks.length > 0);
            } catch (e) {
                console.error('kanban reload', e);
                currentKanbanTasks = [];
                showKanban(false);
            }
        }

        function pct(v){ return typeof v === 'number' ? v : 0; }
        function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

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
            const overdueContainer = document.getElementById('summaryOverdue');
            const completedContainer = document.getElementById('summaryCompleted');
            if (!pendingContainer || !overdueContainer || !completedContainer) return;

            const today = new Date();
            today.setHours(23,59,59,999);

            const categories = {
                pending: new Map(),
                overdue: new Map(),
                completed: new Map(),
            };

            tasks.forEach(t => {
                const progress = typeof t._progress === 'number' ? t._progress : (typeof t.progreso === 'number' ? t.progreso : 0);
                const due = t.fecha_limite ? new Date(`${t.fecha_limite}T23:59:59`) : null;
                const completed = progress >= 100;
                const overdue = t._overdue || t.is_overdue === true || (!completed && due && due < today);
                const pending = !completed && !overdue && progress <= 0;
                const name = (t.assigned_user && t.assigned_user.name) || t.asignado || 'Sin asignar';
                const key = `${t.assigned_user_id || 'none'}::${name}`;

                const add = (map) => {
                    if (!map.has(key)) {
                        map.set(key, { name, count: 0 });
                    }
                    map.get(key).count += 1;
                };

                if (completed) {
                    add(categories.completed);
                } else if (overdue) {
                    add(categories.overdue);
                } else if (pending) {
                    add(categories.pending);
                }
            });

            renderSummaryList(pendingContainer, categories.pending, 'Sin pendientes');
            renderSummaryList(overdueContainer, categories.overdue, 'Sin vencidas');
            renderSummaryList(completedContainer, categories.completed, 'Sin completadas');
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

        setTaskMainView('todo');

        const _origLoadTasks = window.loadTasksForMeeting;
        window.loadTasksForMeeting = async function(id, src){
            if (_origLoadTasks) await _origLoadTasks(id, src);
            await kanbanReload();
        };
    </script>
</body>
</html>
