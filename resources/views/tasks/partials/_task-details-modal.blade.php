<!-- Modal de Detalles de Tarea -->
<div id="taskDetailsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[4000]">
    <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
        <div class="bg-slate-800/95 backdrop-blur-md rounded-2xl shadow-2xl border border-slate-700/50 w-full max-w-5xl max-h-[95vh] overflow-hidden">
            <!-- Header del Modal - Fijo -->
            <div class="bg-gradient-to-r from-slate-800 to-slate-700 p-6 border-b border-slate-600/50">
                <div class="flex justify-between items-center">
                    <h3 id="taskDetailsTitle" class="text-2xl font-bold bg-gradient-to-r from-slate-100 to-slate-300 bg-clip-text text-transparent">Detalles de la Tarea</h3>
                    <button onclick="closeTaskDetailsModal()" class="text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-xl p-2 transition-all duration-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Contenido del Modal - Con scroll invisible -->
            <div class="overflow-y-auto scrollbar-hide" style="max-height: calc(95vh - 120px);">
                <div class="p-6">

                <!-- Contenido del Modal -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <!-- Información Principal de la Tarea -->
                    <div class="xl:col-span-2 space-y-6">
                        <!-- Detalles básicos -->
                        <div class="bg-gradient-to-br from-slate-700/60 to-slate-800/40 rounded-2xl p-6 border border-slate-600/30 shadow-lg">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-2 h-8 bg-gradient-to-b from-blue-500 to-purple-600 rounded-full"></div>
                                <h4 class="text-xl font-semibold text-slate-200">Información de la Tarea</h4>
                            </div>
                            <div class="space-y-5">
                                <!-- Título y Descripción -->
                                <div class="space-y-4">
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-blue-300 uppercase tracking-wide">Título</label>
                                        <p id="detailsTaskTitle" class="text-slate-100 text-lg font-medium mt-2 leading-relaxed"></p>
                                    </div>
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-blue-300 uppercase tracking-wide">Descripción</label>
                                        <p id="detailsTaskDescription" class="text-slate-200 mt-2 leading-relaxed"></p>
                                    </div>
                                </div>

                                <!-- Estado y Prioridad -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-emerald-300 uppercase tracking-wide">Prioridad</label>
                                        <div class="mt-2">
                                            <span id="detailsTaskPriority" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium"></span>
                                        </div>
                                    </div>
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-emerald-300 uppercase tracking-wide">Estado</label>
                                        <div class="mt-2">
                                            <span id="detailsTaskStatus" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fechas -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-purple-300 uppercase tracking-wide">Fecha límite</label>
                                        <p id="detailsTaskDueDate" class="text-slate-100 font-medium mt-2"></p>
                                    </div>
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-purple-300 uppercase tracking-wide">Hora límite</label>
                                        <p id="detailsTaskDueTime" class="text-slate-100 font-medium mt-2"></p>
                                    </div>
                                </div>
                                <!-- Asignación -->
                                <div class="bg-slate-800/40 rounded-xl p-4">
                                    <label class="text-sm font-semibold text-cyan-300 uppercase tracking-wide">Asignado a</label>
                                    <div id="detailsTaskAssigneeContainer" class="mt-3">
                                        <div id="detailsTaskAssigneeInfo" class="flex items-center gap-4 p-4 bg-gradient-to-r from-slate-700/50 to-slate-600/30 rounded-xl border border-slate-500/30 hidden">
                                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 via-purple-500 to-cyan-500 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-lg">
                                                <span id="detailsAssigneeInitials">--</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p id="detailsTaskAssignee" class="text-slate-100 font-semibold text-lg"></p>
                                                <p id="detailsAssigneeEmail" class="text-slate-400 text-sm truncate"></p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span id="detailsAssignmentStatus" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-slate-600/40 text-slate-200">Sin asignar</span>
                                            </div>
                                        </div>
                                        <div id="detailsNoAssignee" class="flex items-center gap-3 p-4 bg-slate-700/30 rounded-xl border border-slate-600/30 text-slate-400">
                                            <div class="w-10 h-10 bg-slate-600/50 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <span class="font-medium">Sin asignar - Usa los controles de abajo para asignar</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Progreso y Reunión -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-green-300 uppercase tracking-wide">Progreso</label>
                                        <div class="mt-3 space-y-2">
                                            <div class="flex justify-between items-center">
                                                <span id="detailsTaskProgress" class="text-lg font-bold text-slate-100">0%</span>
                                            </div>
                                            <div class="w-full bg-slate-600/60 rounded-full h-3 overflow-hidden">
                                                <div id="detailsTaskProgressBar" class="bg-gradient-to-r from-green-500 to-emerald-400 h-full rounded-full transition-all duration-300 ease-out" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-slate-800/40 rounded-xl p-4">
                                        <label class="text-sm font-semibold text-orange-300 uppercase tracking-wide">Reunión</label>
                                        <div class="mt-3">
                                            <p id="detailsMeetingName" class="text-slate-100 font-medium text-lg"></p>
                                            <p id="detailsMeetingOwner" class="text-slate-400 text-sm mt-1"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            </div>

                            <!-- Botones de Acción Principal -->
                            <div class="mt-6 border-t border-slate-600/50 pt-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                                    <button id="editTaskBtn" class="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Editar Tarea
                                    </button>
                                    <button id="completeTaskBtn" class="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Marcar Completada
                                    </button>
                                </div>

                                <!-- Controles de Asignación -->
                                <div id="assignControls" class="bg-gradient-to-br from-slate-800/60 to-slate-700/40 rounded-xl p-5 border border-slate-600/30 mb-4">
                                    <h5 class="text-lg font-semibold text-slate-200 mb-4 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        Asignar Tarea
                                    </h5>
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                            <select id="assigneeSelector" class="px-4 py-3 bg-slate-700/80 border border-slate-500/50 rounded-xl text-slate-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-colors">
                                                <option value="">Selecciona usuario para asignar</option>
                                            </select>
                                            <div class="relative">
                                                <input id="assigneeInput" type="text" placeholder="buscar por nombre, email..." list="assigneeSuggestions" class="w-full px-4 py-3 bg-slate-700/80 border border-slate-500/50 rounded-xl text-slate-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-colors" />
                                                <datalist id="assigneeSuggestions"></datalist>
                                            </div>
                                        </div>
                                        <input id="assignmentMessage" type="text" placeholder="Mensaje opcional para el usuario..." class="w-full px-4 py-3 bg-slate-700/80 border border-slate-500/50 rounded-xl text-slate-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-colors" maxlength="200" />
                                        <div class="flex flex-wrap gap-3">
                                            <button id="assignTaskBtn" class="flex-1 min-w-[160px] flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                </svg>
                                                Asignar
                                            </button>
                                            <button id="cancelAssignmentBtn" type="button" class="flex-1 min-w-[160px] flex items-center justify-center gap-2 px-6 py-3 bg-slate-600 hover:bg-slate-500 text-slate-100 rounded-xl font-medium transition-all duration-200 hidden">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                                Cancelar asignación
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Controles de Respuesta -->
                                <div id="assignmentResponseControls" class="flex flex-wrap gap-3 justify-center">
                                    <button id="acceptTaskBtn" class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Aceptar
                                    </button>
                                    <button id="rejectTaskBtn" class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-700 hover:to-rose-800 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Rechazar
                                    </button>
                                    <button id="reactivateTaskBtn" class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105 hidden">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Reactivar
                                    </button>
                                </div>
                        </div>

                        <!-- Comentarios -->
                        <div class="bg-gradient-to-br from-slate-700/60 to-slate-800/40 rounded-2xl p-6 border border-slate-600/30 shadow-lg">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-2 h-8 bg-gradient-to-b from-cyan-500 to-blue-600 rounded-full"></div>
                                <h4 class="text-xl font-semibold text-slate-200">Comentarios</h4>
                            </div>

                            <!-- Lista de comentarios -->
                            <div id="commentsList" class="space-y-4 mb-6 max-h-80 overflow-y-auto scrollbar-hide pr-2">
                                <!-- Los comentarios se cargarán aquí -->
                                <div class="text-center text-slate-400 py-8">
                                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <p class="text-sm">No hay comentarios aún</p>
                                </div>
                            </div>

                            <!-- Agregar comentario -->
                            <div class="border-t border-slate-600/50 pt-6">
                                <div class="bg-slate-800/60 rounded-xl p-4 border border-slate-600/30">
                                    <div class="flex items-center gap-2 mb-3">
                                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        <span class="text-sm text-slate-300 font-medium">Agregar comentario principal</span>
                                    </div>
                                    <textarea id="newComment"
                                             placeholder="Escribe tu comentario sobre esta tarea..."
                                             class="w-full px-4 py-3 bg-slate-700/80 border border-slate-500/50 rounded-xl text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50 resize-none transition-colors"
                                             rows="3"></textarea>
                                    <div class="flex justify-between items-center mt-4">
                                        <p class="text-xs text-slate-400">
                                            💡 También puedes responder a comentarios específicos usando el botón "Responder"
                                        </p>
                                        <button id="addCommentBtn" class="flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            Agregar Comentario
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar: Archivos Adjuntos -->
                    <div class="space-y-6">
                        <div class="bg-gradient-to-br from-slate-700/60 to-slate-800/40 rounded-2xl p-6 border border-slate-600/30 shadow-lg">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-2 h-8 bg-gradient-to-b from-green-500 to-emerald-600 rounded-full"></div>
                                <h4 class="text-xl font-semibold text-slate-200">Archivos Adjuntos</h4>
                            </div>

                            <!-- Lista de archivos -->
                            <div id="filesList" class="space-y-3 mb-6 max-h-64 overflow-y-auto scrollbar-hide">
                                <!-- Los archivos se cargarán aquí -->
                                <div class="text-center text-slate-400 py-8">
                                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-sm">No hay archivos adjuntos</p>
                                </div>
                            </div>

                            <!-- Subir archivo -->
                            <div class="border-t border-slate-600/50 pt-6">
                                <input type="file" id="fileInput" class="hidden" multiple>
                                <div class="space-y-3">
                                    <select id="driveDestination" class="w-full px-4 py-3 bg-slate-700/80 border border-slate-500/50 rounded-xl text-slate-100 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-colors">
                                        <option value="personal">Drive personal</option>
                                    </select>
                                    <button id="uploadFileBtn" class="w-full px-4 py-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl transition-all duration-200 border-2 border-dashed border-green-500/30 hover:border-green-400/50 group">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-8 h-8 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <span class="font-medium">Subir Archivo</span>
                                        </div>
                                    </button>
                                    <p class="text-xs text-slate-400 text-center">Puedes elegir entre tu Drive personal o el de tu organización. Si la carpeta Documentos no existe se creará automáticamente.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS para scrollbars invisibles y mejoras visuales -->
