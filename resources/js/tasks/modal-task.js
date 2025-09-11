let editingTaskId = null;

function openTaskModal(taskId = null, source = (window.lastSelectedMeetingSource || 'transcriptions_laravel')) {
    const modal = document.getElementById('taskModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');
    const completedContainer = document.getElementById('completedContainer');
    const form = document.getElementById('taskForm');

    editingTaskId = taskId;
    window.lastSelectedMeetingSource = source;

    if (taskId) {
        // Modo edición
        modalTitle.textContent = 'Editar Tarea';
        submitBtn.textContent = 'Actualizar Tarea';
        progressContainer.classList.remove('hidden');
        completedContainer.classList.remove('hidden');

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
                document.getElementById('taskAssignee').value = t.asignado || '';
                document.getElementById('taskProgress').value = (typeof t.progreso === 'number' ? t.progreso : 0);
                document.getElementById('taskCompleted').checked = (t.progreso >= 100);
                updateProgressValue();
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
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeTaskModal() {
    const modal = document.getElementById('taskModal');
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    editingTaskId = null;
}

function updateProgressValue() {
    const progress = document.getElementById('taskProgress').value;
    document.getElementById('progressValue').textContent = progress + '%';
}

// Event listeners
function registerModalTaskEvents() {
    const progressInput = document.getElementById('taskProgress');
    if (progressInput) {
        progressInput.addEventListener('input', updateProgressValue);
    }

    const form = document.getElementById('taskForm');
    if (form) {
        form.addEventListener('submit', function (e) {
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
    }

    document.querySelectorAll('.edit-task').forEach(btn => {
        btn.addEventListener('click', function () {
            const taskId = this.dataset.taskId;
            openTaskModal(taskId);
        });
    });

    const modal = document.getElementById('taskModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                closeTaskModal();
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', registerModalTaskEvents);
} else {
    registerModalTaskEvents();
}

window.openTaskModal = openTaskModal;
window.closeTaskModal = closeTaskModal;
