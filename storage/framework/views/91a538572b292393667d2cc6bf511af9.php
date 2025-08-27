<!-- Contenedor de Pestañas de Reuniones (estilo Reuniones) -->
<div class="info-card">
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
        </ul>
    </nav>

    <div id="meetings-container" class="fade-in stagger-2">
        <div id="my-meetings" class="hidden">
            <div class="meetings-grid" id="my-meetings-grid"></div>
        </div>
        <div id="shared-meetings" class="hidden">
            <div class="meetings-grid" id="shared-meetings-grid"></div>
        </div>
        <div id="containers" class="hidden">
            <div class="meetings-grid" id="containers-grid"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Tabs estilo Reuniones
            const tabButtons = document.querySelectorAll('.tab-transition');
            tabButtons.forEach(btn => btn.addEventListener('click', () => setActiveTab(btn)));

            function setActiveTab(btn) {
                // estilo activo igual al de reuniones
                document.querySelectorAll('.tab-transition').forEach(b => b.classList.remove('bg-slate-700/50'));
                btn.classList.add('bg-slate-700/50');

                const targetId = btn.dataset.target;
                document.querySelectorAll('#meetings-container > div').forEach(c => c.classList.add('hidden'));
                document.getElementById(targetId)?.classList.remove('hidden');

                if (targetId === 'my-meetings') loadMeetingsInto('#my-meetings-grid', '/api/meetings');
                if (targetId === 'shared-meetings') loadMeetingsInto('#shared-meetings-grid', '/api/shared-meetings');
                if (targetId === 'containers') loadContainersInto('#containers-grid');
            }

            // Activar por defecto "Mis reuniones"
            const defaultTab = document.querySelector('button[data-target="my-meetings"]');
            if (defaultTab) setActiveTab(defaultTab);

            async function loadMeetingsInto(gridSelector, url) {
                const grid = document.querySelector(gridSelector);
                grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><div class="loading-spinner"></div><p>Cargando reuniones...</p></div></div>';
                try {
                    const res = await fetch(url);
                    const json = await res.json();
                    if (!json.success) throw new Error(json.message || 'Error');
                    renderMeetingCards(grid, json.meetings || []);
                } catch (e) {
                    grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><p>Error al cargar reuniones</p></div></div>';
                    console.error(e);
                }
            }

            function renderMeetingCards(container, items) {
                container.innerHTML = '';
                if (!items.length) {
                    container.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><p>No hay reuniones</p></div></div>';
                    return;
                }
                // items ya vienen ordenados desc por API
                for (const m of items) {
                    const card = document.createElement('div');
                    card.className = 'meeting-card cursor-pointer';
                    card.setAttribute('data-meeting-id', m.id);
                    card.setAttribute('draggable', 'true');
                    card.innerHTML = `
                        <div class="meeting-card-header">
                            <div class="meeting-content">
                                <div class="meeting-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                                <h3 class="meeting-title">${escapeHtml(m.meeting_name)}</h3>
                                <p class="meeting-date">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    ${escapeHtml(m.created_at)}
                                </p>
                                <div class="meeting-folders">
                                    <div class="folder-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        <span>Transcripción:</span>
                                        <span class="folder-name">${escapeHtml(m.transcript_folder || '')}</span>
                                    </div>
                                    <div class="folder-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728" /></svg>
                                        <span>Audio:</span>
                                        <span class="folder-name">${escapeHtml(m.audio_folder || '')}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Click en toda la tarjeta → cargar panel, verificar si hace falta enriquecer y (re)importar si corresponde
                    const hasJu = !!(m.transcript_drive_id);
                    card.addEventListener('click', async () => {
                        if (!hasJu) return alert('Esta reunión no tiene archivo .ju');
                        window.lastSelectedMeetingId = m.id;
                        if (window.showTasksPanel) window.showTasksPanel(true);
                        // Cargar tareas para decidir si necesitan enriquecimiento
                        const current = await fetchTasksForMeeting(m.id);
                        let needImport = false;
                        if (!current.tasks.length) needImport = true;
                        else {
                            const poor = current.tasks.every(t => (!t.descripcion || String(t.descripcion).trim()==='') && !t.fecha_inicio && !t.fecha_limite && (!t.progreso || t.progreso===0));
                            needImport = poor;
                        }
                        if (needImport) {
                            const ok = await importTasks(m.id);
                            if (!ok) return;
                        }
                        await renderTasksAfterFetch(m.id);
                    });

                    container.appendChild(card);
                }
            }

            async function loadContainersInto(gridSelector) {
                const grid = document.querySelector(gridSelector);
                grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><div class="loading-spinner"></div><p>Cargando contenedores...</p></div></div>';
                try {
                    const res = await fetch('/api/content-containers');
                    const json = await res.json();
                    if (!json.success) throw new Error(json.message || 'Error contenedores');
                    grid.innerHTML = '';
                    if (!json.containers?.length) {
                        grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><p>No tienes contenedores</p></div></div>';
                        return;
                    }
                    for (const c of json.containers) {
                        const el = document.createElement('div');
                        el.className = 'meeting-card cursor-pointer';
                        el.setAttribute('data-container-id', c.id);
                        el.innerHTML = `<div class=\"meeting-card-header\"><div class=\"meeting-content\"><h3 class=\"meeting-title\">${escapeHtml(c.name)}</h3><p class=\"meeting-date\">${(c.meetings_count||0)} reuniones</p></div></div>`;
                        el.addEventListener('click', () => loadContainerMeetings(c));
                        grid.appendChild(el);
                    }
                } catch (e) {
                    grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><p>Error al cargar contenedores</p></div></div>';
                    console.error(e);
                }
            }

            async function loadContainerMeetings(container) {
                const grid = document.querySelector('#containers-grid');
                grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><div class="loading-spinner"></div><p>Cargando reuniones del contenedor...</p></div></div>';
                try {
                    const res = await fetch(`/api/content-containers/${container.id}/meetings`);
                    const json = await res.json();
                    if (!json.success) throw new Error(json.message || 'Error al cargar reuniones del contenedor');
                    // Cabecera con botón volver y nombre del contenedor
                    grid.innerHTML = '';
                    // Asegurar que el contenedor principal no sea grid para colocar header arriba
                    grid.classList.remove('meetings-grid');
                    grid.classList.add('flex','flex-col','gap-4');
                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between';
                    header.innerHTML = `
                        <div class="text-slate-200 font-semibold">Contenedor: ${escapeHtml(container.name)}</div>
                        <button class="px-3 py-1 rounded bg-slate-700/60 text-slate-200 hover:bg-slate-600/60">Volver a contenedores</button>
                    `;
                    header.querySelector('button').addEventListener('click', () => loadContainersInto('#containers-grid'));
                    grid.appendChild(header);

                    const meetingsWrap = document.createElement('div');
                    meetingsWrap.className = 'meetings-grid';
                    grid.appendChild(meetingsWrap);
                    renderMeetingCards(meetingsWrap, json.meetings || []);
                } catch (e) {
                    grid.innerHTML = '<div style="grid-column:1 / -1; display:flex; justify-content:center; align-items:center; padding:1.5rem 0;"><div class="loading-card"><p>Error al cargar reuniones del contenedor</p></div></div>';
                    console.error(e);
                }
            }

            async function existsTasksForMeeting(meetingId) {
                try {
                    const res = await fetch(window.taskLaravel.apiExists, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.taskLaravel.csrf
                        },
                        body: JSON.stringify({ ids: [meetingId] })
                    });
                    const json = await res.json();
                    const map = json.exists || {};
                    return !!map[Number(meetingId)];
                } catch (e) {
                    console.warn('exists check failed', e);
                    return false;
                }
            }

        async function importTasks(meetingId) {
            try {
                const res = await fetch(window.taskLaravel.apiImport(meetingId), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.taskLaravel.csrf }
                });
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || 'Error al importar tareas de la reunión');
                    return false;
                }
                return true;
            } catch (e) {
                console.error(e);
                alert('Error al importar tareas');
                return false;
            }
        }

    async function fetchTasksForMeeting(meetingId){
        const url = new URL(window.taskLaravel.apiTasks, window.location.origin);
        url.searchParams.set('meeting_id', meetingId);
        const res = await fetch(url);
        return await res.json();
    }

    async function renderTasksAfterFetch(meetingId){
            const listEl = document.getElementById('tasks-sidebar-list');
            const statTotal = document.getElementById('stat-total');
            const statPending = document.getElementById('stat-pending');
            const statInprog = document.getElementById('stat-inprogress');
            const statCompleted = document.getElementById('stat-completed');

            listEl.innerHTML = '<p class="text-slate-400">Cargando tareas…</p>';
            try {
        const json = await fetchTasksForMeeting(meetingId);
                if (!json.success) throw new Error('Error al cargar tareas');
                const tasks = json.tasks || [];
                const s = json.stats || {};
                statTotal.textContent = s.total ?? 0;
                statPending.textContent = s.pending ?? 0;
                statInprog.textContent = s.in_progress ?? 0;
                statCompleted.textContent = s.completed ?? 0;
                renderTasksSidebar(tasks);
            } catch (e) {
                console.error(e);
                listEl.innerHTML = '<p class="text-red-400">No se pudieron cargar las tareas.</p>';
            }
        }

    window.loadTasksForMeeting = renderTasksAfterFetch;

        function renderTasksSidebar(tasks) {
            const listEl = document.getElementById('tasks-sidebar-list');
            listEl.innerHTML = '';
            if (!tasks.length) {
                listEl.innerHTML = '<p class="text-slate-400">No hay tareas para esta reunión.</p>';
                return;
            }
            for (const t of tasks) {
                const item = document.createElement('div');
                item.className = 'task-card p-3';
                const due = t.fecha_limite ? new Date(t.fecha_limite).toLocaleDateString() : '';
                const dueTime = t.hora_limite ? String(t.hora_limite).slice(0,5) : '';
                const prog = typeof t.progreso === 'number' ? t.progreso : 0;
                const prio = (t.prioridad || '').toLowerCase();
                item.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-semibold text-slate-100">${escapeHtml(t.tarea || 'Sin nombre')}</h4>
                                ${prio ? `<span class="px-2 py-0.5 text-xs rounded-full ${prio==='alta'?'bg-red-500/20 text-red-400':prio==='media'?'bg-yellow-500/20 text-yellow-400':'bg-green-500/20 text-green-400'}">${prio.charAt(0).toUpperCase()+prio.slice(1)}</span>` : ''}
                                <span class="px-2 py-0.5 text-xs rounded-full ${prog>=100?'bg-green-500/20 text-green-400':prog>0?'bg-yellow-500/20 text-yellow-400':'bg-slate-600/40 text-slate-300'}">${prog>=100?'Completada':prog>0?'En progreso':'Pendiente'}</span>
                            </div>
                            ${t.descripcion ? `<p class="text-slate-400 text-xs mb-2">${escapeHtml(String(t.descripcion)).slice(0,100)}${String(t.descripcion).length>100?'…':''}</p>` : ''}
                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                ${due?`<span class="flex items-center gap-1">📅 ${due}${dueTime?` ${dueTime}`:''}</span>`:''}
                                ${prog?`<span class="flex items-center gap-1">📈 ${prog}%</span>`:''}
                            </div>
                        </div>
                    </div>`;
                listEl.appendChild(item);
            }
        }

        function escapeHtml(s){
            return String(s).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
        }

        // Inicial
        // handled by setActiveTab
    });
    </script>
</div>
<?php /**PATH C:\laragon\www\Juntify\resources\views/tasks/partials/_tabs-reuniones.blade.php ENDPATH**/ ?>