// ===============================================
// VARIABLES Y CONFIGURACIÓN GLOBAL
// ===============================================
let currentMeetings = [];
let isEditingTitle = false;
let currentModalMeeting = null;
// Variables para manejo de audio segmentado en el modal
let meetingSegments = [];
let meetingAudioPlayer = null;
let currentSegmentIndex = null;
let segmentEndHandler = null;
let selectedSegmentIndex = null;
let segmentsModified = false;

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
            closeMeetingModal();
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
    } else if (targetId === 'containers') {
        loadContainers();
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
    const container = document.getElementById('shared-meetings');
    try {
        showLoadingState(container);

        const response = await fetch('/api/shared-meetings', {
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
            renderMeetings(data.meetings, '#shared-meetings', 'No hay reuniones compartidas');
        } else {
            showErrorState(container, data.message || 'Error al cargar reuniones compartidas', loadSharedMeetings);
        }

    } catch (error) {
        console.error('Error loading shared meetings:', error);
        showErrorState(container, 'Error de conexión al cargar reuniones compartidas', loadSharedMeetings);
    }
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
}// Función para mostrar notificaciones elegantes
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;

    const icon = type === 'success' ?
        `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>` :
        `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>`;

    notification.innerHTML = `
        <div class="notification-content">
            ${icon}
            <span>${message}</span>
        </div>
    `;

    // Agregar estilos inline
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: ${type === 'success' ? 'linear-gradient(135deg, #10B981, #059669)' : 'linear-gradient(135deg, #EF4444, #DC2626)'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 400px;
    `;

    notification.querySelector('.notification-content').style.cssText = `
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
    `;

    document.body.appendChild(notification);

    // Animar entrada
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // Auto remover después de 4 segundos
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 4000);
}

// Hacer las funciones disponibles globalmente
window.closePendingMeetingsModal = closePendingMeetingsModal;
window.analyzePendingMeeting = analyzePendingMeeting;

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
                    <button class="icon-btn container-btn" onclick="openContainerSelectModal(${meeting.id})" title="Añadir a contenedor">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12z" />
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
                </div>
            </div>
            <button class="download-btn icon-btn absolute bottom-4 right-4" onclick="openDownloadModal(${meeting.id})" title="Descargar reunión">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                </svg>
            </button>
        </div>
    `;
}

// Exponer la función para su uso global
window.createMeetingCard = createMeetingCard;

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
                </div>
            </div>
            <button class="download-btn icon-btn absolute bottom-4 right-4" onclick="openDownloadModal(${meeting.id})" title="Descargar reunión">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                </svg>
            </button>
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
                    <h3 class="meeting-title">${escapeHtml(container.name || container.title || '')}</h3>
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
                openDownloadModal(card.dataset.meetingId);
            });
        }
        card.addEventListener('click', function(e) {
            // No abrir modal si se hizo click en los botones de acción
            if (e.target.closest('.delete-btn') || e.target.closest('.edit-btn') || e.target.closest('.container-btn') || e.target.closest('.remove-btn') || e.target.closest('.download-btn')) {
                return;
            }

            const meetingId = this.dataset.meetingId;
            const containerModal = document.getElementById('container-meetings-modal');
            if (containerModal && !containerModal.classList.contains('hidden')) {
                openMeetingModalFromContainer(meetingId);
            } else {
                openMeetingModal(meetingId);
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
                    <div class="flex items-center justify-center p-8">
                        <div class="loading-spinner"></div>
                        <span class="ml-3 text-slate-300">Cargando contenedores...</span>
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
            <div class="space-y-2">
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
async function openMeetingModal(meetingId) {
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

    const audioSrc = meeting.audio_path || '';

    const modalHtml = `
        <div class="meeting-modal" id="meetingModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title" id="modalTitle">${escapeHtml(meeting.meeting_name)}</h2>
                        <p class="modal-subtitle">${meeting.created_at} • ${meeting.duration || ''} • ${meeting.participants || 0} participantes</p>
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
                            <div class="audio-player">
                                <audio id="meeting-audio" preload="auto"></audio>
                                <div class="audio-controls">
                                    <button id="audio-play" class="audio-btn" aria-label="Reproducir">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M5 3.87v16.26L19.5 12 5 3.87z" />
                                        </svg>
                                    </button>
                                    <button id="audio-pause" class="audio-btn hidden" aria-label="Pausar">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M6 4h4v16H6zm8 0h4v16h-4z" />
                                        </svg>
                                    </button>
                                    <input type="range" id="audio-progress" value="0" min="0" step="0.1">
                                    ${!audioSrc ? '<p class="text-slate-400 text-sm mt-2">Audio no disponible para esta reunión</p>' : ''}
                                </div>
                                <audio id="meeting-full-audio" preload="auto" style="display:none"></audio>
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
                            <div class="section-content ${meeting.segments && meeting.segments.length > 0 ? 'transcription-segmented' : 'transcription-full'}" style="max-height: 400px; overflow-y: auto;">
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

    document.querySelectorAll('.modal-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            const content = document.getElementById(`tab-${target}`);
            if (content) content.classList.add('active');
        });
    });

    // Configuración de reproductor de audio personalizado
    meetingAudioPlayer = document.getElementById('meeting-audio');
    const fullAudioPlayer = document.getElementById('meeting-full-audio');
    const playBtn = document.getElementById('audio-play');
    const pauseBtn = document.getElementById('audio-pause');
    const progress = document.getElementById('audio-progress');

    if (meetingAudioPlayer && playBtn && pauseBtn && progress) {
        if (audioSrc) {
            meetingAudioPlayer.src = audioSrc;
            meetingAudioPlayer.load();
            if (fullAudioPlayer) {
                fullAudioPlayer.src = audioSrc;
                fullAudioPlayer.load();
            }
            playBtn.disabled = false;
            playBtn.style.opacity = '';
            playBtn.title = 'Reproducir';
        } else {
            console.warn('No hay fuente de audio válida para esta reunión');
            playBtn.disabled = true;
            playBtn.style.opacity = '0.5';
            playBtn.title = 'Audio no disponible';
            return;
        }

        // Agregar manejo de errores para carga de audio
        meetingAudioPlayer.addEventListener('error', (e) => {
            console.error('Error cargando audio:', e);

            // Deshabilitar controles
            playBtn.disabled = true;
            pauseBtn.disabled = true;
            progress.disabled = true;
            playBtn.style.opacity = '0.5';
            pauseBtn.style.opacity = '0.5';
            progress.style.opacity = '0.5';
            playBtn.title = 'Error cargando audio';
            pauseBtn.title = 'Error cargando audio';

            // Mostrar mensaje de error y enlace de descarga
            const audioPlayerContainer = document.querySelector('.audio-player');
            if (audioPlayerContainer && !audioPlayerContainer.querySelector('.audio-error')) {
                const errorMsg = document.createElement('p');
                errorMsg.className = 'audio-error text-red-500 text-sm mt-2';
                errorMsg.textContent = 'No se pudo cargar el audio. ';

                const downloadLink = document.createElement('a');
                downloadLink.href = encodeURI(audioSrc);
                downloadLink.textContent = 'Descargar audio';
                downloadLink.className = 'underline';
                errorMsg.appendChild(downloadLink);

                audioPlayerContainer.appendChild(errorMsg);
            }
        });

        meetingAudioPlayer.addEventListener('loadedmetadata', () => {
            progress.max = meetingAudioPlayer.duration;

            // Rehabilitar controles
            playBtn.disabled = false;
            pauseBtn.disabled = false;
            progress.disabled = false;
            playBtn.style.opacity = '';
            pauseBtn.style.opacity = '';
            progress.style.opacity = '';
            playBtn.title = 'Reproducir';
            pauseBtn.title = 'Pausar';

            // Eliminar mensaje de error si existe
            const audioPlayerContainer = document.querySelector('.audio-player');
            const errorMsg = audioPlayerContainer?.querySelector('.audio-error');
            if (errorMsg) {
                errorMsg.remove();
            }
        });

        meetingAudioPlayer.addEventListener('timeupdate', () => {
            progress.value = meetingAudioPlayer.currentTime;
        });

        playBtn.addEventListener('click', () => {
            if (meetingAudioPlayer.src && meetingAudioPlayer.src !== window.location.href) {
                meetingAudioPlayer.play().catch(error => {
                    console.warn('Error reproduciendo audio:', error);
                    alert('No se pudo reproducir el audio. Puede que el archivo no exista o no sea válido.');
                });
            } else {
                alert('No hay audio disponible para esta reunión.');
            }
        });

        pauseBtn.addEventListener('click', () => {
            meetingAudioPlayer.pause();
        });

        meetingAudioPlayer.addEventListener('play', () => {
            playBtn.classList.add('hidden');
            pauseBtn.classList.remove('hidden');
            pauseBtn.classList.add('active');
        });

        meetingAudioPlayer.addEventListener('pause', () => {
            pauseBtn.classList.add('hidden');
            pauseBtn.classList.remove('active');
            playBtn.classList.remove('hidden');

            // Limpiar manejador de segmentos y restablecer botones cuando se pausa
            if (segmentEndHandler) {
                meetingAudioPlayer.removeEventListener('timeupdate', segmentEndHandler);
                segmentEndHandler = null;
            }
            if (currentSegmentIndex !== null) {
                updateSegmentButtons(null);
            }
        });

        progress.addEventListener('input', () => {
            meetingAudioPlayer.currentTime = progress.value;
        });
    }
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
                const taskText = typeof task === 'string' ? task : (task?.text || task?.description || String(task) || 'Tarea sin descripción');
                const meta = [];
                if (task.assignee) meta.push(`Responsable: ${escapeHtml(task.assignee)}`);
                if (typeof task.progress === 'number') meta.push(`Progreso: ${task.progress}%`);
                return `
                    <li class="task-item">
                        <div class="task-checkbox">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="task-text">${escapeHtml(taskText)}${meta.length ? ` <small>(${meta.join(' - ')})</small>` : ''}</span>
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

    return `
        <div class="transcription-segments">
            ${meetingSegments.map((segment, index) => `
                <div class="transcript-segment" data-segment="${index}">
                    <div class="segment-header">
                        <div class="speaker-info">
                            <div class="speaker-avatar">${segment.avatar}</div>
                            <div class="speaker-details">
                                <div class="speaker-name">${segment.speaker}</div>
                                <div class="speaker-time">${segment.time}</div>
                            </div>
                        </div>
                        <div class="segment-controls">
                            <button class="control-btn" onclick="playSegmentAudio(${index})" title="Reproducir fragmento">
                                ${getPlayIcon('btn-icon')}
                            </button>
                            <button class="control-btn" onclick="openChangeSpeakerModal(${index})" title="Editar hablante">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                </svg>
                            </button>
                            <button class="control-btn" onclick="openGlobalSpeakerModal(${index})" title="Cambiar hablante globalmente">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15a3 3 0 100-6 3 3 0 000 6zm9 0a3 3 0 10-6 0 3 3 0 006 0zm-9 1.5a4.5 4.5 0 00-4.5 4.5v1.5h9v-1.5a4.5 4.5 0 00-4.5-4.5zm9 0a4.5 4.5 0 014.5 4.5v1.5h-9v-1.5a4.5 4.5 0 014.5-4.5z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="segment-audio">
                        <div class="audio-player-mini">
                            <button class="play-btn-mini" onclick="playSegmentAudio(${index})">
                                ${getPlayIcon('play-icon')}
                            </button>
                            <div class="audio-timeline-mini" onclick="seekAudio(${index}, event)">
                                <div class="timeline-progress-mini" style="width: 0%"></div>
                            </div>
                            <span class="audio-duration-mini">${segment.time.split(' - ')[1]}</span>
                        </div>
                    </div>

                    <div class="segment-content">
                        <textarea class="transcript-text" placeholder="Texto de la transcripción..." readonly>${segment.text}</textarea>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderTranscription(transcription) {
    if (!transcription) {
        return '<p class="text-slate-400">Transcripción no disponible.</p>';
    }

    // Formatear texto plano con párrafos
    const formatted = transcription.split('\n')
        .filter(line => line.trim())
        .map(line => `<p>${escapeHtml(line)}</p>`)
        .join('');

    return formatted || '<p class="text-slate-400">Contenido no disponible.</p>';
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
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>${message}</p>
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

function escapeHtml(text) {
    if (!text || typeof text !== 'string') return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
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
            <div class="modal-content">
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
                    <div class="modal-section">
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
            throw new Error('Error al eliminar la reunión');
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
        showNotification('Error al eliminar la reunión: ' + error.message, 'error');
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

async function openDownloadModal(meetingId) {
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

        // Paso 1: Descargar y desencriptar el archivo .ju desde Drive
        console.log('Descargando y desencriptando archivo .ju...');
        const response = await fetch(`/api/meetings/${meetingId}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error('Error al descargar el archivo de la reunión');
        }

        const data = await response.json();

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

        alert(errorMessage);
    } finally {
        // Liberar el lock después de un delay
        setTimeout(() => {
            window.downloadModalProcessing = false;
        }, 1000);
    }
}function showDownloadModalLoading(meetingId) {
    // Crear y mostrar modal de loading centrado perfectamente
    const loadingModal = `
        <div class="fixed inset-0 z-[9999] overflow-hidden" id="downloadLoadingModal">
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
             class="fixed inset-0 z-[9999] overflow-hidden download-modal"
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

// Funciones de contenedores
window.openCreateContainerModal = openCreateContainerModal;
window.openEditContainerModal = openEditContainerModal;
window.closeContainerModal = closeContainerModal;
window.saveContainer = saveContainer;
window.deleteContainer = deleteContainer;
window.openContainerSelectModal = openContainerSelectModal;
window.closeContainerSelectModal = closeContainerSelectModal;

// Funciones del modal de reuniones del contenedor
window.openContainerMeetingsModal = openContainerMeetingsModal;
window.closeContainerMeetingsModal = closeContainerMeetingsModal;
window.retryLoadContainerMeetings = retryLoadContainerMeetings;
window.openMeetingModalFromContainer = openMeetingModalFromContainer;
window.removeMeetingFromContainer = removeMeetingFromContainer;
window.attachMeetingEventListeners = attachMeetingEventListeners;