<style>
.scrollbar-hide {
    /* Firefox */
    scrollbar-width: none;
    /* Safari and Chrome */
    -ms-overflow-style: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Animaciones suaves */
#taskDetailsModal .transform {
    transition: transform 0.2s ease-out;
}

/* Hover effects */
#taskDetailsModal button:hover {
    transform: translateY(-1px);
}

/* Gradient borders */
.gradient-border {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.5), rgba(147, 51, 234, 0.5));
    padding: 1px;
    border-radius: 1rem;
}

.gradient-border-inner {
    background: rgb(51, 65, 85);
    border-radius: calc(1rem - 1px);
}
</style>

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
        option.label = `${labelPrefix}${user.name || user.email || user.username}`;
        dataList.appendChild(option);
    });

    // Luego agregar usuarios del directorio
    directoryUsers.forEach(user => {
        if (!user) return;
        const option = document.createElement('option');
        option.value = user.email || user.username || '';
        option.label = `🔍 ${user.name || user.email || user.username} (${user.email || user.username})`;
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
    const cancelBtn = document.getElementById('cancelAssignmentBtn');
    const acceptBtn = document.getElementById('acceptTaskBtn');
    const rejectBtn = document.getElementById('rejectTaskBtn');
    const reactivateBtn = document.getElementById('reactivateTaskBtn');

    const isTaskOwner = task.owner_username && window.authUsername && task.owner_username === window.authUsername;
    const isMeetingOwner = task.meeting_owner_username && window.authUsername && task.meeting_owner_username === window.authUsername;
    const isOwner = isTaskOwner || isMeetingOwner; // Es dueño si es dueño de la tarea O de la reunión

    // Verificar si el usuario actual es el asignado - usar assigned_user_id directamente
    const isAssignee = task.assigned_user_id && window.authUserId && String(task.assigned_user_id) === String(window.authUserId);

    // Determinar si hay una asignación real (no legacy "No asignado")
    const hasAssignee = !!(task.assigned_user_id || task.assignment_status || (task.asignado && task.asignado !== 'No asignado'));    if (assignWrapper) {
        // Mostrar controles de asignación solo si es dueño y NO hay asignación
        assignWrapper.classList.toggle('hidden', !(isOwner && !hasAssignee));
    }
    // Deshabilitar controles si no es dueño o ya hay asignación
    const canAssign = isOwner && !hasAssignee;

    if (assigneeSelector) assigneeSelector.disabled = !canAssign;
    if (assigneeInput) {
        assigneeInput.disabled = !canAssign;
        assigneeInput.removeEventListener('input', handleAssigneeSearchInput);
        if (canAssign) {
            populateAssigneeSuggestions([]);
            assigneeInput.addEventListener('input', handleAssigneeSearchInput);
        } else {
            populateAssigneeSuggestions([]);
        }
    }
    if (assignBtn) assignBtn.disabled = !canAssign;
    if (cancelBtn) {
        // Mostrar botón cancelar si es dueño y hay una asignación real
        cancelBtn.classList.toggle('hidden', !(isOwner && hasAssignee));
        cancelBtn.disabled = !(isOwner && hasAssignee);
    }

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

    // Controlar permisos de edición y completar tarea
    const editBtn = document.getElementById('editTaskBtn');
    const completeBtn = document.getElementById('completeTaskBtn');

    // El usuario asignado NO puede editar la tarea completa (solo progreso vía kanban)
    // Solo el dueño puede editar la tarea completa
    const canEdit = isOwner;

    // El usuario asignado puede completar la tarea si ha aceptado la asignación
    // El dueño también puede completar la tarea
    const canComplete = isOwner || (isAssignee && task.assignment_status === 'accepted');

    if (editBtn) {
        editBtn.disabled = !canEdit;
        editBtn.classList.toggle('opacity-50', !canEdit);
        editBtn.classList.toggle('cursor-not-allowed', !canEdit);

        if (!canEdit) {
            editBtn.title = isAssignee ? 'Los usuarios asignados no pueden editar la reunión completa' : 'No tienes permisos para editar esta tarea';
        } else {
            editBtn.title = '';
        }
    }

    if (completeBtn) {
        completeBtn.disabled = !canComplete;
        completeBtn.classList.toggle('opacity-50', !canComplete);
        completeBtn.classList.toggle('cursor-not-allowed', !canComplete);

        if (!canComplete) {
            if (isAssignee && task.assignment_status === 'pending') {
                completeBtn.title = 'Debes aceptar la tarea antes de completarla';
            } else if (!isAssignee && !isOwner) {
                completeBtn.title = 'No tienes permisos para completar esta tarea';
            } else {
                completeBtn.title = 'No puedes completar esta tarea';
            }
        } else {
            completeBtn.title = '';
        }
    }

    // Solo cargar usuarios si es dueño y no hay asignación
    if (assigneeSelector && isOwner && !hasAssignee) {
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
            await setupAssignableControls(data.task);
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

    // Mostrar información del dueño de la reunión
    const meetingOwnerEl = document.getElementById('detailsMeetingOwner');
    if (task.meeting_owner_username) {
        meetingOwnerEl.textContent = `Dueño de la reunión: ${task.meeting_owner_username}`;

        // Agregar indicador visual si el usuario actual es dueño de la reunión
        if (window.authUsername && task.meeting_owner_username === window.authUsername) {
            meetingOwnerEl.innerHTML = `Dueño de la reunión: ${task.meeting_owner_username} <span class="text-blue-300 font-medium">(Tú)</span>`;
        }
    } else {
        meetingOwnerEl.textContent = '';
    }

    // Asignación - Información del usuario asignado
    const assigneeInfo = document.getElementById('detailsTaskAssigneeInfo');
    const noAssignee = document.getElementById('detailsNoAssignee');

    // Determinar si hay una asignación (verificar tanto el objeto como el ID)
    const hasAssignment = task.assigned_user || task.assigned_user_id || (task.asignado && task.asignado !== 'No asignado');

    if (hasAssignment) {
        // Hay usuario asignado - mostrar información
        const assigneeName = (task.assigned_user && task.assigned_user.full_name) ||
                             (task.assigned_user && task.assigned_user.username) ||
                             task.asignado || 'Usuario asignado';
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
    const cancelBtn = document.getElementById('cancelAssignmentBtn');
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
                if (data.success) {
                    if (assigneeInput) assigneeInput.value = '';
                    if (assigneeSelector) assigneeSelector.value = '';
                    if (messageInput) messageInput.value = '';
                    populateAssigneeSuggestions([], []);

                    // Mostrar notificación de éxito
                    const successMsg = message
                        ? 'Solicitud de asignación enviada con tu mensaje personalizado'
                        : 'Solicitud de asignación enviada - El usuario recibirá un email';
                    alert(successMsg);

                    loadTaskDetails(task.id);
                    if (typeof kanbanReload === 'function') kanbanReload();
                } else {
                    alert(data.message || 'No se pudo enviar la asignación');
                }
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

    if (cancelBtn) {
        cancelBtn.onclick = async () => {
            if (!confirm('¿Quieres cancelar la asignación actual y dejar la tarea sin asignar?')) {
                return;
            }

            const originalText = cancelBtn.textContent;
            cancelBtn.disabled = true;
            cancelBtn.textContent = 'Cancelando...';

            try {
                const response = await fetch(new URL(`/api/tasks-laravel/tasks/${task.id}/cancel-assignment`, window.location.origin), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]').content || '')
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert('La tarea se ha liberado y quedó sin asignar.');
                    loadTaskDetails(task.id);
                    if (typeof kanbanReload === 'function') kanbanReload();
                    if (typeof loadAndRender === 'function') loadAndRender();
                } else {
                    alert(data.message || 'No se pudo cancelar la asignación');
                }
            } catch (error) {
                console.error('Error canceling assignment:', error);
                alert('Ocurrió un error al cancelar la asignación');
            } finally {
                cancelBtn.disabled = false;
                cancelBtn.textContent = originalText;
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
                    alert('¡Tarea aceptada! Se ha agregado a tu calendario si tiene fecha y hora definida.');
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
                    alert('Tarea rechazada. La asignación ha sido cancelada.');
                    loadTaskDetails(task.id);
                    if (typeof kanbanReload === 'function') kanbanReload();
                    if (typeof loadAndRender === 'function') loadAndRender();
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
            commentsList.innerHTML = `
                <div class="text-center text-slate-400 py-8">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-sm font-medium">No hay comentarios aún</p>
                    <p class="text-xs text-slate-500 mt-1">Sé el primero en comentar sobre esta tarea</p>
                </div>
            `;
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
    wrapper.className = `bg-gradient-to-br from-slate-700/60 to-slate-800/40 rounded-xl p-4 border border-slate-600/30 shadow-sm`;
    if (depth > 0) {
        wrapper.classList.add('mt-3', 'ml-6', 'border-l-4', 'border-l-cyan-500/50', 'pl-4');
    }

    const author = escapeHtml(comment.user || 'Usuario');
    const createdAt = comment.created_at ? formatDate(comment.created_at) : '';
    const text = escapeHtml(comment.text || '');

    // Generar iniciales del autor
    const initials = author.split(' ').map(word => word.charAt(0)).join('').substring(0, 2).toUpperCase();

    wrapper.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                ${initials}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="text-sm font-semibold text-slate-200">${author}</span>
                        <span class="text-xs text-slate-400 ml-2">${createdAt}</span>
                    </div>
                    <button onclick="toggleReplyForm(${comment.id}, '${author}')" class="text-slate-400 hover:text-cyan-400 text-xs font-medium px-2 py-1 hover:bg-slate-700/50 rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                        Responder
                    </button>
                </div>
                <p class="text-slate-100 text-sm leading-relaxed whitespace-pre-line">${text}</p>

                <!-- Formulario de respuesta (inicialmente oculto) -->
                <div id="replyForm-${comment.id}" class="mt-4 hidden">
                    <div class="bg-slate-800/60 rounded-xl p-4 border border-slate-600/30">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                            </svg>
                            <span class="text-sm text-slate-300 font-medium">Respondiendo a ${author}</span>
                        </div>
                        <textarea id="replyText-${comment.id}"
                                 placeholder="Escribe tu respuesta..."
                                 class="w-full px-3 py-2 bg-slate-700/80 border border-slate-500/50 rounded-lg text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50 resize-none transition-colors"
                                 rows="2"></textarea>
                        <div class="flex justify-end gap-2 mt-3">
                            <button onclick="toggleReplyForm(${comment.id})" class="px-3 py-2 text-slate-400 hover:text-slate-200 text-sm font-medium transition-colors">
                                Cancelar
                            </button>
                            <button onclick="submitReply(${comment.id})" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg text-sm font-medium transition-all duration-200 shadow-lg hover:shadow-xl">
                                Enviar Respuesta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    container.appendChild(wrapper);

    // Renderizar respuestas/children
    if (Array.isArray(comment.children) && comment.children.length > 0) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'mt-3 space-y-3';
        comment.children.forEach(child => renderCommentNode(child, childrenContainer, depth + 1));
        wrapper.appendChild(childrenContainer);
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

// Función para mostrar/ocultar el formulario de respuesta
function toggleReplyForm(commentId, authorName = null) {
    const replyForm = document.getElementById(`replyForm-${commentId}`);
    const textArea = document.getElementById(`replyText-${commentId}`);

    if (replyForm.classList.contains('hidden')) {
        // Ocultar todos los otros formularios de respuesta
        document.querySelectorAll('[id^="replyForm-"]').forEach(form => {
            form.classList.add('hidden');
        });

        // Mostrar este formulario
        replyForm.classList.remove('hidden');
        textArea.focus();

        // Scroll suave hacia el formulario
        setTimeout(() => {
            replyForm.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'nearest'
            });
        }, 100);
    } else {
        // Ocultar formulario
        replyForm.classList.add('hidden');
        textArea.value = '';
    }
}

// Función para enviar respuesta
async function submitReply(parentCommentId) {
    const textArea = document.getElementById(`replyText-${parentCommentId}`);
    const text = textArea.value.trim();

    if (!text) {
        alert('Por favor escribe un mensaje antes de enviar');
        return;
    }

    if (!currentTaskDetailsId) {
        alert('Error: No se pudo identificar la tarea');
        return;
    }

    try {
        const response = await fetch(new URL(`/api/tasks-laravel/tasks/${currentTaskDetailsId}/comments`, window.location.origin), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '')
            },
            body: JSON.stringify({
                text: text,
                parent_id: parentCommentId
            })
        });

        const data = await response.json();

        if (data.success) {
            // Limpiar y ocultar formulario
            textArea.value = '';
            toggleReplyForm(parentCommentId);

            // Recargar comentarios para mostrar la nueva respuesta
            await loadTaskComments(currentTaskDetailsId);

            // Mostrar mensaje de éxito
            showNotification('Respuesta enviada correctamente. Se ha notificado al autor del comentario original.', 'success');
        } else {
            throw new Error(data.message || 'Error al enviar respuesta');
        }
    } catch (error) {
        console.error('Error al enviar respuesta:', error);
        alert('Error al enviar la respuesta: ' + error.message);
    }
}

// Función auxiliar para mostrar notificaciones
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-[5000] px-6 py-3 rounded-xl shadow-2xl transform translate-x-full transition-all duration-300 ease-out`;

    if (type === 'success') {
        notification.classList.add('bg-gradient-to-r', 'from-green-600', 'to-emerald-600', 'text-white');
    } else if (type === 'error') {
        notification.classList.add('bg-gradient-to-r', 'from-red-600', 'to-rose-600', 'text-white');
    } else {
        notification.classList.add('bg-gradient-to-r', 'from-blue-600', 'to-cyan-600', 'text-white');
    }

    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${type === 'success' ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>' :
                    type === 'error' ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>' :
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                }
            </svg>
            <span class="font-medium">${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Animar entrada
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // Auto-remove después de 5 segundos
    setTimeout(() => {
        notification.style.transform = 'translateX(full)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Función auxiliar para escapar HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Cerrar modal al hacer clic fuera
document.getElementById('taskDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTaskDetailsModal();
    }
});
</script>
