// ===============================================
// VARIABLES Y CONFIGURACIÓN GLOBAL
// ===============================================
// Nota: usar 'var' y fallbacks a window.* para evitar errores si el script se carga dos veces
var currentMeetings = window.currentMeetings || [];
var isEditingTitle = typeof window.isEditingTitle !== 'undefined' ? window.isEditingTitle : false;
var currentModalMeeting = typeof window.currentModalMeeting !== 'undefined' ? window.currentModalMeeting : null;
// Variables para manejo de audio segmentado en el modal
var meetingSegments = window.meetingSegments || [];
var meetingAudioPlayer = typeof window.meetingAudioPlayer !== 'undefined' ? window.meetingAudioPlayer : null;
var currentSegmentIndex = typeof window.currentSegmentIndex !== 'undefined' ? window.currentSegmentIndex : null;
var segmentEndHandler = typeof window.segmentEndHandler !== 'undefined' ? window.segmentEndHandler : null;
var selectedSegmentIndex = typeof window.selectedSegmentIndex !== 'undefined' ? window.selectedSegmentIndex : null;
var segmentsModified = typeof window.segmentsModified !== 'undefined' ? window.segmentsModified : false;

// Almacenamiento temporal para datos de reuniones descargadas
window.downloadMeetingData = window.downloadMeetingData || {};
window.lastOpenedDownloadMeetingId = window.lastOpenedDownloadMeetingId || null;

// ===============================================
// INICIALIZACIÓN
// ===============================================
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    initializeFadeAnimations();
    initializeContainers(); // Inicializar funcionalidad de contenedores
    initializeDownloadModal();
    const defaultTab = document.querySelector('button[data-target="my-meetings"]');
    if (defaultTab) {
        setActiveTab(defaultTab);
    }
});

// ===============================================
// CONFIGURACIÓN DE EVENT LISTENERS
// ===============================================
function setupEventListeners() {
    // Listener para cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Cerrar el modal de compartir si está abierto; de lo contrario, el modal principal
            const shareModalEl = document.getElementById('shareModal');
            if (shareModalEl && !shareModalEl.classList.contains('hidden')) {
                closeShareModal();
            } else {
                closeMeetingModal();
            }
        }
    });

    // Listener para búsqueda
    const searchInput = document.querySelector('input[placeholder="Buscar en reuniones..."]');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }

    // Listeners para pestañas
    const tabButtons = document.querySelectorAll('.tab-transition');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => setActiveTab(btn));
    });

    // Event listeners para contactos
    const addContactBtn = document.getElementById('add-contact-btn');
    if (addContactBtn) {
        addContactBtn.addEventListener('click', () => openAddContactModal());
    }

    const addContactForm = document.getElementById('add-contact-form');
    if (addContactForm) {
        addContactForm.addEventListener('submit', sendContactRequest);
    }

    const closeModalBtn = document.getElementById('close-modal-btn');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeAddContactModal);
    }

    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeAddContactModal);
    }

    const modal = document.getElementById('add-contact-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAddContactModal();
            }
        });
    }

    const userSearchInput = document.getElementById('user-search-input');
    if (userSearchInput) {
        userSearchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchUser(e.target.value);
            }, 300);
        });
    }

    const contactSearchInput = document.getElementById('contact-search');
    if (contactSearchInput) {
        contactSearchInput.addEventListener('input', (e) => {
            filterContacts(e.target.value);
        });
    }
}

function setActiveTab(button) {
    const targetId = button.dataset.target;

    // Actualizar clases activas en botones
    document.querySelectorAll('.tab-transition').forEach(btn => {
        btn.classList.remove('bg-slate-700/50');
    });
    button.classList.add('bg-slate-700/50');

    // Mostrar pestaña objetivo
    const containers = document.querySelectorAll('#meetings-container > div');
    containers.forEach(c => c.classList.add('hidden'));
    const target = document.getElementById(targetId);
    if (target) {
        target.classList.remove('hidden');
    }

    // Cargar datos correspondientes
    if (targetId === 'my-meetings') {
        loadMyMeetings();
    } else if (targetId === 'shared-meetings') {
        loadSharedMeetings();
        loadOutgoingSharedMeetings();
    } else if (targetId === 'containers') {
        loadContainers();
    } else if (targetId === 'contacts') {
        loadContacts();
    }
}

// ===============================================
// ANIMACIONES DE FADE
// ===============================================
function initializeFadeAnimations() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// ===============================================
// CARGA DE REUNIONES Y CONTENEDORES
// ===============================================
async function loadMyMeetings() {
    const container = document.getElementById('my-meetings');
    try {
        showLoadingState(container);

        const response = await fetch('/api/meetings', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar reuniones');
        }

        const data = await response.json();

        if (data.success) {
            const meetings = [
                ...(Array.isArray(data.meetings) ? data.meetings : []),
                ...(Array.isArray(data.legacy_meetings) ? data.legacy_meetings : []),
            ];
            currentMeetings = meetings;
            renderMeetings(currentMeetings, '#my-meetings', 'No tienes reuniones');
        } else {
            showErrorState(container, data.message || 'Error al cargar reuniones', loadMyMeetings);
        }

        await loadPendingMeetingsStatus();

    } catch (error) {
        console.error('Error loading meetings:', error);
        showErrorState(container, 'Error de conexión al cargar reuniones', loadMyMeetings);
    }
}

async function loadSharedMeetings() {
    const container = document.getElementById('incoming-shared-wrapper');
    try {
        showLoadingState(container);

        const response = await fetch('/api/shared-meetings/v2', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar reuniones compartidas');
        }

        const data = await response.json();

        if (data.success) {
            renderMeetings(data.meetings, '#incoming-shared-wrapper', 'No hay reuniones compartidas', createSharedMeetingCard);
        } else {
            showErrorState(container, data.message || 'Error al cargar reuniones compartidas', loadSharedMeetings);
        }

    } catch (error) {
        console.error('Error loading shared meetings:', error);
        showErrorState(container, 'Error de conexión al cargar reuniones compartidas', loadSharedMeetings);
    }
}

// ==============================
// OUTGOING (Shares I created)
// ==============================
async function loadOutgoingSharedMeetings() {
    const container = document.getElementById('outgoing-shared-wrapper');
    if (!container) return;
    try {
        showLoadingState(container);
        const response = await fetch('/api/shared-meetings/outgoing', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error al cargar compartidas por mí');
        const data = await response.json();
        if (data.success) {
            renderOutgoingShares(data.shares || [], container);
        } else {
            showErrorState(container, data.message || 'Error al cargar compartidas por mí', loadOutgoingSharedMeetings);
        }
    } catch (e) {
        console.error('Error loadOutgoingSharedMeetings', e);
        showErrorState(container, 'Error de conexión', loadOutgoingSharedMeetings);
    }
}

function renderOutgoingShares(shares, container) {
    if (!Array.isArray(shares) || shares.length === 0) {
        container.innerHTML = '<div class="loading-card"><p>No has compartido reuniones</p></div>';
        return;
    }
    const grid = document.createElement('div');
    grid.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6';
    shares.forEach(share => {
        const card = document.createElement('div');
        card.className = 'meeting-card';
        card.innerHTML = `
            <div class="meeting-card-header">
                <div class="meeting-header-top">
                    <div class="meeting-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="meeting-actions">
                        <button type="button" class="icon-btn delete-btn" data-share-id="${share.id}" title="Revocar acceso" aria-label="Revocar acceso">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="meeting-content">
                <h3 class="meeting-title">${escapeHtml(share.title)}</h3>
                <p class="meeting-date">Compartida con: ${escapeHtml(share.shared_with?.name || 'Usuario')}</p>
                <p class="text-xs text-slate-400 mt-1">Estado: <span class="${share.status === 'accepted' ? 'text-green-400' : 'text-yellow-400'}">${share.status}</span></p>
            </div>
        `;
        grid.appendChild(card);
    });
    container.innerHTML = '';
    container.appendChild(grid);

    // Attach listeners for revoke
    container.querySelectorAll('button.delete-btn[data-share-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-share-id');
            confirmRevokeShare(id);
        });
    });
}

async function confirmRevokeShare(id) {
    if (!window.confirm('¿Revocar acceso a esta reunión? El usuario ya no la verá.')) return;
    try {
        const response = await fetch(`/api/shared-meetings/outgoing/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error revocando');
        const data = await response.json();
        if (data.success) {
            loadOutgoingSharedMeetings();
        } else {
            alert(data.message || 'No se pudo revocar');
        }
    } catch (e) {
        console.error('Error revoke share', e);
        alert('Error al revocar acceso');
    }
}

// ===============================================
// CONTACTOS
// ===============================================
let searchTimeout = null;
let selectedUser = null;

async function loadContacts() {
    const list = document.getElementById('contacts-list');
    const userList = document.getElementById('organization-users-list');
    const organizationSection = document.getElementById('organization-section');
    const organizationTitle = document.getElementById('organization-section-title');

    if (list) {
        list.innerHTML = '<div class="loading-state flex flex-col items-center justify-center py-8"><div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mb-3"></div><p class="text-slate-400 text-center">Cargando contactos...</p></div>';
    }

    try {
        const response = await fetch('/api/contacts', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error al cargar contactos');
        const data = await response.json();

        // Ocultar o mostrar la sección de organización basado en la respuesta
        if (organizationSection) {
            if (data.show_organization_section) {
                organizationSection.style.display = 'block';

                // Actualizar el título dinámicamente
                if (organizationTitle) {
                    if (data.has_organization && data.has_groups) {
                        organizationTitle.textContent = 'Usuarios de mi organización y grupos';
                    } else if (data.has_organization) {
                        organizationTitle.textContent = 'Usuarios de mi organización';
                    } else if (data.has_groups) {
                        organizationTitle.textContent = 'Usuarios de mis grupos';
                    } else {
                        organizationTitle.textContent = 'Otros usuarios';
                    }
                }
            } else {
                organizationSection.style.display = 'none';
            }
        }

        renderContacts(data.contacts || [], data.users || []);

        // Después de cargar los contactos, verificar mensajes no leídos
        await checkUnreadMessagesForContacts();
    } catch (error) {
        console.error('Error loading contacts:', error);
        if (list) list.innerHTML = '<div class="text-center py-8 text-red-400">Error al cargar contactos</div>';
        // En caso de error, ocultar la sección de organización
        if (organizationSection) {
            organizationSection.style.display = 'none';
        }
    }
    await loadContactRequests();
}

function renderContacts(contacts, users) {
    const list = document.getElementById('contacts-list');
    const userList = document.getElementById('organization-users-list');
    const countElement = document.getElementById('contacts-count');

    // Actualizar contador
    if (countElement) {
        countElement.textContent = `${contacts.length} contacto${contacts.length !== 1 ? 's' : ''}`;
    }

    // Renderizar contactos
    if (list) {
        list.innerHTML = '';
        if (!contacts.length) {
            list.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="text-slate-400">No tienes contactos aún</p>
                    <p class="text-slate-500 text-sm mt-1">Añade tu primer contacto para empezar</p>
                </div>
            `;
        } else {
            for (const contact of contacts) {
                const contactElement = document.createElement('div');
                contactElement.className = 'contact-card';
                contactElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold">
                                ${contact.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="text-slate-200 font-medium">${contact.name}</h4>
                                <p class="text-slate-400 text-sm">${contact.email}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="startChat('${contact.id}')"
                                    class="relative p-2 text-blue-400 hover:text-blue-300 hover:bg-blue-400/10 rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/20 transform hover:scale-105"
                                    title="Iniciar chat"
                                    id="chat-btn-${contact.id}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                                </svg>
                                <div id="unread-indicator-${contact.id}" class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-slate-800 hidden"></div>
                            </button>
                            <button onclick="deleteContact('${contact.contact_record_id}')"
                                    class="p-2 text-red-400 hover:text-red-300 hover:bg-red-400/10 rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-red-500/20 transform hover:scale-105"
                                    title="Eliminar contacto">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
                list.appendChild(contactElement);
            }
        }
    }

    // Renderizar usuarios de la organización
    if (userList) {
        userList.innerHTML = '';
        if (!users.length) {
            userList.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <p class="text-slate-400">No hay otros usuarios en tu organización</p>
                </div>
            `;
        } else {
            for (const user of users) {
                const userElement = document.createElement('div');
                userElement.className = 'contact-card';

                // Determinar el color de la etiqueta del grupo
                const getGroupColor = (groupName) => {
                    if (!groupName || groupName === 'Sin grupo') {
                        return 'bg-gray-500/20 text-gray-300';
                    }
                    // Colores diferentes para grupos
                    const colors = {
                        'DEVS': 'bg-blue-500/20 text-blue-300',
                        'ADMIN': 'bg-red-500/20 text-red-300',
                        'MARKETING': 'bg-green-500/20 text-green-300',
                        'VENTAS': 'bg-yellow-500/20 text-yellow-300',
                        'SOPORTE': 'bg-purple-500/20 text-purple-300'
                    };
                    return colors[groupName.toUpperCase()] || 'bg-indigo-500/20 text-indigo-300';
                };

                // Determinar el color del rol
                const getRoleColor = (role) => {
                    const roleColors = {
                        'administrador': 'bg-red-600/20 text-red-400',
                        'colaborador': 'bg-blue-600/20 text-blue-400',
                        'invitado': 'bg-gray-600/20 text-gray-400'
                    };
                    return roleColors[role] || 'bg-gray-500/20 text-gray-300';
                };

                userElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold">
                                ${user.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="text-slate-200 font-medium">${user.name}</h4>
                                <p class="text-slate-400 text-sm">${user.email}</p>
                                <div class="flex gap-2 mt-1">
                                    <span class="inline-block ${getGroupColor(user.group_name)} text-xs px-2 py-1 rounded-full">
                                        📂 ${user.group_name || 'Sin grupo'}
                                    </span>
                                    ${user.group_role ? `
                                        <span class="inline-block ${getRoleColor(user.group_role)} text-xs px-2 py-1 rounded-full">
                                            👤 ${user.group_role}
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        <button onclick="openAddContactModal('${user.email}')"
                                class="px-3 py-1 text-yellow-400 hover:text-yellow-300 hover:bg-yellow-400/10 rounded-lg transition-all text-sm font-medium">
                            Añadir
                        </button>
                    </div>
                `;
                userList.appendChild(userElement);
            }
        }
    }
}

// Función para verificar mensajes no leídos para todos los contactos
async function checkUnreadMessagesForContacts() {
    const contactElements = document.querySelectorAll('[id^="chat-btn-"]');

    for (const element of contactElements) {
        const contactId = element.id.replace('chat-btn-', '');
        try {
            const response = await fetch('/api/chats/unread-count', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ contact_id: contactId })
            });

            if (response.ok) {
                const data = await response.json();
                const indicator = document.getElementById(`unread-indicator-${contactId}`);

                if (indicator) {
                    if (data.has_unread) {
                        indicator.classList.remove('hidden');
                    } else {
                        indicator.classList.add('hidden');
                    }
                }
            }
        } catch (error) {
            console.error(`Error checking unread messages for contact ${contactId}:`, error);
        }
    }
}

async function loadContactRequests() {
    const receivedList = document.getElementById('received-requests-list');
    const sentList = document.getElementById('sent-requests-list');

    if (receivedList) receivedList.innerHTML = '<p class="text-slate-400">Cargando...</p>';
    if (sentList) sentList.innerHTML = '<p class="text-slate-400">Cargando...</p>';

    try {
        const response = await fetch('/api/contacts/requests', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error al cargar solicitudes');
        const data = await response.json();
        renderContactRequests(data.received || [], data.sent || []);
    } catch (error) {
        console.error('Error loading contact requests:', error);
        if (receivedList) receivedList.innerHTML = '<p class="text-red-400">Error al cargar solicitudes</p>';
        if (sentList) sentList.innerHTML = '<p class="text-red-400">Error al cargar solicitudes</p>';
    }
}

function renderContactRequests(received, sent) {
    const receivedList = document.getElementById('received-requests-list');
    const sentList = document.getElementById('sent-requests-list');

    // Renderizar solicitudes recibidas
    if (receivedList) {
        receivedList.innerHTML = '';
        if (!received.length) {
            receivedList.innerHTML = '<p class="text-slate-500 text-center py-4">No hay solicitudes pendientes</p>';
        } else {
            for (const request of received) {
                const requestElement = document.createElement('div');
                requestElement.className = 'request-card';
                requestElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                                ${request.sender.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h5 class="text-slate-200 font-medium">${request.sender.name}</h5>
                                <p class="text-slate-400 text-sm">${request.sender.email}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="respondContactRequest('${request.id}', 'accept')"
                                    class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-all">
                                Aceptar
                            </button>
                            <button onclick="respondContactRequest('${request.id}', 'reject')"
                                    class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-all">
                                Rechazar
                            </button>
                        </div>
                    </div>
                `;
                receivedList.appendChild(requestElement);
            }
        }
    }

    // Renderizar solicitudes enviadas
    if (sentList) {
        sentList.innerHTML = '';
        if (!sent.length) {
            sentList.innerHTML = '<p class="text-slate-500 text-center py-4">No hay solicitudes enviadas</p>';
        } else {
            for (const request of sent) {
                const requestElement = document.createElement('div');
                requestElement.className = 'request-card';
                requestElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                                ${request.receiver.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h5 class="text-slate-200 font-medium">${request.receiver.name}</h5>
                                <p class="text-slate-400 text-sm">${request.receiver.email}</p>
                                <span class="inline-block bg-yellow-500/20 text-yellow-300 text-xs px-2 py-1 rounded-full mt-1">Pendiente</span>
                            </div>
                        </div>
                    </div>
                `;
                sentList.appendChild(requestElement);
            }
        }
    }
}

// Modal functionality
function openAddContactModal(prefilledEmail = '') {
    const modal = document.getElementById('add-contact-modal');
    const input = document.getElementById('user-search-input');
    const searchResults = document.getElementById('search-results');
    const submitBtn = document.getElementById('submit-btn');

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (prefilledEmail) {
        input.value = prefilledEmail;
        searchUser(prefilledEmail);
    } else {
        input.value = '';
        searchResults.classList.add('hidden');
        submitBtn.disabled = true;
        selectedUser = null;
    }

    input.focus();
}

function closeAddContactModal() {
    const modal = document.getElementById('add-contact-modal');
    const input = document.getElementById('user-search-input');
    const searchResults = document.getElementById('search-results');
    const submitBtn = document.getElementById('submit-btn');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    input.value = '';
    searchResults.classList.add('hidden');
    submitBtn.disabled = true;
    selectedUser = null;
}

async function searchUser(query) {
    if (!query.trim()) {
        document.getElementById('search-results').classList.add('hidden');
        document.getElementById('submit-btn').disabled = true;
        selectedUser = null;
        return;
    }
    // Evitar llamar API si no cumple longitud mínima (backend exige min:3)
    if (query.trim().length < 3) {
        document.getElementById('search-results').classList.add('hidden');
        document.getElementById('submit-btn').disabled = true;
        selectedUser = null;
        return;
    }

    try {
        const response = await fetch('/api/users/search', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query })
        });
        if (!response.ok) {
            let info = null;
            try { info = await response.json(); } catch(_) {}
            console.warn('[searchUser] fallo', response.status, info);
            // 419 CSRF / 401 auth / 422 validation
            return renderSearchResults([]);
        }
        const data = await response.json().catch(() => ({ users: [] }));
        renderSearchResults(data.users || []);
    } catch (error) {
        console.error('Error searching users:', error);
        renderSearchResults([]);
    }
}

function renderSearchResults(users) {
    const searchResults = document.getElementById('search-results');
    const searchResultsList = document.getElementById('search-results-list');

    if (!users.length) {
        searchResults.classList.add('hidden');
        document.getElementById('submit-btn').disabled = true;
        selectedUser = null;
        return;
    }

    searchResultsList.innerHTML = '';
    for (const user of users) {
        const userElement = document.createElement('div');
        userElement.className = 'user-search-item';
        userElement.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-medium text-sm">
                    ${user.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <h5 class="text-slate-200 font-medium">${user.name}</h5>
                    <p class="text-slate-400 text-sm">${user.email}</p>
                </div>
            </div>
            <div class="text-xs text-slate-500">
                ${user.exists_in_juntify ? 'Usuario de Juntify' : 'Usuario externo'}
            </div>
        `;

        userElement.addEventListener('click', () => selectUser(user));
        searchResultsList.appendChild(userElement);
    }

    searchResults.classList.remove('hidden');
}

function selectUser(user) {
    selectedUser = user;
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = false;

    // Highlight selected user
    const items = document.querySelectorAll('.user-search-item');
    items.forEach(item => item.classList.remove('bg-yellow-500/20', 'border-yellow-500/50'));

    const selectedItem = Array.from(items).find(item =>
        item.textContent.includes(user.email)
    );
    if (selectedItem) {
        selectedItem.classList.add('bg-yellow-500/20', 'border-yellow-500/50');
    }
}

async function sendContactRequest(event) {
    event.preventDefault();

    if (!selectedUser) {
        alert('Por favor selecciona un usuario');
        return;
    }

    const submitBtn = document.getElementById('submit-btn');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';

    try {
        const response = await fetch('/api/contacts', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: selectedUser.email
            })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Error al enviar solicitud');
        }

        closeAddContactModal();
        await loadContacts();

        // Show success message
        showNotification('Solicitud enviada correctamente', 'success');

    } catch (error) {
        console.error('Error sending contact request:', error);
        showNotification(error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

async function respondContactRequest(id, action) {
    try {
        const response = await fetch(`/api/contacts/requests/${id}/respond`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action })
        });
        if (!response.ok) throw new Error('Error al responder solicitud');
        await loadContacts();
        showNotification(
            action === 'accept' ? 'Solicitud aceptada' : 'Solicitud rechazada',
            'success'
        );
    } catch (error) {
        console.error('Error responding to contact request:', error);
        showNotification('Error al procesar la solicitud', 'error');
    }
}

async function deleteContact(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este contacto?')) return;
    try {
        const response = await fetch(`/api/contacts/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error al eliminar contacto');
        await loadContacts();
        showNotification('Contacto eliminado correctamente', 'success');
    } catch (error) {
        console.error('Error deleting contact:', error);
        showNotification('Error al eliminar contacto', 'error');
    }
}

// Funciones auxiliares
async function startChat(contactId) {
    try {
        showNotification('Iniciando chat...', 'info');

        const response = await fetch('/api/chats/create-or-find', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                contact_id: contactId
            })
        });

        if (!response.ok) {
            throw new Error('Error al crear/buscar el chat');
        }

        const data = await response.json();

        // Redirigir a la vista de chat con el ID del chat para seleccionarlo automáticamente
        window.location.href = `/chats?chat_id=${data.chat_id}`;

    } catch (error) {
        console.error('Error starting chat:', error);
        showNotification('Error al iniciar el chat', 'error');
    }
}
function showNotification(message, type = 'info') {
    // Ensure container below navbar, centered
    let container = document.getElementById('global-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'global-toast-container';
        container.style.position = 'fixed';
        container.style.top = '72px'; // slightly below navbar
        container.style.left = '50%';
        container.style.transform = 'translateX(-50%)';
        container.style.width = 'min(420px, calc(100% - 2rem))';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        container.style.zIndex = '9500';
        container.style.pointerEvents = 'none';
        document.body.appendChild(container);
    }

    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `p-4 rounded-lg shadow-lg w-full transform transition-all duration-300 opacity-0 translate-y-2`;

    // Agregar estilos según el tipo
    switch (type) {
        case 'success':
            notification.classList.add('bg-green-500', 'text-white');
            break;
        case 'error':
            notification.classList.add('bg-red-500', 'text-white');
            break;
        case 'info':
            notification.classList.add('bg-blue-500', 'text-white');
            break;
        default:
            notification.classList.add('bg-slate-700', 'text-slate-200');
    }

    notification.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 18a9 9 0 110-18 9 9 0 010 18z"/></svg>
            </div>
            <div class="text-sm leading-5">${escapeHtml(message)}</div>
        </div>
    `;

    notification.style.pointerEvents = 'auto';
    container.appendChild(notification);

    // Animar entrada (fade/slide)
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-y-2');
    }, 10);

    // Auto-remover después de 4s
    setTimeout(() => {
        notification.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => notification.remove(), 200);
    }, 4000);
}

