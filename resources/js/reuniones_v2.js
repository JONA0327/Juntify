// ===============================================
// VARIABLES Y CONFIGURACIÓN GLOBAL
// ===============================================
let currentMeetings = [];
let isEditingTitle = false;
let currentModalMeeting = null;

// ===============================================
// INICIALIZACIÓN
// ===============================================
document.addEventListener('DOMContentLoaded', function() {
    loadMeetings();
    setupEventListeners();
    initializeFadeAnimations();
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
// CARGA DE REUNIONES
// ===============================================
async function loadMeetings() {
    try {
        showLoadingState();

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
            currentMeetings = data.meetings;
            renderMeetings(currentMeetings);
        } else {
            showErrorState(data.message || 'Error al cargar reuniones');
        }

        // Cargar estado de reuniones pendientes
        await loadPendingMeetingsStatus();

    } catch (error) {
        console.error('Error loading meetings:', error);
        showErrorState('Error de conexión al cargar reuniones');
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
function renderMeetings(meetings) {
    const container = document.querySelector('#meetings-container') || document.querySelector('.fade-in.stagger-2');
    if (!container) return;

    // Si ya hay tarjetas renderizadas por Blade (SSR), no sobreescribir el HTML.
    const hasSSRContent = container.querySelector('.meeting-card');
    if (hasSSRContent) {
        attachMeetingEventListeners();
        return;
    }

    if (!meetings || meetings.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <h3 class="text-lg font-semibold mb-2">No hay reuniones disponibles</h3>
                <p>Aún no tienes reuniones grabadas. Inicia tu primera reunión para comenzar.</p>
            </div>
        `;
        return;
    }

    const meetingsHtml = `
        <div class="meetings-grid">
            ${meetings.map(meeting => createMeetingCard(meeting)).join('')}
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
        <div class="meeting-card" data-meeting-id="${meeting.id}">
            <div class="meeting-card-header">
                <div class="meeting-content">
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
                    <button class="action-btn edit-btn" onclick="editMeetingName(${meeting.id})" title="Editar nombre de reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button class="action-btn delete-btn" onclick="deleteMeeting(${meeting.id})" title="Eliminar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
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
    const meetingCards = document.querySelectorAll('.meeting-card');
    meetingCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // No abrir modal si se hizo click en el botón de eliminar o editar
            if (e.target.closest('.delete-btn') || e.target.closest('.edit-btn')) {
                return;
            }

            const meetingId = this.dataset.meetingId;
            openMeetingModal(meetingId);
        });
    });
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

        updateLoadingStep(2); // Paso 2: Descifrando archivo

        if (!response.ok) {
            throw new Error('Error al cargar detalles de la reunión');
        }

        const data = await response.json();

        updateLoadingStep(3); // Paso 3: Descargando audio

        if (data.success) {
            updateLoadingStep(4); // Paso 4: Procesando contenido

            // Esperar un poco para mostrar el progreso completo
            await new Promise(resolve => setTimeout(resolve, 500));

            currentModalMeeting = data.meeting;
            renderMeetingModal(data.meeting);
            showMeetingModal();
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

function renderMeetingModal(meeting) {
    const modalHtml = `
        <div class="meeting-modal active" id="meetingModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-section">
                        <h2 class="modal-title" id="modalTitle">${escapeHtml(meeting.meeting_name)}</h2>
                        <p class="modal-subtitle">Reunión del ${meeting.created_at}</p>
                    </div>

                    <button class="close-btn" onclick="closeMeetingModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Reproductor de Audio -->
                    <div class="modal-section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M9 9v6l6-6" />
                            </svg>
                            Audio de la Reunión
                        </h3>
                        <div class="audio-player">
                            <audio controls preload="metadata">
                                <source src="${meeting.audio_path}" type="audio/mpeg">
                                Tu navegador no soporta el reproductor de audio.
                            </audio>
                        </div>
                    </div>

                    <!-- Resumen -->
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

                    <!-- Puntos Clave -->
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

                    <!-- Tareas/Acciones -->
                    ${meeting.tasks && meeting.tasks.length > 0 ? `
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
                    ` : ''}

                    <!-- Participantes/Hablantes -->
                    ${meeting.speakers && meeting.speakers.length > 0 ? `
                    <div class="modal-section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Participantes
                        </h3>
                        <div class="section-content">
                            ${renderSpeakers(meeting.speakers)}
                        </div>
                    </div>
                    ` : ''}

                    <!-- Transcripción por Segmentos -->
                    ${meeting.segments && meeting.segments.length > 0 ? `
                    <div class="modal-section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                            Transcripción por Hablante
                        </h3>
                        <div class="section-content transcription-segmented" style="max-height: 400px; overflow-y: auto;">
                            ${renderSegments(meeting.segments)}
                        </div>
                    </div>
                    ` : `
                    <!-- Transcripción Completa -->
                    <div class="modal-section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                            Transcripción Completa
                        </h3>
                        <div class="section-content transcription-full" style="max-height: 400px; overflow-y: auto;">
                            ${renderTranscription(meeting.transcription)}
                        </div>
                    </div>
                    `}
                </div>
            </div>
        </div>
    `;

    // Añadir modal al DOM
    const existingModal = document.getElementById('meetingModal');
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHtml);
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
                // Asegurar que task sea un string
                const taskText = typeof task === 'string' ? task : (task?.text || task?.description || String(task) || 'Tarea sin descripción');
                return `
                    <li class="task-item">
                        <div class="task-checkbox">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="task-text">${escapeHtml(taskText)}</span>
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
        return '<p class="text-slate-400">No hay segmentación por hablante disponible.</p>';
    }

    return `
        <div class="transcription-segments">
            ${segments.map((segment, index) => `
                <div class="segment">
                    <div class="segment-header">
                        <span class="speaker-label">${escapeHtml(segment.speaker || 'Hablante ' + (index + 1))}</span>
                        ${segment.timestamp ? `<span class="timestamp">${segment.timestamp}</span>` : ''}
                    </div>
                    <div class="segment-text">${escapeHtml(segment.text)}</div>
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

// ===============================================
// CONTROL DEL MODAL
// ===============================================
function showMeetingModal() {
    const modal = document.getElementById('meetingModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Añadir animación de entrada
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }
}

function closeMeetingModal() {
    const modal = document.getElementById('meetingModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';

        // Esperar a que termine la animación antes de ocultar
        setTimeout(() => {
            if (modal && !modal.classList.contains('active')) {
                modal.style.display = 'none';
                modal.remove();
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
function showLoadingState() {
    const container = document.querySelector('.fade-in.stagger-2');
    container.innerHTML = `
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Cargando reuniones...</p>
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

function showErrorState(message) {
    const container = document.querySelector('.fade-in.stagger-2');
    container.innerHTML = `
        <div class="error-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <h3 class="text-lg font-semibold mb-2">Error al cargar</h3>
            <p>${message}</p>
            <button onclick="loadMeetings()" class="btn-primary mt-4">Reintentar</button>
        </div>
    `;
}

// ===============================================
// BÚSQUEDA
// ===============================================
function handleSearch(event) {
    const query = event.target.value.toLowerCase().trim();

    if (!query) {
        renderMeetings(currentMeetings);
        return;
    }

    const filtered = currentMeetings.filter(meeting =>
        meeting.meeting_name.toLowerCase().includes(query) ||
        (meeting.folder_name && meeting.folder_name.toLowerCase().includes(query)) ||
        (meeting.preview_text && meeting.preview_text.toLowerCase().includes(query))
    );

    renderMeetings(filtered);
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
                        <button class="action-btn secondary" onclick="closeEditNameModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar
                        </button>
                        <button class="action-btn primary" onclick="confirmEditMeetingName(${meetingId})">
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
            loadMeetings();
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
                        <button class="action-btn secondary" onclick="closeDeleteConfirmationModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelar
                        </button>
                        <button class="action-btn danger" onclick="confirmDeleteMeeting(${meetingId})">
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
            loadMeetings();
            closeMeetingModal();
        } else {
            throw new Error(data.message || 'Error al eliminar la reunión');
        }

    } catch (error) {
        console.error('Error deleting meeting:', error);
        showNotification('Error al eliminar la reunión: ' + error.message, 'error');
    }
}// ===============================================
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
