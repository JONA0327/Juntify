// ===== VARIABLES GLOBALES =====
let containers = [];
let currentContainer = null;
let isEditMode = false;

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializeContainers();
    setupEventListeners();
});

function initializeContainers() {
    loadContainers();
}

function setupEventListeners() {
    // Botones de crear contenedor
    document.getElementById('create-container-btn')?.addEventListener('click', openCreateModal);
    document.getElementById('create-first-container-btn')?.addEventListener('click', openCreateModal);

    // Modal
    document.getElementById('cancel-modal-btn')?.addEventListener('click', closeModal);
    document.getElementById('save-container-btn')?.addEventListener('click', saveContainer);
    document.getElementById('container-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        saveContainer();
    });

    // Búsqueda
    document.getElementById('search-containers')?.addEventListener('input', filterContainers);

    // Contador de caracteres en descripción
    const descriptionField = document.getElementById('container-description');
    if (descriptionField) {
        descriptionField.addEventListener('input', updateCharacterCount);
    }

    // Cerrar modal al hacer clic fuera
    document.getElementById('container-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('container-modal').classList.contains('hidden')) {
            closeModal();
        }
    });
}

// ===== CARGA DE DATOS =====
async function loadContainers() {
    try {
        showLoadingState();

        const response = await fetch('/api/content-containers', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            containers = data.containers;
            renderContainers();
        } else {
            throw new Error(data.message || 'Error al cargar contenedores');
        }

    } catch (error) {
        console.error('Error loading containers:', error);
        showNotification('Error al cargar contenedores: ' + error.message, 'error');
        showEmptyState();
    }
}

// ===== RENDERIZADO =====
function renderContainers() {
    const containersList = document.getElementById('containers-list');
    const loadingState = document.getElementById('loading-state');
    const emptyState = document.getElementById('empty-state');

    if (containers.length === 0) {
        showEmptyState();
        return;
    }

    loadingState.classList.add('hidden');
    emptyState.classList.add('hidden');
    containersList.classList.remove('hidden');

    containersList.innerHTML = containers.map(container => createContainerCard(container)).join('');

    // Agregar event listeners a los botones de cada contenedor
    containers.forEach(container => {
        const editBtn = document.getElementById(`edit-container-${container.id}`);
        const deleteBtn = document.getElementById(`delete-container-${container.id}`);

        if (editBtn) {
            editBtn.addEventListener('click', () => openEditModal(container));
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => deleteContainer(container.id));
        }
    });
}

function createContainerCard(container) {
    return `
        <div class="bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl p-6 hover:bg-slate-800/70 hover:border-slate-600/50 transition-all duration-200 shadow-lg shadow-black/10 group">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <!-- Header del Contenedor -->
                    <div class="flex items-start gap-4 mb-4">
                        <div class="bg-gradient-to-br from-yellow-400/20 to-amber-400/20 rounded-xl p-3 border border-yellow-400/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-xl font-semibold text-white mb-2 truncate">${escapeHtml(container.name)}</h3>
                            ${container.description ? `<p class="text-slate-400 text-sm line-clamp-2">${escapeHtml(container.description)}</p>` : ''}
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <div class="flex items-center gap-6 text-sm text-slate-400 mb-4">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Creado: ${container.created_at}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 7a2 2 0 012-2h10a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>${container.meetings_count} reuniones</span>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex items-center gap-2 ml-4">
                    <button
                        id="edit-container-${container.id}"
                        class="p-2 text-slate-400 hover:text-yellow-400 hover:bg-yellow-400/10 rounded-lg transition-all duration-200"
                        title="Editar contenedor"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        id="delete-container-${container.id}"
                        class="p-2 text-slate-400 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-all duration-200"
                        title="Eliminar contenedor"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Badge de Estado -->
            <div class="flex justify-between items-end">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-400/10 text-green-400 border border-green-400/20">
                    Activo
                </span>

                <!-- Botón Ver Contenido -->
                <button class="text-yellow-400 hover:text-yellow-300 text-sm font-medium hover:underline transition-all duration-200">
                    Ver contenido →
                </button>
            </div>
        </div>
    `;
}

function showLoadingState() {
    document.getElementById('loading-state').classList.remove('hidden');
    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('containers-list').classList.add('hidden');
}

function showEmptyState() {
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('empty-state').classList.remove('hidden');
    document.getElementById('containers-list').classList.add('hidden');
}

// ===== MODAL =====
function openCreateModal() {
    isEditMode = false;
    currentContainer = null;

    document.getElementById('modal-title').textContent = 'Crear Contenedor';
    document.getElementById('save-btn-text').textContent = 'Guardar';

    // Limpiar formulario
    document.getElementById('container-form').reset();
    clearErrors();
    updateCharacterCount();

    document.getElementById('container-modal').classList.remove('hidden');
    document.getElementById('container-name').focus();
}

