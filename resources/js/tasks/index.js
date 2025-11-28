document.addEventListener('DOMContentLoaded', () => {
    const isBusinessPlan = document.body.dataset.isBusinessPlan === '1';

    window.taskLaravel = {
        csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
        apiTasks: '/api/tasks-laravel/tasks',
        apiTasksShow: (id) => `/api/tasks-laravel/tasks/${id}`,
        apiTasksUpdate: (id) => `/api/tasks-laravel/tasks/${id}`,
        apiTasksDestroy: (id) => `/api/tasks-laravel/tasks/${id}`,
        apiExists: '/api/tasks-laravel/exists',
        apiImport: (meetingId) => `/api/tasks-laravel/import/${meetingId}`,
        isBusinessPlan: isBusinessPlan
    };

    // Helper opcional para mostrar/ocultar el panel de tareas lateral
    window.showTasksPanel = function(show){
        const empty = document.getElementById('tasks-empty');
        const panel = document.getElementById('tasks-panel');
        if (empty) empty.classList.toggle('hidden', !!show);
        if (panel) panel.classList.toggle('hidden', !show);
    };

    // Extensión Kanban: agrupa tareas por estado y muestra un resumen interactivo
    const csrfToken = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '');
    let currentKanbanTab = 'board';
    let currentKanbanTasks = [];
    let currentTaskMainView = 'calendario';
    let availableMeetings = [];
    let currentMeetingFilter = null; // null = todas, number = meeting_id específico

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
    const kanbanUndatedCount = document.getElementById('kanban-undated-count');
    const kanbanUndatedList = document.getElementById('kanban-undated-list');
    const kanbanUndatedEmpty = document.getElementById('kanban-undated-empty');

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

    function populateMeetingFilter(meetings) {
        const select = document.getElementById('meetingFilter');
        if (!select) return;

        const currentValue = select.value;
        const totalTasks = meetings.reduce((sum, m) => sum + (m.task_count || 0), 0);

        select.innerHTML = `<option value="">🌐 Todas las reuniones (${totalTasks} tareas)</option>`;

        // Ordenar reuniones por número de tareas (descendente) y luego por nombre
        meetings
            .sort((a, b) => {
                const countDiff = (b.task_count || 0) - (a.task_count || 0);
                return countDiff !== 0 ? countDiff : a.name.localeCompare(b.name, 'es', { sensitivity: 'base' });
            })
            .forEach(meeting => {
                if (!meeting || !meeting.id) return;
                const option = document.createElement('option');
                option.value = meeting.id;
                const taskCount = meeting.task_count || 0;
                const taskText = taskCount === 1 ? '1 tarea' : `${taskCount} tareas`;
                option.textContent = `📋 ${meeting.name} (${taskText})`;
                select.appendChild(option);
            });

        // Restaurar selección si era válida
        if (currentValue && Array.from(select.options).some(opt => opt.value === currentValue)) {
            select.value = currentValue;
        }
    }

    function updateFilterStats(tasks, meetings, currentFilter) {
        const statsEl = document.getElementById('filterStats');
        const clearBtn = document.getElementById('clearMeetingFilter');
        const filterBadge = document.getElementById('activeFilterBadge');
        const filterContainer = document.getElementById('meetingFilterContainer');

        if (!statsEl) return;

        if (currentFilter) {
            const meeting = meetings.find(m => String(m.id) === String(currentFilter));
            const meetingName = meeting ? meeting.name : 'Reunión desconocida';
            const taskCount = tasks.length;

            statsEl.textContent = `📋 ${meetingName}: ${taskCount} tarea${taskCount === 1 ? '' : 's'}`;
            statsEl.className = 'text-blue-300 font-medium';

            if (clearBtn) clearBtn.classList.remove('hidden');
            if (filterBadge) filterBadge.classList.remove('hidden');
            if (filterContainer) {
                filterContainer.classList.add('bg-blue-900/20', 'border-blue-600/30');
                filterContainer.classList.remove('bg-slate-800/30', 'border-slate-700/40');
            }
        } else {
            const totalTasks = tasks.length;
            const meetingCount = meetings.length;

            statsEl.textContent = `🌐 ${totalTasks} tarea${totalTasks === 1 ? '' : 's'} en ${meetingCount} reunión${meetingCount === 1 ? '' : 'es'}`;
            statsEl.className = 'text-slate-400';

            if (clearBtn) clearBtn.classList.add('hidden');
            if (filterBadge) filterBadge.classList.add('hidden');
            if (filterContainer) {
                filterContainer.classList.remove('bg-blue-900/20', 'border-blue-600/30');
                filterContainer.classList.add('bg-slate-800/30', 'border-slate-700/40');
            }
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

        const layout = document.getElementById('tasks-layout');
        if (layout) {
            layout.classList.toggle('kanban-active', currentTaskMainView === 'tablero');
        }

        const board = document.getElementById('kanban-board');
        if (board) {
            const visible = kanbanHasData() && currentTaskMainView === 'tablero';
            board.classList.toggle('hidden', !visible);
        }
    }

    function setTaskMainView(view){
        // Para usuarios business, solo permitir calendario
        if (window.taskLaravel && window.taskLaravel.isBusinessPlan && view === 'tablero') {return;
        }

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

            // Priorizar filtro de reunión específico sobre la reunión del último contexto
            if (currentMeetingFilter) {
                url.searchParams.set('meeting_id', currentMeetingFilter);
            } else if (hasMeeting && !currentMeetingFilter) {
                url.searchParams.set('meeting_id', window.lastSelectedMeetingId);
            }

            const res = await fetch(url);
            const json = await res.json();
            if (!json.success) {
                currentKanbanTasks = [];
                if (kanbanOverdueList) kanbanOverdueList.innerHTML = '';
                if (kanbanOverdueCount) kanbanOverdueCount.textContent = '0';
                if (kanbanOverdueEmpty) kanbanOverdueEmpty.classList.remove('hidden');
                if (kanbanUndatedList) kanbanUndatedList.innerHTML = '';
                if (kanbanUndatedCount) kanbanUndatedCount.textContent = '0';
                if (kanbanUndatedEmpty) kanbanUndatedEmpty.classList.remove('hidden');
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

            // Actualizar filtro de reuniones disponibles
            if (Array.isArray(json.meetings)) {
                availableMeetings = json.meetings.map(m => ({
                    ...m,
                    task_count: rawTasks.filter(t => String(t.meeting_id) === String(m.id)).length
                }));
                populateMeetingFilter(availableMeetings);
            }

            // Actualizar estadísticas del filtro
            updateFilterStats(enrichedTasks, availableMeetings, currentMeetingFilter);

            currentKanbanTasks = enrichedTasks;

            const columns = { pending: [], in_progress: [], completed: [], approved: [] };
            const overdueTasks = [];
            const undatedTasks = [];

            enrichedTasks.forEach(t => {
                const progress = typeof t._progress === 'number' ? t._progress : 0;
                const isApproved = t.assignment_status === 'completed';
                const isCompleted = progress >= 100;
                const hasDueDate = !!t.fecha_limite;

                if (!hasDueDate) {
                    undatedTasks.push(t);
                }

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
                    if (!hasDueDate) {
                        return;
                    }
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

            if (kanbanUndatedCount) kanbanUndatedCount.textContent = String(undatedTasks.length);
            if (kanbanUndatedList) {
                kanbanUndatedList.innerHTML = '';
                undatedTasks.forEach(t => {
                    const card = document.createElement('div');
                    card.className = 'kanban-card bg-slate-900/70 border border-amber-500/50 rounded-xl p-3 text-sm text-amber-100 transition-colors cursor-pointer hover:border-amber-400/80';
                    card.dataset.id = t.id;

                    const assigneeName = (t.assigned_user && t.assigned_user.name) || t.asignado || 'Sin responsable';
                    const meetingLabel = t.meeting_name ? escapeHtml(t.meeting_name) : 'Sin reunión';

                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-amber-100 leading-snug">${escapeHtml(t.tarea || 'Sin nombre')}</p>
                            <span class="text-[10px] uppercase tracking-wide text-amber-100 bg-amber-500/20 border border-amber-400/40 rounded-full px-2 py-0.5">Sin fecha</span>
                        </div>
                        <div class="mt-1 text-[11px] text-amber-100/80">Responsable: ${escapeHtml(assigneeName)}</div>
                        <div class="mt-1 text-[11px] text-amber-100/70 flex items-center gap-1">🗓 <span>${meetingLabel}</span></div>
                        <p class="mt-2 text-[11px] text-amber-200/80 italic">Haz clic para asignar fecha y responsable.</p>
                    `;

                    card.addEventListener('click', () => {
                        if (typeof openTaskModal === 'function') {
                            openTaskModal(t.id, 'tasks_laravel');
                        }
                    });

                    kanbanUndatedList.appendChild(card);
                });
            }
            if (kanbanUndatedEmpty) kanbanUndatedEmpty.classList.toggle('hidden', undatedTasks.length > 0);

            updateKanbanSummary(enrichedTasks);
            setKanbanTab(currentKanbanTab);
            showKanban(true);
        } catch (e) {
            console.error('kanban reload', e);
            currentKanbanTasks = [];
            if (kanbanOverdueList) kanbanOverdueList.innerHTML = '';
            if (kanbanOverdueCount) kanbanOverdueCount.textContent = '0';
            if (kanbanOverdueEmpty) kanbanOverdueEmpty.classList.remove('hidden');
            if (kanbanUndatedList) kanbanUndatedList.innerHTML = '';
            if (kanbanUndatedCount) kanbanUndatedCount.textContent = '0';
            if (kanbanUndatedEmpty) kanbanUndatedEmpty.classList.remove('hidden');
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
            const hasDueDate = !!t.fecha_limite;
            const isOverdue = t._overdue || t.is_overdue === true || (!isCompleted && due && due < today);
            const isInProgress = !isCompleted && !isOverdue && progress > 0;
            const isPending = hasDueDate && !isCompleted && !isOverdue && progress <= 0;

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

    // Establecer vista por defecto según el tamaño de pantalla
    function setDefaultView() {
        if (window.innerWidth <= 768) {
            // En móvil, forzar kanban disponible y usar tablero
            showKanban(true);
            setTaskMainView('tablero');
        } else {
            setTaskMainView('calendario');
        }
    }

    // Override la función original para permitir calendario en móvil
    const originalSetTaskMainView = setTaskMainView;
    setTaskMainView = function(view) {
        // Permitir ambas vistas en móvil
        currentTaskMainView = ['calendario', 'tablero'].includes(view) ? view : 'tablero';

        // Ocultar filtro cuando esté en calendario
        const filterContainer = document.getElementById('meetingFilterContainer');
        if (filterContainer) {
            if (currentTaskMainView === 'calendario') {
                filterContainer.style.display = 'none';
            } else {
                filterContainer.style.display = 'block';
            }
        }

        // Forzar mostrar kanban board cuando esté en vista tablero
        const kanbanBoard = document.getElementById('kanban-board');
        if (kanbanBoard) {
            if (currentTaskMainView === 'tablero') {
                kanbanBoard.classList.remove('hidden');
                kanbanBoard.dataset.hasKanban = '1';
                document.body.classList.add('kanban-active');
            } else {
                kanbanBoard.classList.add('hidden');
                document.body.classList.remove('kanban-active');
            }
        }

        // Ocultar/mostrar elementos de calendario explícitamente
        document.querySelectorAll('[data-task-view-targets="calendario"]').forEach(element => {
            if (currentTaskMainView === 'tablero') {
                element.style.display = 'none';
            } else {
                element.style.display = 'block';
            }
        });

        refreshTaskViewVisibility();
    };        setDefaultView();

    // Ajustar vista cuando cambie el tamaño de pantalla
    window.addEventListener('resize', () => {
        setDefaultView();
    });

    // Event listeners para filtro de reuniones
    const meetingFilterSelect = document.getElementById('meetingFilter');
    const clearMeetingFilterBtn = document.getElementById('clearMeetingFilter');

    if (meetingFilterSelect) {
        meetingFilterSelect.addEventListener('change', async (e) => {
            const selectedValue = e.target.value;
            currentMeetingFilter = selectedValue ? parseInt(selectedValue) : null;

            // Limpiar el contexto de reunión seleccionada si hay filtro específico
            if (currentMeetingFilter) {
                window.lastSelectedMeetingId = null;
                window.lastSelectedMeetingName = null;
            }

            await kanbanReload();
        });
    }

    if (clearMeetingFilterBtn) {
        clearMeetingFilterBtn.addEventListener('click', async () => {
            currentMeetingFilter = null;
            if (meetingFilterSelect) meetingFilterSelect.value = '';
            await kanbanReload();
        });
    }

    const _origLoadTasks = window.loadTasksForMeeting;
    window.loadTasksForMeeting = async function(id, src){
        if (_origLoadTasks) await _origLoadTasks(id, src);
        await kanbanReload();
    };

    window.kanbanAllowDrop = kanbanAllowDrop;
    window.kanbanDrop = kanbanDrop;
    window.setTaskMainView = setTaskMainView;
    window.kanbanReload = kanbanReload;

    kanbanReload();
});
