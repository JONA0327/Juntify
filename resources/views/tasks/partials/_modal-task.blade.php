<!-- Modal para Crear/Editar Tarea -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[1100]">
    <div class="flex items-center justify-center min-h-screen p-4">

        <div class="bg-slate-800 rounded-lg shadow-xl w-full max-w-md sm:max-w-lg md:max-w-2xl max-h-screen overflow-y-auto">

            <div class="p-6">
                <!-- Header del Modal -->
                <div class="flex justify-between items-center mb-6">
                    <h3 id="modalTitle" class="text-lg font-semibold text-slate-100">Crear Nueva Tarea</h3>
                    <button onclick="closeTaskModal()" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Formulario -->
                <form id="taskForm">
                    <input type="hidden" id="taskId" name="taskId">

                    <!-- Título/Texto de la tarea -->
                    <div class="mb-4">
                        <label for="taskText" class="block text-sm font-medium text-slate-300 mb-2">
                            Título de la tarea <span class="text-red-400">*</span>
                        </label>
                        <input type="text" id="taskText" name="text" required
                               class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ej: Revisar propuesta de proyecto">
                    </div>

                    <!-- Descripción -->
                    <div class="mb-4">
                        <label for="taskDescription" class="block text-sm font-medium text-slate-300 mb-2">
                            Descripción
                        </label>
                        <textarea id="taskDescription" name="description" rows="3"
                                  class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Describe los detalles de la tarea..."></textarea>
                    </div>

                    <!-- Fecha de vencimiento -->
                    <div class="mb-4">
                        <label for="taskDueDate" class="block text-sm font-medium text-slate-300 mb-2">
                            Fecha de vencimiento
                        </label>
                        <input type="date" id="taskDueDate" name="due_date"
                               class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Hora límite -->
                    <div class="mb-4">
                        <label for="taskDueTime" class="block text-sm font-medium text-slate-300 mb-2">
                            Hora límite
                        </label>
                        <input type="time" id="taskDueTime" name="due_time"
                               class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Prioridad -->
                    <div class="mb-4">
                        <label for="taskPriority" class="block text-sm font-medium text-slate-300 mb-2">
                            Prioridad
                        </label>
                        <select id="taskPriority" name="priority"
                                class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>

                    <!-- Asignar a -->
                    <div class="mb-4">
                        <label for="taskAssigneeSelector" class="block text-sm font-medium text-slate-300 mb-2">
                            Asignar a
                        </label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select id="taskAssigneeSelector"
                                    class="w-full sm:w-1/2 px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecciona contacto o miembro</option>
                            </select>
                            <input type="text" id="taskAssigneeInput" name="assignee"
                                   class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Usuario o correo (opcional)">
                        </div>
                        <input type="hidden" id="taskAssignedUserId" name="assigned_user_id">
                        <p class="text-xs text-slate-400 mt-1">Deja vacío para asignarte la tarea a ti mismo</p>
                    </div>

                    <!-- Progreso (solo para editar) -->
                    <div id="progressContainer" class="mb-4 hidden">
                        <label for="taskProgress" class="block text-sm font-medium text-slate-300 mb-2">
                            Progreso (%)
                        </label>
                        <input type="range" id="taskProgress" name="progress" min="0" max="100" value="0"
                               class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer">
                        <div class="flex justify-between text-xs text-slate-400 mt-1">
                            <span>0%</span>
                            <span id="progressValue">0%</span>
                            <span>100%</span>
                        </div>
                    </div>

                    <!-- Completada (solo para editar) -->
                    <div id="completedContainer" class="mb-6 hidden">
                        <label class="flex items-center">
                            <input type="checkbox" id="taskCompleted" name="completed"
                                   class="w-4 h-4 text-blue-600 bg-slate-700 border-slate-600 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-300">Marcar como completada</span>
                        </label>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeTaskModal()"
                                class="px-4 py-2 text-slate-400 hover:text-slate-200 border border-slate-600 rounded-lg hover:border-slate-500 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" id="submitBtn"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            Crear Tarea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let editingTaskId = null;
const taskAssigneeSelector = document.getElementById('taskAssigneeSelector');
const taskAssigneeInput = document.getElementById('taskAssigneeInput');
const taskAssignedUserIdInput = document.getElementById('taskAssignedUserId');
let lastLoadedMeetingIdForTaskModal = null;
let lastLoadedQueryForTaskModal = '';
let taskAssigneeSearchTimeout = null;
const ASSIGNEE_SEARCH_DELAY = 350;
const ASSIGNEE_MIN_SEARCH_LENGTH = 2;

function taskModalGetAssignableManager() {
    return window.AssignableUsersManager;
}