// Variables globales del chat modal
let currentChatId = null;
let currentContactId = null;
let chatMessages = [];
let isChatLoading = false;

// Función para abrir el modal de chat
async function openChatModal(chatId, contactId) {
    try {
        currentChatId = chatId;
        currentContactId = contactId;

        // Obtener información del contacto
        const contactInfo = await getContactInfo(contactId);

        // Configurar el modal
        setupChatModal(contactInfo);

        // Mostrar el modal con animación
        const modal = document.getElementById('chat-modal');
        if (modal) {
            const modalContent = modal.querySelector('.bg-slate-900\\/95');

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            // Trigger animation
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                if (modalContent) {
                    modalContent.classList.remove('scale-95');
                    modalContent.classList.add('scale-100');
                }
            }, 10);

            // Cargar mensajes
            await loadChatMessages();

            // Configurar event listeners del modal
            setupChatModalEventListeners();
        }
    } catch (error) {
        console.error('Error opening chat modal:', error);
        showNotification('Error al abrir el chat', 'error');
    }
}

// Función para obtener información del contacto
async function getContactInfo(contactId) {
    // Buscar en los contactos ya cargados primero
    const contacts = document.querySelectorAll('.contact-card');
    for (const contactCard of contacts) {
        const chatBtn = contactCard.querySelector('[onclick*="startChat"]');
        if (chatBtn && chatBtn.getAttribute('onclick').includes(contactId)) {
            const name = contactCard.querySelector('h4').textContent;
            const email = contactCard.querySelector('p').textContent;
            return { name, email };
        }
    }

    // Si no se encuentra, buscar en usuarios de organización
    const orgUsers = document.querySelectorAll('#organization-users-list .contact-card');
    for (const userCard of orgUsers) {
        const addBtn = userCard.querySelector('[onclick*="openAddContactModal"]');
        if (addBtn && addBtn.getAttribute('onclick').includes(contactId)) {
            const name = userCard.querySelector('h4').textContent;
            const email = userCard.querySelector('p').textContent;
            return { name, email };
        }
    }

    // Fallback - hacer una búsqueda API si es necesario
    return { name: 'Usuario', email: 'usuario@email.com' };
}

// Función para configurar el modal de chat
// Función para determinar el estado en línea del usuario
function getUserOnlineStatus(contactInfo) {
    // Simular estado basado en la hora actual y el email del usuario
    // En una implementación real, esto vendría de la base de datos o websockets
    const now = new Date();
    const hour = now.getHours();
    const emailHash = contactInfo.email.split('').reduce((a, b) => {
        a = ((a << 5) - a) + b.charCodeAt(0);
        return a & a;
    }, 0);

    // Simular que usuarios están más activos en horas laborales
    const isWorkingHours = hour >= 8 && hour <= 18;
    const userActivity = Math.abs(emailHash) % 100;

    if (isWorkingHours) {
        return userActivity < 70; // 70% probabilidad en línea en horas laborales
    } else {
        return userActivity < 30; // 30% probabilidad en línea fuera de horas laborales
    }
}

function setupChatModal(contactInfo) {
    const avatar = document.getElementById('chat-contact-avatar');
    const username = document.getElementById('chat-contact-name');
    const email = document.getElementById('chat-contact-email');
    const statusIndicator = document.getElementById('chat-status-indicator');

    if (avatar) avatar.textContent = contactInfo.name.charAt(0).toUpperCase();
    if (username) username.textContent = contactInfo.name;

    // Determinar estado en línea/desconectado de manera más realista
    const isOnline = getUserOnlineStatus(contactInfo);

    if (email && statusIndicator) {
        if (isOnline) {
            email.textContent = 'En línea';
            statusIndicator.className = 'w-2 h-2 bg-green-400 rounded-full animate-pulse';
        } else {
            // Simular último tiempo de conexión
            const lastSeenOptions = ['Hace 5 min', 'Hace 1 hora', 'Hace 2 horas', 'Ayer', 'Hace 2 días'];
            const randomLastSeen = lastSeenOptions[Math.floor(Math.random() * lastSeenOptions.length)];
            email.textContent = `Últ. vez ${randomLastSeen}`;
            statusIndicator.className = 'w-2 h-2 bg-gray-400 rounded-full';
        }
    }
}

// Función para cargar mensajes del chat
async function loadChatMessages() {
    try {
        isChatLoading = true;
        updateChatMessagesDisplay();

        const response = await fetch(`/api/chats/${currentChatId}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) throw new Error('Error al cargar mensajes');

        chatMessages = await response.json();
        updateChatMessagesDisplay();
        scrollChatToBottom();
    } catch (error) {
        console.error('Error loading chat messages:', error);
        showNotification('Error al cargar mensajes', 'error');
    } finally {
        isChatLoading = false;
    }
}

// Función para enviar mensaje de chat
async function sendChatMessage() {
    const messageInput = document.getElementById('chat-message-input');
    const messageText = messageInput.value.trim();

    if (!messageText) return;

    try {
        const sendBtn = document.getElementById('chat-send-btn');
        sendBtn.disabled = true;

        // Ocultar indicador de escritura
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.classList.add('hidden');
        }

        const response = await fetch(`/api/chats/${currentChatId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                body: messageText
            })
        });

        if (!response.ok) throw new Error('Error al enviar mensaje');

        const newMessage = await response.json();
        chatMessages.push(newMessage);
        messageInput.value = '';
        updateChatMessagesDisplay();
        scrollChatToBottom();

    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('Error al enviar mensaje', 'error');
    } finally {
        document.getElementById('chat-send-btn').disabled = false;
    }
}

// Función para enviar archivo de chat
async function sendChatFile(file) {
    try {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(`/api/chats/${currentChatId}/messages`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: formData
        });

        if (!response.ok) throw new Error('Error al enviar archivo');

        const newMessage = await response.json();
        chatMessages.push(newMessage);
        updateChatMessagesDisplay();
        scrollChatToBottom();

        // Limpiar input
        document.getElementById('chat-file-input').value = '';

    } catch (error) {
        console.error('Error sending file:', error);
        showNotification('Error al enviar archivo', 'error');
    }
}

