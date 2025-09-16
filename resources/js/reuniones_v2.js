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
    // Exportar funciones críticas que podrían ser llamadas por botones inline
    window.openShareModal = openShareModal;
    window.closeShareModal = closeShareModal;
    window.confirmShare = confirmShare;
    window.toggleContact = toggleContact;
    window.forceOpenShareModal = function(id){ console.log('[shareModal] forceOpenShareModal'); openShareModal(id||0); };
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
// FUNCIONALIDAD DE COMPARTIR (REINSERTADA)
// ===============================================
let currentShareMeetingId = null;
let selectedContacts = new Set();
let allContacts = [];

function openShareModal(meetingId){
    try { console.log('[shareModal] openShareModal called (reinjected)', {meetingId}); } catch(e){}
    currentShareMeetingId = meetingId;
    selectedContacts.clear();
    const modal = document.getElementById('shareModal');
    if(!modal){ console.warn('[shareModal] #shareModal no existe'); return; }
    modal.classList.remove('hidden');
    modal.classList.add('show');
    const confirmBtn = document.getElementById('confirmShare');
    if (confirmBtn) confirmBtn.disabled = true;
    loadContactsForSharing();
    setupContactSearch();
}

function closeShareModal(){
    const modal = document.getElementById('shareModal');
    if(!modal) return;
    modal.classList.remove('show');
    modal.classList.add('hidden');
    currentShareMeetingId = null;
    selectedContacts.clear();
    allContacts = [];
}

async function loadContactsForSharing(){
    const list = document.getElementById('shareModal-contactsList');
    if(list) list.innerHTML = '<div class="p-4 text-center text-slate-400">Cargando contactos...</div>';
    try {
        const resp = await fetch('/api/shared-meetings/contacts', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept':'application/json'
            },
            credentials: 'include'
        });
        const data = await resp.json().catch(()=>({success:false}));
        if(data.success){
            allContacts = data.contacts || [];
            renderContactsList(allContacts);
        } else {
            if(list) list.innerHTML = '<div class="p-4 text-center text-red-400">Error al cargar contactos</div>';
        }
    } catch(err){
        console.error('[shareModal] error loadContactsForSharing', err);
        if(list) list.innerHTML = '<div class="p-4 text-center text-red-400">Error de conexión</div>';
    }
}

function setupContactSearch(){
    const input = document.getElementById('shareModal-contactSearch');
    if(!input) return;
    if(input.__boundShareSearch) return;
    input.addEventListener('input', () => {
        const term = input.value.toLowerCase().trim();
        const filtered = term ? allContacts.filter(c => (c.name||'').toLowerCase().includes(term) || (c.email||'').toLowerCase().includes(term)) : allContacts;
        renderContactsList(filtered);
    });
    input.__boundShareSearch = true;
}

function renderContactsList(list){
    const container = document.getElementById('shareModal-contactsList');
    if(!container) return;
    if(!list.length){
        container.innerHTML = '<div class="p-4 text-center text-slate-400">Sin contactos</div>';
        return;
    }
    container.innerHTML = '';
    list.forEach(c => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'w-full text-left px-3 py-2 flex items-center justify-between hover:bg-slate-700/50 border-b border-slate-700/40 last:border-b-0 transition';
        item.innerHTML = `<span class="text-sm text-slate-200">${escapeHtml(c.name||c.email||'Usuario')}</span><span class="text-xs text-slate-400">${escapeHtml(c.email||'')}</span>`;
        item.addEventListener('click', ()=> toggleContact(c));
        container.appendChild(item);
    });
}

function toggleContact(contact){
    if(!contact || (!contact.id && !contact.email)) return;
    const key = contact.id || contact.email;
    if(selectedContacts.has(key)){
        selectedContacts.delete(key);
    } else {
        selectedContacts.add(key);
    }
    updateSelectedContactsUI();
}

