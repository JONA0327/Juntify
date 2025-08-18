<!-- Modal para Crear/Editar Tarea -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-slate-800 rounded-lg shadow-xl max-w-md w-full max-h-screen overflow-y-auto">
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
                        <label for="taskAssignee" class="block text-sm font-medium text-slate-300 mb-2">
                            Asignar a
                        </label>
                        <input type="text" id="taskAssignee" name="assignee"
                               class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Username del usuario (opcional)">
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

function openTaskModal(taskId = null) {
    const modal = document.getElementById('taskModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');
    const completedContainer = document.getElementById('completedContainer');
    const form = document.getElementById('taskForm');

    editingTaskId = taskId;

    if (taskId) {
        // Modo edición
        modalTitle.textContent = 'Editar Tarea';
        submitBtn.textContent = 'Actualizar Tarea';
        progressContainer.classList.remove('hidden');
        completedContainer.classList.remove('hidden');

        // Cargar datos de la tarea
        fetch(`/api/tasks/${taskId}`)
            .then(response => response.json())
            .then(task => {
                document.getElementById('taskId').value = task.id;
                document.getElementById('taskText').value = task.text;
                document.getElementById('taskDescription').value = task.description || '';
                document.getElementById('taskDueDate').value = task.due_date || '';
                document.getElementById('taskPriority').value = task.priority;
                document.getElementById('taskAssignee').value = task.assignee || '';
                document.getElementById('taskProgress').value = task.progress || 0;
                document.getElementById('taskCompleted').checked = task.completed;
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
}

function closeTaskModal() {
    const modal = document.getElementById('taskModal');
    modal.classList.add('hidden');
    editingTaskId = null;
}

function updateProgressValue() {
    const progress = document.getElementById('taskProgress').value;
    document.getElementById('progressValue').textContent = progress + '%';
}

// Event listeners
document.getElementById('taskProgress').addEventListener('input', updateProgressValue);

document.getElementById('taskForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    // Convertir checkbox a boolean
    data.completed = document.getElementById('taskCompleted').checked;

    const url = editingTaskId ? `/api/tasks/${editingTaskId}` : '/api/tasks';
    const method = editingTaskId ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.taskData.csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeTaskModal();
            location.reload(); // Recargar para mostrar la nueva/editada tarea
        } else {
            alert('Error al guardar la tarea: ' + (result.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar la tarea');
    });
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
