<!-- Modal para Crear/Editar Tarea -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[4000]">
    <div class="flex items-center justify-center min-h-screen p-4 sm:p-6">

        <div class="bg-slate-800 rounded-lg shadow-xl w-full max-w-md sm:max-w-lg md:max-w-2xl max-h-[90vh] overflow-y-auto">

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
                    <div class="mb-4" x-data="userAssignmentComponent()">
                        <label for="taskAssignee" class="block text-sm font-medium text-slate-300 mb-2">
                            Asignar a
                        </label>

                        <!-- Campo de búsqueda -->
                        <div class="relative">
                            <input type="text"
                                   id="taskAssignee"
                                   x-model="searchTerm"
                                   @input="searchUsers()"
                                   @focus="showDropdown = true"
                                   @blur="handleBlur()"
                                   class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Buscar por nombre o correo..."
                                   autocomplete="off">

                            <!-- Input oculto para el valor real -->
                            <input type="hidden" name="assignee" x-model="selectedUserId">

                            <!-- Dropdown de resultados -->
                            <div x-show="showDropdown && (users.length > 0 || directoryUsers.length > 0)"
                                 x-transition
                                 class="absolute z-50 w-full mt-1 bg-slate-800 border border-slate-600 rounded-lg shadow-xl max-h-64 overflow-y-auto">

                                <!-- Usuario asignado actualmente -->
                                <template x-if="currentAssignedUser && !searchTerm">
                                    <div class="p-3 border-b border-slate-600">
                                        <div class="text-xs font-medium text-slate-400 mb-2">Asignado actualmente:</div>
                                        <div class="flex items-center gap-2 p-2 bg-blue-600/20 border border-blue-500/30 rounded-lg">
                                            <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-slate-100 truncate" x-text="currentAssignedUser.name"></div>
                                                <div class="text-xs text-slate-400 truncate" x-text="currentAssignedUser.email"></div>
                                            </div>
                                            <button type="button" @click="removeAssignment()" class="text-red-400 hover:text-red-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Usuarios conocidos -->
                                <template x-for="user in users" :key="user.id">
                                    <div @mousedown.prevent="selectUser(user)"
                                         class="px-3 py-2 hover:bg-slate-700 cursor-pointer border-b border-slate-700 last:border-b-0">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-medium text-sm"
                                                 x-text="(user.name || user.email).charAt(0).toUpperCase()"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-slate-100 truncate" x-text="user.name || user.email"></div>
                                                <div class="text-xs text-slate-400 truncate" x-text="user.email"></div>
                                                <div class="text-xs text-blue-400" x-text="user.label"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Separador si hay usuarios de directorio -->
                                <template x-if="users.length > 0 && directoryUsers.length > 0">
                                    <div class="border-t border-slate-600"></div>
                                </template>

                                <!-- Usuarios del directorio -->
                                <template x-for="user in directoryUsers" :key="user.id">
                                    <div @mousedown.prevent="selectUser(user)"
                                         class="px-3 py-2 hover:bg-slate-700 cursor-pointer border-b border-slate-700 last:border-b-0">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gradient-to-br from-gray-500 to-gray-600 rounded-full flex items-center justify-center text-white font-medium text-sm"
                                                 x-text="(user.name || user.email).charAt(0).toUpperCase()"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-slate-100 truncate" x-text="user.name || user.email"></div>
                                                <div class="text-xs text-slate-400 truncate" x-text="user.email"></div>
                                                <div class="text-xs text-amber-400" x-text="user.label"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Estado de carga -->
                                <template x-if="loading">
                                    <div class="p-3 text-center text-slate-400">
                                        <svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                <!-- Sin resultados -->
                                <template x-if="!loading && users.length === 0 && directoryUsers.length === 0 && searchTerm.length >= 2">
                                    <div class="p-3 text-center text-slate-400">
                                        No se encontraron usuarios
                                    </div>
                                </template>
                            </div>
                        </div>

                        <p class="text-xs text-slate-400 mt-1">Busca por nombre o correo. Deja vacío para asignarte la tarea a ti mismo</p>
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

