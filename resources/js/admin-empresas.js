/**
 * Módulo JavaScript para el Panel de Administración de Empresas
 * Maneja modales, formularios y eventos de las vistas de empresas
 */

class EmpresaAdmin {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupModalHandlers();
        this.setupFormHandlers();
    }

    /**
     * Configurar event listeners generales
     */
    setupEventListeners() {
        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('form-modal')) {
                this.hideModal(e.target.id);
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.form-modal[style*="block"]');
                if (activeModal) {
                    this.hideModal(activeModal.id);
                }
            }
        });
    }

    /**
     * Configurar manejadores de modales
     */
    setupModalHandlers() {
        // Botón para mostrar modal de nueva empresa
        const newEmpresaBtn = document.querySelector('[onclick*="newEmpresaModal"]');
        if (newEmpresaBtn) {
            newEmpresaBtn.removeAttribute('onclick');
            newEmpresaBtn.addEventListener('click', () => this.showModal('newEmpresaModal'));
        }

        // Botón para mostrar modal de agregar integrante
        const addIntegranteBtn = document.querySelector('[onclick*="addIntegranteModal"]');
        if (addIntegranteBtn) {
            addIntegranteBtn.removeAttribute('onclick');
            addIntegranteBtn.addEventListener('click', () => this.showModal('addIntegranteModal'));
        }

        // Botones de cancelar en modales
        document.querySelectorAll('[onclick*="hideModal"]').forEach(btn => {
            const modalId = btn.getAttribute('onclick').match(/hideModal\('([^']+)'\)/)?.[1];
            if (modalId) {
                btn.removeAttribute('onclick');
                btn.addEventListener('click', () => this.hideModal(modalId));
            }
        });
    }

    /**
     * Configurar manejadores de formularios
     */
    setupFormHandlers() {
        // Formulario de agregar integrante con procesamiento de permisos
        const addIntegranteForm = document.querySelector('#addIntegranteModal form');
        if (addIntegranteForm) {
            addIntegranteForm.addEventListener('submit', (e) => {
                this.processPermissionsForm(e);
            });
        }

        // Botones de edición de empresa
        document.querySelectorAll('[onclick*="editEmpresa"]').forEach(btn => {
            const onclickAttr = btn.getAttribute('onclick');
            const match = onclickAttr.match(/editEmpresa\((\d+),\s*'([^']*)'\)/);

            if (match) {
                const [, id, nombre] = match;
                btn.removeAttribute('onclick');
                btn.addEventListener('click', () => {
                    this.editEmpresa(parseInt(id), nombre);
                });
            }
        });

        // Formularios de confirmación de eliminación
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            const message = form.getAttribute('onsubmit').match(/confirm\('([^']+)'\)/)?.[1];
            if (message) {
                form.removeAttribute('onsubmit');
                form.addEventListener('submit', (e) => {
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            }
        });
    }

    /**
     * Mostrar modal
     * @param {string} modalId - ID del modal a mostrar
     */
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
            // Focus en el primer input del modal
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }

    /**
     * Ocultar modal
     * @param {string} modalId - ID del modal a ocultar
     */
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300); // Tiempo de la animación
            // Limpiar formulario si existe
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
        }
    }

    /**
     * Editar empresa
     * @param {number} id - ID de la empresa
     * @param {string} nombre - Nombre de la empresa
     */
    editEmpresa(id, nombre) {
        const modal = document.getElementById('editEmpresaModal');
        if (!modal) return;

        // Llenar los campos del formulario
        const form = modal.querySelector('form');
        if (form) {
            form.action = `/admin/empresas/${id}`;

            const nombreInput = form.querySelector('#edit_nombre_empresa');
            if (nombreInput) nombreInput.value = nombre;
        }

        this.showModal('editEmpresaModal');
    }

    /**
     * Procesar formulario de permisos (convertir textarea a array)
     * @param {Event} e - Evento del formulario
     */
    processPermissionsForm(e) {
        const permisosTextarea = e.target.querySelector('#permisos');
        if (!permisosTextarea) return;

        const permisos = permisosTextarea.value
            .split('\n')
            .map(p => p.trim())
            .filter(p => p.length > 0);

        // Limpiar campos hidden existentes
        e.target.querySelectorAll('input[name^="permisos["]').forEach(input => {
            input.remove();
        });

        // Crear campos hidden para los permisos
        permisos.forEach((permiso, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `permisos[${index}]`;
            input.value = permiso;
            e.target.appendChild(input);
        });
    }

    /**
     * Validar formulario antes del envío
     * @param {HTMLFormElement} form - Formulario a validar
     * @returns {boolean} - Si el formulario es válido
     */
    validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#ef4444';
                isValid = false;
            } else {
                field.style.borderColor = 'rgba(255, 255, 255, 0.1)';
            }
        });

        return isValid;
    }

    /**
     * Mostrar notificación temporal
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - Tipo de notificación (success, error)
     */
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.textContent = message;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.maxWidth = '400px';

        document.body.appendChild(notification);

        // Remover después de 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * Confirmar acción destructiva
     * @param {string} message - Mensaje de confirmación
     * @returns {boolean} - Si el usuario confirmó
     */
    confirmAction(message) {
        return confirm(message);
    }
}