// Función para actualizar la visualización de mensajes
function updateChatMessagesDisplay() {
    const messagesList = document.getElementById('chat-messages-list');

    if (isChatLoading) {
        messagesList.innerHTML = `
            <div class="text-center py-8">
                <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-slate-400">Cargando mensajes...</p>
            </div>
        `;
        return;
    }

    if (chatMessages.length === 0) {
        messagesList.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                </svg>
                <p class="text-slate-400">No hay mensajes aún</p>
                <p class="text-slate-500 text-sm mt-1">Envía el primer mensaje para comenzar la conversación</p>
            </div>
        `;
        return;
    }

    // Obtener ID del usuario actual dinámicamente
    const currentUserId = document.querySelector('meta[name="user-id"]')?.getAttribute('content') || null;

    messagesList.innerHTML = chatMessages.map(message => {
        const isOwn = message.sender_id === currentUserId;
        const time = new Date(message.created_at).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div class="flex ${isOwn ? 'justify-end' : 'justify-start'}">
                <div class="max-w-xs lg:max-w-md ${isOwn ? 'bg-blue-600' : 'bg-slate-700'} rounded-lg px-4 py-2">
                    ${message.body ? `<p class="text-white">${escapeHtml(message.body)}</p>` : ''}
                    ${message.file_path ? `
                        <div class="mt-2">
                            <a href="/storage/${message.file_path}" target="_blank"
                               class="flex items-center gap-2 text-blue-200 hover:text-blue-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                Ver archivo
                            </a>
                        </div>
                    ` : ''}
                    <div class="flex ${isOwn ? 'justify-end' : 'justify-start'} mt-1">
                        <span class="text-xs ${isOwn ? 'text-blue-200' : 'text-slate-400'}">${time}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Función para hacer scroll hacia abajo en el chat
function scrollChatToBottom() {
    const container = document.getElementById('chat-messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Función para cerrar el modal de chat
function closeChatModal() {
    const modal = document.getElementById('chat-modal');
    if (modal) {
        const modalContent = modal.querySelector('.bg-slate-900\\/95');

        // Iniciar animación de salida
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');

        if (modalContent) {
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
        }

        // Ocultar modal después de la animación
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');

            // Limpiar variables
            currentChatId = null;
            currentContactId = null;
            chatMessages = [];

            // Limpiar el input
            const messageInput = document.getElementById('chat-message-input');
            if (messageInput) messageInput.value = '';
        }, 300);
    }
}

// Función para configurar event listeners del modal de chat
function setupChatModalEventListeners() {
    const messageForm = document.getElementById('chat-message-form');
    const messageInput = document.getElementById('chat-message-input');
    const fileBtn = document.getElementById('chat-file-btn');
    const fileInput = document.getElementById('chat-file-input');
    const closeBtn = document.getElementById('close-chat-modal');

    // Prevenir múltiples event listeners
    if (messageForm && messageForm.hasAttribute('data-listeners-added')) return;
    if (messageForm) messageForm.setAttribute('data-listeners-added', 'true');

    // Enviar mensaje con Enter
    if (messageInput) {
        // Indicador de escritura
        let typingTimer;
        messageInput.addEventListener('input', function() {
            const typingIndicator = document.getElementById('typing-indicator');
            if (typingIndicator && this.value.trim().length > 0) {
                typingIndicator.classList.remove('hidden');
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    typingIndicator.classList.add('hidden');
                }, 1000);
            } else if (typingIndicator) {
                typingIndicator.classList.add('hidden');
            }
        });

        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }

    // Enviar mensaje con botón
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendChatMessage();
        });
    }

    // Seleccionar archivo
    if (fileBtn) {
        fileBtn.addEventListener('click', function() {
            if (fileInput) fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                sendChatFile(this.files[0]);
            }
        });
    }

    // Cerrar modal
    if (closeBtn) {
        closeBtn.addEventListener('click', closeChatModal);
    }

    // Cerrar modal al hacer clic fuera
    const modal = document.getElementById('chat-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeChatModal();
            }
        });
    }
}

// Función auxiliar para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function filterContacts(searchTerm) {
    const contactCards = document.querySelectorAll('#contacts-list .contact-card');
    const searchLower = searchTerm.toLowerCase();

    contactCards.forEach(card => {
        const name = card.querySelector('h4').textContent.toLowerCase();
        const email = card.querySelector('p').textContent.toLowerCase();

        if (name.includes(searchLower) || email.includes(searchLower)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// ===============================================
// REUNIONES PENDIENTES
// ===============================================
async function loadPendingMeetingsStatus() {
    try {
        const response = await fetch('/api/pending-meetings', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (response.ok) {
            const data = await response.json();
            updatePendingMeetingsButton(data.has_pending, data.pending_meetings?.length || 0);
        }
    } catch (error) {
        console.error('Error loading pending meetings status:', error);
    }
}

function updatePendingMeetingsButton(hasPending, count = 0) {
    // Buscar el botón por el texto que contiene
    const buttons = Array.from(document.querySelectorAll('button'));
    const button = buttons.find(btn => {
        const span = btn.querySelector('span');
        return span && (
            span.textContent.includes('Reuniones Pendientes') ||
            span.textContent.includes('reuniones pendientes') ||
            span.textContent.includes('No hay reuniones pendientes')
        );
    });

    if (!button) {
        console.warn('Botón de reuniones pendientes no encontrado');
        console.log('Botones disponibles:', buttons.map(b => b.textContent.trim()));
        return;
    }

    const span = button.querySelector('span');
    if (!span) {
        console.warn('Span del botón no encontrado');
        return;
    }

    if (hasPending) {
        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        span.textContent = `Reuniones Pendientes (${count})`;

        // Agregar event listener si no existe
        if (!button.hasAttribute('data-pending-listener')) {
            button.addEventListener('click', openPendingMeetingsModal);
            button.setAttribute('data-pending-listener', 'true');
        }

        console.log(`Botón habilitado con ${count} reuniones pendientes`);
    } else {
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        span.textContent = 'No hay reuniones pendientes';

        // Remover event listener
        button.removeEventListener('click', openPendingMeetingsModal);
        button.removeAttribute('data-pending-listener');

        console.log('Botón deshabilitado - no hay reuniones pendientes');
    }
}

async function openPendingMeetingsModal() {
    try {
        const response = await fetch('/api/pending-meetings', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar reuniones pendientes');
        }

        const data = await response.json();

        if (data.success) {
            showPendingMeetingsModal(data.pending_meetings);
        } else {
            alert('Error al cargar reuniones pendientes');
        }
    } catch (error) {
        console.error('Error opening pending meetings modal:', error);
        alert('Error de conexión al cargar reuniones pendientes');
    }
}

function showPendingMeetingsModal(pendingMeetings) {
    const modalHTML = `
        <div class="meeting-modal active" id="pendingMeetingsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Reuniones Pendientes
                        </h2>
                        <p class="modal-subtitle">Selecciona una reunión para analizar</p>
                    </div>

                    <button class="close-btn" onclick="closePendingMeetingsModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    ${pendingMeetings.length > 0 ? `
                        <div class="modal-section">
                            <h3 class="section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Audios Disponibles (${pendingMeetings.length})
                            </h3>
                            <div class="pending-meetings-grid">
                                ${pendingMeetings.map(meeting => `
                                    <div class="pending-meeting-card" data-meeting-id="${meeting.id}">
                                        <div class="pending-card-header">
                                            <div class="pending-meeting-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M9 9v6l6-6" />
                                                </svg>
                                            </div>
                                            <div class="pending-meeting-info">
                                                <h4 class="pending-meeting-name">${escapeHtml(meeting.name)}</h4>
                                                <div class="pending-meeting-meta">
                                                    <span class="meta-item">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        ${meeting.created_at}
                                                    </span>
                                                    <span class="meta-item">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707L16.414 6.414A1 1 0 0015.707 6H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                                                        </svg>
                                                        ${meeting.size}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pending-card-actions">
                                            <button class="analyze-btn primary" onclick="analyzePendingMeeting(${meeting.id})" data-meeting-id="${meeting.id}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                </svg>
                                                Analizar Audio
                                            </button>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : `
                        <div class="modal-section">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                    </svg>
                                </div>
                                <h3>No hay reuniones pendientes</h3>
                                <p>Todas tus reuniones han sido procesadas.</p>
                            </div>
                        </div>
                    `}
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = document.getElementById('pendingMeetingsModal');

    // Deshabilitar scroll del body
    document.body.style.overflow = 'hidden';
}

function closePendingMeetingsModal() {
    const modal = document.getElementById('pendingMeetingsModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';

        setTimeout(() => {
            if (modal && !modal.classList.contains('active')) {
                modal.remove();
            }
        }, 300);
    }
}

async function analyzePendingMeeting(meetingId) {
    try {
        const button = document.querySelector(`.analyze-btn[data-meeting-id="${meetingId}"]`);
        if (!button) {
            console.error('Botón no encontrado para meeting ID:', meetingId);
            return;
        }

        const card = button.closest('.pending-meeting-card');
        const meetingName = card.querySelector('.pending-meeting-name').textContent;
        const originalText = button.innerHTML;

        // Agregar clase de loading y cambiar texto
        button.classList.add('loading');
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>Descargando...</span>
        `;
        button.disabled = true;

        // Deshabilitar toda la tarjeta visualmente
        card.style.opacity = '0.7';
        card.style.pointerEvents = 'none';

        const response = await fetch(`/api/pending-meetings/${meetingId}/analyze`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        const data = await response.json();

        if (data.success) {
            // Mostrar notificación de descarga exitosa
            showNotification(`Audio "${meetingName}" descargado. Redirigiendo al procesamiento...`, 'success');

            // Cambiar el botón a estado de procesamiento
            button.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Procesando...</span>
            `;
            button.classList.remove('loading');
            button.classList.add('processing');

            // Redirigir a audio-processing con información del audio pendiente
            setTimeout(() => {
                // Almacenar información del audio pendiente en localStorage
                localStorage.setItem('pendingAudioData', JSON.stringify({
                    pendingId: meetingId,
                    tempFile: data.temp_file,
                    originalName: data.filename,
                    isPendingAudio: true,
                    status: 'processing'
                }));

                // Redirigir a la página de audio-processing
                window.location.href = '/audio-processing';
            }, 1500);

        } else {
            throw new Error(data.error || 'Error al analizar audio');
        }

    } catch (error) {
        console.error('Error analyzing pending meeting:', error);
        showNotification('Error al procesar audio: ' + error.message, 'error');

        // Restaurar botón y tarjeta
        const button = document.querySelector(`.analyze-btn[data-meeting-id="${meetingId}"]`);
        const card = button?.closest('.pending-meeting-card');

        if (button) {
            button.classList.remove('loading', 'processing');
            button.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Analizar Audio</span>
            `;
            button.disabled = false;
        }

        if (card) {
            card.style.opacity = '';
            card.style.pointerEvents = '';
        }
    }
}

// Hacer las funciones disponibles globalmente
window.closePendingMeetingsModal = closePendingMeetingsModal;
window.analyzePendingMeeting = analyzePendingMeeting;
window.openAddContactModal = openAddContactModal;
window.closeAddContactModal = closeAddContactModal;
window.respondContactRequest = respondContactRequest;
window.deleteContact = deleteContact;
window.startChat = startChat;
window.openChatModal = openChatModal;
window.closeChatModal = closeChatModal;

// ===============================================
// RENDERIZADO DE REUNIONES
// ===============================================
function renderMeetings(items, targetSelector, emptyMessage, cardCreator = createMeetingCard) {
    const container = typeof targetSelector === 'string' ? document.querySelector(targetSelector) : targetSelector;
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <h3 class="text-lg font-semibold mb-2">${emptyMessage}</h3>
            </div>
        `;
        return;
    }

    const meetingsHtml = `
        <div class="meetings-grid">
            ${items.map(item => cardCreator(item)).join('')}
        </div>
    `;

    container.innerHTML = meetingsHtml;
    attachMeetingEventListeners();
}

// ===============================================
// CREACIÓN DE TARJETA DE REUNIÓN
// ===============================================
function createMeetingCard(meeting) {
    return `
        <div class="meeting-card" data-meeting-id="${meeting.id}" draggable="true">
            <div class="meeting-card-header">
                <div class="meeting-content">
                    <div class="meeting-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="meeting-title">${escapeHtml(meeting.meeting_name)}</h3>
                    <p class="meeting-date">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        ${meeting.created_at}
                    </p>

                    <div class="meeting-folders">
                        <div class="folder-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Transcripción:</span>
                            <span class="folder-name">${escapeHtml(meeting.transcript_folder)}</span>
                        </div>
                        <div class="folder-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728" />
                            </svg>
                            <span>Audio:</span>
                            <span class="folder-name">${escapeHtml(meeting.audio_folder)}</span>
                        </div>
                    </div>
                </div>

                <div class="meeting-actions">
                    <button class="icon-btn view-btn" onclick="openMeetingModal(${meeting.id})" title="Ver reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                    <button class="icon-btn container-btn" onclick="openContainerSelectModal(${meeting.id})" title="Añadir a contenedor">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12z" />
                        </svg>
                    </button>
                    <button class="share-btn icon-btn" onclick="document.getElementById('shareModal') && openShareModal(${meeting.id})" aria-label="Compartir reunión" title="Compartir reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                        </svg>
                    </button>
                    <button class="icon-btn edit-btn" onclick="editMeetingName(${meeting.id})" title="Editar nombre de reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button class="icon-btn delete-btn" onclick="deleteMeeting(${meeting.id})" title="Eliminar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <button class="download-btn icon-btn" onclick="openDownloadModal(${meeting.id})" title="Descargar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Exponer la función para su uso global
window.createMeetingCard = createMeetingCard;

// Tarjeta específica para reuniones compartidas (solo Descargar y Añadir a contenedor)
function createSharedMeetingCard(shared) {
    const meetingId = shared.meeting_id; // ID real de la reunión
    const sharedId = shared.id; // ID del vínculo compartido
    const title = shared.title || shared.meeting_name || 'Reunión compartida';
    let createdAt = '';
    try {
        if (shared.date) {
            const d = new Date(shared.date);
            if (!isNaN(d.getTime())) {
                createdAt = d.toLocaleString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
            } else if (typeof shared.date === 'string') {
                createdAt = shared.date;
            }
        }
    } catch (_) {
        createdAt = typeof shared.date === 'string' ? shared.date : '';
    }

    return `
        <div class="meeting-card" data-meeting-id="${meetingId}" data-shared-id="${sharedId}" draggable="true">
            <div class="meeting-card-header">
                <div class="meeting-content">
                    <div class="meeting-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="meeting-title">${escapeHtml(title)}</h3>
                    <p class="meeting-date">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        ${createdAt || ''}
                    </p>
                    ${shared.shared_by?.name ? `<p class="text-slate-400 text-sm">Compartido por: ${escapeHtml(shared.shared_by.name)}</p>` : ''}
                </div>

                <div class="meeting-actions">
                    <button class="icon-btn container-btn" onclick="openContainerSelectModal(${meetingId})" title="Añadir a contenedor">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12z" />
                        </svg>
                    </button>
                    <button class="download-btn icon-btn" onclick="openDownloadModal(${meetingId}, ${sharedId})" title="Descargar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                        </svg>
                    </button>
                    <button class="icon-btn delete-btn" onclick="openUnlinkSharedModal(${sharedId})" title="Quitar de compartidas">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Exponer función de tarjeta compartida si es útil externamente
window.createSharedMeetingCard = createSharedMeetingCard;

// Modal para quitar reunión de compartidas
function openUnlinkSharedModal(sharedId) {
    const modalHTML = `
        <div class="meeting-modal active" id="unlinkSharedModal">
            <div class="modal-content delete-modal">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            Quitar de Reuniones Compartidas
                        </h2>
                        <p class="modal-subtitle">Esta acción solo quitará la reunión de tu lista. No se borrará para el propietario.</p>
                    </div>

                    <button class="close-btn" onclick="closeUnlinkSharedModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="delete-confirmation-content">
                        <div class="warning-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="delete-title">¿Quitar de tus compartidas?</h3>
                        <p class="delete-message">
                            Esta acción eliminará la referencia a la reunión en tu lista de "Reuniones compartidas". Los archivos en Drive y los datos del propietario no se verán afectados.
                        </p>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn secondary" onclick="closeUnlinkSharedModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar
                        </button>
                        <button id="confirm-unlink-btn" class="modal-btn danger" onclick="confirmUnlinkSharedMeeting(${sharedId})">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Quitar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.body.style.overflow = 'hidden';
}

function closeUnlinkSharedModal() {
    const modal = document.getElementById('unlinkSharedModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => {
            if (modal && !modal.classList.contains('active')) {
                modal.remove();
            }
        }, 300);
    }
}

async function confirmUnlinkSharedMeeting(sharedId) {
    const btn = document.getElementById('confirm-unlink-btn');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-50');
    }
    try {
        await unlinkSharedMeeting(sharedId);
        closeUnlinkSharedModal();
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-50');
        }
    }
}

// Eliminar una reunión del listado de compartidas (sin borrar la reunión del propietario)
async function unlinkSharedMeeting(sharedId) {
    try {
        const res = await fetch(`/api/shared-meetings/${sharedId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json'
            }
        });

        if (!res.ok) {
            let msg = 'No se pudo quitar de compartidas';
            try {
                const data = await res.json();
                if (data?.message) msg = data.message;
            } catch (_) {}
            throw new Error(msg);
        }

        let data = null;
        try { data = await res.json(); } catch (_) { data = { success: true }; }

        if (data.success !== false) {
            showNotification('Reunión eliminada de tus compartidas', 'success');
            // Recargar lista de compartidas
            await loadSharedMeetings();
        } else {
            throw new Error(data.message || 'No se pudo quitar de compartidas');
        }
    } catch (err) {
        console.error('unlinkSharedMeeting error:', err);
        showNotification(err.message || 'Error al quitar de compartidas', 'error');
    }
}

// Exponer por si se requiere en ámbito global
window.unlinkSharedMeeting = unlinkSharedMeeting;

function createContainerMeetingCard(meeting) {
    return `
        <div class="meeting-card" data-meeting-id="${meeting.id}" draggable="true">
            <div class="meeting-card-header">
                <div class="meeting-content">
                    <div class="meeting-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="meeting-title">${escapeHtml(meeting.meeting_name)}</h3>
                    <p class="meeting-date">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        ${meeting.created_at}
                    </p>

                    <div class="meeting-folders">
                        <div class="folder-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Transcripción:</span>
                            <span class="folder-name">${escapeHtml(meeting.transcript_folder)}</span>
                        </div>
                        <div class="folder-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728" />
                            </svg>
                            <span>Audio:</span>
                            <span class="folder-name">${escapeHtml(meeting.audio_folder)}</span>
                        </div>
                    </div>
                </div>

                <div class="meeting-actions">
                    <button class="icon-btn remove-btn" onclick="removeMeetingFromContainer(${meeting.id})" title="Quitar del contenedor">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2h3" />
                        </svg>
                    </button>
                        <button class="share-btn icon-btn" onclick="openShareModal(${meeting.id})" aria-label="Compartir reunión" title="Compartir reunión">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                        </svg>
                    </button>
                    <button class="icon-btn edit-btn" onclick="editMeetingName(${meeting.id})" title="Editar nombre de reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button class="icon-btn delete-btn" onclick="deleteMeeting(${meeting.id})" title="Eliminar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <button class="download-btn icon-btn" onclick="openDownloadModal(${meeting.id})" title="Descargar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function createContainerCard(container) {
    return `
        <div class="meeting-card container-card" data-container-id="${container.id}" onclick="openContainerMeetingsModal(${container.id})" style="cursor: pointer;">
            <div class="meeting-card-header">
                <div class="meeting-content">
                    <div class="meeting-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                        </svg>
                    </div>
                    <h3 class="meeting-title">${escapeHtml(container.name || container.title || '')}${container.group_name ? ` <span class="company-badge">${escapeHtml(container.group_name)}</span>` : ''}</h3>
                    <p class="meeting-date">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                        </svg>
                        ${container.meetings_count || 0} reuniones
                    </p>
                    ${container.description ? `<p class="meeting-description">${escapeHtml(container.description)}</p>` : ''}
                </div>
                <div class="meeting-actions">
                    <button onclick="event.stopPropagation(); openEditContainerModal(${JSON.stringify(container).replace(/"/g, '&quot;')})" class="edit-btn" title="Editar contenedor">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button onclick="event.stopPropagation(); deleteContainer(${container.id})" class="delete-btn" title="Eliminar contenedor">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

// ===============================================
// EVENT LISTENERS PARA REUNIONES
// ===============================================
function attachMeetingEventListeners() {
    const meetingCards = document.querySelectorAll('.meeting-card[data-meeting-id]');
    meetingCards.forEach(card => {
        card.addEventListener('dragstart', e => {
            e.dataTransfer.setData('meeting-id', card.dataset.meetingId);
        });
        const downloadBtn = card.querySelector('.download-btn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const sharedId = card.dataset.sharedId;
                if (sharedId) {
                    openDownloadModal(card.dataset.meetingId, sharedId);
                } else {
                    openDownloadModal(card.dataset.meetingId);
                }
            });
        }
        const shareBtn = card.querySelector('.share-btn');
        if (shareBtn) {
            shareBtn.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
        card.addEventListener('click', function(e) {
            // No abrir modal si se hizo click en los botones de acción
            if (e.target.closest('.delete-btn') || e.target.closest('.edit-btn') || e.target.closest('.container-btn') || e.target.closest('.remove-btn') || e.target.closest('.download-btn') || e.target.closest('.share-btn')) {
                return;
            }

            const meetingId = this.dataset.meetingId;
            const containerModal = document.getElementById('container-meetings-modal');
            if (containerModal && !containerModal.classList.contains('hidden')) {
                openMeetingModalFromContainer(meetingId);
            } else {
                const sharedId = this.dataset.sharedId || null;
                openMeetingModal(meetingId, sharedId);
            }
        });
    });
}

function attachContainerEventListeners() {
    const containerCards = document.querySelectorAll('.container-card');
    containerCards.forEach(card => {
        card.addEventListener('dragover', e => e.preventDefault());
        card.addEventListener('drop', e => {
            e.preventDefault();
            const meetingId = e.dataTransfer.getData('meeting-id');
            if (meetingId) {
                addMeetingToContainer(meetingId, card.dataset.containerId);
            }
        });

        card.addEventListener('click', (e) => {
            if (e.target.closest('.edit-btn') || e.target.closest('.delete-btn')) {
                return;
            }
            openContainerMeetingsModal(card.dataset.containerId);
        });
    });
}

async function addMeetingToContainer(meetingId, containerId) {
    try {
        const response = await fetch(`/api/content-containers/${containerId}/meetings`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ meeting_id: meetingId })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showNotification(data.message || 'Error al agregar la reunión al contenedor', 'error');
            return false;
        }

        showNotification(data.message || 'Reunión agregada al contenedor', 'success');

        currentMeetings = currentMeetings.filter(m => m.id != meetingId);
        renderMeetings(currentMeetings, '#my-meetings', 'No tienes reuniones');
        loadContainers();

        return true;
    } catch (error) {
        console.error('Error adding meeting to container:', error);
        showNotification('Error al agregar la reunión al contenedor', 'error');
        return false;
    }
}

async function removeMeetingFromContainer(meetingId) {
    if (!currentContainerForMeetings) return;

    try {
        const response = await fetch(`/api/content-containers/${currentContainerForMeetings}/meetings/${meetingId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showNotification(data.message || 'Error al quitar la reunión del contenedor', 'error');
            return;
        }

        showNotification(data.message || 'Reunión quitada del contenedor', 'success');
        loadContainerMeetings(currentContainerForMeetings);
        loadContainers();
    } catch (error) {
        console.error('Error removing meeting from container:', error);
        showNotification('Error al quitar la reunión del contenedor', 'error');
    }
}

async function toggleContainer(containerId, card) {
    if (card.classList.contains('expanded')) {
        card.classList.remove('expanded');
        const nested = card.querySelector('.nested-meetings');
        if (nested) nested.remove();
        return;
    }

    try {
        const response = await fetch(`/api/content-containers/${containerId}/meetings`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error al cargar reuniones');
        const data = await response.json();
        if (data.success) {
            const html = `<div class="nested-meetings">${data.meetings.map(m => createMeetingCard(m)).join('')}</div>`;
            card.insertAdjacentHTML('beforeend', html);
            card.classList.add('expanded');
            attachMeetingEventListeners();
        }
    } catch (error) {
        console.error('Error loading container meetings:', error);
    }
}

async function openContainerSelectModal(meetingId) {
    // Mostrar modal de loading inmediatamente
    const loadingModalHtml = `
        <div class="meeting-modal active" id="containerSelectModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Cargando contenedores...</h2>
                    <button class="close-btn" onclick="closeContainerSelectModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-center">
                        <div class="stack-center">
                            <div class="loading-spinner mb-4"></div>
                            <span class="text-slate-300">Cargando contenedores...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', loadingModalHtml);
    document.body.style.overflow = 'hidden';

    try {
        // Asegurar que los contenedores estén cargados
        if (!containers || containers.length === 0) {
            await loadContainers();
        }

        // Verificar si hay contenedores después de cargar
        if (!containers || containers.length === 0) {
            updateModalContent('No hay contenedores disponibles', `
                <div class="text-center p-8">
                    <div class="mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12z" />
                        </svg>
                    </div>
                    <p class="text-slate-400 mb-4">No tienes contenedores creados aún.</p>
                    <button onclick="closeContainerSelectModal(); openContainerModal()" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-600 text-slate-900 rounded-lg font-medium hover:from-yellow-500 hover:to-yellow-700 transition-all">
                        Crear primer contenedor
                    </button>
                </div>
            `);
            return;
        }

        // Actualizar modal con los contenedores disponibles
        updateModalContent('Seleccionar contenedor', `
            <div class="modal-list-wrap space-y-2">
                ${containers.map(c => `
                    <button class="w-full text-left px-4 py-3 rounded-lg bg-slate-800/30 border border-slate-700/50 hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 text-slate-200" data-id="${c.id}">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12z" />
                            </svg>
                            <div>
                                <div class="font-medium">${escapeHtml(c.name || c.title || '')}</div>
                                ${c.description ? `<div class="text-sm text-slate-400 mt-1">${escapeHtml(c.description)}</div>` : ''}
                            </div>
                        </div>
                    </button>
                `).join('')}
            </div>
        `);

        // Agregar event listeners a los botones
        document.querySelectorAll('#containerSelectModal [data-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const containerId = btn.dataset.id;

                // Mostrar loading en el botón clickeado
                btn.innerHTML = `
                    <div class="flex items-center">
                        <div class="loading-spinner-small mr-3"></div>
                        <span>Agregando...</span>
                    </div>
                `;
                btn.disabled = true;

                const success = await addMeetingToContainer(meetingId, containerId);
                if (success) {
                    closeContainerSelectModal();
                }
            });
        });

    } catch (error) {
        console.error('Error loading containers:', error);
        updateModalContent('Error', `
            <div class="text-center p-8">
                <div class="mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-slate-400 mb-4">Error al cargar los contenedores.</p>
                <button onclick="closeContainerSelectModal()" class="px-4 py-2 bg-slate-700 text-slate-200 rounded-lg hover:bg-slate-600 transition-all">
                    Cerrar
                </button>
            </div>
        `);
    }
}

function updateModalContent(title, bodyContent) {
    const modal = document.getElementById('containerSelectModal');
    if (!modal) return;

    const titleElement = modal.querySelector('.modal-title');
    const bodyElement = modal.querySelector('.modal-body');

    if (titleElement) titleElement.textContent = title;
    if (bodyElement) bodyElement.innerHTML = bodyContent;
}

function closeContainerSelectModal() {
    const modal = document.getElementById('containerSelectModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => modal.remove(), 300);
    }
}

// ===============================================
// MODAL DE REUNIÓN
// ===============================================
async function openMeetingModal(meetingId, sharedMeetingId = null) {
    try {
        // Mostrar modal de loading inmediatamente
        showModalLoadingState();

        const response = await fetch(`/api/meetings/${meetingId}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            let errorMessage = 'Error al cargar la reunión';
            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // ignore json parse errors
            }
            closeMeetingModal();
            alert(errorMessage);
            return;
        }

        const data = await response.json();

        if (data.success) {
            const meeting = data.meeting || {};

            // Construir segmentos y transcripción desde transcriptions si es necesario
            if ((!meeting.segments || meeting.segments.length === 0) && Array.isArray(meeting.transcriptions)) {
                meeting.segments = meeting.transcriptions.map((t, index) => ({
                    speaker: t.speaker || t.display_speaker || `Hablante ${index + 1}`,
                    time: t.time || t.timestamp,
                    text: t.text || '',
                    start: t.start ?? 0,
                    end: t.end ?? 0,
                }));
            }
            if (!meeting.transcription && Array.isArray(meeting.segments)) {
                meeting.transcription = meeting.segments.map(s => s.text).join(' ');
            }

            if (meeting.is_legacy) {
                updateLoadingStep(4); // Omitir pasos de descarga/desencriptado
            } else {
                updateLoadingStep(2); // Paso 2: Descifrando archivo
                updateLoadingStep(3); // Paso 3: Descargando audio
                updateLoadingStep(4); // Paso 4: Procesando contenido
            }

            // Esperar un poco para mostrar el progreso completo
            await new Promise(resolve => setTimeout(resolve, 500));

            if (sharedMeetingId) {
                const resolvedLinks = await tryResolveSharedDriveLinks(sharedMeetingId);
                if (resolvedLinks && resolvedLinks.audio_link) {
                    meeting.audio_path = resolvedLinks.audio_link;
                }
                meeting.shared_meeting_id = sharedMeetingId;
            }

            currentModalMeeting = meeting;
            // Guardar bandera de encriptación
            currentModalMeeting.needs_encryption = meeting.needs_encryption;
            showMeetingModal(meeting);
        } else {
            closeMeetingModal();
            alert('Error al cargar la reunión: ' + (data.message || 'Error desconocido'));
        }

    } catch (error) {
        console.error('Error loading meeting details:', error);
        closeMeetingModal();
        alert('Error de conexión al cargar la reunión');
    }
}

