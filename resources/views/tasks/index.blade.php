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

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pt-20 lg:pl-24 lg:pt-24 lg:mt-[130px]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                @include('tasks.partials._header') <div class="mt-8 flex flex-col-reverse lg:grid lg:grid-cols-3 lg:gap-8 items-start">

                    <div class="lg:col-span-2 flex flex-col gap-8 w-full mt-8 lg:mt-0">
                        <div x-data="{ open: window.innerWidth >= 1024 }" class="bg-slate-800/50 border border-slate-700/50 rounded-xl">
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
                        @include('tasks.partials._tabs-reuniones')
                        <!-- Kanban simple por reunión -->
                        <div id="kanban-board" class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hidden">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold">Kanban</h3>
                                <div class="text-slate-400 text-sm">Arrastra tareas entre columnas para actualizar progreso</div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                            </div>
                        </div>
                    </div>

                    <aside class="col-span-1 w-full">
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
        // Extensión Kanban mínima: actualiza progreso al arrastrar
        const csrfToken = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '');
        function showKanban(show){ document.getElementById('kanban-board').classList.toggle('hidden', !show); }
        function kanbanAllowDrop(ev){ ev.preventDefault(); }
        async function kanbanDrop(ev, status){ ev.preventDefault(); const id = ev.dataTransfer.getData('text/plain'); const prog = status==='completed'?100:(status==='in_progress'?50:0); try{ const res = await fetch(new URL(`/api/tasks-laravel/tasks/${id}`, window.location.origin), { method:'PUT', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ progreso: prog }) }); const data = await res.json(); if (data.success){ await kanbanReload(); } }catch(e){ console.error(e); } }
        async function kanbanReload(){ if (!window.lastSelectedMeetingId) return; try{ const url = new URL('/api/tasks-laravel/tasks', window.location.origin); url.searchParams.set('meeting_id', window.lastSelectedMeetingId); const res = await fetch(url); const json = await res.json(); if (!json.success) return; const tasks = json.tasks||[]; const cols = { pending: [], in_progress: [], completed: [] }; tasks.forEach(t=>{ const p = typeof t.progreso==='number'?t.progreso:0; const st = p>=100?'completed':(p>0?'in_progress':'pending'); cols[st].push(t); }); document.querySelectorAll('#kanban-board .kanban-list').forEach(el=>el.innerHTML=''); Object.entries(cols).forEach(([st,list])=>{ const target = document.querySelector(`#kanban-board .kanban-col[data-status="${st}"] .kanban-list`); list.forEach(t=>{ const card = document.createElement('div'); card.className='bg-slate-800/60 border border-slate-700 rounded p-2 mb-2 cursor-move'; card.draggable=true; card.dataset.id=t.id; card.addEventListener('dragstart', ev=>{ ev.dataTransfer.setData('text/plain', String(t.id)); }); card.innerHTML = `<div class="text-sm text-slate-200">${escapeHtml(t.tarea||'Sin nombre')}</div><div class="text-[11px] text-slate-400">${(t.prioridad||'-')} • ${pct(t.progreso)}%</div>`; card.addEventListener('dblclick', ()=>{ if (typeof openTaskDetailsModal==='function') openTaskDetailsModal(t.id); }); target.appendChild(card); }); }); showKanban(true); }catch(e){ console.error('kanban reload', e); }}
        function pct(v){ return typeof v==='number'?v:0; }
        function escapeHtml(s){ return String(s||'').replace(/[&<>"]+/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
        // Hook carga desde tabs script: renderTasksAfterFetch llama window.loadTasksForMeeting, que invocamos aquí para refrescar kanban también
        const _origLoadTasks = window.loadTasksForMeeting; window.loadTasksForMeeting = async function(id, src){ if (_origLoadTasks) await _origLoadTasks(id, src); await kanbanReload(); };
    </script>
</body>
</html>