function resetTaskAssigneeFields() {
    if (taskAssigneeSelector) {
        taskAssigneeSelector.value = '';
    }
    if (taskAssigneeInput) {
        taskAssigneeInput.value = '';
    }
    if (taskAssignedUserIdInput) {
        taskAssignedUserIdInput.value = '';
    }
    lastLoadedQueryForTaskModal = '';
}

function handleTaskAssigneeChange() {
    if (!taskAssigneeSelector || !taskAssignedUserIdInput) {
        return;
    }
    const selectedValue = taskAssigneeSelector.value || '';
    const selectedOption = taskAssigneeSelector.options[taskAssigneeSelector.selectedIndex];
    taskAssignedUserIdInput.value = selectedValue;

    if (!taskAssigneeInput) {
        return;
    }

    if (selectedValue) {
        const optionName = selectedOption?.dataset?.name || '';
        const optionEmail = selectedOption?.dataset?.email || '';
        const displayValue = optionName || optionEmail || selectedOption?.textContent || '';
        taskAssigneeInput.value = displayValue;
    } else {
        taskAssigneeInput.value = '';
    }
}

function handleTaskAssigneeInputChange() {
    if (taskAssignedUserIdInput) {
        taskAssignedUserIdInput.value = '';
    }
    if (taskAssigneeSelector) {
        taskAssigneeSelector.value = '';
    }

    const meetingId = window.lastSelectedMeetingId || null;
    if (!meetingId || !taskAssigneeInput) {
        return;
    }

    const value = taskAssigneeInput.value.trim();
    const shouldSearch = value.length >= ASSIGNEE_MIN_SEARCH_LENGTH;
    const query = shouldSearch ? value : '';
    const forceRefresh = shouldSearch || lastLoadedQueryForTaskModal !== query;

    if (taskAssigneeSearchTimeout) {
        clearTimeout(taskAssigneeSearchTimeout);
    }

    taskAssigneeSearchTimeout = setTimeout(() => {
        refreshTaskAssigneeOptions(meetingId, null, { forceRefresh, query });
    }, ASSIGNEE_SEARCH_DELAY);
}

function refreshTaskAssigneeOptions(meetingId, selectedUserId = null, { forceRefresh = false, query = '' } = {}) {
    const manager = taskModalGetAssignableManager();
    if (!manager || typeof manager.loadAssignableUsers !== 'function') {
        lastLoadedMeetingIdForTaskModal = meetingId;
        return Promise.resolve([]);
    }
    const normalizedQuery = (query || '').trim();
    const shouldForceRefresh = forceRefresh
        || meetingId !== lastLoadedMeetingIdForTaskModal
        || normalizedQuery !== lastLoadedQueryForTaskModal;
    return manager
        .loadAssignableUsers(meetingId, { forceRefresh: shouldForceRefresh, query: normalizedQuery })
        .then(users => {
            if (typeof manager.populateAssigneeSelector === 'function') {
                manager.populateAssigneeSelector(taskAssigneeSelector, users, selectedUserId);
            }
            lastLoadedMeetingIdForTaskModal = meetingId;
            lastLoadedQueryForTaskModal = normalizedQuery;
            return users;
        })
        .catch(error => {
            console.error('Error loading assignable users for task modal:', error);
            return [];
        });
}