function showMeetingModal(meeting) {
    console.log('Datos de la reunión:', meeting);
    console.log('Ruta de audio:', meeting.audio_path);

    // Asegurar que summary, key_points, tasks y segmentos estén en el formato correcto
    meeting.summary = meeting.summary || '';
    meeting.key_points = Array.isArray(meeting.key_points) ? meeting.key_points : [];
    meeting.tasks = Array.isArray(meeting.tasks) ? meeting.tasks : [];

    if ((!meeting.segments || meeting.segments.length === 0) && Array.isArray(meeting.transcriptions)) {
        meeting.segments = meeting.transcriptions.map((t, index) => ({
            speaker: t.speaker || t.display_speaker || `Hablante ${index + 1}`,
            time: t.time || t.timestamp,
            text: t.text || '',
            start: t.start ?? 0,
            end: t.end ?? 0,
        }));
    }

    if (!meeting.transcription && Array.isArray(meeting.segments)) {
        meeting.transcription = meeting.segments.map(s => s.text).join(' ');
    }

    // Calcular número de participantes automáticamente basándose en hablantes únicos
    let participantCount = 0;
    if (Array.isArray(meeting.segments) && meeting.segments.length > 0) {
        const uniqueSpeakers = new Set();
        meeting.segments.forEach(segment => {
            if (segment.speaker && segment.speaker.trim()) {
                // Normalizar nombres de hablantes para evitar duplicados por variaciones menores
                const normalizedSpeaker = segment.speaker.trim().toLowerCase()
                    .replace(/\s+/g, ' ') // Normalizar espacios
                    .replace(/hablante\s*(\d+)/i, 'speaker_$1'); // Normalizar "Hablante X" a "speaker_X"
                uniqueSpeakers.add(normalizedSpeaker);
            }
        });
        participantCount = uniqueSpeakers.size;
        console.log('Hablantes únicos encontrados:', Array.from(uniqueSpeakers));
        console.log('Número de participantes calculado:', participantCount);
    }

    // Si no se pudo calcular desde los segmentos, usar el valor original o 0
    if (participantCount === 0) {
        participantCount = meeting.participants || 0;
    }

    const audioSrc = meeting.audio_path || '';

    const modalHtml = `
        <div class="meeting-modal" id="meetingModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title" id="modalTitle">${escapeHtml(meeting.meeting_name)}</h2>
                        <p class="modal-subtitle">${meeting.created_at} • ${meeting.duration || ''} • ${participantCount} participantes</p>
                    </div>
                    <button class="close-btn" onclick="closeMeetingModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-nav">
                    <button class="modal-tab active" data-tab="summary">Resumen</button>
                    <button class="modal-tab" data-tab="key-points">Puntos clave</button>
                    <button class="modal-tab" data-tab="tasks">Tareas</button>
                    <button class="modal-tab" data-tab="transcription">Transcripción</button>
                </div>

                <div class="modal-body">
                    <div class="tab-content active" id="tab-summary">
                        <div class="modal-section">
                            <h3 class="section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M9 9v6l6-6" />
                                </svg>
                                Audio de la Reunión
                            </h3>
                            <div class="audio-player" id="audio-player-wrapper">
                                <div id="audio-loading" class="w-full flex flex-col items-center justify-center gap-2 p-4 rounded-md bg-slate-800/60 border border-slate-700 text-slate-300 text-xs" style="${audioSrc ? '' : 'display:none'}">
                                    <div class="w-full h-1.5 bg-slate-700/70 rounded overflow-hidden relative">
                                        <div class="h-full bg-yellow-500 animate-pulse w-1/3" id="audio-loading-bar"></div>
                                    </div>
                                    <p>Cargando audio...</p>
                                </div>
                                <audio id="meeting-audio" preload="metadata" controls style="display:none;width:100%;accent-color:#fbbf24;background:rgba(30,41,59,.6);border:1px solid #475569;border-radius:8px;"></audio>
                                ${!audioSrc ? '<p class="text-slate-400 text-sm mt-2">Audio no disponible para esta reunión</p>' : ''}
                            </div>
                        </div>
                        <div class="modal-section">
                            <h3 class="section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Resumen del Análisis
                            </h3>
                            <div class="section-content">
                                ${escapeHtml(meeting.summary)}
                            </div>
                        </div>
                    </div>
                    <div class="tab-content" id="tab-key-points">
                        <div class="modal-section">
                            <h3 class="section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Puntos Clave
                            </h3>
                            <div class="section-content">
                                ${renderKeyPoints(meeting.key_points)}
                            </div>
                        </div>
                    </div>
                    <div class="tab-content" id="tab-tasks">
                        <div class="modal-section">
                            <h3 class="section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                Tareas y Acciones
                            </h3>
                            <div class="section-content">
                                ${renderTasks(meeting.tasks)}
                            </div>
                        </div>
                    </div>
                    <div class="tab-content" id="tab-transcription">
                        <div class="modal-section">
                            <h3 class="section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                                Transcripción${meeting.segments && meeting.segments.length > 0 ? ' con hablantes' : ''}
                            </h3>
                            <div class="section-content ${meeting.segments && meeting.segments.length > 0 ? 'transcription-segmented' : 'transcription-full'}" style="max-height: 60vh; overflow-y: auto; padding: 0.25rem 0; background: transparent; border: none; width: 100%; box-sizing: border-box;">
                                ${meeting.segments && meeting.segments.length > 0 ? renderSegments(meeting.segments) : renderTranscription(meeting.transcription)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('meetingModal');
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = document.getElementById('meetingModal');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => {
        modal.classList.add('active');
    });

    // Ajustar altura de textareas de transcripción al contenido
    autoResizeTranscripts();

    document.querySelectorAll('.modal-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            const content = document.getElementById(`tab-${target}`);
            if (content) content.classList.add('active');

            // Asegurar que los textos de transcripción se ajusten al contenido
            if (target === 'transcription') {
                autoResizeTranscripts();
            }
        });
    });

    // Configuración de reproductor de audio nativo (barra del sistema)
    meetingAudioPlayer = document.getElementById('meeting-audio');
    if (meetingAudioPlayer && audioSrc) {
        const loadingEl = document.getElementById('audio-loading');
        const loadingBar = document.getElementById('audio-loading-bar');
        const origin = typeof location !== 'undefined' ? location.origin : '';
        const fallbackUrl = meeting?.id ? `/api/meetings/${meeting.id}/audio` : null;
        const resolvedFallbackUrl = fallbackUrl && origin ? new URL(fallbackUrl, origin).href : null;
        const isExternalAudio = !!(audioSrc && origin && !audioSrc.startsWith(origin));
        let triedFallback = false;

        const finalizePlayer = () => {
            if (loadingEl) loadingEl.remove();
            meetingAudioPlayer.style.display = 'block';
        };

        const attachError = () => {
            meetingAudioPlayer.addEventListener('error', () => {
                if (!triedFallback && fallbackUrl && (!resolvedFallbackUrl || meetingAudioPlayer.src !== resolvedFallbackUrl)) {
                    triedFallback = true;
                    console.warn('[audio] Fallback endpoint streaming:', fallbackUrl);
                    meetingAudioPlayer.src = fallbackUrl;
                    try { meetingAudioPlayer.load(); } catch (_) {}
                } else {
                    if (loadingEl) loadingEl.innerHTML = `<p class="text-red-400 text-sm">No se pudo cargar el audio. <a class='underline' href='${encodeURI(audioSrc)}'>Descargar</a></p>`;
                }
            });
        };

        const bufferedDownload = async () => {
            try {
                const resp = await fetch(audioSrc, { redirect: 'follow' });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const total = parseInt(resp.headers.get('Content-Length') || '0', 10);
                if (total > 0 && total > 150 * 1024 * 1024) {
                    // Muy grande: usar streaming normal
                    meetingAudioPlayer.src = audioSrc;
                    attachError();
                    meetingAudioPlayer.addEventListener('loadedmetadata', finalizePlayer, { once: true });
                    meetingAudioPlayer.load();
                    return;
                }
                const reader = resp.body.getReader();
                const chunks = [];
                let received = 0; let lastUpdate = 0;
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    chunks.push(value); received += value.length;
                    if (loadingBar && total > 0) {
                        const pct = Math.min(100, Math.round((received / total) * 100));
                        if (pct - lastUpdate >= 5) { // throttle paints
                            loadingBar.style.width = pct + '%';
                            lastUpdate = pct;
                        }
                    }
                }
                const blob = new Blob(chunks, { type: 'audio/mpeg' });
                const objUrl = URL.createObjectURL(blob);
                meetingAudioPlayer.src = objUrl;
                attachError();
                meetingAudioPlayer.addEventListener('loadedmetadata', finalizePlayer, { once: true });
                meetingAudioPlayer.load();
            } catch (e) {
                console.error('[audio] Error descarga previa', e);
                meetingAudioPlayer.src = audioSrc; // fallback directo
                attachError();
                meetingAudioPlayer.addEventListener('loadedmetadata', finalizePlayer, { once: true });
                try { meetingAudioPlayer.load(); } catch (_) {}
            }
        };
        if (isExternalAudio && fallbackUrl) {
            triedFallback = true;
            attachError();
            meetingAudioPlayer.src = fallbackUrl;
            meetingAudioPlayer.addEventListener('loadedmetadata', finalizePlayer, { once: true });
            try { meetingAudioPlayer.load(); } catch (_) {}
        } else {
            bufferedDownload();
        }
    }
}

// Ajusta automáticamente la altura de los textarea de transcripción
function autoResizeTranscripts() {
    requestAnimationFrame(() => {
        const areas = document.querySelectorAll('#meetingModal textarea.transcript-text');
        areas.forEach((ta) => {
            try {
                ta.style.height = 'auto';
                ta.style.overflowY = 'hidden';
                const newH = Math.max(48, ta.scrollHeight);
                ta.style.height = newH + 'px';
            } catch (_) { /* ignore */ }
        });
    });
}

// ===============================================
// FUNCIONES DE RENDERIZADO
// ===============================================
function renderKeyPoints(keyPoints) {
    if (!keyPoints || !Array.isArray(keyPoints) || keyPoints.length === 0) {
        return '<p class="text-slate-400">No se identificaron puntos clave específicos.</p>';
    }

    return `
        <ul class="key-points-list">
            ${keyPoints.map(point => {
                const pointText = typeof point === 'string' ? point : (point?.text || point?.description || String(point) || 'Punto sin descripción');
                return `<li>${escapeHtml(pointText)}</li>`;
            }).join('')}
        </ul>
    `;
}

function renderTasks(tasks) {
    if (!tasks || !Array.isArray(tasks) || tasks.length === 0) {
        return '<p class="text-slate-400">No se identificaron tareas o acciones específicas.</p>';
    }

    return `
        <ul class="tasks-list">
            ${tasks.map(task => {
                // Normalizar campos para soportar tanto payload del analizador como tareas de BD
                if (typeof task === 'string') {
                    const simpleText = task.trim();
                    return `
                        <li class="task-item">
                            <div class="task-checkbox">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span class="task-text">${escapeHtml(simpleText || 'Tarea sin descripción')}</span>
                        </li>
                    `;
                }

                const text = task?.text || task?.tarea || task?.title || task?.name || task?.descripcion || task?.description || task?.context || '';
                const desc = task?.descripcion || task?.description || '';
                const assignee = task?.assignee || task?.asignado || '';
                const due = task?.dueDate || task?.fecha_limite || task?.fecha_inicio || '';
                const progress = (typeof task?.progress === 'number') ? task.progress : ((typeof task?.progreso === 'number') ? task.progreso : null);

                const lines = [];
                if (assignee) lines.push(`Responsable: ${escapeHtml(assignee)}`);
                if (due) lines.push(`Fecha: ${escapeHtml(String(due))}`);
                if (progress !== null) lines.push(`Progreso: ${progress}%`);

                const meta = lines.length ? ` <small>(${lines.join(' - ')})</small>` : '';

                const main = text ? escapeHtml(String(text)) : (desc ? escapeHtml(String(desc)) : 'Tarea sin descripción');
                return `
                    <li class="task-item">
                        <div class="task-checkbox">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="task-text">${main}${meta}</span>
                    </li>
                `;
            }).join('')}
        </ul>
    `;
}

function renderSpeakers(speakers) {
    if (!speakers || speakers.length === 0) {
        return '<p class="text-slate-400">No se identificaron hablantes específicos.</p>';
    }

    return `
        <div class="speakers-grid">
            ${speakers.map(speaker => `
                <div class="speaker-card">
                    <div class="speaker-avatar">${speaker.charAt(0).toUpperCase()}</div>
                    <span class="speaker-name">${escapeHtml(speaker)}</span>
                </div>
            `).join('')}
        </div>
    `;
}

function renderSegments(segments) {
    if (!segments || segments.length === 0) {
        meetingSegments = [];
        segmentsModified = false;
        return '<p class="text-slate-400">No hay segmentación por hablante disponible.</p>';
    }

    segmentsModified = false;
    meetingSegments = segments.map((segment, index) => {
        const speaker = segment.speaker || `Hablante ${index + 1}`;
        const avatar = speaker.toString().slice(0, 2).toUpperCase();
        const start = typeof segment.start === 'number' ? segment.start : 0;
        const end = typeof segment.end === 'number' ? segment.end : 0;
        const time = segment.timestamp || `${formatTime(start * 1000)} - ${formatTime(end * 1000)}`;
        return { ...segment, speaker, avatar, start, end, time, text: segment.text || '' };
    });

    const html = `
        <div class="transcription-segments" style="display: flex; flex-direction: column; gap: 1rem; padding: 0; margin: 0; width: 100%;">
            ${meetingSegments.map((segment, index) => `
                <div class="transcript-segment" data-segment="${index}" style="background: rgba(51, 65, 85, 0.4); border: 1px solid #475569; border-radius: 12px; padding: 1.5rem; margin: 0; width: 100%; box-sizing: border-box; display: block; position: relative;">
                    <div class="segment-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; gap: 1rem; width: 100%;">
                        <div class="speaker-info" style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                            <div class="speaker-avatar" style="width: 2.5rem; height: 2.5rem; border-radius: 50%; background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #0f172a; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0; text-align: center;">${segment.avatar}</div>
                            <div class="speaker-details" style="display: flex; flex-direction: column; gap: 0.25rem;">
                                <div class="speaker-name" style="font-weight: 600; color: #e2e8f0; font-size: 0.875rem; margin: 0; padding: 0;">${segment.speaker}</div>
                                <div class="speaker-time" style="font-size: 0.75rem; color: #94a3b8; font-family: ui-monospace, monospace; margin: 0; padding: 0;">${segment.time}</div>
                            </div>
                        </div>
                        <div class="segment-controls" style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                            <button class="control-btn" onclick="playSegmentAudio(${index})" title="Reproducir fragmento" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; padding: 0.5rem; color: #94a3b8; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center;">
                                ${getPlayIcon('btn-icon')}
                            </button>
                            <button class="control-btn" onclick="openChangeSpeakerModal(${index})" title="Editar hablante" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; padding: 0.5rem; color: #94a3b8; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center;">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="width: 1rem; height: 1rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                </svg>
                            </button>
                            <button class="control-btn" onclick="openGlobalSpeakerModal(${index})" title="Cambiar hablante globalmente" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; padding: 0.5rem; color: #94a3b8; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center;">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="width: 1rem; height: 1rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15a3 3 0 100-6 3 3 0 000 6zm9 0a3 3 0 10-6 0 3 3 0 006 0zm-9 1.5a4.5 4.5 0 00-4.5 4.5v1.5h9v-1.5a4.5 4.5 0 00-4.5-4.5zm9 0a4.5 4.5 0 014.5 4.5v1.5h-9v-1.5a4.5 4.5 0 014.5-4.5z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="segment-audio" style="margin-bottom: 1rem; width: 100%;">
                        <div class="audio-player-mini" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: rgba(30, 41, 59, 0.6); border: 1px solid #475569; border-radius: 8px; width: 100%; box-sizing: border-box;">
                            <button class="play-btn-mini" onclick="playSegmentAudio(${index})" style="background: #fbbf24; border: none; border-radius: 50%; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0;">
                                ${getPlayIcon('play-icon')}
                            </button>
                            <div class="audio-timeline-mini" onclick="seekAudio(${index}, event)" style="flex: 1; height: 4px; background: rgba(255, 255, 255, 0.2); border-radius: 2px; cursor: pointer; position: relative;">
                                <div class="timeline-progress-mini" style="height: 100%; background: #fbbf24; border-radius: 2px; width: 0%; transition: width 0.1s ease;"></div>
                            </div>
                            <span class="audio-duration-mini" style="font-size: 0.75rem; color: #94a3b8; font-family: ui-monospace, monospace; min-width: 3rem; text-align: right; margin: 0; padding: 0;">${segment.time.split(' - ')[1]}</span>
                        </div>
                    </div>

                    <div class="segment-content" style="width: 100%; clear: both;">
                        <textarea class="transcript-text" placeholder="Texto de la transcripción..." readonly style="background: rgba(30, 41, 59, 0.4); border: 1px solid #475569; border-radius: 8px; padding: 1rem; color: #cbd5e1; font-size: 0.95rem; line-height: 1.6; resize: none; min-height: 60px; width: 100%; font-family: inherit; overflow: hidden; display: block; box-sizing: border-box; margin: 0; white-space: pre-wrap; overflow-wrap: anywhere;">${segment.text}</textarea>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    autoResizeTranscripts();
    return html;
}

function renderTranscription(transcription) {
    if (!transcription) {
        return '<p class="text-slate-400">Transcripción no disponible.</p>';
    }

    // Formatear texto plano con párrafos
    const formatted = transcription.split('\n')
        .filter(line => line.trim())
        .map(line => `<p style="white-space: pre-wrap; overflow-wrap: anywhere; margin: 0 0 0.5rem 0;">${escapeHtml(line)}</p>`)
        .join('');

    autoResizeTranscripts();

    return formatted || '<p class="text-slate-400" style="white-space: pre-wrap; overflow-wrap: anywhere;">Contenido no disponible.</p>';
}

function getPlayIcon(cls) {
    return `<svg class="${cls}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" /></svg>`;
}

function getPauseIcon(cls) {
    return `<svg class="${cls}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" /></svg>`;
}

function updateSegmentButtons(activeIndex) {
    meetingSegments.forEach((_, idx) => {
        const segmentEl = document.querySelector(`[data-segment="${idx}"]`);
        if (!segmentEl) return;
        const headerBtn = segmentEl.querySelector('.segment-controls .control-btn');
        const miniBtn = segmentEl.querySelector('.play-btn-mini');
        const isActive = idx === activeIndex;
        if (headerBtn) headerBtn.innerHTML = isActive ? getPauseIcon('btn-icon') : getPlayIcon('btn-icon');
        if (miniBtn) miniBtn.innerHTML = isActive ? getPauseIcon('play-icon') : getPlayIcon('play-icon');
    });
}

function setSegmentButtonsDisabled(disabled) {
    meetingSegments.forEach((_, idx) => {
        const segmentEl = document.querySelector(`[data-segment="${idx}"]`);
        if (!segmentEl) return;
        const headerBtn = segmentEl.querySelector('.segment-controls .control-btn');
        const miniBtn = segmentEl.querySelector('.play-btn-mini');
        if (headerBtn) headerBtn.disabled = disabled;
        if (miniBtn) miniBtn.disabled = disabled;
    });
}

function resetSegmentProgress(index) {
    const progress = document.querySelector(`[data-segment="${index}"] .timeline-progress-mini`);
    if (progress) {
        progress.style.width = '0%';
    }
}

function playSegmentAudio(segmentIndex) {
    const segment = meetingSegments[segmentIndex];
    if (!segment) return;

    if (!meetingAudioPlayer) {
        meetingAudioPlayer = document.getElementById('meeting-full-audio');
    }
    if (!meetingAudioPlayer) return;

    // Verificar si el audio tiene una fuente válida
    if (!meetingAudioPlayer.src || meetingAudioPlayer.src === window.location.href) {
        console.warn('No hay fuente de audio válida para reproducir segmentos');
        alert('Audio no disponible para esta reunión.');
        return;
    }

    if (currentSegmentIndex === segmentIndex && !meetingAudioPlayer.paused) {
        meetingAudioPlayer.pause();
        if (segmentEndHandler) {
            meetingAudioPlayer.removeEventListener('timeupdate', segmentEndHandler);
            segmentEndHandler = null;
        }
        resetSegmentProgress(segmentIndex);
        updateSegmentButtons(null);
        currentSegmentIndex = null;
        return;
    }

    if (segmentEndHandler) {
        meetingAudioPlayer.removeEventListener('timeupdate', segmentEndHandler);
        segmentEndHandler = null;
    }

    if (!meetingAudioPlayer.paused) {
        meetingAudioPlayer.pause();
    }
    if (currentSegmentIndex !== null && currentSegmentIndex !== segmentIndex) {
        resetSegmentProgress(currentSegmentIndex);
    }
    const stopTime = segment.end;
    const startTime = segment.start;

    segmentEndHandler = () => {
        const duration = stopTime - startTime;
        const elapsed = meetingAudioPlayer.currentTime - startTime;
        const progressEl = document.querySelector(`[data-segment="${segmentIndex}"] .timeline-progress-mini`);
        if (progressEl && duration > 0) {
            const percent = Math.min(100, Math.max(0, (elapsed / duration) * 100));
            progressEl.style.width = percent + '%';
        }

        if (meetingAudioPlayer.currentTime >= stopTime) {
            meetingAudioPlayer.pause();
            meetingAudioPlayer.removeEventListener('timeupdate', segmentEndHandler);
            segmentEndHandler = null;
            resetSegmentProgress(segmentIndex);
            updateSegmentButtons(null);
            currentSegmentIndex = null;
        }
    };

    meetingAudioPlayer.addEventListener('timeupdate', segmentEndHandler);

    const startPlayback = () => {
        resetSegmentProgress(segmentIndex);
        meetingAudioPlayer.currentTime = startTime;
        meetingAudioPlayer.play().catch(error => {
            console.warn('Error reproduciendo segmento de audio:', error);
            alert('No se pudo reproducir este segmento de audio.');
            resetSegmentProgress(segmentIndex);
            updateSegmentButtons(null);
            currentSegmentIndex = null;
            if (segmentEndHandler) {
                meetingAudioPlayer.removeEventListener('timeupdate', segmentEndHandler);
                segmentEndHandler = null;
            }
        });
        currentSegmentIndex = segmentIndex;
        updateSegmentButtons(segmentIndex);
    };

    if (meetingAudioPlayer.readyState < 2) {
        setSegmentButtonsDisabled(true);
        const onLoaded = () => {
            meetingAudioPlayer.removeEventListener('canplaythrough', onLoaded);
            meetingAudioPlayer.removeEventListener('loadeddata', onLoaded);
            setSegmentButtonsDisabled(false);
            startPlayback();
        };
        meetingAudioPlayer.addEventListener('canplaythrough', onLoaded);
        meetingAudioPlayer.addEventListener('loadeddata', onLoaded);
        return;
    }

    startPlayback();
}

function openChangeSpeakerModal(segmentIndex) {
    selectedSegmentIndex = segmentIndex;
    const currentName = meetingSegments[segmentIndex].speaker;
    document.getElementById('speaker-name-input').value = currentName;
    document.getElementById('change-speaker-modal').classList.add('show');
}

function closeChangeSpeakerModal() {
    document.getElementById('change-speaker-modal').classList.remove('show');
}

function confirmSpeakerChange() {
    const input = document.getElementById('speaker-name-input');
    const newName = input.value.trim();
    if (!newName) {
        showNotification('Debes ingresar un nombre válido', 'warning');
        return;
    }

    meetingSegments[selectedSegmentIndex].speaker = newName;
    segmentsModified = true;
    const element = document.querySelector(`[data-segment="${selectedSegmentIndex}"] .speaker-name`);
    if (element) {
        element.textContent = newName;
    }

    closeChangeSpeakerModal();
    showNotification('Hablante actualizado correctamente', 'success');
}

function openGlobalSpeakerModal(segmentIndex) {
    selectedSegmentIndex = segmentIndex;
    const currentName = meetingSegments[segmentIndex].speaker;
    document.getElementById('current-speaker-name').value = currentName;
    document.getElementById('global-speaker-name-input').value = '';
    document.getElementById('change-global-speaker-modal').classList.add('show');
}

function closeGlobalSpeakerModal() {
    document.getElementById('change-global-speaker-modal').classList.remove('show');
}

function confirmGlobalSpeakerChange() {
    const newName = document.getElementById('global-speaker-name-input').value.trim();
    const currentName = document.getElementById('current-speaker-name').value;
    if (!newName) {
        showNotification('Debes ingresar un nombre válido', 'warning');
        return;
    }

    meetingSegments.forEach((segment, idx) => {
        if (segment.speaker === currentName) {
            segment.speaker = newName;
            const el = document.querySelector(`[data-segment="${idx}"] .speaker-name`);
            if (el) {
                el.textContent = newName;
            }
        }
    });

    segmentsModified = true;

    closeGlobalSpeakerModal();
    showNotification('Hablantes actualizados correctamente', 'success');
}

function seekAudio(segmentIndex, event) {
    const timeline = event.currentTarget;
    const rect = timeline.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const percentage = (clickX / rect.width) * 100;

    const progress = timeline.querySelector('.timeline-progress-mini');
    if (progress) {
        progress.style.width = percentage + '%';
    }

    const segment = meetingSegments[segmentIndex];
    if (!segment) return;
    const duration = segment.end - segment.start;
    const targetTime = segment.start + (duration * (percentage / 100));

    if (!meetingAudioPlayer) {
        meetingAudioPlayer = document.getElementById('meeting-full-audio');
    }
    if (!meetingAudioPlayer) return;

    // Verificar si el audio tiene una fuente válida antes de intentar cambiar currentTime
    if (!meetingAudioPlayer.src || meetingAudioPlayer.src === window.location.href) {
        console.warn('No hay fuente de audio válida para hacer seek');
        return;
    }

    try {
        meetingAudioPlayer.currentTime = targetTime;
    } catch (error) {
        console.warn('Error al hacer seek en el audio:', error);
    }
}

function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
}

// ===============================================
// CONTROL DEL MODAL
// ===============================================
async function closeMeetingModal() {
    if (segmentsModified && currentModalMeeting) {
        try {
            await fetch(`/api/meetings/${currentModalMeeting.id}/segments`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ segments: meetingSegments })
            });
        } catch (error) {
            console.error('Error guardando segmentos:', error);
        }
        segmentsModified = false;
    }

    const modal = document.getElementById('meetingModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';

        // Esperar a que termine la animación antes de ocultar
        setTimeout(async () => {
            if (modal && !modal.classList.contains('active')) {
                modal.style.display = 'none';
                modal.remove();

                if (currentModalMeeting?.needs_encryption) {
                    try {
                        const encryptResponse = await fetch(`/api/meetings/${currentModalMeeting.id}/encrypt`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                segments: currentModalMeeting.segments,
                                summary: currentModalMeeting.summary,
                                key_points: currentModalMeeting.key_points,
                                tasks: currentModalMeeting.tasks
                            })
                        });

                        if (!encryptResponse.ok) {
                            throw new Error('Error encrypting meeting');
                        }

                        currentModalMeeting.needs_encryption = false;
                    } catch (error) {
                        console.error('Error encrypting meeting:', error);
                    }
                }

                cleanupModalFiles();
            }
        }, 300);
    }
}

// Hacer la función disponible globalmente
window.closeMeetingModal = closeMeetingModal;

// ===============================================
// FUNCIONES DE ESTADO
// ===============================================
function showLoadingState(container, message = 'Cargando reuniones...') {
    if (!container) return;
    container.innerHTML = `
        <div class="loading-state flex flex-col items-center justify-center py-8">
            <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mb-3"></div>
            <p class="text-slate-400 text-center">${message}</p>
        </div>
    `;
}

function showModalLoadingState() {
    const modalHtml = `
        <div class="meeting-modal active" id="meetingModal">
            <div class="modal-content loading">
                <div class="loading-state">
                    <div class="loading-header">
                        <h2>Preparando reunión</h2>
                        <p>Descargando y procesando archivos...</p>
                    </div>

                    <div class="loading-progress">
                        <div class="loading-spinner"></div>
                        <div class="loading-steps">
                            <div class="loading-step active" id="step-1">
                                <div class="step-icon">📥</div>
                                <span>Descargando transcripción</span>
                            </div>
                            <div class="loading-step" id="step-2">
                                <div class="step-icon">🔓</div>
                                <span>Descifrando archivo</span>
                            </div>
                            <div class="loading-step" id="step-3">
                                <div class="step-icon">🎵</div>
                                <span>Descargando audio</span>
                            </div>
                            <div class="loading-step" id="step-4">
                                <div class="step-icon">⚡</div>
                                <span>Procesando contenido</span>
                            </div>
                        </div>
                    </div>

                    <div class="loading-tip">
                        <small>💡 Esto puede tomar unos segundos dependiendo del tamaño de los archivos</small>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('meetingModal');
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
}

function updateLoadingStep(stepNumber) {
    const modal = document.getElementById('meetingModal');
    if (!modal) return;

    // Marcar paso anterior como completado
    for (let i = 1; i < stepNumber; i++) {
        const step = modal.querySelector(`#step-${i}`);
        if (step) {
            step.classList.remove('active');
            step.classList.add('completed');
        }
    }

    // Marcar paso actual como activo
    const currentStep = modal.querySelector(`#step-${stepNumber}`);
    if (currentStep) {
        currentStep.classList.add('active');
    }
}

function showErrorState(container, message, retryCallback) {
    if (!container) return;
    container.innerHTML = `
        <div class="error-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <h3>Error de conexión</h3>
            <div class="subtitle" style="color: #fbbf24; font-weight: 500; margin-bottom: 0.75rem;">No se pudo conectar con el servidor</div>
            <p>${message || 'Intenta recargar la página o verifica tu conexión a internet.'}</p>
            <button class="btn-primary" id="retry-load">Reintentar</button>
        </div>
    `;

    const retryBtn = container.querySelector('#retry-load');
    if (retryBtn && typeof retryCallback === 'function') {
        retryBtn.addEventListener('click', retryCallback);
    }
}

// ===============================================
// BÚSQUEDA
// ===============================================
function handleSearch(event) {
    const query = event.target.value.toLowerCase().trim();

    if (!query) {
        renderMeetings(currentMeetings, '#my-meetings', 'No tienes reuniones');
        return;
    }

    const filtered = currentMeetings.filter(meeting =>
        meeting.meeting_name.toLowerCase().includes(query) ||
        (meeting.folder_name && meeting.folder_name.toLowerCase().includes(query)) ||
        (meeting.preview_text && meeting.preview_text.toLowerCase().includes(query))
    );

    renderMeetings(filtered, '#my-meetings', 'No se encontraron reuniones');
}

// ===============================================
// LIMPIEZA DE ARCHIVOS TEMPORALES
// ===============================================
async function cleanupModalFiles() {
    try {
        await fetch('/api/meetings/cleanup', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
    } catch (error) {
        console.error('Error cleaning up files:', error);
    }
}

// ===============================================
// UTILIDADES
// ===============================================
function normalizeDriveUrl(url) {
    if (!url || typeof url !== 'string') return url;

    try {
        const parsedUrl = new URL(url);

        // Verificar si el ID viene como parámetro (?id=)
        const idParam = parsedUrl.searchParams.get('id');
        if (idParam) {
            return `https://drive.google.com/uc?export=download&id=${idParam}`;
        }

        // Buscar el ID en la ruta (/d/ID/)
        const pathMatch = parsedUrl.pathname.match(/\/d\/([^/]+)/);
        if (pathMatch) {
            return `https://drive.google.com/uc?export=download&id=${pathMatch[1]}`;
        }
    } catch (e) {
        // En caso de URL inválida, intentar coincidir con expresiones regulares básicas
        const regexMatch = url.match(/drive\.google\.com\/.*[?&]id=([^&]+)/);
        if (regexMatch) {
            return `https://drive.google.com/uc?export=download&id=${regexMatch[1]}`;
        }
        const pathRegexMatch = url.match(/drive\.google\.com\/file\/d\/([^\/]+)/);
        if (pathRegexMatch) {
            return `https://drive.google.com/uc?export=download&id=${pathRegexMatch[1]}`;
        }
    }

    return url;
}

// ===============================================
// FUNCIONES DE DESCARGA
// ===============================================
async function downloadJuFile(meetingId) {
    try {
        window.location.href = `/api/meetings/${meetingId}/download-ju`;
    } catch (error) {
        console.error('Error downloading .ju file:', error);
        alert('Error al descargar el archivo .ju');
    }
}

async function downloadAudioFile(meetingId) {
    try {
        window.location.href = `/api/meetings/${meetingId}/download-audio`;
    } catch (error) {
        console.error('Error downloading audio file:', error);
        alert('Error al descargar el archivo de audio');
    }
}

async function saveMeetingTitle(meetingId, newTitle) {
    try {
        const response = await fetch(`/api/meetings/${meetingId}/name`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ name: newTitle })
        });

        if (!response.ok) {
            throw new Error('Error al guardar el título');
        }

        return await response.json();
    } catch (error) {
        console.error('Error saving meeting title:', error);
        throw error;
    }
}

