<!-- Modal de Detalles de Tarea -->
<div id="taskDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[4000]">
    <div class="flex items-center justify-center min-h-screen p-4 sm:p-6">
        <div class="bg-slate-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
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
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Asignado a:</label>
                                        <div id="detailsTaskAssigneeContainer" class="mt-2">
                                            <div id="detailsTaskAssigneeInfo" class="flex items-center gap-3 p-3 bg-slate-700/30 rounded-lg border border-slate-600/50 hidden">
                                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                    <span id="detailsAssigneeInitials">--</span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p id="detailsTaskAssignee" class="text-slate-100 font-medium"></p>
                                                    <p id="detailsAssigneeEmail" class="text-slate-400 text-sm truncate"></p>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <span id="detailsAssignmentStatus" class="inline-block px-2 py-1 rounded text-xs bg-slate-600/40 text-slate-200">Sin asignar</span>
                                                </div>
                                            </div>
                                            <div id="detailsNoAssignee" class="flex items-center gap-2 p-3 bg-slate-700/20 rounded-lg border border-slate-600/30 text-slate-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                <span>Sin asignar - Usa los controles de abajo para asignar</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Progreso:</label>
                                        <div class="mt-1">
                                            <div class="w-full bg-slate-600 rounded-full h-2">
                                                <div id="detailsTaskProgressBar" class="bg-blue-500 h-2 rounded-full" style="width: 0%"></div>
                                            </div>
                                            <span id="detailsTaskProgress" class="text-sm text-slate-300"></span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-slate-300">Reunión:</label>
                                        <p id="detailsMeetingName" class="text-slate-100 mt-1"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="mt-4 flex flex-wrap gap-2 items-center">
                                <button id="editTaskBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    Editar Tarea
                                </button>
                                <button id="completeTaskBtn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                                    Marcar Completada
                                </button>
                                <div id="assignControls" class="flex flex-col gap-3 ml-auto">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                        <select id="assigneeSelector" class="px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-slate-100 text-sm min-w-[200px]">
                                            <option value="">Selecciona usuario para asignar</option>
                                        </select>
                                        <span class="text-slate-400 text-sm">o</span>
                                        <input id="assigneeInput" type="text" placeholder="buscar por nombre, email..." list="assigneeSuggestions" class="px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-slate-100 text-sm min-w-[200px]" />
                                        <datalist id="assigneeSuggestions"></datalist>
                                    </div>
                                    <div class="flex gap-2">
                                        <input id="assignmentMessage" type="text" placeholder="Mensaje opcional para el usuario..." class="flex-1 px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-slate-100 text-sm" maxlength="200" />
                                        <button id="assignTaskBtn" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">
                                            📤 Asignar
                                        </button>
                                    </div>
                                </div>
                                <div id="assignmentResponseControls" class="flex items-center gap-2">
                                    <button id="acceptTaskBtn" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">Aceptar</button>
                                    <button id="rejectTaskBtn" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm">Rechazar</button>
                                    <button id="reactivateTaskBtn" class="px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm hidden">Reactivar</button>
                                </div>
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
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    <select id="driveDestination" class="px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-slate-100 text-sm min-w-[180px]">
                                        <option value="personal">Drive personal</option>
                                    </select>
                                    <button id="uploadFileBtn" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors border-2 border-dashed border-green-500/50 hover:border-green-500">
                                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        Subir Archivo
                                    </button>
                                </div>
                                <p class="text-xs text-slate-400 mt-2">Puedes elegir entre tu Drive personal o el de tu organización. Si la carpeta Documentos no existe se creará automáticamente.</p>
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
let cachedAssignableUsers = null;
let directorySearchCache = new Map();
let directorySearchTimeout = null;

const assignmentStatusStyles = {
    pending: { text: 'Pendiente de aceptación', class: 'bg-yellow-500/20 text-yellow-300' },
    accepted: { text: 'Aceptada', class: 'bg-blue-500/20 text-blue-300' },
    completed: { text: 'Completada', class: 'bg-green-500/20 text-green-300' },
    rejected: { text: 'Rechazada', class: 'bg-rose-500/20 text-rose-300' },
};

