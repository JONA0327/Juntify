<!-- Modal de Detalles de Tarea -->
<div id="taskDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[1200]">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-slate-800 rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="p-6">
                <!-- Header del Modal -->
                <div class="flex justify-between items-center mb-6">
                    <h3 id="taskDetailsTitle" class="text-xl font-semibold text-slate-100">Detalles de la Tarea</h3>
                    <button onclick="closeTaskDetailsModal()" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Contenido del Modal -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Información de la Tarea -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Detalles básicos -->
                        <div class="bg-slate-700/50 rounded-lg p-4">
                            <h4 class="text-lg font-medium text-slate-200 mb-3">Información de la Tarea</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-slate-300">Título:</label>
                                    <p id="detailsTaskTitle" class="text-slate-100 mt-1"></p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-300">Descripción:</label>
                                    <p id="detailsTaskDescription" class="text-slate-100 mt-1"></p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Prioridad:</label>
                                        <span id="detailsTaskPriority" class="inline-block mt-1 px-2 py-1 rounded text-xs"></span>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Estado:</label>
                                        <span id="detailsTaskStatus" class="inline-block mt-1 px-2 py-1 rounded text-xs"></span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Fecha límite:</label>
                                        <p id="detailsTaskDueDate" class="text-slate-100 mt-1"></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Hora límite:</label>
                                        <p id="detailsTaskDueTime" class="text-slate-100 mt-1"></p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Asignado a:</label>
                                        <p id="detailsTaskAssignee" class="text-slate-100 mt-1"></p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Progreso:</label>
                                        <div class="mt-1">
                                            <div class="w-full bg-slate-600 rounded-full h-2">
                                                <div id="detailsTaskProgressBar" class="bg-blue-500 h-2 rounded-full" style="width: 0%"></div>
                                            </div>
                                            <span id="detailsTaskProgress" class="text-sm text-slate-300"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="mt-4 flex gap-2">
                                <button id="editTaskBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    Editar Tarea
                                </button>
                                <button id="completeTaskBtn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                                    Marcar Completada
                                </button>
                            </div>
                        </div>

                        <!-- Comentarios -->
                        <div class="bg-slate-700/50 rounded-lg p-4">
                            <h4 class="text-lg font-medium text-slate-200 mb-4">Comentarios</h4>

                            <!-- Lista de comentarios -->
                            <div id="commentsList" class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                                <!-- Los comentarios se cargarán aquí -->
                            </div>

                            <!-- Agregar comentario -->
                            <div class="border-t border-slate-600 pt-4">
                                <textarea id="newComment"
                                         placeholder="Agregar un comentario..."
                                         class="w-full px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                         rows="3"></textarea>
                                <button id="addCommentBtn" class="mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    Agregar Comentario
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Archivos Adjuntos -->
                    <div class="space-y-6">
                        <div class="bg-slate-700/50 rounded-lg p-4">
                            <h4 class="text-lg font-medium text-slate-200 mb-4">Archivos Adjuntos</h4>

                            <!-- Lista de archivos -->
                            <div id="filesList" class="space-y-2 mb-4">
                                <!-- Los archivos se cargarán aquí -->
                            </div>

                            <!-- Subir archivo -->
                            <div class="border-t border-slate-600 pt-4">
                                <input type="file" id="fileInput" class="hidden" multiple>
                                <button id="uploadFileBtn" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors border-2 border-dashed border-green-500/50 hover:border-green-500">
                                    <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    Subir Archivo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>
<?php /**PATH C:\laragon\www\Juntify\resources\views/tasks/partials/_task-details-modal.blade.php ENDPATH**/ ?>