function openTaskModal(taskId = null, source = (window.lastSelectedMeetingSource || 'transcriptions_laravel')) {
    const modal = document.getElementById('taskModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');
    const completedContainer = document.getElementById('completedContainer');
    const form = document.getElementById('taskForm');

    editingTaskId = taskId;
    window.lastSelectedMeetingSource = source;

    const baseMeetingId = window.lastSelectedMeetingId || null;
    resetTaskAssigneeFields();

    if (taskId) {
        // Modo edición
        modalTitle.textContent = 'Editar Tarea';
        submitBtn.textContent = 'Actualizar Tarea';
        progressContainer.classList.remove('hidden');
        completedContainer.classList.remove('hidden');
        refreshTaskAssigneeOptions(baseMeetingId);

        // Para todas las reuniones, usar tasks_laravel
        fetch(new URL(`/api/tasks-laravel/tasks/${taskId}`, window.location.origin))
            .then(response => response.json())
            .then(resp => {
                if (!resp.success) throw new Error('No se pudo cargar la tarea');
                const t = resp.task;

                document.getElementById('taskId').value = t.id;
                document.getElementById('taskText').value = t.tarea || '';
                document.getElementById('taskDescription').value = t.descripcion || '';

                // Manejar fecha límite
                if (t.fecha_limite) {
                    document.getElementById('taskDueDate').value = t.fecha_limite;
                } else {
                    document.getElementById('taskDueDate').value = '';
                }

                // Manejar hora límite
                if (t.hora_limite) {
                    // Asegurar formato HH:MM
                    let horaLimite = t.hora_limite.toString();
                    if (horaLimite.length > 5) {
                        horaLimite = horaLimite.slice(0, 5);
                    }
                    document.getElementById('taskDueTime').value = horaLimite;
                } else {
                    document.getElementById('taskDueTime').value = '';
                }

                document.getElementById('taskPriority').value = (t.prioridad || 'media');
                if (taskAssigneeInput) {
                    taskAssigneeInput.value = (t.assigned_user && t.assigned_user.name) || t.asignado || '';
                }
                if (taskAssignedUserIdInput) {
                    taskAssignedUserIdInput.value = t.assigned_user_id ? String(t.assigned_user_id) : '';
                }
                document.getElementById('taskProgress').value = (typeof t.progreso === 'number' ? t.progreso : 0);
                document.getElementById('taskCompleted').checked = (t.progreso >= 100);
                updateProgressValue();

                const meetingId = t.meeting_id || baseMeetingId || null;
                const selectedUserId = t.assigned_user_id ? String(t.assigned_user_id) : null;
                refreshTaskAssigneeOptions(meetingId, selectedUserId, { forceRefresh: meetingId !== lastLoadedMeetingIdForTaskModal })
                    .then(() => {
                        if (taskAssigneeSelector && selectedUserId) {
                            taskAssigneeSelector.value = selectedUserId;
                            handleTaskAssigneeChange();
                        }
                    });
            })
            .catch(error => {
                console.error('Error cargando tarea:', error);
                alert('Error al cargar la tarea');
            });
    } else {
        // Modo creación
        modalTitle.textContent = 'Crear Nueva Tarea';
        submitBtn.textContent = 'Crear Tarea';
        progressContainer.classList.add('hidden');
        completedContainer.classList.add('hidden');
        form.reset();
        resetTaskAssigneeFields();
        refreshTaskAssigneeOptions(baseMeetingId, null, { forceRefresh: baseMeetingId !== lastLoadedMeetingIdForTaskModal });
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeTaskModal() {
    const modal = document.getElementById('taskModal');
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    editingTaskId = null;
    resetTaskAssigneeFields();
}

function updateProgressValue() {
    const progress = document.getElementById('taskProgress').value;
    document.getElementById('progressValue').textContent = progress + '%';
}

// Event listeners
if (taskAssigneeSelector) {
    taskAssigneeSelector.addEventListener('change', handleTaskAssigneeChange);
}
if (taskAssigneeInput) {
    taskAssigneeInput.addEventListener('input', handleTaskAssigneeInputChange);
}
document.getElementById('taskProgress').addEventListener('input', updateProgressValue);

document.getElementById('taskForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const entries = Object.fromEntries(formData.entries());
    const isEdit = !!editingTaskId;
    const source = window.lastSelectedMeetingSource || 'transcriptions_laravel';

    // Para todas las reuniones (tanto meetings como transcriptions_laravel), usar tasks_laravel
    if (window.lastSelectedMeetingId) {
        const payload = {
            tarea: entries.text,
            descripcion: entries.description || null,
            prioridad: entries.priority || null,
            fecha_inicio: null,
            fecha_limite: entries.due_date || null,
            hora_limite: entries.due_time || null,
            asignado: entries.assignee || null,
            assigned_user_id: entries.assigned_user_id ? entries.assigned_user_id : null,
            progreso: parseInt(document.getElementById('taskProgress').value || '0', 10)
        };
        if (!isEdit) {
            payload.meeting_id = window.lastSelectedMeetingId;
        }

        const endpoint = isEdit ? `/api/tasks-laravel/tasks/${editingTaskId}` : '/api/tasks-laravel/tasks';
        const method = isEdit ? 'PUT' : 'POST';

        fetch(new URL(endpoint, window.location.origin), {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || window.taskData?.csrfToken)
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeTaskModal();
                if (window.loadTasksForMeeting && window.lastSelectedMeetingId) {
                    window.loadTasksForMeeting(window.lastSelectedMeetingId, source);
                } else {
                    location.reload();
                }
            } else {
                alert('Error al guardar la tarea: ' + (result.message || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar la tarea');
        });
    } else {
        alert('Selecciona una reunión primero para asociar la tarea.');
    }
});

// Event listeners para abrir modal desde botones
document.querySelectorAll('.edit-task').forEach(btn => {
    btn.addEventListener('click', function() {
        const taskId = this.dataset.taskId;
        openTaskModal(taskId);
    });
});

// Cerrar modal al hacer clic fuera de él
document.getElementById('taskModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTaskModal();
    }
});
</script>