async function loadAssignableUsers() {
    if (cachedAssignableUsers) {
        return cachedAssignableUsers;
    }
    try {
        const response = await fetch(new URL('/api/tasks-laravel/assignable-users', window.location.origin), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            }
        });
        const data = await response.json();
        cachedAssignableUsers = Array.isArray(data.users) ? data.users : [];
    } catch (error) {
        console.error('Error loading assignable users:', error);
        cachedAssignableUsers = [];
    }
    return cachedAssignableUsers;
}

function handleAssigneeAccessWarning(event) {
    const select = event?.currentTarget;
    if (!select) return;
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption) return;

    const access = selectedOption.dataset.taskAccess || 'full';
    if (access === 'blocked') {
        const message = selectedOption.dataset.restrictedMessage || 'Este usuario no puede ver tareas por su plan.';
        alert(message);
        select.value = '';
    }
}

function populateAssigneeSelector(users, currentId) {
    const select = document.getElementById('assigneeSelector');
    if (!select) return;

    const previousValue = currentId ? String(currentId) : '';
    select.innerHTML = '<option value="">Selecciona usuario para asignar</option>';

    // Agrupar usuarios por fuente para mejor organización
    const grouped = users.reduce((acc, user) => {
        const source = user.source || 'other';
        if (!acc[source]) acc[source] = [];
        acc[source].push(user);
        return acc;
    }, {});

    // Orden de prioridad de las fuentes
    const sourceOrder = ['organization', 'group', 'contact', 'directory', 'other'];

    sourceOrder.forEach(sourceKey => {
        const sourceUsers = grouped[sourceKey];
        if (!sourceUsers || sourceUsers.length === 0) return;

        // Agregar grupo si hay usuarios de múltiples fuentes
        if (Object.keys(grouped).length > 1) {
            const groupOption = document.createElement('option');
            groupOption.disabled = true;
            groupOption.style.fontWeight = 'bold';
            groupOption.style.backgroundColor = '#374151';
            groupOption.style.color = '#d1d5db';
            groupOption.textContent = `── ${sourceUsers[0].label || sourceKey.toUpperCase()} ──`;
            select.appendChild(groupOption);
        }

        // Agregar usuarios del grupo
        sourceUsers.forEach(user => {
            if (!user || !user.id) return;
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = `${user.name || user.email}`;
            option.dataset.email = user.email || '';
            option.dataset.username = user.username || '';
            option.dataset.source = user.source || '';
            option.dataset.label = user.label || '';
            const access = user.task_access || 'full';
            const restrictedMessage = user.restricted_message || '';
            option.dataset.taskAccess = access;
            if (restrictedMessage) {
                option.dataset.restrictedMessage = restrictedMessage;
            }
            if (access === 'blocked') {
                option.textContent += ' (sin acceso a tareas)';
            } else if (access === 'organization_only') {
                option.textContent += ' (solo organización)';
            }
            option.title = `${user.name || user.email} - ${user.email || ''}`;
            select.appendChild(option);
        });
    });

    // Seleccionar valor previo si existe
    if (previousValue && Array.from(select.options).some(opt => opt.value === previousValue)) {
        select.value = previousValue;
    } else {
        select.value = '';
    }

    select.disabled = users.length <= 0;

    if (!select.dataset.accessGuardAttached) {
        select.addEventListener('change', handleAssigneeAccessWarning);
        select.dataset.accessGuardAttached = '1';
    }
}

async function fetchDirectorySuggestions(query) {
    const normalized = query.trim().toLowerCase();
    if (normalized.length < 2) {
        populateAssigneeSuggestions([]);
        return;
    }

    if (directorySearchCache.has(normalized)) {
        const cached = directorySearchCache.get(normalized);
        populateAssigneeSuggestions(cached.known || [], cached.directory || []);
        return;
    }

    try {
        const url = new URL('/api/tasks-laravel/assignable-users', window.location.origin);
        url.searchParams.set('q', normalized);
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            }
        });
        const data = await response.json();

        const knownUsers = Array.isArray(data.users) ? data.users : [];
        const directoryUsers = Array.isArray(data.directory) ? data.directory : [];

        directorySearchCache.set(normalized, { known: knownUsers, directory: directoryUsers });
        populateAssigneeSuggestions(knownUsers, directoryUsers);
    } catch (error) {
        console.error('Error loading directory suggestions:', error);
        populateAssigneeSuggestions([], []);
    }
}