function openTaskModal(taskId = null, source = (window.lastSelectedMeetingSource || 'transcriptions_laravel')) {
    const modal = document.getElementById('taskModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('taskForm');

    editingTaskId = taskId;
    window.lastSelectedMeetingSource = source;

    if (taskId) {
        // Modo edición
        modalTitle.textContent = 'Editar Tarea';
        submitBtn.textContent = 'Actualizar Tarea';

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

                // Configurar asignación de usuario
                const assignmentComponent = Alpine.$data(document.querySelector('[x-data*="userAssignmentComponent"]'));
                if (assignmentComponent && t.assigned_user) {
                    assignmentComponent.setCurrentAssignment(
                        t.assigned_user.id,
                        t.assigned_user.full_name || t.assigned_user.username,
                        t.assigned_user.email
                    );
                }
            })
            .catch(error => {
                console.error('Error cargando tarea:', error);
                alert('Error al cargar la tarea');
            });
    } else {
        // Modo creación
        modalTitle.textContent = 'Crear Nueva Tarea';
        submitBtn.textContent = 'Crear Tarea';
        form.reset();

        // Reset del componente de asignación
        const assignmentComponent = Alpine.$data(document.querySelector('[x-data*="userAssignmentComponent"]'));
        if (assignmentComponent) {
            assignmentComponent.removeAssignment();
        }
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
            assigned_user_id: entries.assignee || null
        };
        if (!isEdit) {
            payload.meeting_id = window.lastSelectedMeetingId;
            payload.progreso = 0;
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

// Componente Alpine.js para asignación de usuarios
function userAssignmentComponent() {
    return {
        searchTerm: '',
        selectedUserId: '',
        users: [],
        directoryUsers: [],
        loading: false,
        showDropdown: false,
        currentAssignedUser: null,
        searchTimeout: null,

        init() {
            // Cargar usuarios iniciales
            this.loadInitialUsers();
        },

        async loadInitialUsers() {
            try {
                const response = await fetch('/api/tasks-laravel/assignable-users');
                const data = await response.json();
                if (data.success) {
                    this.users = data.users || [];
                }
            } catch (error) {
                console.error('Error cargando usuarios:', error);
            }
        },

        async searchUsers() {
            clearTimeout(this.searchTimeout);

            if (this.searchTerm.length < 2) {
                await this.loadInitialUsers();
                return;
            }

            this.searchTimeout = setTimeout(async () => {
                this.loading = true;
                try {
                    const response = await fetch(`/api/tasks-laravel/assignable-users?q=${encodeURIComponent(this.searchTerm)}`);
                    const data = await response.json();
                    if (data.success) {
                        this.users = data.users || [];
                        this.directoryUsers = data.directory || [];
                    }
                } catch (error) {
                    console.error('Error en búsqueda:', error);
                } finally {
                    this.loading = false;
                }
            }, 300);
        },

        selectUser(user) {
            this.selectedUserId = user.id;
            this.searchTerm = user.name || user.email;
            this.showDropdown = false;
            this.currentAssignedUser = user;
        },

        removeAssignment() {
            this.selectedUserId = '';
            this.searchTerm = '';
            this.currentAssignedUser = null;
        },

        handleBlur() {
            // Pequeño delay para permitir clicks en el dropdown
            setTimeout(() => {
                this.showDropdown = false;
            }, 200);
        },

        setCurrentAssignment(userId, userName, userEmail) {
            if (userId) {
                this.selectedUserId = userId;
                this.searchTerm = userName || userEmail;
                this.currentAssignedUser = {
                    id: userId,
                    name: userName || userEmail,
                    email: userEmail
                };
            } else {
                this.removeAssignment();
            }
        }
    }
}
</script>
