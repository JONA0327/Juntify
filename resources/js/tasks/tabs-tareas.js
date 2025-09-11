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