function populateAssigneeSuggestions(knownUsers = [], directoryUsers = []) {
    const dataList = document.getElementById('assigneeSuggestions');
    if (!dataList) return;

    dataList.innerHTML = '';

    // Primero agregar usuarios conocidos (organización, grupos, contactos)
    knownUsers.forEach(user => {
        if (!user) return;
        const option = document.createElement('option');
        option.value = user.email || user.username || '';
        const labelPrefix = user.label ? `${user.label} ` : '';
        const access = user.task_access || 'full';
        const suffix = access === 'blocked'
            ? ' (sin acceso a tareas)'
            : (access === 'organization_only' ? ' (solo organización)' : '');
        option.label = `${labelPrefix}${user.name || user.email || user.username}${suffix}`;
        option.dataset.taskAccess = access;
        if (user.restricted_message) {
            option.dataset.restrictedMessage = user.restricted_message;
        }
        dataList.appendChild(option);
    });

    // Luego agregar usuarios del directorio
    directoryUsers.forEach(user => {
        if (!user) return;
        const option = document.createElement('option');
        option.value = user.email || user.username || '';
        const access = user.task_access || 'full';
        const suffix = access === 'blocked'
            ? ' (sin acceso a tareas)'
            : (access === 'organization_only' ? ' (solo organización)' : '');
        option.label = `🔍 ${user.name || user.email || user.username} (${user.email || user.username})${suffix}`;
        option.dataset.taskAccess = access;
        if (user.restricted_message) {
            option.dataset.restrictedMessage = user.restricted_message;
        }
        dataList.appendChild(option);
    });
}

function scheduleDirectorySearch(value) {
    if (directorySearchTimeout) {
        clearTimeout(directorySearchTimeout);
        directorySearchTimeout = null;
    }

    const normalized = (value || '').trim();
    if (normalized.length < 2) {
        directorySearchTimeout = setTimeout(() => populateAssigneeSuggestions([], []), 0);
        return;
    }

    directorySearchTimeout = setTimeout(() => fetchDirectorySuggestions(normalized), 250);
}

function handleAssigneeSearchInput(event) {
    scheduleDirectorySearch(event?.target?.value || '');
}

async function setupAssignableControls(task) {
    const assignWrapper = document.getElementById('assignControls');
    const responseControls = document.getElementById('assignmentResponseControls');
    const assigneeSelector = document.getElementById('assigneeSelector');
    const assigneeInput = document.getElementById('assigneeInput');
    const assignBtn = document.getElementById('assignTaskBtn');
    const acceptBtn = document.getElementById('acceptTaskBtn');
    const rejectBtn = document.getElementById('rejectTaskBtn');
    const reactivateBtn = document.getElementById('reactivateTaskBtn');

    const isOwner = task.owner_username && window.authUsername && task.owner_username === window.authUsername;
    const isAssignee = task.assigned_user && window.authUserId && String(task.assigned_user.id) === String(window.authUserId);

    if (assignWrapper) {
        assignWrapper.classList.toggle('hidden', !isOwner);
    }
    if (assigneeSelector) assigneeSelector.disabled = !isOwner;
    if (assigneeInput) {
        assigneeInput.disabled = !isOwner;
        assigneeInput.removeEventListener('input', handleAssigneeSearchInput);
        if (isOwner) {
            populateAssigneeSuggestions([]);
            assigneeInput.addEventListener('input', handleAssigneeSearchInput);
        } else {
            populateAssigneeSuggestions([]);
        }
    }
    if (assignBtn) assignBtn.disabled = !isOwner;

    if (responseControls) {
        responseControls.classList.toggle('hidden', !isAssignee);
    }
    if (acceptBtn) {
        acceptBtn.classList.toggle('hidden', !(isAssignee && task.assignment_status === 'pending'));
    }
    if (rejectBtn) {
        rejectBtn.classList.toggle('hidden', !(isAssignee && task.assignment_status === 'pending'));
    }
    if (reactivateBtn) {
        if (isOwner && (task.progreso || 0) >= 100) {
            reactivateBtn.classList.remove('hidden');
        } else {
            reactivateBtn.classList.add('hidden');
        }
    }

    if (assigneeSelector && isOwner) {
        const users = await loadAssignableUsers();
        populateAssigneeSelector(users, task.assigned_user_id);
    }
}