function updateSelectedContactsUI(){
    const wrapper = document.getElementById('selectedContactsContainer');
    const list = document.getElementById('selectedContacts');
    const confirmBtn = document.getElementById('confirmShare');
    if(!wrapper || !list) return;
    list.innerHTML='';
    if(selectedContacts.size){
        wrapper.classList.remove('hidden');
        selectedContacts.forEach(k => {
            const badge = document.createElement('span');
            badge.className = 'inline-flex items-center gap-1 px-2 py-1 rounded bg-slate-700/60 text-xs text-slate-200';
            badge.textContent = k;
            list.appendChild(badge);
        });
        if(confirmBtn) confirmBtn.disabled = false;
    } else {
        wrapper.classList.add('hidden');
        if(confirmBtn) confirmBtn.disabled = true;
    }
}

async function confirmShare(){
    if(!currentShareMeetingId){ alert('No hay reunión seleccionada'); return; }
    if(!selectedContacts.size){ alert('Selecciona al menos un contacto'); return; }
    try {
        const message = (document.getElementById('shareModal-shareMessage')?.value)||'';
        const response = await fetch('/api/shared-meetings/share', {
            method: 'POST',
            headers: {
                'Content-Type':'application/json',
                'Accept':'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                meeting_id: currentShareMeetingId,
                contacts: Array.from(selectedContacts),
                message
            })
        });
        const data = await response.json();
        if(data.success){
            alert('Reunión compartida correctamente');
            closeShareModal();
            // refrescar outgoing/incoming
            loadOutgoingSharedMeetings();
            loadSharedMeetings();
        } else {
            alert(data.message || 'Error al compartir');
        }
    } catch(err){
        console.error('[shareModal] error confirmShare', err);
        alert('Error de conexión al compartir');
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
        container.style.zIndex = '9999';
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
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

// ===============================================
// BÚSQUEDA / FILTRADO DE REUNIONES (handleSearch restaurado)
// ===============================================
if (typeof window.handleSearch !== 'function') {
  window.handleSearch = function handleSearch(e) {
    try {
      const term = (e?.target?.value || '').toLowerCase().trim();
      // Si no hay datos cargados aún, sólo logueamos.
      if (!Array.isArray(currentMeetings) || !currentMeetings.length) {
        console.log('[handleSearch] No hay reuniones cargadas todavía, term=', term);
        return;
      }
      const filtered = term
        ? currentMeetings.filter(m => {
            const name = (m.meeting_name || '').toLowerCase();
            const created = (m.created_at || '').toLowerCase();
            return name.includes(term) || created.includes(term);
          })
        : currentMeetings.slice();

      const container = document.getElementById('my-meetings');
      if (!container) return;

      // Render simple (puedes reemplazar por tu renderizado avanzado si existía)
      if (!filtered.length) {
        container.innerHTML = '<div class="loading-card md:col-span-2 xl:col-span-3"><p>No hay resultados</p></div>';
        return;
      }

      let html = '<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">';
      filtered.forEach(m => {
        html += `\n<div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3 shadow-lg shadow-black/10">\n  <div class="flex items-start justify-between gap-3">\n    <div class="flex-1 min-w-0">\n      <h3 class="text-base font-semibold text-white truncate">${(m.meeting_name||'Sin título').replace(/</g,'&lt;')}</h3>\n      <p class="text-xs text-slate-400 mt-0.5">${(m.created_at||'').replace(/</g,'&lt;')}</p>\n    </div>\n    <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-600/40 text-xs font-medium text-slate-200 transition" data-share-meeting-id="${m.id}" onclick="openShareModal(${m.id})">\n      <svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-4 w-4\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\">\n        <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z\" />\n      </svg>\n      Compartir\n    </button>\n  </div>\n  <div class=\"flex items-center gap-2\">\n    <button class=\"px-3 py-1.5 bg-slate-700/40 hover:bg-slate-600/40 rounded-lg text-xs text-slate-200 transition\" onclick=\"openMeetingModal(${m.id})\">Ver</button>\n  </div>\n</div>`;
      });
      html += '</div>';
      container.innerHTML = html;
      // Re-marcar botones de compartir recién renderizados
      if (typeof markShareButtons === 'function') markShareButtons(container);
    } catch(err) {
      console.error('[handleSearch] Error filtrando', err);
    }
  };
}
