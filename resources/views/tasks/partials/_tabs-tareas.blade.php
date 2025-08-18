<!-- Contenedor de Pestañas de Tareas -->
<div class="info-card">
    <nav class="tabs-nav">
        <a class="tab-link active" data-tab="all">Todas las tareas</a>
        <a class="tab-link" data-tab="pending">Pendientes</a>
        <a class="tab-link" data-tab="in_progress">En progreso</a>
        <a class="tab-link" data-tab="completed">Completadas</a>
        <a class="tab-link" data-tab="overdue">Vencidas</a>
    </nav>

    <!-- Filtros -->
    <div class="flex gap-4 mb-6">
        <select id="priority-filter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-200">
            <option value="all">Todas las prioridades</option>
            <option value="alta">Alta prioridad</option>
            <option value="media">Media prioridad</option>
            <option value="baja">Baja prioridad</option>
        </select>

        <input type="date" id="date-filter" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-200">
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-slate-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-blue-400">{{ $stats['total'] }}</div>
            <div class="text-sm text-slate-400">Total</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-yellow-400">{{ $stats['pending'] }}</div>
            <div class="text-sm text-slate-400">Pendientes</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-orange-400">{{ $stats['in_progress'] }}</div>
            <div class="text-sm text-slate-400">En progreso</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-green-400">{{ $stats['completed'] }}</div>
            <div class="text-sm text-slate-400">Completadas</div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-red-400">{{ $stats['overdue'] }}</div>
            <div class="text-sm text-slate-400">Vencidas</div>
        </div>
    </div>

    <!-- Contenido de tareas -->
    <div id="tasks-container" class="flex flex-col gap-4">
        @forelse($tasks as $task)
            <div class="task-card priority-{{ $task->priority }} {{ $task->completed ? 'task-completed' : '' }} {{ $task->is_overdue ? 'task-overdue' : '' }}"
                 data-task-id="{{ $task->id }}"
                 data-priority="{{ $task->priority }}"
                 data-status="{{ $task->status }}"
                 data-due-date="{{ $task->due_date ? $task->due_date->format('Y-m-d') : '' }}">

                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h4 class="font-semibold text-slate-100">{{ $task->text }}</h4>

                            <!-- Badge de prioridad -->
                            <span class="px-2 py-1 text-xs rounded-full
                                {{ $task->priority === 'alta' ? 'bg-red-500/20 text-red-400' : '' }}
                                {{ $task->priority === 'media' ? 'bg-yellow-500/20 text-yellow-400' : '' }}
                                {{ $task->priority === 'baja' ? 'bg-green-500/20 text-green-400' : '' }}">
                                {{ ucfirst($task->priority) }}
                            </span>

                            <!-- Badge de estado -->
                            <span class="px-2 py-1 text-xs rounded-full
                                {{ $task->completed ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400' }}">
                                {{ $task->completed ? 'Completada' : ($task->progress > 0 ? 'En progreso' : 'Pendiente') }}
                            </span>
                        </div>

                        @if($task->description)
                            <p class="text-slate-400 text-sm mb-3">{{ Str::limit($task->description, 100) }}</p>
                        @endif

                        <div class="flex items-center gap-4 text-sm text-slate-500">
                            @if($task->due_date)
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ $task->due_date->format('d/m/Y') }}
                                </span>
                            @endif

                            @if($task->assignee && $task->assignee !== auth()->user()->username)
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    {{ $task->assignedUser->name ?? $task->assignee }}
                                </span>
                            @endif

                            @if($task->progress > 0)
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    {{ $task->progress }}%
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="flex gap-2">
                        @if(!$task->completed)
                            <button class="btn btn-success btn-sm complete-task" data-task-id="{{ $task->id }}" title="Marcar como completada">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </button>
                        @endif

                        <button class="btn btn-secondary btn-sm edit-task" data-task-id="{{ $task->id }}" title="Editar tarea">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>

                        @if($task->username === auth()->user()->username)
                            <button class="btn btn-danger btn-sm delete-task" data-task-id="{{ $task->id }}" title="Eliminar tarea">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Barra de progreso -->
                @if($task->progress > 0 && !$task->completed)
                    <div class="mt-3">
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: {{ $task->progress }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-8 text-slate-400">
                <svg class="w-16 h-16 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p>No tienes tareas aún</p>
                <button class="btn btn-primary mt-4" onclick="openTaskModal()">Crear tu primera tarea</button>
            </div>
        @endforelse
    </div>

    <!-- Paginación -->
    @if($tasks->hasPages())
        <div class="mt-6">
            {{ $tasks->links() }}
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtros
    const priorityFilter = document.getElementById('priority-filter');
    const dateFilter = document.getElementById('date-filter');
    const tabLinks = document.querySelectorAll('.tab-link');

    let currentTab = 'all';

    // Manejo de pestañas
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Actualizar estilos de pestañas
            tabLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');

            currentTab = this.dataset.tab;
            filterTasks();
        });
    });

    // Manejo de filtros
    priorityFilter.addEventListener('change', filterTasks);
    dateFilter.addEventListener('change', filterTasks);

    function filterTasks() {
        const priority = priorityFilter.value;
        const date = dateFilter.value;
        const tasks = document.querySelectorAll('.task-card');

        tasks.forEach(task => {
            let show = true;

            // Filtro por pestaña
            if (currentTab !== 'all') {
                const taskStatus = task.dataset.status;
                const isCompleted = task.classList.contains('task-completed');
                const isOverdue = task.classList.contains('task-overdue');

                switch (currentTab) {
                    case 'pending':
                        show = !isCompleted && taskStatus === 'pending';
                        break;
                    case 'in_progress':
                        show = !isCompleted && taskStatus === 'in_progress';
                        break;
                    case 'completed':
                        show = isCompleted;
                        break;
                    case 'overdue':
                        show = isOverdue && !isCompleted;
                        break;
                }
            }

            // Filtro por prioridad
            if (show && priority !== 'all') {
                show = task.dataset.priority === priority;
            }

            // Filtro por fecha
            if (show && date) {
                const taskDate = task.dataset.dueDate;
                show = taskDate === date;
            }

            task.style.display = show ? 'block' : 'none';
        });
    }

    // Marcar como completada
    document.querySelectorAll('.complete-task').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.taskId;

            fetch(`/api/tasks/${taskId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.taskData.csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Recargar para actualizar la vista
                } else {
                    alert('Error al completar la tarea');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al completar la tarea');
            });
        });
    });

    // Eliminar tarea
    document.querySelectorAll('.delete-task').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que quieres eliminar esta tarea?')) {
                const taskId = this.dataset.taskId;

                fetch(`/api/tasks/${taskId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.taskData.csrfToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Recargar para actualizar la vista
                    } else {
                        alert('Error al eliminar la tarea');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar la tarea');
                });
            }
        });
    });
});
</script>