async function deleteMeeting(meetingId) {
    // Mostrar modal de confirmación personalizado
    showDeleteConfirmationModal(meetingId);
}

function editMeetingName(meetingId) {
    const meeting = currentMeetings.find(m => m.id == meetingId);
    if (!meeting) return;

    showEditNameModal(meetingId, meeting.meeting_name);
}

function showEditNameModal(meetingId, currentName) {
    const modalHTML = `
        <div class="meeting-modal active" id="editNameModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Editar Nombre de Reunión
                        </h2>
                        <p class="modal-subtitle">Cambia el nombre de la reunión y se actualizará en Drive y la base de datos</p>
                    </div>

                    <button class="close-btn" onclick="closeEditNameModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="modal-section">
                        <div class="edit-name-content">
                            <label class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                Nombre de la reunión
                            </label>
                            <input
                                type="text"
                                id="newMeetingName"
                                class="form-input"
                                value="${escapeHtml(currentName)}"
                                placeholder="Ingresa el nuevo nombre"
                                maxlength="100"
                            >
                            <small class="form-help">
                                Se actualizarán los archivos en Drive (.ju y audio) y la base de datos
                            </small>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button class="modal-btn secondary" onclick="closeEditNameModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar
                        </button>
                        <button class="modal-btn primary" onclick="confirmEditMeetingName(${meetingId})">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.body.style.overflow = 'hidden';

    // Enfocar y seleccionar el texto del input
    setTimeout(() => {
        const input = document.getElementById('newMeetingName');
        input.focus();
        input.select();
    }, 100);
}

function closeEditNameModal() {
    const modal = document.getElementById('editNameModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';

        setTimeout(() => {
            if (modal && !modal.classList.contains('active')) {
                modal.remove();
            }
        }, 300);
    }
}

async function confirmEditMeetingName(meetingId) {
    const newName = document.getElementById('newMeetingName').value.trim();

    if (!newName) {
        showNotification('El nombre no puede estar vacío', 'error');
        return;
    }

    const meeting = currentMeetings.find(m => m.id == meetingId);
    if (!meeting) {
        showNotification('Reunión no encontrada', 'error');
        return;
    }

    if (newName === meeting.meeting_name) {
        closeEditNameModal();
        return;
    }

    try {
        closeEditNameModal();
        showNotification('Actualizando nombre en Drive y base de datos...', 'info');

        const response = await fetch(`/api/meetings/${meetingId}/name`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: newName
            })
        });

        if (!response.ok) {
            throw new Error('Error al actualizar el nombre');
        }

        const data = await response.json();

        if (data.success) {
            showNotification('Nombre actualizado correctamente en Drive y base de datos', 'success');
            // Recargar la lista de reuniones para reflejar los cambios
            loadMyMeetings();
        } else {
            throw new Error(data.message || 'Error al actualizar el nombre');
        }

    } catch (error) {
        console.error('Error updating meeting name:', error);
        showNotification('Error al actualizar el nombre: ' + error.message, 'error');
    }
}

function showDeleteConfirmationModal(meetingId) {
    const meeting = currentMeetings.find(m => m.id == meetingId);
    const meetingName = meeting ? meeting.meeting_name : 'reunión';

    const modalHTML = `
        <div class="meeting-modal active" id="deleteConfirmationModal">
            <div class="modal-content delete-modal">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            Confirmar Eliminación
                        </h2>
                        <p class="modal-subtitle">Esta acción no se puede deshacer</p>
                    </div>

                    <button class="close-btn" onclick="closeDeleteConfirmationModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="delete-confirmation-content">
                        <div class="warning-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="delete-title">¿Estás seguro?</h3>
                        <p class="delete-message">
                            Estás a punto de eliminar la reunión <strong>"${escapeHtml(meetingName)}"</strong>.
                            Esta acción eliminará permanentemente:
                        </p>
                        <ul class="delete-items">
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M9 9v6l6-6" />
                                </svg>
                                Audio de la reunión
                            </li>
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                                Transcripción completa
                            </li>
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Análisis y resumen
                            </li>
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Puntos clave y tareas
                            </li>
                        </ul>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn secondary" onclick="closeDeleteConfirmationModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar
                        </button>
                        <button class="modal-btn danger" onclick="confirmDeleteMeeting(${meetingId})">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Eliminar Reunión
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.body.style.overflow = 'hidden';
}

