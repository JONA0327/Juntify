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