// Variables globales para gestión de roles
let selectedUser = null;
let searchTimeout = null;

/**
 * Buscar usuarios por nombre o email
 * @param {string} query - Texto de búsqueda
 */
window.searchUsers = function(query) {
    // Limpiar timeout anterior
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    const resultsContainer = document.getElementById('user_results');

    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }

    // Debounce search
    searchTimeout = setTimeout(() => {
        resultsContainer.innerHTML = '<div style="padding: 16px; text-align: center; color: rgba(255,255,255,0.7);"><span class="loading-spinner"></span> Buscando usuarios...</div>';
        resultsContainer.style.display = 'block';

        fetch(`/admin/search-users?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users.length > 0) {
                    displayUserResults(data.users);
                } else {
                    resultsContainer.innerHTML = '<div style="padding: 16px; text-align: center; color: rgba(255,255,255,0.5);">No se encontraron usuarios</div>';
                }
            })
            .catch(error => {
                console.error('Error searching users:', error);
                resultsContainer.innerHTML = '<div style="padding: 16px; text-align: center; color: #ef4444;">Error al buscar usuarios</div>';
            });
    }, 300);
};

/**
 * Mostrar resultados de búsqueda de usuarios
 * @param {Array} users - Lista de usuarios encontrados
 */
function displayUserResults(users) {
    const resultsContainer = document.getElementById('user_results');

    const html = users.map(user => `
        <div class="user-result-item" onclick="selectUser('${user.id}', '${user.full_name}', '${user.email}', '${user.roles}', '${user.plan}', '${user.plan_expires_at || ''}', '${user.plan_status}', ${user.is_role_protected})">
            <div class="user-result-name">${user.full_name}</div>
            <div class="user-result-email">${user.email}</div>
            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                <span class="user-result-role ${user.roles}">${user.roles.toUpperCase()}</span>
                <span class="plan-status ${user.plan_status}">${user.plan_status === 'active' ? 'Activo' : 'Expirado'}</span>
                ${user.is_role_protected ? '<span style="font-size: 10px; color: #f59e0b;">🔒 Protegido</span>' : ''}
            </div>
        </div>
    `).join('');

    resultsContainer.innerHTML = html;
}

/**
 * Seleccionar un usuario para cambio de rol
 */
window.selectUser = function(id, name, email, currentRole, plan, planExpires, planStatus, isProtected) {
    selectedUser = {
        id: id,
        name: name,
        email: email,
        currentRole: currentRole,
        plan: plan,
        planExpires: planExpires,
        planStatus: planStatus,
        isProtected: isProtected
    };

    // Ocultar resultados de búsqueda
    document.getElementById('user_results').style.display = 'none';

    // Mostrar selector de rol
    document.getElementById('role_selector').style.display = 'block';
    document.getElementById('role_actions').style.display = 'block';

    // Mostrar información del usuario seleccionado
    const userInfoContainer = document.getElementById('selected_user_info');
    const userInfoContent = document.getElementById('user_info_content');

    const expiresText = planExpires ? `Expira: ${planExpires}` : 'Sin expiración';
    const statusText = planStatus === 'active' ? '✅ Activo' : '❌ Expirado';

    userInfoContent.innerHTML = `
        <div class="user-info-card">
            <div class="user-info-row">
                <span class="user-info-label">Nombre</span>
                <span class="user-info-value">${name}</span>
            </div>
            <div class="user-info-row">
                <span class="user-info-label">Email</span>
                <span class="user-info-value">${email}</span>
            </div>
            <div class="user-info-row">
                <span class="user-info-label">Rol Actual</span>
                <span class="user-info-value">
                    <span class="user-result-role ${currentRole}">${currentRole.toUpperCase()}</span>
                    ${isProtected ? ' 🔒 Protegido' : ''}
                </span>
            </div>
            <div class="user-info-row">
                <span class="user-info-label">Plan</span>
                <span class="user-info-value">${plan}</span>
            </div>
            <div class="user-info-row">
                <span class="user-info-label">Estado del Plan</span>
                <span class="user-info-value">
                    <span class="plan-status ${planStatus}">${statusText}</span>
                </span>
            </div>
            ${planExpires ? `
            <div class="user-info-row">
                <span class="user-info-label">Expiración</span>
                <span class="user-info-value">${expiresText}</span>
            </div>
            ` : ''}
        </div>
    `;

    userInfoContainer.style.display = 'block';

    // Limpiar campo de búsqueda
    document.getElementById('search_user').value = `${name} (${email})`;
};

/**
 * Limpiar selección de usuario
 */
window.clearUserSelection = function() {
    selectedUser = null;

    // Ocultar elementos
    document.getElementById('user_results').style.display = 'none';
    document.getElementById('role_selector').style.display = 'none';
    document.getElementById('role_actions').style.display = 'none';
    document.getElementById('selected_user_info').style.display = 'none';

    // Limpiar campos
    document.getElementById('search_user').value = '';
    document.getElementById('new_role').value = '';
};

/**
 * Actualizar rol de usuario seleccionado
 */
window.updateUserRole = function() {
    if (!selectedUser) {
        alert('No hay usuario seleccionado');
        return;
    }

    const newRole = document.getElementById('new_role').value;
    if (!newRole) {
        alert('Selecciona un nuevo rol');
        return;
    }

    if (newRole === selectedUser.currentRole) {
        alert('El usuario ya tiene este rol');
        return;
    }

    // Confirmar cambio
    const roleNames = {
        'free': 'Free',
        'basic': 'Basic (1 mes)',
        'business': 'Business (1 mes)',
        'enterprise': 'Enterprise (1 mes)',
        'founder': 'Founder (sin expiración)',
        'bni': 'BNI (sin expiración)',
        'developer': 'Developer (sin expiración)',
        'superadmin': 'Superadmin (sin expiración)'
    };

    const confirmMessage = `¿Estás seguro de cambiar el rol de "${selectedUser.name}" de "${selectedUser.currentRole}" a "${roleNames[newRole]}"?`;

    if (!confirm(confirmMessage)) {
        return;
    }

    // Mostrar loading
    const updateBtn = document.getElementById('update_role_btn');
    const originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<span class="loading-spinner"></span> Actualizando...';
    updateBtn.disabled = true;

    // Enviar petición
    fetch('/admin/update-user-role', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            user_id: selectedUser.id,
            new_role: newRole
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito
            showSuccessMessage(data.message);

            // Limpiar selección
            clearUserSelection();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error updating user role:', error);
        alert('Error al actualizar el rol del usuario');
    })
    .finally(() => {
        // Restaurar botón
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    });
};

/**
 * Mostrar mensaje de éxito
 * @param {string} message - Mensaje a mostrar
 */
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success';
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.maxWidth = '400px';

    document.body.appendChild(alertDiv);

    // Remover después de 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new EmpresaAdmin();
});

// Exportar para uso global si es necesario
window.EmpresaAdmin = EmpresaAdmin;