function openEditModal(container) {
    isEditMode = true;
    currentContainer = container;

    document.getElementById('modal-title').textContent = 'Editar Contenedor';
    document.getElementById('save-btn-text').textContent = 'Actualizar';

    // Llenar formulario
    document.getElementById('container-name').value = container.name;
    document.getElementById('container-description').value = container.description || '';
    clearErrors();
    updateCharacterCount();

    document.getElementById('container-modal').classList.remove('hidden');
    document.getElementById('container-name').focus();
}

function closeModal() {
    document.getElementById('container-modal').classList.add('hidden');
    currentContainer = null;
    isEditMode = false;
    clearErrors();
}

// ===== CRUD OPERATIONS =====
async function saveContainer() {
    const saveBtn = document.getElementById('save-container-btn');
    const saveBtnText = document.getElementById('save-btn-text');
    const saveBtnLoading = document.getElementById('save-btn-loading');

    // Obtener datos del formulario
    const formData = {
        name: document.getElementById('container-name').value.trim(),
        description: document.getElementById('container-description').value.trim() || null
    };

    // Validar
    if (!formData.name) {
        showFieldError('name-error', 'El nombre es requerido');
        return;
    }

    try {
        // UI Loading
        saveBtn.disabled = true;
        saveBtnText.classList.add('hidden');
        saveBtnLoading.classList.remove('hidden');
        clearErrors();

        const url = isEditMode ? `/api/content-containers/${currentContainer.id}` : '/api/content-containers';
        const method = isEditMode ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            closeModal();
            loadContainers(); // Recargar lista
        } else {
            if (data.errors) {
                // Mostrar errores de validación
                Object.keys(data.errors).forEach(field => {
                    showFieldError(`${field}-error`, data.errors[field][0]);
                });
            } else {
                throw new Error(data.message);
            }
        }

    } catch (error) {
        console.error('Error saving container:', error);
        showNotification('Error al guardar: ' + error.message, 'error');

    } finally {
        // Reset UI
        saveBtn.disabled = false;
        saveBtnText.classList.remove('hidden');
        saveBtnLoading.classList.add('hidden');
    }
}

async function deleteContainer(containerId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este contenedor? Esta acción no se puede deshacer.')) {
        return;
    }

    try {
        const response = await fetch(`/api/content-containers/${containerId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            loadContainers(); // Recargar lista
        } else {
            throw new Error(data.message);
        }

    } catch (error) {
        console.error('Error deleting container:', error);
        showNotification('Error al eliminar: ' + error.message, 'error');
    }
}

// ===== BÚSQUEDA Y FILTROS =====
function filterContainers() {
    const searchTerm = document.getElementById('search-containers').value.toLowerCase();

    if (!searchTerm) {
        renderContainers();
        return;
    }

    const filteredContainers = containers.filter(container =>
        container.name.toLowerCase().includes(searchTerm) ||
        (container.description && container.description.toLowerCase().includes(searchTerm))
    );

    // Renderizar resultados filtrados
    const containersList = document.getElementById('containers-list');

    if (filteredContainers.length === 0) {
        containersList.innerHTML = `
            <div class="text-center py-12">
                <div class="text-slate-400 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p>No se encontraron contenedores que coincidan con "${searchTerm}"</p>
                </div>
            </div>
        `;
        return;
    }

    containersList.innerHTML = filteredContainers.map(container => createContainerCard(container)).join('');

    // Re-agregar event listeners
    filteredContainers.forEach(container => {
        const editBtn = document.getElementById(`edit-container-${container.id}`);
        const deleteBtn = document.getElementById(`delete-container-${container.id}`);

        if (editBtn) {
            editBtn.addEventListener('click', () => openEditModal(container));
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => deleteContainer(container.id));
        }
    });
}

// ===== UTILIDADES =====
function updateCharacterCount() {
    const textarea = document.getElementById('container-description');
    const counter = document.getElementById('description-count');

    if (textarea && counter) {
        counter.textContent = textarea.value.length;
    }
}

function showFieldError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }
}

function clearErrors() {
    const errorElements = document.querySelectorAll('[id$="-error"]');
    errorElements.forEach(element => {
        element.classList.add('hidden');
        element.textContent = '';
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function showNotification(message, type = 'info') {
    const container = document.getElementById('notification-container');
    if (!container) return;

    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';

    notification.className = `${bgColor} text-white px-6 py-4 rounded-xl shadow-lg transform transition-all duration-300 translate-x-full opacity-0`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <span class="flex-1">${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="text-white/80 hover:text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `;

    container.appendChild(notification);

    // Animar entrada
    setTimeout(() => {
        notification.classList.remove('translate-x-full', 'opacity-0');
    }, 100);

    // Auto remove después de 5 segundos
    setTimeout(() => {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}