function populateDriveDestination(options) {
    const select = document.getElementById('driveDestination');
    if (!select) return;

    const available = Array.isArray(options) && options.length > 0 ? options : [{ value: 'personal', label: 'Drive personal' }];
    const previousValue = select.value;
    select.innerHTML = '';

    available.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.value || 'personal';
        option.textContent = opt.organization_name ? `${opt.label} (${opt.organization_name})` : (opt.label || 'Drive personal');
        select.appendChild(option);
    });

    if (previousValue && available.some(opt => opt.value === previousValue)) {
        select.value = previousValue;
    }

    select.disabled = available.length <= 1;
}

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

    // Estado general de la tarea (progreso)
    const statusEl = document.getElementById('detailsTaskStatus');
    let status = 'Pendiente';
    let statusClass = 'bg-yellow-500/20 text-yellow-300';

    if ((task.progreso || 0) >= 100) {
        status = 'Completada';
        statusClass = 'bg-green-500/20 text-green-300';
    } else if ((task.progreso || 0) > 0) {
        status = 'En progreso';
        statusClass = 'bg-blue-500/20 text-blue-300';
    }

    statusEl.textContent = status;
    statusEl.className = `inline-block mt-1 px-2 py-1 rounded text-xs ${statusClass}`;

    // Fechas y reunión
    document.getElementById('detailsTaskDueDate').textContent = task.fecha_limite || 'No definida';
    document.getElementById('detailsTaskDueTime').textContent = task.hora_limite || 'No definida';
    document.getElementById('detailsMeetingName').textContent = task.meeting_name || 'Sin reunión';

    // Asignación - Información del usuario asignado
    const assigneeInfo = document.getElementById('detailsTaskAssigneeInfo');
    const noAssignee = document.getElementById('detailsNoAssignee');

    if (task.assigned_user || task.asignado) {
        // Hay usuario asignado - mostrar información
        const assigneeName = (task.assigned_user && task.assigned_user.full_name) ||
                             (task.assigned_user && task.assigned_user.name) ||
                             task.asignado || 'Usuario';
        const assigneeEmail = (task.assigned_user && task.assigned_user.email) || '';

        // Generar iniciales para el avatar
        const initials = assigneeName.split(' ').map(word => word.charAt(0)).join('').substring(0, 2).toUpperCase();

        document.getElementById('detailsTaskAssignee').textContent = assigneeName;
        document.getElementById('detailsAssigneeEmail').textContent = assigneeEmail;
        document.getElementById('detailsAssigneeInitials').textContent = initials || '??';

        assigneeInfo.classList.remove('hidden');
        noAssignee.classList.add('hidden');
    } else {
        // No hay usuario asignado
        assigneeInfo.classList.add('hidden');
        noAssignee.classList.remove('hidden');
    }
    const assignmentStatusEl = document.getElementById('detailsAssignmentStatus');
    const statusKey = task.assignment_status || (task.assigned_user_id ? 'accepted' : null);
    const assignmentStatus = assignmentStatusStyles[statusKey] || { text: 'Sin asignar', class: 'bg-slate-600/40 text-slate-200' };
    assignmentStatusEl.textContent = assignmentStatus.text;
    assignmentStatusEl.className = `inline-block mt-1 px-2 py-1 rounded text-xs ${assignmentStatus.class}`;

    // Progreso
    const progress = Number(task.progreso) || 0;
    document.getElementById('detailsTaskProgress').textContent = `${progress}%`;
    document.getElementById('detailsTaskProgressBar').style.width = `${Math.min(Math.max(progress, 0), 100)}%`;

    // Botones principales
    const editBtn = document.getElementById('editTaskBtn');
    if (editBtn) {
        editBtn.onclick = () => {
            closeTaskDetailsModal();
            if (typeof openTaskModal === 'function') {
                openTaskModal(task.id, 'transcriptions_laravel');
            }
        };
    }

    const completeBtn = document.getElementById('completeTaskBtn');
    if (completeBtn) {
        completeBtn.onclick = () => completeTask(task.id);
    }

    const assigneeSelector = document.getElementById('assigneeSelector');
    const assigneeInput = document.getElementById('assigneeInput');
    const assignBtn = document.getElementById('assignTaskBtn');
    if (assignBtn) {
        assignBtn.onclick = async () => {
            const selectedId = assigneeSelector ? assigneeSelector.value : '';
            const manualValue = (assigneeInput?.value || '').trim();
            const messageInput = document.getElementById('assignmentMessage');
            const message = messageInput ? messageInput.value.trim() : '';
            const payload = {};

            if (selectedId) {
                payload.user_id = selectedId;
            } else if (manualValue) {
                if (manualValue.includes('@')) {
                    payload.email = manualValue;
                } else {
                    payload.username = manualValue;
                }
            } else {
                alert('Selecciona o escribe un usuario para asignar.');
                return;
            }

            // Agregar mensaje si se proporcionó
            if (message) {
                payload.message = message;
            }

            // Deshabilitar botón durante la asignación
            assignBtn.disabled = true;
            assignBtn.textContent = '📤 Enviando...';

            try {
                const response = await fetch(new URL(`/api/tasks-laravel/tasks/${task.id}/assign`, window.location.origin), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '')
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'No se pudo enviar la asignación');
                    return;
                }

                if (assigneeInput) assigneeInput.value = '';
                if (assigneeSelector) assigneeSelector.value = '';
                if (messageInput) messageInput.value = '';
                populateAssigneeSuggestions([], []);

                const successMsg = message
                    ? 'Solicitud de asignación enviada con tu mensaje personalizado'
                    : 'Solicitud de asignación enviada - El usuario recibirá un email';
                alert(successMsg);

                loadTaskDetails(task.id);
                if (typeof kanbanReload === 'function') kanbanReload();
            } catch (error) {
                console.error('Error assigning task:', error);
                alert('Error al asignar la tarea');
            } finally {
                // Restaurar botón
                assignBtn.disabled = false;
                assignBtn.textContent = '📤 Asignar';
            }
        };
    }

    const acceptBtn = document.getElementById('acceptTaskBtn');
    if (acceptBtn) {
        acceptBtn.onclick = async () => {
            try {
                const response = await fetch(new URL(`/api/tasks-laravel/tasks/${task.id}/respond`, window.location.origin), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '')
                    },
                    body: JSON.stringify({ action: 'accept' })
                });
                const data = await response.json();
                if (data.success) {
                    alert('Has aceptado la tarea');
                    loadTaskDetails(task.id);
                    if (typeof kanbanReload === 'function') kanbanReload();
                    if (typeof loadAndRender === 'function') loadAndRender();
                } else {
                    alert(data.message || 'No se pudo aceptar la tarea');
                }
            } catch (error) {
                console.error('Error al aceptar la tarea', error);
                alert('Error al aceptar la tarea');
            }
        };
    }

    const rejectBtn = document.getElementById('rejectTaskBtn');
    if (rejectBtn) {
        rejectBtn.onclick = async () => {
            try {
                const response = await fetch(new URL(`/api/tasks-laravel/tasks/${task.id}/respond`, window.location.origin), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '')
                    },
                    body: JSON.stringify({ action: 'reject' })
                });
                const data = await response.json();
                if (data.success) {
                    alert('Has rechazado la tarea');
                    loadTaskDetails(task.id);
                    if (typeof kanbanReload === 'function') kanbanReload();
                } else {
                    alert(data.message || 'No se pudo rechazar la tarea');
                }
            } catch (error) {
                console.error('Error al rechazar la tarea', error);
                alert('Error al rechazar la tarea');
            }
        };
    }

    const reactivateBtn = document.getElementById('reactivateTaskBtn');
    if (reactivateBtn) {
        reactivateBtn.onclick = async () => {
            const reasonPrompt = window.prompt('Describe el motivo de la reactivación (opcional):', '');
            if (reasonPrompt === null) {
                return;
            }
            try {
                const response = await fetch(new URL(`/api/tasks-laravel/tasks/${task.id}/reactivate`, window.location.origin), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '')
                    },
                    body: JSON.stringify({ reason: reasonPrompt?.trim() || undefined })
                });
                const data = await response.json();
                if (data.success) {
                    alert('Tarea reactivada');
                    loadTaskDetails(task.id);
                    if (typeof kanbanReload === 'function') kanbanReload();
                    if (typeof loadAndRender === 'function') loadAndRender();
                } else {
                    alert(data.message || 'No se pudo reactivar la tarea');
                }
            } catch (error) {
                console.error('Error al reactivar la tarea', error);
                alert('Error al reactivar la tarea');
            }
        };
    }

    setupAssignableControls(task).catch(err => console.error('setupAssignableControls error', err));
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

        if (data.success && Array.isArray(data.comments)) {
            data.comments.forEach(comment => renderCommentNode(comment, commentsList));
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

        if (data.success && Array.isArray(data.files)) {
            data.files.forEach(file => {
                const fileEl = document.createElement('div');
                fileEl.className = 'bg-slate-600/50 rounded-lg p-3 flex items-center justify-between';

                const infoWrapper = document.createElement('div');
                infoWrapper.className = 'flex items-center gap-2';
                infoWrapper.innerHTML = `
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                `;

                const nameSpan = document.createElement('span');
                nameSpan.className = 'text-sm text-slate-200';
                nameSpan.textContent = file.name || file.filename || 'Documento';
                infoWrapper.appendChild(nameSpan);

                if (file.drive_type === 'organization') {
                    const badge = document.createElement('span');
                    badge.className = 'ml-2 text-[11px] px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300';
                    badge.textContent = 'Organización';
                    infoWrapper.appendChild(badge);
                }

                const downloadBtn = document.createElement('button');
                downloadBtn.className = 'text-blue-400 hover:text-blue-300 text-xs';
                downloadBtn.textContent = 'Descargar';
                downloadBtn.addEventListener('click', () => downloadFile(file.id));

                fileEl.appendChild(infoWrapper);
                fileEl.appendChild(downloadBtn);
                filesList.appendChild(fileEl);
            });
        }

        if (data.success && data.drive_options) {
            populateDriveDestination(data.drive_options);
        }

        if (filesList.children.length === 0) {
            filesList.innerHTML = '<p class="text-slate-400 text-sm">No hay archivos adjuntos.</p>';
        }
    } catch (error) {
        console.error('Error loading files:', error);
        populateDriveDestination([]);
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

    const destination = document.getElementById('driveDestination');
    if (destination) {
        formData.append('drive_type', destination.value || 'personal');
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
            if (typeof kanbanReload === 'function') kanbanReload();
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

function renderCommentNode(comment, container, depth = 0) {
    if (!comment) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'bg-slate-600/50 rounded-lg p-3';
    if (depth > 0) {
        wrapper.classList.add('mt-2', 'ml-4', 'border-l', 'border-slate-500/60', 'pl-4');
    }

    const author = escapeHtml(comment.user || 'Usuario');
    const createdAt = comment.created_at ? formatDate(comment.created_at) : '';
    const text = escapeHtml(comment.text || '');

    wrapper.innerHTML = `
        <div class="flex justify-between items-start mb-2">
            <span class="text-sm font-medium text-slate-300">${author}</span>
            <span class="text-xs text-slate-400">${createdAt}</span>
        </div>
        <p class="text-slate-100 text-sm whitespace-pre-line">${text}</p>
    `;

    container.appendChild(wrapper);

    if (Array.isArray(comment.children)) {
        comment.children.forEach(child => renderCommentNode(child, container, depth + 1));
    }
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
