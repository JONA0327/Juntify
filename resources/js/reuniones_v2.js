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

    } catch (error) {
        console.error('Error loading meetings:', error);
        showErrorState('Error de conexión al cargar reuniones');
    }
}

// ===============================================
// RENDERIZADO DE REUNIONES
// ===============================================
function renderMeetings(meetings) {
    const container = document.querySelector('.fade-in.stagger-2');

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
                    <button class="action-btn delete-btn" onclick="deleteMeeting(${meeting.id})" title="Eliminar reunión">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
    const meetingCards = document.querySelectorAll('.meeting-card');
    meetingCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // No abrir modal si se hizo click en el botón de eliminar
            if (e.target.closest('.delete-btn')) {
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
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
    if (!confirm('¿Estás seguro de que quieres eliminar esta reunión? Esta acción no se puede deshacer.')) {
        return;
    }

    try {
        const response = await fetch(`/api/meetings/${meetingId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (!response.ok) {
            throw new Error('Error al eliminar la reunión');
        }

        // Recargar la lista de reuniones
        loadMeetings();
        closeMeetingModal();
    } catch (error) {
        console.error('Error deleting meeting:', error);
        alert('Error al eliminar la reunión');
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