function closeDeleteConfirmationModal() {
    const modal = document.getElementById('deleteConfirmationModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';

        setTimeout(() => {
            if (modal && !modal.classList.contains('active')) {
                modal.remove();
            }
        }, 300);
    }
}

async function confirmDeleteMeeting(meetingId) {
    closeDeleteConfirmationModal();

    try {
        showNotification('Eliminando archivos de Drive...', 'info');

        const response = await fetch(`/api/meetings/${meetingId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            try {
                const data = await response.json();
                throw new Error(data.message || 'Error al eliminar la reunión');
            } catch (e) {
                throw new Error('Error al eliminar la reunión');
            }
        }

        const data = await response.json();

        if (data.success) {
            showNotification('Reunión eliminada correctamente de Drive y base de datos', 'success');
            // Recargar la lista de reuniones
            loadMyMeetings();
            closeMeetingModal();
        } else {
            throw new Error(data.message || 'Error al eliminar la reunión');
        }

    } catch (error) {
        console.error('Error deleting meeting:', error);
        showNotification(error.message, 'error');
    }
}

// ===============================================
// FUNCIONALIDAD DE CONTENEDORES
// ===============================================
let containers = [];
let currentContainer = null;
let isEditMode = false;

// Inicializar funcionalidad de contenedores
function initializeContainers() {
    // Event listeners para contenedores
    const createContainerBtn = document.getElementById('create-container-btn');
    if (createContainerBtn) {
        createContainerBtn.addEventListener('click', openCreateContainerModal);
    }

    const cancelModalBtn = document.getElementById('cancel-modal-btn');
    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', closeContainerModal);
    }

    const saveContainerBtn = document.getElementById('save-container-btn');
    if (saveContainerBtn) {
        saveContainerBtn.addEventListener('click', saveContainer);
    }

    const containerForm = document.getElementById('container-form');
    if (containerForm) {
        containerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveContainer();
        });
    }

    // Contador de caracteres en descripción
    const descriptionField = document.getElementById('container-description');
    if (descriptionField) {
        descriptionField.addEventListener('input', updateCharacterCount);
    }

    // Cerrar modal al hacer clic fuera
    const containerModal = document.getElementById('container-modal');
    if (containerModal) {
        containerModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeContainerModal();
            }
        });
    }

    // Cerrar modal con ESC (ya está en setupEventListeners, pero agregamos específico para contenedores)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('container-modal').classList.contains('hidden')) {
            closeContainerModal();
        }
    });
}

async function loadContainers() {
    const containersTab = document.getElementById('containers');
    showLoadingState(containersTab, 'Cargando contenedores...');
    try {
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
    }
}

function renderContainers() {
    const containersTab = document.getElementById('containers');

    if (!containersTab) return;

    if (containers.length === 0) {
        containersTab.innerHTML = `
            <div class="text-center py-20">
                <div class="mx-auto max-w-md">
                    <div class="bg-slate-800/30 backdrop-blur-sm rounded-full h-24 w-24 mx-auto mb-6 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-300 mb-3">No hay contenedores</h3>
                    <p class="text-slate-400 mb-6">Crea tu primer contenedor para organizar tus reuniones</p>
                    <button onclick="openCreateContainerModal()" class="bg-gradient-to-r from-yellow-400 to-amber-400 text-slate-900 px-6 py-3 rounded-xl font-semibold hover:from-yellow-300 hover:to-amber-300 transition-all duration-200 shadow-lg shadow-yellow-400/20 hover:shadow-yellow-400/30">
                        Crear Primer Contenedor
                    </button>
                </div>
            </div>
        `;
        return;
    }

    containersTab.innerHTML = `
        <div class="space-y-4">
            ${containers.map(container => createContainerCard(container)).join('')}
        </div>
    `;

    // Agregar event listeners a los botones de cada contenedor
    containers.forEach(container => {
        const editBtn = document.getElementById(`edit-container-${container.id}`);
        const deleteBtn = document.getElementById(`delete-container-${container.id}`);

        if (editBtn) {
            editBtn.addEventListener('click', () => openEditContainerModal(container));
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => deleteContainer(container.id));
        }
    });
}

function openCreateContainerModal() {
    isEditMode = false;
    currentContainer = null;

    document.getElementById('modal-title').textContent = 'Crear Contenedor';
    document.getElementById('save-btn-text').textContent = 'Guardar';

    // Limpiar formulario
    document.getElementById('container-form').reset();
    clearContainerErrors();
    updateCharacterCount();

    document.getElementById('container-modal').classList.remove('hidden');
    document.getElementById('container-name').focus();
}

function openEditContainerModal(container) {
    isEditMode = true;
    currentContainer = container;

    document.getElementById('modal-title').textContent = 'Editar Contenedor';
    document.getElementById('save-btn-text').textContent = 'Actualizar';

    // Llenar formulario
    document.getElementById('container-name').value = container.name;
    document.getElementById('container-description').value = container.description || '';
    clearContainerErrors();
    updateCharacterCount();

    document.getElementById('container-modal').classList.remove('hidden');
    document.getElementById('container-name').focus();
}

function closeContainerModal() {
    document.getElementById('container-modal').classList.add('hidden');
    currentContainer = null;
    isEditMode = false;
    clearContainerErrors();
}

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
        clearContainerErrors();

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
            closeContainerModal();
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
    // Mostrar modal de confirmación personalizado
    showDeleteContainerConfirmationModal(containerId);
}

function showDeleteContainerConfirmationModal(containerId) {
    // Buscar el contenedor en los datos actuales
    const container = containers.find(c => c.id == containerId);
    const containerName = container ? container.name : 'contenedor';

    const modalHTML = `
        <div class="meeting-modal active" id="deleteContainerConfirmationModal">
            <div class="modal-content delete-modal">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            Confirmar Eliminación
                        </h2>
                        <p class="modal-subtitle">Esta acción no se puede deshacer</p>
                    </div>

                    <button class="close-btn" onclick="closeDeleteContainerConfirmationModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="delete-confirmation-content">
                        <div class="warning-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="delete-title">¿Estás seguro?</h3>
                        <p class="delete-message">
                            Estás a punto de eliminar el contenedor <strong>"${escapeHtml(containerName)}"</strong>.
                            Esta acción eliminará permanentemente:
                        </p>
                        <ul class="delete-items">
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                El contenedor y su configuración
                            </li>
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Las asociaciones con reuniones
                            </li>
                        </ul>
                        <p class="delete-warning">
                            <strong>Nota:</strong> Las reuniones no se eliminarán, solo se desvinculan del contenedor.
                        </p>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="modal-footer-buttons">
                        <button class="modal-btn secondary" onclick="closeDeleteContainerConfirmationModal()">
                            Cancelar
                        </button>

                        <button class="modal-btn danger" onclick="confirmDeleteContainer(${containerId})">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Eliminar Contenedor
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeDeleteContainerConfirmationModal() {
    const modal = document.getElementById('deleteContainerConfirmationModal');
    if (modal) {
        modal.remove();
    }
}

async function confirmDeleteContainer(containerId) {
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
            closeDeleteContainerConfirmationModal();
        } else {
            throw new Error(data.message);
        }

    } catch (error) {
        console.error('Error deleting container:', error);
        showNotification('Error al eliminar: ' + error.message, 'error');
    }
}

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

function clearContainerErrors() {
    const errorElements = document.querySelectorAll('[id$="-error"]');
    errorElements.forEach(element => {
        element.classList.add('hidden');
        element.textContent = '';
    });
}

async function openDownloadModal(meetingId, sharedMeetingId = null) {
    // Prevenir ejecución múltiple
    if (window.downloadModalProcessing) {
        console.log('Modal de descarga ya en proceso...');
        return;
    }

    window.downloadModalProcessing = true;

    console.log('Iniciando descarga para reunión:', meetingId);

    try {
        // Cerrar el modal de contenedor si está abierto
        previousContainerForDownload = currentContainerForMeetings;
        if (previousContainerForDownload) {
            closeContainerMeetingsModal();
        }

        // Mostrar loading inicial
        showDownloadModalLoading(meetingId);

    // Paso 1: Descargar y desencriptar el archivo .ju desde Drive (con timeout y manejo de errores)
        console.log('Descargando y desencriptando archivo .ju...');
        const controller = new AbortController();
        const timeoutMs = 15000; // 15s para evitar esperas largas
        const timeoutId = setTimeout(() => controller.abort('timeout'), timeoutMs);

        let data = null;
        let response = null;
        try {
            response = await fetch(`/api/meetings/${meetingId}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                signal: controller.signal,
            });
        } catch (netErr) {
            console.error('Fallo de red al obtener reunión:', netErr);
            clearTimeout(timeoutId);
            // Intentar resolver enlaces directos si es compartida
            if (sharedMeetingId) {
                const links = await tryResolveSharedDriveLinks(sharedMeetingId);
                if (links) {
                    showDownloadFallbackModal(meetingId, 'No se pudo preparar la descarga automáticamente.', links);
                    return;
                }
            }
            showDownloadFallbackModal(meetingId, 'No se pudo conectar para preparar la descarga.');
            return;
        } finally {
            clearTimeout(timeoutId);
        }

        if (!response.ok) {
            let serverMsg = '';
            try { serverMsg = await response.text(); } catch(_) {}
            console.error('Error HTTP al preparar descarga', response.status, serverMsg);
            if (sharedMeetingId) {
                const links = await tryResolveSharedDriveLinks(sharedMeetingId);
                if (links) {
                    showDownloadFallbackModal(meetingId, `Error ${response.status} al preparar la descarga.`, links);
                    return;
                }
            }
            showDownloadFallbackModal(meetingId, `Error ${response.status} al preparar la descarga.`);
            return;
        }

        try {
            data = await response.json();
        } catch (parseErr) {
            console.error('Error parseando JSON de la reunión:', parseErr);
            if (sharedMeetingId) {
                const links = await tryResolveSharedDriveLinks(sharedMeetingId);
                if (links) {
                    showDownloadFallbackModal(meetingId, 'Respuesta inválida del servidor al preparar la descarga.', links);
                    return;
                }
            }
            showDownloadFallbackModal(meetingId, 'Respuesta inválida del servidor al preparar la descarga.');
            return;
        }

        if (!data.success) {
            throw new Error(data.message || 'Error al procesar el archivo de la reunión');
        }

        console.log('Archivo descargado y desencriptado exitosamente');

        // Paso 2: Crear y mostrar el modal de selección
        createDownloadModal();

    // Paso 3: Guardar los datos y mostrar opciones
    window.downloadMeetingData[meetingId] = data.meeting;
    window.lastOpenedDownloadMeetingId = meetingId;
    const modals = document.querySelectorAll('[name="download-meeting"]');
        if (modals && modals.length) {
            modals.forEach(m => {
                m.dataset.meetingId = meetingId;
        const inner = m.querySelector('.download-modal');
        if (inner) inner.dataset.meetingId = meetingId;
            });

            // Inicializar los event listeners del modal
            initializeDownloadModal();

            // Mostrar el modal con las opciones
            showDownloadModalOptions(data.meeting);

            console.log('Modal de selección mostrado para reunión:', meetingId);
        } else {
            throw new Error('No se pudo crear el modal de descarga');
        }

    } catch (error) {
        console.error('Error en el proceso de descarga:', error);

        // Cerrar loading modal si está abierto
        closeDownloadModal();

        // Mostrar error específico
        let errorMessage = 'Error al procesar la reunión para descarga';
        if (error.message && error.message.includes('transcript')) {
            errorMessage = 'La transcripción de esta reunión no está disponible. No se puede generar el PDF.';
        } else if (error.message) {
            errorMessage = error.message;
        }

    // Mostrar un modal de fallback con botones de descarga directa
    if (sharedMeetingId) {
        const links = await tryResolveSharedDriveLinks(sharedMeetingId);
        if (links) {
            showDownloadFallbackModal(meetingId, errorMessage, links);
        } else {
            showDownloadFallbackModal(meetingId, errorMessage);
        }
    } else {
        showDownloadFallbackModal(meetingId, errorMessage);
    }
    } finally {
        // Liberar el lock después de un delay
        setTimeout(() => {
            window.downloadModalProcessing = false;
        }, 1000);
    }
}

// Modal simple de fallback con descargas directas cuando la preparación falla
function showDownloadFallbackModal(meetingId, message, directLinks = null) {
    // Cerrar loading si existe
    const loadingModal = document.getElementById('downloadLoadingModal');
    if (loadingModal) {
        loadingModal.remove();
        document.body.style.overflow = '';
    }

    const html = `
        <div class="fixed inset-0 z-[9500] overflow-hidden" id="downloadFallbackModal">
            <div class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>
            <div class="fixed inset-0 flex items-center justify-center">
                <div class="relative bg-slate-800 rounded-xl shadow-2xl w-full max-w-md mx-4 border border-slate-700 p-6">
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Descarga directa</h2>
                        <button class="text-slate-400 hover:text-white" onclick="(function(){document.getElementById('downloadFallbackModal')?.remove();document.body.style.overflow='';})();">✕</button>
                    </div>
                    <p class="text-slate-300 mb-4">${escapeHtml(message || 'No se pudo preparar la descarga.')}</p>
                    <div class="space-y-3">
                        ${directLinks?.ju_link ? `<a href="${directLinks.ju_link}" target="_blank" rel="noopener" class="block w-full text-center px-4 py-3 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded-lg">Descargar archivo .ju</a>` : `<a href="/api/meetings/${meetingId}/download-ju" class="block w-full text-center px-4 py-3 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded-lg">Descargar archivo .ju</a>`}
                        ${directLinks?.audio_link ? `<a href="${directLinks.audio_link}" target="_blank" rel="noopener" class="block w-full text-center px-4 py-3 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded-lg">Descargar audio</a>` : `<a href="/api/meetings/${meetingId}/download-audio" class="block w-full text-center px-4 py-3 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded-lg">Descargar audio</a>`}
                        <a href="/google/reauth" class="block w-full text-center px-4 py-3 bg-amber-500 hover:bg-amber-400 text-slate-900 rounded-lg">Revalidar sesión de Google Drive</a>
                    </div>
                </div>
            </div>
        </div>`;

    // Quitar cualquier modal previo de fallback para evitar duplicados
    document.getElementById('downloadFallbackModal')?.remove();
    document.body.insertAdjacentHTML('beforeend', html);
    document.body.style.overflow = 'hidden';
}

// Resuelve enlaces directos a Drive para reuniones compartidas
async function tryResolveSharedDriveLinks(sharedMeetingId) {
    try {
        const res = await fetch('/api/shared-meetings/resolve-drive-links', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ shared_meeting_id: sharedMeetingId })
        });
        if (!res.ok) return null;
        const data = await res.json();
        if (!data?.success) return null;
        return { ju_link: data.ju_link || null, audio_link: data.audio_link || null };
    } catch (e) {
        console.warn('tryResolveSharedDriveLinks failed', e);
        return null;
    }
}

function showDownloadModalLoading(meetingId) {
    // Crear y mostrar modal de loading centrado perfectamente
    const loadingModal = `
        <div class="fixed inset-0 z-[9500] overflow-hidden" id="downloadLoadingModal">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>

            <!-- Container centrado con flexbox -->
            <div class="fixed inset-0 flex items-center justify-center">
                <div class="relative bg-slate-800 rounded-xl shadow-2xl w-full max-w-md mx-4 border border-slate-700">

                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-slate-700">
                        <h2 class="text-xl font-semibold text-white">Preparando descarga</h2>
                        <button class="text-slate-400 hover:text-white transition-colors" onclick="closeDownloadModal()">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6">
                        <div class="flex items-center justify-center space-x-3 mb-6">
                            <!-- Spinner -->
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-500"></div>
                            <div class="text-slate-300">
                                <p class="font-medium">Descargando archivo .ju...</p>
                                <p class="text-sm text-slate-400">Desencriptando contenido de la reunión</p>
                            </div>
                        </div>

                        <!-- Progress steps -->
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 text-sm">
                                <div class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                                <span class="text-slate-300">Conectando con Google Drive...</span>
                            </div>
                            <div class="flex items-center space-x-3 text-sm">
                                <div class="w-3 h-3 bg-slate-600 rounded-full"></div>
                                <span class="text-slate-400">Descargando archivo encriptado...</span>
                            </div>
                            <div class="flex items-center space-x-3 text-sm">
                                <div class="w-3 h-3 bg-slate-600 rounded-full"></div>
                                <span class="text-slate-400">Procesando contenido...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', loadingModal);
    document.body.style.overflow = 'hidden';
}function showDownloadModalOptions(meetingData) {
    // Cerrar modal de loading
    const loadingModal = document.getElementById('downloadLoadingModal');
    if (loadingModal) {
        loadingModal.remove();
        document.body.style.overflow = '';
    }

    // Abrir el modal de opciones de descarga con los datos disponibles
    window.dispatchEvent(new CustomEvent('open-modal', {
        detail: 'download-meeting'
    }));

    // Mantener selección del usuario (no forzar checks por defecto)
}

function createDownloadModal() {
    // Eliminar cualquier modal existente para evitar instancias duplicadas
    document.querySelectorAll('[name="download-meeting"]').forEach(m => m.remove());

    // Crear el modal centrado perfectamente
    const modalHtml = `
        <div x-data="{ show: false }"
             x-show="show"
             x-on:open-modal.window="$event.detail == 'download-meeting' ? show = true : null"
             x-on:close-modal.window="$event.detail == 'download-meeting' ? show = false : null"
             x-on:close.stop="show = false"
             x-on:keydown.escape.window="show = false"
             name="download-meeting"
             class="fixed inset-0 z-[9500] overflow-hidden download-modal"
             style="display: none;">

            <!-- Overlay con fondo semi-transparente -->
            <div class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>

            <!-- Container centrado con flexbox -->
            <div class="fixed inset-0 flex items-center justify-center">
                <div class="relative bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg mx-4 border border-slate-700">

                    <!-- Contenido del modal -->
                    <div class="p-6 space-y-6">
                        <div class="text-center border-b border-slate-700 pb-4">
                            <h2 class="text-2xl font-semibold text-white mb-2">📄 Descargar Reunión</h2>
                            <p class="text-slate-300 text-sm">Selecciona el contenido que deseas incluir en el PDF</p>
                        </div>

                        <!-- Opciones de descarga -->
                        <div class="space-y-3">
                            <label class="flex items-center space-x-3 p-4 rounded-lg hover:bg-slate-700 cursor-pointer transition-colors border border-slate-600 hover:border-slate-500">
                                <input type="checkbox" value="summary" class="download-option w-5 h-5 text-yellow-500 bg-slate-700 border-slate-600 rounded focus:ring-yellow-500 focus:ring-2">
                                <div class="flex-1">
                                    <span class="text-slate-200 font-medium text-lg">� Resumen</span>
                                    <p class="text-slate-400 text-sm">Resumen ejecutivo de la reunión</p>
                                </div>
                            </label>

                            <label class="flex items-center space-x-3 p-4 rounded-lg hover:bg-slate-700 cursor-pointer transition-colors border border-slate-600 hover:border-slate-500">
                                <input type="checkbox" value="key_points" class="download-option w-5 h-5 text-yellow-500 bg-slate-700 border-slate-600 rounded focus:ring-yellow-500 focus:ring-2">
                                <div class="flex-1">
                                    <span class="text-slate-200 font-medium text-lg">🎯 Puntos Clave</span>
                                    <p class="text-slate-400 text-sm">Aspectos más importantes discutidos</p>
                                </div>
                            </label>

                            <label class="flex items-center space-x-3 p-4 rounded-lg hover:bg-slate-700 cursor-pointer transition-colors border border-slate-600 hover:border-slate-500">
                                <input type="checkbox" value="transcription" class="download-option w-5 h-5 text-yellow-500 bg-slate-700 border-slate-600 rounded focus:ring-yellow-500 focus:ring-2">
                                <div class="flex-1">
                                    <span class="text-slate-200 font-medium text-lg">📝 Transcripción</span>
                                    <p class="text-slate-400 text-sm">Transcripción completa de la conversación</p>
                                </div>
                            </label>

                            <label class="flex items-center space-x-3 p-4 rounded-lg hover:bg-slate-700 cursor-pointer transition-colors border border-slate-600 hover:border-slate-500">
                                <input type="checkbox" value="tasks" class="download-option w-5 h-5 text-yellow-500 bg-slate-700 border-slate-600 rounded focus:ring-yellow-500 focus:ring-2">
                                <div class="flex-1">
                                    <span class="text-slate-200 font-medium text-lg">✅ Tareas</span>
                                    <p class="text-slate-400 text-sm">Acciones y tareas asignadas</p>
                                </div>
                            </label>
                        </div>

                        <!-- Vista previa (se abrirá en un modal independiente) -->
                        <div class="pt-2">
                            <button class="preview-pdf w-full px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg transition-colors">Vista previa</button>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-700">
                            <button class="px-6 py-3 text-slate-300 hover:text-white bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors font-medium"
                                    x-on:click="$dispatch('close-modal','download-meeting')">
                                Cancelar
                            </button>
                            <button class="confirm-download px-8 py-3 bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-semibold rounded-lg transition-colors">
                                <span class="flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span>Descargar PDF</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
}function closeDownloadModal() {
    // Liberar el lock de procesamiento
    window.downloadModalProcessing = false;

    // Cerrar modal de loading si está abierto
    const loadingModal = document.getElementById('downloadLoadingModal');
    if (loadingModal) {
        loadingModal.remove();
        document.body.style.overflow = '';
    }

    // Cerrar modal de opciones si está abierto
    window.dispatchEvent(new CustomEvent('close-modal', {
        detail: 'download-meeting'
    }));

    // Limpiar cualquier modal de descarga existente
    const downloadModal = document.querySelector('[name="download-meeting"]');
    if (downloadModal) {
        downloadModal.remove();
    }

    // Reabrir modal de contenedor si estaba activo
    if (previousContainerForDownload) {
        openContainerMeetingsModal(previousContainerForDownload);
        previousContainerForDownload = null;
    }
}

// ==============================
// Modal de Vista Previa (full)
// ==============================
function ensurePreviewModal() {
    // Modal ya existe en Blade; solo asegura que hay handlers de cierre
    const closeBtn = document.getElementById('closeFullPreview');
    if (closeBtn && !closeBtn.dataset.listenerAdded) {
        closeBtn.dataset.listenerAdded = 'true';
        closeBtn.addEventListener('click', closeFullPreviewModal);
    }
}

function openFullPreviewModal(url) {
    ensurePreviewModal();
    const modal = document.getElementById('fullPreviewModal');
    const frame = document.getElementById('fullPreviewFrame');
    frame.src = url;
    modal.classList.remove('hidden');
}

function closeFullPreviewModal() {
    const modal = document.getElementById('fullPreviewModal');
    if (!modal) return;
    const frame = document.getElementById('fullPreviewFrame');
    frame.src = 'about:blank';
    modal.classList.add('hidden');
}

function initializeDownloadModal() {
    // Adjuntar listeners a todos los botones presentes (Blade + dinámico)
    const confirmButtons = Array.from(document.querySelectorAll('.confirm-download'));
    const previewButtons = Array.from(document.querySelectorAll('.preview-pdf'));

    // Vista previa
    previewButtons.forEach(previewBtn => {
        if (!previewBtn || previewBtn.dataset.listenerAdded) return;
        previewBtn.dataset.listenerAdded = 'true';
        previewBtn.addEventListener('click', async () => {
            // Evitar múltiples solicitudes si ya está cargando
            if (previewBtn.disabled) return;

            const wrapper = previewBtn.closest('[name="download-meeting"]') || document.querySelector('[name="download-meeting"]');
            const container = previewBtn.closest('.download-modal') || (wrapper && (wrapper.querySelector('.download-modal') || wrapper)) || null;
            let meetingId = (wrapper?.dataset.meetingId) || (container?.dataset.meetingId) || window.lastOpenedDownloadMeetingId;
            if (!meetingId) {
                alert('Faltan datos para la vista previa');
                return;
            }
            const meetingData = window.downloadMeetingData[meetingId];
            if (!meetingData) {
                alert('Faltan datos para la vista previa');
                return;
            }
            let selectedItems = [];
            if (container) {
                selectedItems = Array.from(container.querySelectorAll('.download-option:checked')).map(cb => cb.value);
            } else if (wrapper) {
                selectedItems = Array.from(wrapper.querySelectorAll('.download-option:checked')).map(cb => cb.value);
            } else {
                selectedItems = Array.from(document.querySelectorAll('[name="download-meeting"] .download-option:checked')).map(cb => cb.value);
            }
            if (selectedItems.length === 0) {
                alert('Selecciona al menos una sección para previsualizar');
                return;
            }
            const payload = {
                meeting_name: meetingData.meeting_name,
                sections: selectedItems,
                data: {
                    summary: selectedItems.includes('summary') ? meetingData.summary : null,
                    key_points: selectedItems.includes('key_points') ? meetingData.key_points : null,
                    transcription: selectedItems.includes('transcription') ? meetingData.transcription : null,
                    tasks: selectedItems.includes('tasks') ? meetingData.tasks : null,
                    speakers: meetingData.speakers || [],
                    segments: meetingData.segments || []
                }
            };
            // Mostrar estado de carga en el botón
            const originalPreviewContent = previewBtn.innerHTML;
            previewBtn.disabled = true;
            previewBtn.innerHTML = `
                <span class="flex items-center justify-center space-x-2">
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-slate-200"></div>
                    <span>Generando vista previa...</span>
                </span>
            `;

            try {
                // Enviar POST y obtener blob URL
                const res = await fetch('/api/meetings/' + meetingId + '/preview-pdf', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/pdf'
                    },
                    body: JSON.stringify(payload)
                });
                if (!res.ok) {
                    const err = await res.text();
                    throw new Error(err || 'No se pudo generar la vista previa');
                }
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                openFullPreviewModal(url);
            } catch (e) {
                alert('Error en vista previa: ' + e.message);
            } finally {
                // Restaurar el botón
                previewBtn.disabled = false;
                previewBtn.innerHTML = originalPreviewContent;
            }
        });
    });

    // Descargar
    confirmButtons.forEach(confirm => {
        if (!confirm || confirm.dataset.listenerAdded) return;
        confirm.dataset.listenerAdded = 'true';
        confirm.addEventListener('click', async () => {
            const wrapper = confirm.closest('[name="download-meeting"]') || document.querySelector('[name="download-meeting"]');
            const container = confirm.closest('.download-modal') || (wrapper && (wrapper.querySelector('.download-modal') || wrapper)) || null;
            let meetingId = (wrapper?.dataset.meetingId) || (container?.dataset.meetingId) || window.lastOpenedDownloadMeetingId;
            const meetingData = meetingId ? window.downloadMeetingData[meetingId] : null;
            let originalContent = null;

            if (!meetingId) {
                alert('Error: No se encontró el ID de la reunión');
                return;
            }

            if (!meetingData) {
                alert('Error: No se han cargado los datos de la reunión');
                return;
            }

            try {
                let selectedItems = [];
                if (container) {
                    selectedItems = Array.from(container.querySelectorAll('.download-option:checked')).map(cb => cb.value);
                } else if (wrapper) {
                    selectedItems = Array.from(wrapper.querySelectorAll('.download-option:checked')).map(cb => cb.value);
                } else {
                    selectedItems = Array.from(document.querySelectorAll('[name="download-meeting"] .download-option:checked')).map(cb => cb.value);
                }

                if (selectedItems.length === 0) {
                    alert('Por favor selecciona al menos una sección para descargar');
                    return;
                }

                // Mostrar loading en el botón con mejor UI
                confirm.disabled = true;
                originalContent = confirm.innerHTML;
                confirm.innerHTML = `
                    <span class="flex items-center space-x-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-slate-900"></div>
                        <span>Generando PDF...</span>
                    </span>
                `;

                try {
                    console.log('Generando PDF con secciones:', selectedItems);

                    // Preparar los datos para enviar al servidor
                    const downloadData = {
                        meeting_id: meetingId,
                        meeting_name: meetingData.meeting_name,
                        created_at: meetingData.created_at,
                        sections: selectedItems,
                        data: {
                            summary: selectedItems.includes('summary') ? meetingData.summary : null,
                            key_points: selectedItems.includes('key_points') ? meetingData.key_points : null,
                            transcription: selectedItems.includes('transcription') ? meetingData.transcription : null,
                            tasks: selectedItems.includes('tasks') ? meetingData.tasks : null,
                            speakers: meetingData.speakers || [],
                            segments: meetingData.segments || []
                        }
                    };

                    // Enviar al endpoint de descarga
                    const response = await fetch('/api/meetings/' + meetingId + '/download-pdf', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(downloadData)
                    });

                    if (response.ok) {
                        console.log('PDF generado exitosamente');

                        // Si la respuesta es un blob (PDF), descargarlo
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;

                        // Nombre del archivo con timestamp
                        const timestamp = new Date().toISOString().split('T')[0];
                        const cleanName = meetingData.meeting_name.replace(/[^\w\s]/gi, '').trim();
                        a.download = `${cleanName}_${timestamp}.pdf`;

                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);

                        // Cerrar modal después de un pequeño delay
                        setTimeout(() => {
                            closeDownloadModal();
                        }, 500);

                    } else {
                        const errorData = await response.json();
                        console.error('Error del servidor:', errorData);
                        alert('Error al generar PDF: ' + (errorData.message || 'Error desconocido'));
                    }

                } catch (error) {
                    console.error('Error downloading PDF:', error);
                    alert('Error al descargar: ' + error.message);
                } finally {
                    // Restaurar botón siempre
                    if (confirm) {
                        confirm.disabled = false;
                        confirm.innerHTML = originalContent;
                    }
                }
            } catch (error) {
                console.error('Error general en initializeDownloadModal:', error);
                alert('Error inesperado: ' + error.message);
                // Restaurar botón en caso de error general
                if (confirm && originalContent) {
                    confirm.disabled = false;
                    confirm.innerHTML = originalContent;
                }
            }
        });
    });
}

// Variables globales para el modal de reuniones del contenedor
let currentContainerForMeetings = null;
let previousContainerForDownload = null;

// ===============================================
// MODAL DE REUNIONES DEL CONTENEDOR
// ===============================================

async function openContainerMeetingsModal(containerId) {
    currentContainerForMeetings = containerId;

    // Mostrar modal
    document.getElementById('container-meetings-modal').classList.remove('hidden');

    // Mostrar estado de carga
    showContainerMeetingsLoading();

    // Cargar reuniones del contenedor
    await loadContainerMeetings(containerId);
}

function closeContainerMeetingsModal() {
    document.getElementById('container-meetings-modal').classList.add('hidden');
    currentContainerForMeetings = null;
}

function showContainerMeetingsLoading() {
    document.getElementById('container-meetings-loading').classList.remove('hidden');
    document.getElementById('container-meetings-list').classList.add('hidden');
    document.getElementById('container-meetings-empty').classList.add('hidden');
    document.getElementById('container-meetings-error').classList.add('hidden');
}

function showContainerMeetingsList() {
    document.getElementById('container-meetings-loading').classList.add('hidden');
    document.getElementById('container-meetings-list').classList.remove('hidden');
    document.getElementById('container-meetings-empty').classList.add('hidden');
    document.getElementById('container-meetings-error').classList.add('hidden');
}

function showContainerMeetingsEmpty() {
    document.getElementById('container-meetings-loading').classList.add('hidden');
    document.getElementById('container-meetings-list').classList.add('hidden');
    document.getElementById('container-meetings-empty').classList.remove('hidden');
    document.getElementById('container-meetings-error').classList.add('hidden');
}

function showContainerMeetingsError(message) {
    document.getElementById('container-meetings-loading').classList.add('hidden');
    document.getElementById('container-meetings-list').classList.add('hidden');
    document.getElementById('container-meetings-empty').classList.add('hidden');
    document.getElementById('container-meetings-error').classList.remove('hidden');
    document.getElementById('container-meetings-error-message').textContent = message;
}

async function loadContainerMeetings(containerId) {
    try {
        const response = await fetch(`/api/content-containers/${containerId}/meetings`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const data = await response.json();
        console.log('Container meetings response:', data); // Debug

        if (data.success) {
            // Verificar que container existe en la respuesta
            if (data.container && data.container.name) {
                // Actualizar título del modal
                document.getElementById('container-meetings-title').textContent = `Reuniones en "${data.container.name}"`;
                document.getElementById('container-meetings-subtitle').textContent = data.container.description || '';
            } else {
                // Fallback si no hay información del contenedor
                document.getElementById('container-meetings-title').textContent = 'Reuniones del Contenedor';
                document.getElementById('container-meetings-subtitle').textContent = '';
                console.warn('Container information missing in response:', data);
            }

            if (data.meetings && data.meetings.length > 0) {
                renderContainerMeetings(data.meetings);
                showContainerMeetingsList();
            } else {
                showContainerMeetingsEmpty();
            }
        } else {
            throw new Error(data.message || 'Error al cargar reuniones del contenedor');
        }

    } catch (error) {
        console.error('Error loading container meetings:', error);
        showContainerMeetingsError(error.message);
    }
}

function renderContainerMeetings(meetings) {
    const container = document.getElementById('container-meetings-list');

    currentMeetings = meetings;
    container.innerHTML = meetings.map(m => createContainerMeetingCard(m)).join('');
    attachMeetingEventListeners();
}

function openMeetingModalFromContainer(meetingId) {
    // Cerrar el modal del contenedor
    closeContainerMeetingsModal();

    // Abrir el modal de la reunión
    setTimeout(() => {
        openMeetingModal(meetingId);
    }, 100);
}

function retryLoadContainerMeetings() {
    if (currentContainerForMeetings) {
        showContainerMeetingsLoading();
        loadContainerMeetings(currentContainerForMeetings);
    }
}

// =========================================================
// Drive folder selector with organization support
// =========================================================
async function loadDriveOptions() {
    const role = window.userRole || document.body.dataset.userRole;
    const organizationId = window.currentOrganizationId || document.body.dataset.organizationId;
    const driveSelect = document.getElementById('drive-select');

    if (!driveSelect) {
        console.warn('🔍 [reuniones_v2 - loadDriveOptions] Drive select element not found');
        return;
    }

    // Allow both administrators and colaboradores to see drive options
    console.log('🔍 [reuniones_v2 - loadDriveOptions] Loading drive options for role:', role);

    try {
        // Clear existing options
        driveSelect.innerHTML = '';

        // Load personal drive name
        console.log('🔍 [reuniones_v2 - loadDriveOptions] Fetching personal drive data...');
        try {
            const personalRes = await fetch('/drive/sync-subfolders');
            console.log('🔍 [reuniones_v2 - loadDriveOptions] Personal drive response status:', personalRes.status);

            if (personalRes.ok) {
                const personalData = await personalRes.json();
                console.log('🔍 [reuniones_v2 - loadDriveOptions] Personal drive data:', personalData);

                if (personalData.root_folder) {
                    const personalOpt = document.createElement('option');
                    personalOpt.value = 'personal';
                    personalOpt.textContent = `🏠 ${personalData.root_folder.name}`;
                    driveSelect.appendChild(personalOpt);
                    console.log('✅ [reuniones_v2 - loadDriveOptions] Added personal option:', personalData.root_folder.name);
                }
            } else {
                console.warn('⚠️ [reuniones_v2 - loadDriveOptions] Personal drive request failed:', await personalRes.text());
            }
        } catch (e) {
            console.warn('⚠️ [reuniones_v2 - loadDriveOptions] Could not load personal drive name:', e);
            // Fallback to default
            const personalOpt = document.createElement('option');
            personalOpt.value = 'personal';
            personalOpt.textContent = 'Personal';
            driveSelect.appendChild(personalOpt);
            console.log('📝 [reuniones_v2 - loadDriveOptions] Added fallback personal option');
        }

        // Load organization drive name (for both admin and colaborador)
        if (organizationId) {
            console.log('🔍 [reuniones_v2 - loadDriveOptions] Fetching organization drive data...');
            try {
                const orgRes = await fetch(`/api/organizations/${organizationId}/drive/subfolders`);
                console.log('🔍 [reuniones_v2 - loadDriveOptions] Organization drive response status:', orgRes.status);

                if (orgRes.ok) {
                    const orgData = await orgRes.json();
                    console.log('🔍 [reuniones_v2 - loadDriveOptions] Organization drive data:', orgData);

                    if (orgData.root_folder) {
                        const orgOpt = document.createElement('option');
                        orgOpt.value = 'organization';
                        orgOpt.textContent = `🏢 ${orgData.root_folder.name}`;
                        driveSelect.appendChild(orgOpt);
                        console.log('✅ [reuniones_v2 - loadDriveOptions] Added organization option:', orgData.root_folder.name);
                    }
                } else {
                    console.warn('⚠️ [reuniones_v2 - loadDriveOptions] Organization drive request failed:', await orgRes.text());
                }
            } catch (e) {
                console.warn('⚠️ [reuniones_v2 - loadDriveOptions] Could not load organization drive name:', e);
                // Fallback to default
                const orgOpt = document.createElement('option');
                orgOpt.value = 'organization';
                orgOpt.textContent = 'Organization';
                driveSelect.appendChild(orgOpt);
                console.log('📝 [reuniones_v2 - loadDriveOptions] Added fallback organization option');
            }
        }

        // Set default selection based on role
        if (driveSelect.options.length > 0) {
            const saved = sessionStorage.getItem('selectedDrive');
            if (saved && driveSelect.querySelector(`option[value="${saved}"]`)) {
                driveSelect.value = saved;
                console.log('📄 [reuniones_v2 - loadDriveOptions] Restored saved selection:', saved);
            } else {
                // For colaboradores in organizations, default to organization
                if (role === 'colaborador' && organizationId && driveSelect.querySelector('option[value="organization"]')) {
                    driveSelect.value = 'organization';
                    console.log('👥 [reuniones_v2 - loadDriveOptions] Set default to organization for colaborador');
                } else {
                    driveSelect.selectedIndex = 0;
                    console.log('🎯 [reuniones_v2 - loadDriveOptions] Set default to first option');
                }
            }
        }

        // Show the selector for both admin and colaborador
        driveSelect.style.display = 'block';
        console.log('👁️ [reuniones_v2 - loadDriveOptions] Drive selector is now visible');

    } catch (e) {
        console.error('❌ [reuniones_v2 - loadDriveOptions] Error loading drive options:', e);
        // Fallback to original options
        driveSelect.innerHTML = `
            <option value="personal">Personal</option>
            <option value="organization">Organization</option>
        `;
        console.log('🔄 [reuniones_v2 - loadDriveOptions] Fallback to default options');
    }
}

async function loadDriveFolders() {
    const role = window.userRole || document.body.dataset.userRole;
    const organizationId = window.currentOrganizationId || document.body.dataset.organizationId;
    const driveSelect = document.getElementById('drive-select');
    const rootSelect = document.getElementById('root-folder-select');
    const transcriptionSelect = document.getElementById('transcription-subfolder-select');
    const audioSelect = document.getElementById('audio-subfolder-select');

    console.log('🔍 [reuniones_v2 - loadDriveFolders] Starting with debug info:', {
        role,
        organizationId,
        driveSelectValue: driveSelect?.value,
        driveSelectExists: !!driveSelect
    });

    // First, load drive options with real folder names
    await loadDriveOptions();

    // Updated logic to allow colaboradores to choose between personal and organization
    let useOrg;
    if (role === 'colaborador') {
        // For colaboradores, check the drive select value if it exists
        useOrg = driveSelect ? driveSelect.value === 'organization' : true; // default to org if no selector
    } else if (role === 'administrador' && driveSelect) {
        useOrg = driveSelect.value === 'organization';
    } else {
        useOrg = false; // default to personal
    }

    console.log('🔍 [reuniones_v2 - loadDriveFolders] Drive selection logic:', {
        role,
        useOrg,
        driveSelectValue: driveSelect?.value,
        reasoning: role === 'colaborador' ? 'colaborador can choose' : 'administrator choice'
    });

    const endpoint = useOrg ? `/api/organizations/${organizationId}/drive/subfolders` : '/drive/sync-subfolders';
    console.log('🔍 [reuniones_v2 - loadDriveFolders] Using endpoint:', endpoint);
    try {
        const res = await fetch(endpoint);
        console.log('🔍 [reuniones_v2 - loadDriveFolders] Fetch response status:', res.status);

        if (!res.ok) {
            console.error('🔍 [reuniones_v2 - loadDriveFolders] Request failed with status:', res.status);
            return;
        }

        const data = await res.json();
        console.log('🔍 [reuniones_v2 - loadDriveFolders] Received data:', data);

        // Don't hide drive select for colaboradores anymore - they can choose
        console.log('🔍 [reuniones_v2 - loadDriveFolders] Drive select visibility:', {
            role,
            willHide: false, // Changed: don't hide for colaboradores
            driveSelectExists: !!driveSelect
        });

        if (rootSelect) {
            rootSelect.innerHTML = '';
            if (data.root_folder) {
                const opt = document.createElement('option');
                opt.value = data.root_folder.google_id;
                opt.textContent = `📁 ${data.root_folder.name}`;
                rootSelect.appendChild(opt);
                console.log('✅ [reuniones_v2 - loadDriveFolders] Added root folder option:', {
                    name: data.root_folder.name,
                    googleId: data.root_folder.google_id
                });
            } else {
                console.warn('⚠️ [reuniones_v2 - loadDriveFolders] No root folder found in response');
            }
        }

        const populate = (select, selectName) => {
            if (!select) {
                console.warn(`⚠️ [reuniones_v2 - loadDriveFolders] ${selectName} select not found`);
                return;
            }
            select.innerHTML = '';
            const list = data.subfolders || [];
            console.log(`🔍 [reuniones_v2 - loadDriveFolders] Populating ${selectName} with ${list.length} subfolders:`, list);

            if (list.length) {
                const none = document.createElement('option');
                none.value = '';
                none.textContent = 'Sin subcarpeta';
                select.appendChild(none);
                list.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.google_id;
                    opt.textContent = `📂 ${f.name}`;
                    select.appendChild(opt);
                    console.log(`✅ [reuniones_v2 - loadDriveFolders] Added ${selectName} subfolder:`, f.name);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No se encontraron subcarpetas';
                select.appendChild(opt);
                console.log(`📝 [reuniones_v2 - loadDriveFolders] No subfolders found for ${selectName}`);
            }
        };

        populate(transcriptionSelect, 'transcription');
        populate(audioSelect, 'audio');

        console.log('✅ [reuniones_v2 - loadDriveFolders] Successfully loaded drive folders');

    } catch (e) {
        console.error('❌ [reuniones_v2 - loadDriveFolders] Error loading drive folders:', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Debug inicial para reuniones
    console.log('🚀 [reuniones_v2] Iniciando aplicación...');
    console.log('🔍 [reuniones_v2] Variables globales:', {
        userRole: window.userRole || document.body.dataset.userRole,
        organizationId: window.currentOrganizationId || document.body.dataset.organizationId,
        bodyDatasets: Object.keys(document.body.dataset),
        windowVars: Object.keys(window).filter(k => k.includes('user') || k.includes('org'))
    });

    const driveSelect = document.getElementById('drive-select');
    console.log('🔍 [reuniones_v2] Drive select element found:', !!driveSelect);

    if (driveSelect) {
        driveSelect.addEventListener('change', () => {
            console.log('🔄 [reuniones_v2] Drive selection changed to:', driveSelect.value);
            loadDriveFolders();
        });
    }

    if (document.getElementById('root-folder-select')) {
        console.log('🔍 [reuniones_v2] About to call loadDriveFolders...');
        loadDriveFolders();
    }
});

// ===============================================
// FUNCIONES GLOBALES PARA HTML INLINE
// ===============================================
// Hacer funciones disponibles globalmente para onclick en HTML
window.closeMeetingModal = closeMeetingModal;
window.openMeetingModal = openMeetingModal;
window.downloadJuFile = downloadJuFile;
window.downloadAudioFile = downloadAudioFile;
window.saveMeetingTitle = saveMeetingTitle;
window.deleteMeeting = deleteMeeting;
window.editMeetingName = editMeetingName;
window.closeDeleteConfirmationModal = closeDeleteConfirmationModal;
window.confirmDeleteMeeting = confirmDeleteMeeting;
window.closeEditNameModal = closeEditNameModal;
window.confirmEditMeetingName = confirmEditMeetingName;
window.playSegmentAudio = playSegmentAudio;
window.seekAudio = seekAudio;
window.openChangeSpeakerModal = openChangeSpeakerModal;
window.closeChangeSpeakerModal = closeChangeSpeakerModal;
window.confirmSpeakerChange = confirmSpeakerChange;
window.openGlobalSpeakerModal = openGlobalSpeakerModal;
window.closeGlobalSpeakerModal = closeGlobalSpeakerModal;
window.confirmGlobalSpeakerChange = confirmGlobalSpeakerChange;
window.openDownloadModal = openDownloadModal;
window.closeDownloadModal = closeDownloadModal;
window.showDownloadModalLoading = showDownloadModalLoading;
window.createDownloadModal = createDownloadModal;

// Funciones del modal de quitar reunión compartida
window.openUnlinkSharedModal = openUnlinkSharedModal;
window.closeUnlinkSharedModal = closeUnlinkSharedModal;
window.confirmUnlinkSharedMeeting = confirmUnlinkSharedMeeting;

// Funciones de contenedores
window.openCreateContainerModal = openCreateContainerModal;
window.openEditContainerModal = openEditContainerModal;
window.closeContainerModal = closeContainerModal;
window.saveContainer = saveContainer;
window.deleteContainer = deleteContainer;
window.showDeleteContainerConfirmationModal = showDeleteContainerConfirmationModal;
window.closeDeleteContainerConfirmationModal = closeDeleteContainerConfirmationModal;
window.confirmDeleteContainer = confirmDeleteContainer;
window.openContainerSelectModal = openContainerSelectModal;
window.closeContainerSelectModal = closeContainerSelectModal;

// Funciones del modal de reuniones del contenedor
window.openContainerMeetingsModal = openContainerMeetingsModal;
window.closeContainerMeetingsModal = closeContainerMeetingsModal;
window.retryLoadContainerMeetings = retryLoadContainerMeetings;
window.openMeetingModalFromContainer = openMeetingModalFromContainer;
window.removeMeetingFromContainer = removeMeetingFromContainer;
window.attachMeetingEventListeners = attachMeetingEventListeners;

// Funciones de compartir reuniones
window.openShareModal = openShareModal;
window.closeShareModal = closeShareModal;
window.confirmShare = confirmShare;
window.toggleContact = toggleContact;
window.forceOpenShareModal = function(id){ console.log('[shareModal] forceOpenShareModal'); openShareModal(id||0); };

// ===============================================
// FUNCIONALIDAD DE COMPARTIR REUNIONES
// ===============================================

let currentShareMeetingId = null;
let selectedContacts = new Set();
let allContacts = [];

// Abrir modal de compartir
function openShareModal(meetingId) {
    try { console.log('[shareModal] openShareModal called', { meetingId }); } catch(e){}
    currentShareMeetingId = meetingId;
    selectedContacts.clear();

    const modal = document.getElementById('shareModal');
    if (!modal) { console.warn('[shareModal] #shareModal not found'); return; }

    const selectedContactsContainer = document.getElementById('selectedContactsContainer');
    const confirmBtn = document.getElementById('confirmShare');
    // Ensure modal overrides base `.modal { display: none; }` styles
    // by adding the `.show` class and removing Tailwind's `hidden`.
    modal.classList.remove('hidden');
    modal.classList.add('show');
    try { console.log('[shareModal] modal classes after show:', modal.className); } catch(e){}

    // Resetear estado
    const searchInput = document.getElementById('shareModal-contactSearch');
    if (searchInput) searchInput.value = '';
    const messageInput = document.getElementById('shareModal-shareMessage');
    if (messageInput) messageInput.value = '';
    if (selectedContactsContainer) selectedContactsContainer.classList.add('hidden');
    if (confirmBtn) confirmBtn.disabled = true;

    // Cargar contactos
    loadContactsForSharing();

    // Setup búsqueda de contactos
    setupContactSearch();
    window.__lastShareModalOpen = { id: meetingId, ts: Date.now() };
}

// Cerrar modal de compartir
function closeShareModal() {
    const modal = document.getElementById('shareModal');
    if (!modal) return;

    // Restore initial hidden state and remove the `.show` class
    modal.classList.remove('show');
    modal.classList.add('hidden');
    currentShareMeetingId = null;
    selectedContacts.clear();
    allContacts = [];
}

// Cargar contactos disponibles para compartir
async function loadContactsForSharing() {
    const contactsList = document.getElementById('shareModal-contactsList');

    try {
        const response = await fetch('/api/shared-meetings/contacts', {
            method: 'GET',
            credentials: 'include', // ensure session cookies are sent
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')) || ''
            },
            cache: 'no-store'
        });

        // If not OK, try to parse JSON error, else show fallback
        let data = { success: false };
        try {
            data = await response.json();
        } catch (_) {
            // non-JSON response (e.g., HTML from a redirect)
        }

        if (data.success) {
            allContacts = data.contacts;
            renderContactsList(allContacts);
        } else {
            contactsList.innerHTML = `
                <div class="p-4 text-center text-red-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Error al cargar contactos
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading contacts:', error);
        contactsList.innerHTML = `
            <div class="p-4 text-center text-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Error al cargar contactos. Intenta recargar la página o vuelve a iniciar sesión.
            </div>
        `;
    }
}

// Renderizar lista de contactos
function renderContactsList(contacts) {
    const contactsList = document.getElementById('shareModal-contactsList');

    if (contacts.length === 0) {
        contactsList.innerHTML = `
            <div class="p-4 text-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                No tienes contactos disponibles
            </div>
        `;
        return;
    }

    contactsList.innerHTML = contacts.map(contact => {
        // Asegurar ID como string para evitar errores en inline JS (UUIDs, etc.)
        const cid = String(contact.id).replace(/'/g, "\\'");
        return `
        <div class="contact-item p-3 border-b border-slate-700 hover:bg-slate-700/50 cursor-pointer transition-colors" onclick="toggleContact('${cid}')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-yellow-400 text-slate-900 flex items-center justify-center font-semibold text-sm">
                        ${contact.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="font-medium text-slate-200">${contact.name}</p>
                        <p class="text-sm text-slate-400">${contact.email}</p>
                    </div>
                </div>
                <div class="contact-checkbox">
                    <input type="checkbox" id="shareModal-contact-${cid}" class="w-4 h-4 text-yellow-400 bg-slate-700 border-slate-600 rounded focus:ring-yellow-400 focus:ring-2">
                </div>
            </div>
        </div>
    `}).join('');
}

// Toggle selección de contacto
function toggleContact(contactId) {
    const idStr = String(contactId);
    const checkbox = document.getElementById(`shareModal-contact-${idStr}`);
    const contact = allContacts.find(c => String(c.id) === idStr);

    if (!contact) return;

    if (selectedContacts.has(idStr)) {
        selectedContacts.delete(idStr);
        checkbox.checked = false;
    } else {
        selectedContacts.add(idStr);
        checkbox.checked = true;
    }

    updateSelectedContactsDisplay();
    updateConfirmButton();
}

// Actualizar visualización de contactos seleccionados
function updateSelectedContactsDisplay() {
    const container = document.getElementById('selectedContactsContainer');
    const selectedContactsDiv = document.getElementById('selectedContacts');

    if (selectedContacts.size === 0) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');

    const selectedContactsArray = Array.from(selectedContacts).map(id =>
        allContacts.find(c => String(c.id) === String(id))
    ).filter(Boolean);

    selectedContactsDiv.innerHTML = selectedContactsArray.map(contact => {
        const cid = String(contact.id).replace(/'/g, "\\'");
        return `
        <div class="flex items-center gap-2 bg-yellow-400/20 text-yellow-400 px-3 py-1 rounded-full text-sm">
            <span>${contact.name}</span>
            <button onclick="toggleContact('${cid}')" class="text-yellow-400 hover:text-yellow-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `}).join('');
}

// Actualizar estado del botón confirmar
function updateConfirmButton() {
    const confirmBtn = document.getElementById('confirmShare');
    if (confirmBtn) confirmBtn.disabled = selectedContacts.size === 0;
}

// Setup búsqueda de contactos
function setupContactSearch() {
    const searchInput = document.getElementById('shareModal-contactSearch');

    if (!searchInput) return;
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();

        if (searchTerm === '') {
            renderContactsList(allContacts);
        } else {
            const filteredContacts = allContacts.filter(contact =>
                contact.name.toLowerCase().includes(searchTerm) ||
                contact.email.toLowerCase().includes(searchTerm)
            );
            renderContactsList(filteredContacts);
        }

        // Mantener las selecciones después del filtrado
        selectedContacts.forEach(contactId => {
            const checkbox = document.getElementById(`shareModal-contact-${String(contactId)}`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    });
}

// Confirmar compartir reunión
async function confirmShare() {
    if (selectedContacts.size === 0 || !currentShareMeetingId) return;

    const confirmBtn = document.getElementById('confirmShare');
    const message = document.getElementById('shareModal-shareMessage').value;

    // Deshabilitar botón y mostrar loading
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = `
        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Compartiendo...
    `;

    try {
        const response = await fetch('/api/shared-meetings/share', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                meeting_id: currentShareMeetingId,
                contact_ids: Array.from(selectedContacts).map(v => {
                    const n = Number(v);
                    return Number.isFinite(n) ? n : String(v);
                }),
                message: message
            })
        });

        let data = {};
        try {
            data = await response.json();
        } catch (_) {
            // non-JSON response
        }

        if (response.ok && data.success) {
            showNotification('Reunión compartida exitosamente', 'success');
            closeShareModal();
        } else {
            showNotification((data && data.message) || 'Error al compartir reunión', 'error');
        }
    } catch (error) {
        console.error('Error sharing meeting:', error);
        showNotification('Error al compartir reunión', 'error');
    } finally {
        // Restaurar botón
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
            Compartir
        `;
        updateConfirmButton();
    }
}
