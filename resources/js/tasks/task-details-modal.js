let currentTaskDetailsId = null;

function openTaskDetailsModal(taskId) {
    currentTaskDetailsId = taskId;
    loadTaskDetails(taskId);
    document.getElementById('taskDetailsModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeTaskDetailsModal() {
    document.getElementById('taskDetailsModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    currentTaskDetailsId = null;
}

async function loadTaskDetails(taskId) {
    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${taskId}`, window.location.origin), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            }
        });

        const data = await response.json();
        if (data.success && data.task) {
            populateTaskDetails(data.task);
            loadTaskComments(taskId);
            loadTaskFiles(taskId);
        }
    } catch (error) {
        console.error('Error loading task details:', error);
    }
}

function populateTaskDetails(task) {
    document.getElementById('detailsTaskTitle').textContent = task.tarea || 'Sin título';
    document.getElementById('detailsTaskDescription').textContent = task.descripcion || 'Sin descripción';

    // Prioridad
    const priorityEl = document.getElementById('detailsTaskPriority');
    const priorityMap = {
        'alta': { text: 'Alta', class: 'bg-red-500/20 text-red-300' },
        'media': { text: 'Media', class: 'bg-yellow-500/20 text-yellow-300' },
        'baja': { text: 'Baja', class: 'bg-green-500/20 text-green-300' }
    };
    const priority = priorityMap[task.prioridad] || { text: 'Media', class: 'bg-gray-500/20 text-gray-300' };
    priorityEl.textContent = priority.text;
    priorityEl.className = `inline-block mt-1 px-2 py-1 rounded text-xs ${priority.class}`;

    // Estado
    const statusEl = document.getElementById('detailsTaskStatus');
    let status = 'Pendiente';
    let statusClass = 'bg-yellow-500/20 text-yellow-300';

    if (task.progreso >= 100) {
        status = 'Completada';
        statusClass = 'bg-green-500/20 text-green-300';
    } else if (task.progreso > 0) {
        status = 'En progreso';
        statusClass = 'bg-blue-500/20 text-blue-300';
    }

    statusEl.textContent = status;
    statusEl.className = `inline-block mt-1 px-2 py-1 rounded text-xs ${statusClass}`;

    // Fechas
    document.getElementById('detailsTaskDueDate').textContent = task.fecha_limite || 'No definida';
    document.getElementById('detailsTaskDueTime').textContent = task.hora_limite || 'No definida';

    // Asignado
    document.getElementById('detailsTaskAssignee').textContent = task.asignado || 'No asignado';

    // Progreso
    const progress = task.progreso || 0;
    document.getElementById('detailsTaskProgress').textContent = `${progress}%`;
    document.getElementById('detailsTaskProgressBar').style.width = `${progress}%`;

    // Configurar botones
    document.getElementById('editTaskBtn').onclick = () => {
        closeTaskDetailsModal();
        if (typeof openTaskModal === 'function') {
            openTaskModal(task.id, 'transcriptions_laravel');
        }
    };

    document.getElementById('completeTaskBtn').onclick = () => completeTask(task.id);
}

async function loadTaskComments(taskId) {
    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${taskId}/comments`, window.location.origin), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            }
        });

        const data = await response.json();
        const commentsList = document.getElementById('commentsList');
        commentsList.innerHTML = '';

        if (data.success && data.comments) {
            data.comments.forEach(comment => {
                const commentEl = document.createElement('div');
                commentEl.className = 'bg-slate-600/50 rounded-lg p-3';
                commentEl.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-sm font-medium text-slate-300">${comment.user || 'Usuario'}</span>
                        <span class="text-xs text-slate-400">${formatDate(comment.created_at)}</span>
                    </div>
                    <p class="text-slate-100 text-sm">${comment.text}</p>
                `;
                commentsList.appendChild(commentEl);
            });
        }

        if (commentsList.children.length === 0) {
            commentsList.innerHTML = '<p class="text-slate-400 text-sm">No hay comentarios aún.</p>';
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    }
}

async function loadTaskFiles(taskId) {
    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${taskId}/files`, window.location.origin), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            }
        });

        const data = await response.json();
        const filesList = document.getElementById('filesList');
        filesList.innerHTML = '';

        if (data.success && data.files) {
            data.files.forEach(file => {
                const fileEl = document.createElement('div');
                fileEl.className = 'bg-slate-600/50 rounded-lg p-3 flex items-center justify-between';
                fileEl.innerHTML = `
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm text-slate-200">${file.filename}</span>
                    </div>
                    <button onclick="downloadFile('${file.id}')" class="text-blue-400 hover:text-blue-300 text-xs">
                        Descargar
                    </button>
                `;
                filesList.appendChild(fileEl);
            });
        }

        if (filesList.children.length === 0) {
            filesList.innerHTML = '<p class="text-slate-400 text-sm">No hay archivos adjuntos.</p>';
        }
    } catch (error) {
        console.error('Error loading files:', error);
    }
}

// Event listeners
document.getElementById('addCommentBtn').addEventListener('click', async () => {
    const commentText = document.getElementById('newComment').value.trim();
    if (!commentText || !currentTaskDetailsId) return;

    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${currentTaskDetailsId}/comments`, window.location.origin), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            },
            body: JSON.stringify({ text: commentText })
        });

        const data = await response.json();
        if (data.success) {
            document.getElementById('newComment').value = '';
            loadTaskComments(currentTaskDetailsId);
        }
    } catch (error) {
        console.error('Error adding comment:', error);
    }
});

document.getElementById('uploadFileBtn').addEventListener('click', () => {
    document.getElementById('fileInput').click();
});

document.getElementById('fileInput').addEventListener('change', async (e) => {
    const files = e.target.files;
    if (!files.length || !currentTaskDetailsId) return;

    const formData = new FormData();
    for (const file of files) {
        formData.append('files[]', file);
    }

    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${currentTaskDetailsId}/files`, window.location.origin), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            loadTaskFiles(currentTaskDetailsId);
            e.target.value = '';
        }
    } catch (error) {
        console.error('Error uploading files:', error);
    }
});

async function completeTask(taskId) {
    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${taskId}/complete`, window.location.origin), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            }
        });

        const data = await response.json();
        if (data.success) {
            loadTaskDetails(taskId);
            // Recargar calendario si existe
            if (typeof loadAndRender === 'function') {
                loadAndRender();
            }
        }
    } catch (error) {
        console.error('Error completing task:', error);
    }
}

function downloadFile(fileId) {
    window.open(new URL(`/api/tasks-laravel/files/${fileId}/download`, window.location.origin), '_blank');
}

function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
}

// Cerrar modal al hacer clic fuera
document.getElementById('taskDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTaskDetailsModal();
    }
});

window.openTaskDetailsModal = openTaskDetailsModal;
window.closeTaskDetailsModal = closeTaskDetailsModal;

