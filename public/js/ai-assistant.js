/**
 * =========================================
 * ASISTENTE IA - JAVASCRIPT PRINCIPAL
 * =========================================
 */

// Variables globales
let currentSessionId = null;
let currentContext = {
    type: 'general',
    id: null,
    data: {}
};
let isLoading = false;
let selectedFiles = [];
let selectedDocuments = [];
let loadedContextItems = [];
let currentContextType = 'containers';
let allMeetings = [];
let allContainers = [];
let chatSessions = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeAiAssistant();
});

/**
 * Inicializar el asistente IA
 */
async function initializeAiAssistant() {
    try {
        await loadChatSessions();
        setupEventListeners();
        await createNewChatSession();
    } catch (error) {
        console.error('Error initializing AI Assistant:', error);
        showNotification('Error al inicializar el asistente', 'error');
    }
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Auto-resize del textarea
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            adjustTextareaHeight(this);
            updateSendButton(isLoading);
        });
    }

    // Botón para seleccionar contexto
    const contextBtn = document.getElementById('context-selector-btn');
    if (contextBtn) {
        contextBtn.addEventListener('click', openContextSelector);
    }

    // Event listeners para el formulario de chat
    const chatForm = document.getElementById('chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', handleMessageSubmit);
    }

    // Botón para nueva conversación
    const newChatBtn = document.getElementById('new-chat-btn');
    if (newChatBtn) {
        newChatBtn.addEventListener('click', createNewChat);
    }

    // Drag and drop para documentos
    // Uploader modal usa su propio setup cuando se abre; aquí sólo el chat
    setupChatDropZone();
    // Paperclip para adjuntar desde el chat
    const attachBtn = document.getElementById('attach-file-btn');
    const chatHiddenInput = document.getElementById('file-input');
    if (attachBtn && chatHiddenInput) {
        attachBtn.addEventListener('click', (e) => {
            e.preventDefault();
            chatHiddenInput.click();
        });
        chatHiddenInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files || []);
            if (files.length > 0) {
                uploadChatAttachments(files);
            }
            // Reset input so selecting same file again triggers change
            e.target.value = '';
        });
    }

    // Pestañas de detalles
    setupDetailsTabs();
}

/**
 * =========================================
 * GESTIÓN DE SESIONES DE CHAT
 * =========================================
 */

/**
 * Cargar sesiones de chat existentes
 */
async function loadChatSessions() {
    try {
        const response = await fetch('/api/ai-assistant/sessions');
        const data = await response.json();

        if (data.success) {
            chatSessions = Array.isArray(data.sessions) ? data.sessions : [];
            renderChatSessions(chatSessions);
        }
    } catch (error) {
        console.error('Error loading chat sessions:', error);
    }
}

/**
 * Renderizar sesiones de chat en la sidebar
 */
function renderChatSessions(sessions) {
    const sessionsList = document.getElementById('sessions-list');
    if (!sessionsList) return;

    if (sessions.length === 0) {
        sessionsList.innerHTML = `
            <div class="empty-sessions">
                <p>No hay conversaciones anteriores</p>
            </div>
        `;
        return;
    }

    sessionsList.innerHTML = sessions.map(session => `
        <div class="chat-session-item ${session.id === currentSessionId ? 'active' : ''}"
             onclick="loadChatSession(${session.id})">
            <div class="session-info">
                <div class="session-title">${escapeHtml(session.title)}</div>
                ${session.last_message ? `
                    <div class="session-preview">${escapeHtml(session.last_message.content)}</div>
                ` : ''}
                <div class="session-meta">
                    <span class="session-context">${getContextDisplayName(session.context_type)}</span>
                    <span class="session-time">${formatRelativeTime(session.last_activity)}</span>
                </div>
            </div>
            <button type="button"
                    class="session-delete-btn"
                    onclick="event.stopPropagation(); deleteChatSession(${session.id});"
                    aria-label="Eliminar conversación">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 7h12m-9 0V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a2 2 0 01-2 2H9a2 2 0 01-2-2V7h10z"></path>
                </svg>
            </button>
        </div>
    `).join('');
}

/**
 * Eliminar una sesión de chat existente
 */
async function deleteChatSession(sessionId) {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/api/ai-assistant/sessions/${sessionId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || ''
            },
            body: JSON.stringify({ force_delete: false })
        });

        if (!response.ok) {
            throw new Error('Respuesta no válida del servidor');
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'No se pudo eliminar la conversación');
        }

        chatSessions = chatSessions.filter(session => session.id !== sessionId);
        let nextSessionId = null;

        if (sessionId === currentSessionId) {
            currentSessionId = null;
            if (chatSessions.length > 0) {
                nextSessionId = chatSessions[0].id;
            } else {
                renderMessages([]);
            }
        }

        renderChatSessions(chatSessions);

        if (nextSessionId !== null) {
            await loadChatSession(nextSessionId);
        }

        showNotification('Conversación eliminada correctamente', 'success');
    } catch (error) {
        console.error('Error deleting chat session:', error);
        showNotification('Error al eliminar la conversación', 'error');
    }
}

/**
 * Crear nueva sesión de chat
 */
async function createNewChat() {
    await createNewChatSession();
}

/**
 * Crear nueva sesión de chat (función interna)
 */
async function createNewChatSession() {
    try {
        const response = await fetch('/api/ai-assistant/sessions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                context_type: currentContext.type,
                context_id: currentContext.id,
                context_data: currentContext.data
            })
        });

        const data = await response.json();
        if (data.success) {
            currentSessionId = data.session.id;
            await loadChatSessions();
            await loadMessages();
            updateContextIndicator();
        }
    } catch (error) {
        console.error('Error creating new chat session:', error);
        showNotification('Error al crear nueva conversación', 'error');
    }
}

/**
 * Cargar sesión de chat específica
 */
async function loadChatSession(sessionId) {
    if (sessionId === currentSessionId) return;

    try {
        currentSessionId = sessionId;
        await loadMessages();

        // Actualizar UI
        document.querySelectorAll('.chat-session-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[onclick="loadChatSession(${sessionId})"]`)?.classList.add('active');

    } catch (error) {
        console.error('Error loading chat session:', error);
        showNotification('Error al cargar la conversación', 'error');
    }
}

/**
 * =========================================
 * GESTIÓN DE MENSAJES
 * =========================================
 */

/**
 * Cargar mensajes de la sesión actual
 */
async function loadMessages() {
    if (!currentSessionId) return;

    try {
        const response = await fetch(`/api/ai-assistant/sessions/${currentSessionId}/messages`);
        const data = await response.json();

        if (data.success) {
            renderMessages(data.messages);
            updateSessionInfo(data.session);
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

/**
 * Actualizar información de la sesión
 */
function updateSessionInfo(session) {
    if (!session) return;

    // Actualizar el título de la sesión si existe un elemento para ello
    const sessionTitle = document.getElementById('current-session-title') || document.getElementById('sessionTitle');
    if (sessionTitle && session.title) {
        sessionTitle.textContent = session.title;
    }

    // Actualizar cualquier otra información de la sesión
    if (session.context_info) {
        updateContextIndicator(session.context_info);
    }
}

/**
 * Renderizar mensajes en el chat
 */
function renderMessages(messages) {
    const chatMessages = document.getElementById('messages-container');
    if (!chatMessages) return;

    if (messages.length === 0) {
        // Mostrar mensaje de bienvenida
        chatMessages.innerHTML = `
            <div class="welcome-message">
                <div class="ai-avatar">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <div class="welcome-content">
                    <h3>¡Hola! Soy tu asistente IA</h3>
                    <p>Puedo ayudarte con análisis de reuniones, búsqueda en documentos, y responder preguntas sobre tu contenido. ¿En qué puedo ayudarte hoy?</p>
                    <div class="suggestions">
                        <button class="suggestion-btn" onclick="sendSuggestion('Muéstrame un resumen de mis últimas reuniones')">
                            📊 Resumen de reuniones recientes
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('¿Qué tareas pendientes tengo?')">
                            ✅ Tareas pendientes
                        </button>
                        <button class="suggestion-btn" onclick="sendSuggestion('Buscar información en mis documentos')">
                            🔍 Buscar en documentos
                        </button>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    chatMessages.innerHTML = messages.map(message => renderMessage(message)).join('');
    scrollToBottom();
}

/**
 * Renderizar un mensaje individual
 */
function renderMessage(message) {
    const isUser = message.role === 'user';
    const avatar = isUser ?
        `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>` :
        `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
        </svg>`;

    const metadata = message.metadata || {};
    const attachments = message.attachments && message.attachments.length > 0 ? renderAttachments(message.attachments) : '';
    const citations = !isUser ? renderMessageCitations(metadata) : '';

    return `
        <div class="message ${isUser ? 'user' : 'assistant'}">
            <div class="message-avatar ${isUser ? 'user' : 'assistant'}">
                ${avatar}
            </div>
            <div class="message-content">
                <div class="message-bubble">
                    ${formatMessageContent(message.content)}
                    ${attachments}
                    ${citations}
                </div>
                <div class="message-time">${formatTime(message.created_at)}</div>
            </div>
        </div>
    `;
}

/**
 * Enviar mensaje
 */
async function sendMessage(messageText = null) {
    if (!currentSessionId) return;

    const messageInput = document.getElementById('message-input');
    const message = messageText || messageInput?.value?.trim() || '';

    if (!message && selectedFiles.length === 0) return;

    const attachmentsToSend = [...selectedFiles];

    isLoading = true;
    updateSendButton(true);

    try {
        // Agregar mensaje del usuario inmediatamente
        if (message) {
            addMessageToChat({
                role: 'user',
                content: message,
                created_at: new Date().toISOString(),
                attachments: attachmentsToSend.map(f => ({ name: f.name, type: 'file' }))
            });
        }

        // Limpiar input
        if (messageInput) {
            messageInput.value = '';
            adjustTextareaHeight(messageInput);
        }
        selectedFiles = [];
        updateAttachmentsDisplay();

        // Enviar al servidor
        const response = await fetch(`/api/ai-assistant/sessions/${currentSessionId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                content: message,
                attachments: attachmentsToSend
            })
        });

        const data = await response.json();
        if (data.success) {
            // Agregar respuesta de la IA
            addMessageToChat(data.assistant_message);

            if (data.session) {
                updateSessionInfo(data.session);
                await loadChatSessions();
            }
        } else {
            throw new Error(data.message || 'Error al enviar mensaje');
        }

    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('Error al enviar mensaje', 'error');
    } finally {
        isLoading = false;
        updateSendButton(false);
    }
}

/**
 * Agregar mensaje al chat (para actualizaciones en tiempo real)
 */
function addMessageToChat(message) {
    const chatMessages = document.getElementById('messages-container');
    if (!chatMessages) return;

    // Remover mensaje de bienvenida si existe
    const welcomeMessage = chatMessages.querySelector('.welcome-message');
    if (welcomeMessage) {
        welcomeMessage.remove();
    }

    // Agregar nuevo mensaje
    const messageElement = document.createElement('div');
    messageElement.innerHTML = renderMessage(message);
    const newMessage = messageElement.firstElementChild;
    if (newMessage) {
        chatMessages.appendChild(newMessage);
    }

    scrollToBottom();
}

/**
 * Enviar sugerencia predefinida
 */
function sendSuggestion(suggestion) {
    const messageInput = document.getElementById('message-input');
    if (!messageInput) return;
    messageInput.value = suggestion;
    adjustTextareaHeight(messageInput);
    sendMessage();
}

/**
 * =========================================
 * GESTIÓN DE CONTEXTO
 * =========================================
 */

/**
 * Abrir selector de contexto
 */
async function openContextSelector() {
    const modal = document.getElementById('contextSelectorModal');
    modal.classList.add('active');

    // Inicializar con contenedores por defecto
    currentContextType = 'containers';
    switchContextType('containers');

    // Cargar datos iniciales
    await loadContextData();
    updateLoadedContextUI();
}

/**
 * Cerrar selector de contexto
 */
function closeContextSelector() {
    const modal = document.getElementById('contextSelectorModal');
    modal.classList.remove('active');
}

/**
 * Cambiar tipo de contexto
 */
function switchContextType(type) {
    currentContextType = type;

    // Actualizar navegación
    document.querySelectorAll('.context-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-type="${type}"]`).classList.add('active');

    // Actualizar vistas
    document.querySelectorAll('.context-view').forEach(view => {
        view.classList.remove('active');
    });

    if (type === 'containers') {
        document.getElementById('containersView').classList.add('active');
        document.getElementById('contextSearchInput').placeholder = 'Buscar contenedores...';
    } else if (type === 'meetings') {
        document.getElementById('meetingsView').classList.add('active');
        document.getElementById('contextSearchInput').placeholder = 'Buscar reuniones...';
    }

    // Cargar datos del tipo seleccionado
    loadContextData();
}

/**
 * Cargar datos de contexto
 */
async function loadContextData() {
    if (currentContextType === 'containers') {
        await loadContainers();
    } else if (currentContextType === 'meetings') {
        await loadMeetings();
    }
}

/**
 * Cargar contenedores
 */
async function loadContainers() {
    const grid = document.getElementById('containersGrid');
    grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Cargando contenedores...</p></div>';

    try {
        const response = await fetch('/api/ai-assistant/containers');
        const data = await response.json();

        if (data.success) {
            allContainers = data.containers;
            renderContainers(data.containers);
        }
    } catch (error) {
        console.error('Error loading containers:', error);
        grid.innerHTML = '<div class="empty-state"><p>Error al cargar contenedores</p></div>';
    }
}

/**
 * Cargar reuniones
 */
async function loadMeetings() {
    const grid = document.getElementById('meetingsGrid');
    grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Cargando reuniones...</p></div>';

    try {
        const response = await fetch('/api/ai-assistant/meetings');
        const data = await response.json();

        if (data.success) {
            allMeetings = data.meetings;
            renderMeetings(data.meetings);
        }
    } catch (error) {
        console.error('Error loading meetings:', error);
        grid.innerHTML = '<div class="empty-state"><p>Error al cargar reuniones</p></div>';
    }
}

/**
 * Renderizar contenedores
 */
function renderContainers(containers) {
    const grid = document.getElementById('containersGrid');
    if (!grid) return;

    if (containers.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <p>No hay contenedores disponibles</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = containers.map(container => `
        <div class="container-card" onclick="addContainerToContext(${container.id})">
            <h4>${escapeHtml(container.name)}</h4>
            <div class="container-meta">
                ${container.meetings_count} reuniones
            </div>
        </div>
    `).join('');
}

/**
 * Renderizar reuniones
 */
function renderMeetings(meetings) {
    const grid = document.getElementById('meetingsGrid');
    if (!grid) return;

    if (meetings.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <p>No hay reuniones disponibles</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = meetings.map(meeting => {
        const title = meeting.meeting_name || 'Reunión sin título';
        const createdAt = formatDate(meeting.created_at);
        const duration = meeting.duration ? String(meeting.duration) : 'Duración no disponible';

        return `
        <div class="meeting-card">
            <div class="meeting-card-header">
                <div class="meeting-card-title" onclick="openMeetingDetails(${meeting.id})">
                    <h4>${escapeHtml(title)}</h4>
                    <div class="meeting-card-meta">
                        ${escapeHtml(createdAt)} • ${escapeHtml(duration)}
                    </div>
                </div>
            </div>
            <div class="meeting-card-actions">
                <button class="meeting-action-btn load-btn" onclick="addMeetingToContext(${meeting.id})" title="Cargar al contexto">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    }).join('');
}

/**
 * Abrir detalles de reunión
 */
async function openMeetingDetails(meetingId) {
    const modal = document.getElementById('meetingDetailsModal');
    modal.classList.add('active');

    // Encontrar la reunión en la lista
    const meeting = allMeetings.find(m => m.id === meetingId);
    if (meeting) {
        document.getElementById('meetingDetailsTitle').textContent = meeting.meeting_name || 'Reunión sin título';
    }

    // Mostrar estado de carga en todas las pestañas
    showLoadingInTabs();

    // Cargar datos de la reunión
    await loadMeetingDetails(meetingId);
}

/**
 * Cerrar modal de detalles de reunión
 */
function closeMeetingDetailsModal() {
    const modal = document.getElementById('meetingDetailsModal');
    modal.classList.remove('active');
}

/**
 * Cambiar pestaña en el modal de detalles
 */
function switchTab(tabName) {
    // Actualizar botones de pestañas
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Actualizar contenido de pestañas
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.getElementById(`${tabName}Tab`).classList.add('active');
}

/**
 * Mostrar estado de carga en todas las pestañas
 */
function showLoadingInTabs() {
    const tabs = ['summary', 'keypoints', 'tasks', 'transcription'];
    tabs.forEach(tab => {
        const tabElement = document.getElementById(`${tab}Tab`);
        if (tabElement) {
            tabElement.innerHTML = `
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Cargando ${tab === 'keypoints' ? 'puntos clave' :
                        tab === 'tasks' ? 'tareas' :
                        tab === 'transcription' ? 'transcripción' : 'resumen'}...</p>
                </div>
            `;
        }
    });
}

/**
 * Cargar detalles completos de una reunión
 */
async function loadMeetingDetails(meetingId) {
    try {
        const response = await fetch(`/api/meetings/${meetingId}`);
        const data = await response.json();

        if (data.success) {
            const meeting = data.meeting;

            // Procesar datos del archivo .ju si existe
            if (meeting.segments || meeting.summary || meeting.key_points || meeting.tasks) {
                renderMeetingDetails(meeting);
            } else {
                // Intentar obtener datos del archivo .ju
                await loadJuFileData(meetingId);
            }
        }
    } catch (error) {
        console.error('Error loading meeting details:', error);
        showErrorInTabs('Error al cargar los detalles de la reunión');
    }
}

/**
 * Cargar datos del archivo .ju
 */
async function loadJuFileData(meetingId) {
    try {
        // Esta función intentaría descargar y parsear el archivo .ju
        // Por ahora, mostraremos mensaje de no disponible
        showErrorInTabs('Detalles no disponibles para esta reunión');
    } catch (error) {
        console.error('Error loading .ju file:', error);
        showErrorInTabs('Error al cargar archivo .ju');
    }
}

/**
 * Renderizar detalles de la reunión en las pestañas
 */
function renderMeetingDetails(meeting) {
    // Resumen
    const summaryTab = document.getElementById('summaryTab');
    summaryTab.innerHTML = `
        <div class="summary-content">
            ${meeting.summary || 'No hay resumen disponible para esta reunión.'}
        </div>
    `;

    // Puntos clave
    const keypointsTab = document.getElementById('keypointsTab');
    if (meeting.key_points && meeting.key_points.length > 0) {
        keypointsTab.innerHTML = `
            <div class="keypoints-content">
                <ul>
                    ${meeting.key_points.map(point => `<li>${escapeHtml(point)}</li>`).join('')}
                </ul>
            </div>
        `;
    } else {
        keypointsTab.innerHTML = '<div class="keypoints-content">No hay puntos clave disponibles.</div>';
    }

    // Tareas
    const tasksTab = document.getElementById('tasksTab');
    if (meeting.tasks && meeting.tasks.length > 0) {
        tasksTab.innerHTML = `
            <div class="tasks-content">
                ${meeting.tasks.map(task => `
                    <div class="task-item">
                        <div class="task-checkbox ${task.completed ? 'completed' : ''}"></div>
                        <div class="task-content">
                            <h5>${escapeHtml(task.title || task.tarea)}</h5>
                            ${task.description || task.descripcion ? `<p>${escapeHtml(task.description || task.descripcion)}</p>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        tasksTab.innerHTML = '<div class="tasks-content">No hay tareas disponibles.</div>';
    }

    // Transcripción
    const transcriptionTab = document.getElementById('transcriptionTab');
    if (meeting.segments && meeting.segments.length > 0) {
        const transcriptionText = meeting.segments.map(segment =>
            `${segment.speaker || 'Participante'}: ${segment.text}`
        ).join('\n\n');

        transcriptionTab.innerHTML = `
            <div class="transcription-content">${escapeHtml(transcriptionText)}</div>
        `;
    } else {
        transcriptionTab.innerHTML = '<div class="transcription-content">No hay transcripción disponible.</div>';
    }
}

/**
 * Mostrar error en todas las pestañas
 */
function showErrorInTabs(message) {
    const tabs = ['summary', 'keypoints', 'tasks', 'transcription'];
    tabs.forEach(tab => {
        const tabElement = document.getElementById(`${tab}Tab`);
        if (tabElement) {
            tabElement.innerHTML = `<div class="error-state"><p>${message}</p></div>`;
        }
    });
}

/**
 * Agregar reunión al contexto cargado
 */
function addMeetingToContext(meetingId) {
    const meeting = allMeetings.find(m => m.id === meetingId);
    if (!meeting) return;

    // Verificar si ya está cargada
    const existingItem = loadedContextItems.find(item => item.type === 'meeting' && item.id === meetingId);
    if (existingItem) {
        showNotification('Esta reunión ya está cargada en el contexto', 'warning');
        return;
    }

    // Agregar al contexto
    loadedContextItems.push({
        type: 'meeting',
        id: meetingId,
        title: meeting.meeting_name || 'Reunión sin título',
        data: meeting
    });

    updateLoadedContextUI();
    showNotification('Reunión agregada al contexto', 'success');
}

/**
 * Actualizar UI del contexto cargado
 */
function updateLoadedContextUI() {
    const container = document.getElementById('loadedContextItems');
    const loadBtn = document.getElementById('loadContextBtn');

    if (loadedContextItems.length === 0) {
        container.innerHTML = '<div class="empty-context"><p>No hay contexto cargado</p></div>';
        loadBtn.disabled = true;
    } else {
        container.innerHTML = loadedContextItems.map((item, index) => `
            <div class="context-item">
                <div class="context-item-info">
                    <div class="context-item-title">${escapeHtml(item.title)}</div>
                    <div class="context-item-type">${item.type === 'meeting' ? 'Reunión' : 'Contenedor'}</div>
                </div>
                <button class="context-item-remove" onclick="removeContextItem(${index})">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `).join('');
        loadBtn.disabled = false;
    }
}

/**
 * Remover elemento del contexto
 */
function removeContextItem(index) {
    loadedContextItems.splice(index, 1);
    updateLoadedContextUI();
}

/**
 * Cargar contexto seleccionado
 */
async function loadSelectedContext() {
    if (loadedContextItems.length === 0) return;

    try {
        const serializedItems = loadedContextItems
            .filter(item => item && item.type && item.id !== undefined && item.id !== null)
            .map(item => ({
                type: item.type,
                id: item.id
            }));

        if (serializedItems.length === 0) {
            showNotification('No se pudo cargar el contexto seleccionado', 'error');
            return;
        }

        // Si solo hay un elemento y es un contenedor, crear sesión de tipo 'container'
        if (serializedItems.length === 1 && serializedItems[0].type === 'container') {
            const containerId = serializedItems[0].id;
            const container = allContainers.find(c => c.id === containerId);

            // Preload all .ju files for meetings in this container before creating the session
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const preloadResp = await fetch(`/api/ai-assistant/containers/${containerId}/preload`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({})
                });
                // Ignore result intentionally; just warming cache and verifying availability
                await preloadResp.json().catch(() => ({}));
            } catch (e) {
                console.warn('Preload contenedor falló, continúo de todos modos', e);
            }
            currentContext = {
                type: 'container',
                id: containerId,
                data: {
                    container_name: container ? container.name : 'Contenedor seleccionado'
                }
            };
        } else if (serializedItems.length === 1 && serializedItems[0].type === 'meeting') {
            // Mantener compatibilidad para un único meeting seleccionado
            const meetingId = serializedItems[0].id;
            const meeting = allMeetings.find(m => m.id === meetingId);
            currentContext = {
                type: 'meeting',
                id: meetingId,
                data: meeting ? meeting : { meeting_name: 'Reunión seleccionada' }
            };
        } else {
            // Contexto mixto para múltiples elementos
            currentContext = {
                type: 'mixed',
                id: null,
                data: {
                    items: serializedItems
                }
            };
        }

        await createNewChatSession();
        closeContextSelector();
        updateContextIndicator();

        // Limpiar contexto cargado
        loadedContextItems = [];
        updateLoadedContextUI();

        showNotification('Contexto cargado exitosamente', 'success');
    } catch (error) {
        console.error('Error loading context:', error);
        showNotification('Error al cargar el contexto', 'error');
    }
}

/**
 * Filtrar elementos de contexto
 */
function filterContextItems(searchTerm) {
    const term = searchTerm.toLowerCase();

    if (currentContextType === 'containers') {
        const filtered = allContainers.filter(container =>
            container.name.toLowerCase().includes(term)
        );
        renderContainers(filtered);
    } else if (currentContextType === 'meetings') {
        const filtered = allMeetings.filter(meeting =>
            (meeting.meeting_name || '').toLowerCase().includes(term)
        );
        renderMeetings(filtered);
    }
}

/**
 * Seleccionar contenedor (función legacy mantenida para compatibilidad)
 */
async function selectContainer(containerId) {
    try {
        // Obtener datos del contenedor
        const response = await fetch(`/api/content-containers/${containerId}/meetings`);
        const data = await response.json();

        if (data.success) {
            // En lugar de crear sesión inmediata, agregar al panel de contexto cargado
            const name = data.container?.name || data.container_name || 'Contenedor';
            // Evitar duplicados
            const exists = loadedContextItems.some(i => i.type === 'container' && i.id === containerId);
            if (!exists) {
                loadedContextItems.push({ type: 'container', id: containerId, title: name, data: { name } });
                updateLoadedContextUI();
                showNotification('Contenedor agregado al contexto', 'success');
            } else {
                showNotification('Este contenedor ya está en el contexto', 'warning');
            }
        }
    } catch (error) {
        console.error('Error selecting container:', error);
        showNotification('Error al seleccionar contenedor', 'error');
    }
}

/**
 * Agregar contenedor al contexto cargado (sin llamar al backend)
 */
function addContainerToContext(containerId) {
    const container = allContainers.find(c => c && c.id === containerId);
    if (!container) {
        showNotification('Contenedor no encontrado', 'error');
        return;
    }

    const exists = loadedContextItems.some(item => item.type === 'container' && item.id === containerId);
    if (exists) {
        showNotification('Este contenedor ya está cargado en el contexto', 'warning');
        return;
    }

    loadedContextItems.push({
        type: 'container',
        id: containerId,
        title: container.name,
        data: { name: container.name }
    });

    updateLoadedContextUI();
    showNotification('Contenedor agregado al contexto', 'success');
}

/**
 * Seleccionar todas las reuniones
 */
async function selectAllMeetings() {
    try {
        const response = await fetch('/api/ai-assistant/meetings');
        const data = await response.json();

        if (data.success) {
            const meetings = Array.isArray(data.meetings) ? data.meetings : [];
            const items = meetings
                .filter(meeting => meeting && meeting.id !== undefined && meeting.id !== null)
                .map(meeting => ({
                    type: 'meeting',
                    id: meeting.id
                }));

            currentContext = {
                type: 'mixed',
                id: null,
                data: {
                    items: items,
                    label: 'Todas las reuniones'
                }
            };

            await createNewChatSession();
            closeContextSelector();
            updateContextIndicator();
        }
    } catch (error) {
        console.error('Error selecting all meetings:', error);
        showNotification('Error al cargar reuniones', 'error');
    }
}

/**
 * Abrir selector de conversaciones con contactos
 */
async function openContactChatSelector() {
    const modal = document.getElementById('contactChatSelectorModal');
    modal.classList.add('active');

    try {
        const response = await fetch('/api/ai-assistant/contact-chats');
        const data = await response.json();

        if (data.success) {
            renderContactChats(data.chats);
        }
    } catch (error) {
        console.error('Error loading contact chats:', error);
        showNotification('Error al cargar conversaciones', 'error');
    }
}

/**
 * Cerrar selector de conversaciones
 */
function closeContactChatSelector() {
    const modal = document.getElementById('contactChatSelectorModal');
    modal.classList.remove('active');
}

/**
 * Renderizar conversaciones con contactos
 */
function renderContactChats(chats) {
    const list = document.getElementById('contactChatsList');
    if (!list) return;

    if (chats.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <p>No hay conversaciones disponibles</p>
            </div>
        `;
        return;
    }

    list.innerHTML = chats.map(chat => `
        <div class="contact-chat-item" onclick="selectContactChat(${chat.id})">
            <h4>${escapeHtml(chat.contact_name)}</h4>
            <p>${escapeHtml(chat.contact_email)}</p>
            <div class="chat-meta">
                ${chat.messages_count} mensajes
                ${chat.last_message ? `
                    <span>• ${formatRelativeTime(chat.last_message.created_at)}</span>
                ` : ''}
            </div>
        </div>
    `).join('');
}

/**
 * Seleccionar conversación con contacto
 */
function selectContactChat(chatId) {
    currentContext = {
        type: 'contact_chat',
        id: chatId,
        data: {}
    };

    createNewChatSession();
    closeContactChatSelector();
    updateContextIndicator();
}

/**
 * Cargar todas las conversaciones con contactos
 */
function loadAllContactChats() {
    currentContext = {
        type: 'contact_chat',
        id: 'all',
        data: {}
    };

    createNewChatSession();
    closeContactChatSelector();
    updateContextIndicator();
}

/**
 * =========================================
 * GESTIÓN DE DOCUMENTOS
 * =========================================
 */

/**
 * Abrir uploader de documentos
 */
async function openDocumentUploader() {
    const modal = document.getElementById('documentUploaderModal');
    modal.classList.add('active');

    // Cargar documentos existentes por defecto
    await loadExistingDocuments();
    // Enlazar drag&drop del uploader ahora que los elementos existen en DOM
    setupFileDropZone();
}

/**
 * Cerrar uploader de documentos
 */
function closeDocumentUploader() {
    const modal = document.getElementById('documentUploaderModal');
    modal.classList.remove('active');
    selectedFiles = [];
    selectedDocuments = [];
    updateFileDisplay();
}

/**
 * Cambiar pestaña en el modal de documentos
 */
function switchDocumentTab(tabName) {
    // Actualizar botones de pestañas
    document.querySelectorAll('.document-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[onclick="switchDocumentTab('${tabName}')"]`).classList.add('active');

    // Actualizar contenido
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tabName}Tab`).classList.add('active');

    // Actualizar botones del footer
    const uploadBtn = document.getElementById('uploadDocumentBtn');
    const loadBtn = document.getElementById('loadDocumentsBtn');

    if (tabName === 'upload') {
        uploadBtn.style.display = selectedFiles.length > 0 ? 'flex' : 'none';
        loadBtn.style.display = 'none';
    } else {
        uploadBtn.style.display = 'none';
        loadBtn.style.display = 'flex';
    }
}

/**
 * Configurar zona de drop para archivos
 */
function setupFileDropZone() {
    const dropZone = document.getElementById('fileDropZone');
    const fileInput = document.getElementById('fileInput');

    if (!dropZone || !fileInput) return;

    // Click para abrir selector
    dropZone.addEventListener('click', () => fileInput.click());

    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });

    // Selección de archivos
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
}

/**
 * Manejar archivos seleccionados
 */
function handleFiles(files) {
    Array.from(files).forEach(file => {
        if (isValidFile(file)) {
            selectedFiles.push(file);
        } else {
            showNotification(`Archivo no válido: ${file.name}`, 'error');
        }
    });

    updateFileDisplay();
    switchDocumentTab('upload'); // Cambiar a pestaña de upload
}

/**
 * Validar archivo
 */
function isValidFile(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    return file.size <= maxSize && allowedTypes.includes(file.type);
}

/**
 * Actualizar visualización de archivos seleccionados
 */
function updateFileDisplay() {
    const container = document.getElementById('selectedFiles');
    const uploadBtn = document.getElementById('uploadDocumentBtn');

    if (!container) return;

    if (selectedFiles.length === 0) {
        container.innerHTML = '';
        uploadBtn.style.display = 'none';
        return;
    }

    container.innerHTML = selectedFiles.map((file, index) => `
        <div class="selected-file">
            <div class="file-info">
                <div class="file-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="file-details">
                    <h5>${escapeHtml(file.name)}</h5>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
            </div>
            <button class="remove-file-btn" onclick="removeSelectedFile(${index})">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `).join('');

    uploadBtn.style.display = 'flex';
}

/**
 * Remover archivo seleccionado
 */
function removeSelectedFile(index) {
    selectedFiles.splice(index, 1);
    updateFileDisplay();
}

/**
 * Subir documentos
 */
async function uploadDocuments() {
    if (selectedFiles.length === 0) return;

    try {
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });

        const driveType = document.getElementById('driveType').value;
        const driveFolderId = document.getElementById('driveFolderId').value;

        formData.append('drive_type', driveType);
        if (driveFolderId) {
            formData.append('drive_folder_id', driveFolderId);
        }
        // Mantener la sesión actual (no crear otra)
        if (currentSessionId) {
            formData.append('session_id', String(currentSessionId));
        }

        // Mostrar mensajes de carga en el chat por cada archivo
        const loadingMessages = [];
        selectedFiles.forEach(file => {
            const msg = {
                role: 'assistant',
                content: `Cargando documento "${file.name}"…`,
                created_at: new Date().toISOString(),
            };
            addMessageToChat(msg);
            loadingMessages.push({ name: file.name });
        });

        const response = await fetch('/api/ai-assistant/documents/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            showNotification('Documentos subidos, procesando…', 'success');
            const uploaded = Array.isArray(data.documents) ? data.documents : [];
            const docIds = uploaded.map(d => d && (d.id ?? d.document_id)).filter(Boolean);

            // Limpiar selección y cerrar modal
            selectedFiles = [];
            updateFileDisplay();
            closeDocumentUploader();

            // Esperar a que terminen de procesarse y confirmar en el chat
            if (docIds.length > 0) {
                try {
                    const waitResp = await fetch('/api/ai-assistant/documents/wait', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ ids: docIds, timeout_ms: 12000 })
                    });
                    const waitData = await waitResp.json().catch(() => ({ success: false }));
                    const finishedDocs = (waitData && waitData.success ? waitData.documents : uploaded) || [];

                    finishedDocs.forEach(doc => {
                        const status = doc.processing_status || 'processing';
                        const name = doc.name || 'Documento';
                        if (status === 'completed') {
                            addMessageToChat({
                                role: 'assistant',
                                content: `El documento "${name}" ya está cargado y listo para usar en esta conversación.`,
                                created_at: new Date().toISOString(),
                            });
                        } else if (status === 'failed') {
                            addMessageToChat({
                                role: 'assistant',
                                content: `Hubo un error procesando el documento "${name}".`,
                                created_at: new Date().toISOString(),
                            });
                        } else {
                            addMessageToChat({
                                role: 'assistant',
                                content: `El documento "${name}" sigue procesándose. Te avisaré cuando esté listo.`,
                                created_at: new Date().toISOString(),
                            });
                        }
                    });
                } catch (e) {
                    console.warn('Espera de procesamiento de documentos falló:', e);
                }
            }
        } else {
            throw new Error(data.message || 'Error al subir documentos');
        }

    } catch (error) {
        console.error('Error uploading documents:', error);
        showNotification('Error al subir documentos', 'error');
    }
}

/**
 * Cargar documentos existentes
 */
async function loadExistingDocuments() {
    try {
        const response = await fetch('/api/ai-assistant/documents');
        const data = await response.json();

        if (data.success) {
            renderExistingDocuments(data.documents);
        }
    } catch (error) {
        console.error('Error loading documents:', error);
    }
}

/**
 * Renderizar documentos existentes
 */
function renderExistingDocuments(documents) {
    const list = document.getElementById('documentsList');
    if (!list) return;

    if (documents.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <p>No hay documentos disponibles</p>
            </div>
        `;
        return;
    }

    list.innerHTML = documents.map(doc => `
        <div class="document-item" onclick="toggleDocumentSelection(${doc.id})">
            <input type="checkbox" id="doc-${doc.id}" style="margin-right: 0.75rem;">
            <div class="file-info">
                <div class="file-icon">
                    ${getFileTypeIcon(doc.document_type)}
                </div>
                <div class="file-details">
                    <h5>${escapeHtml(doc.name)}</h5>
                    <div class="file-size">${formatFileSize(doc.file_size)} • ${getProcessingStatus(doc.processing_status)}</div>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Toggle selección de documento
 */
function toggleDocumentSelection(docId) {
    const checkbox = document.getElementById(`doc-${docId}`);
    checkbox.checked = !checkbox.checked;

    if (checkbox.checked) {
        if (!selectedDocuments.includes(docId)) {
            selectedDocuments.push(docId);
        }
    } else {
        selectedDocuments = selectedDocuments.filter(id => id !== docId);
    }
}

/**
 * Cargar documentos seleccionados
 */
function loadSelectedDocuments() {
    if (selectedDocuments.length === 0) {
        showNotification('Selecciona al menos un documento', 'warning');
        return;
    }

    currentContext = {
        type: 'documents',
        id: 'selected',
        data: selectedDocuments
    };

    createNewChatSession();
    closeDocumentUploader();
    updateContextIndicator();
}

/**
 * =========================================
 * FUNCIONES DE UTILIDAD
 * =========================================
 */

/**
 * Ajustar altura del textarea
 */
function adjustTextareaHeight(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 128) + 'px';
}

/**
 * Manejar Enter en el textarea
 */
function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

/**
 * Actualizar botón de envío
 */
function updateSendButton(loading) {
    const sendBtn = document.getElementById('send-btn');
    if (!sendBtn) return;

    const messageInput = document.getElementById('message-input');
    const hasMessage = messageInput && messageInput.value.trim().length > 0;

    sendBtn.disabled = loading || !hasMessage;
    if (loading) {
        sendBtn.innerHTML = `
            <div class="spinner" style="width: 1.125rem; height: 1.125rem;"></div>
        `;
    } else {
        sendBtn.innerHTML = `
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
        `;
    }
}

/**
 * Scroll al final del chat
 */
function scrollToBottom() {
    const chatMessages = document.getElementById('messages-container');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

/**
 * Actualizar indicador de contexto
 */
function updateContextIndicator() {
    const indicator = document.getElementById('contextIndicator');
    const title = document.getElementById('chatTitle');

    if (!indicator || !title) return;

    let contextText = 'General';
    let titleText = 'Asistente IA - Juntify';

    switch (currentContext.type) {
        case 'container':
            contextText = 'Contenedor';
            titleText = `Contenedor: ${currentContext.data.container_name || 'Reuniones'}`;
            break;
        case 'meeting':
            contextText = 'Reuniones';
            titleText = 'Análisis de Reuniones';
            break;
        case 'contact_chat':
            contextText = 'Conversaciones';
            titleText = 'Análisis de Conversaciones';
            break;
        case 'documents':
            contextText = 'Documentos';
            titleText = 'Análisis de Documentos';
            break;
        case 'mixed': {
            const items = currentContext.data && Array.isArray(currentContext.data.items)
                ? currentContext.data.items.length
                : 0;
            const label = currentContext.data && currentContext.data.label ? currentContext.data.label : 'Contexto mixto';
            contextText = items > 0 ? `${label} (${items} elementos)` : label;
            titleText = label;
            break;
        }
    }

    indicator.innerHTML = `<span class="context-type">${contextText}</span>`;
    title.textContent = titleText;
}

/**
 * Configurar pestañas de detalles
 */
function setupDetailsTabs() {
    document.querySelectorAll('.details-tabs .tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchDetailsTab(tabName);
        });
    });
}

/**
 * Cambiar pestaña de detalles
 */
function switchDetailsTab(tabName) {
    // Actualizar botones
    document.querySelectorAll('.details-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Actualizar contenido
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.querySelector(`.tab-pane[data-tab="${tabName}"]`).classList.add('active');
}

/**
 * Cargar detalles para contenedor
 */
function loadDetailsForContainer(containerId) {
    // Mostrar pestañas y contenido
    document.getElementById('detailsTabs').style.display = 'flex';
    document.getElementById('tabContent').style.display = 'block';
    document.querySelector('.empty-details').style.display = 'none';

    // Aquí cargarías los detalles específicos del contenedor
    // Por ahora, contenido de ejemplo
    document.getElementById('summaryContent').innerHTML = `
        <div class="detail-section">
            <h4>Resumen del Contenedor</h4>
            <p>Información resumida de todas las reuniones en este contenedor...</p>
        </div>
    `;
}

/**
 * Cargar detalles para todas las reuniones
 */
function loadDetailsForAllMeetings() {
    document.getElementById('detailsTabs').style.display = 'flex';
    document.getElementById('tabContent').style.display = 'block';
    document.querySelector('.empty-details').style.display = 'none';

    document.getElementById('summaryContent').innerHTML = `
        <div class="detail-section">
            <h4>Resumen General</h4>
            <p>Análisis completo de todas tus reuniones...</p>
        </div>
    `;
}

/**
 * Formatear contenido del mensaje
 */
function formatMessageContent(content) {
    content = content || '';
    // Convertir URLs a enlaces
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    content = content.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');

    // Convertir saltos de línea
    content = content.replace(/\n/g, '<br>');

    return content;
}

/**
 * Renderizar adjuntos del mensaje
 */
function renderAttachments(attachments) {
    if (!attachments || attachments.length === 0) return '';

    return `
        <div class="message-attachments">
            ${attachments.map(attachment => `
                <div class="attachment-item">
                    <span class="attachment-name">${escapeHtml(attachment.name)}</span>
                </div>
            `).join('')}
        </div>
    `;
}

function renderMessageCitations(metadata) {
    if (!metadata || !Array.isArray(metadata.citations) || metadata.citations.length === 0) {
        return '';
    }

    return `
        <div class="message-citations">
            <div class="citations-title">Fuentes</div>
            <ul class="citations-list">
                ${metadata.citations.map((citation, index) => renderCitationItem(citation, index)).join('')}
            </ul>
        </div>
    `;
}

function renderCitationItem(citation, index) {
    const marker = citation.marker || `Fuente ${index + 1}`;
    const fragment = citation.fragment || {};
    const locationLabel = formatCitationLocation(fragment.location);
    const preview = getCitationPreview(fragment.text);

    let encodedCitation = '';
    try {
        encodedCitation = encodeURIComponent(JSON.stringify(citation));
    } catch (error) {
        console.error('No se pudo codificar la cita:', error);
    }

    return `
        <li class="citation-item">
            <button class="citation-link" data-citation="${encodedCitation}" onclick="handleCitationClick(event)">
                [${escapeHtml(marker)}]
            </button>
            ${locationLabel ? `<span class="citation-location">${escapeHtml(locationLabel)}</span>` : ''}
            ${preview ? `<div class="citation-preview">${escapeHtml(preview)}</div>` : ''}
        </li>
    `;
}

function getCitationPreview(text) {
    if (!text) {
        return '';
    }

    const normalized = text.trim();
    if (normalized.length <= 180) {
        return normalized;
    }

    return normalized.slice(0, 177) + '…';
}

function formatCitationLocation(location) {
    if (!location || typeof location !== 'object') {
        return '';
    }

    switch (location.type) {
        case 'document': {
            const title = location.title || `Documento ${location.document_id ?? ''}`.trim();
            const page = location.page ? `, página ${location.page}` : '';
            return `${title}${page}`;
        }
        case 'meeting': {
            const title = location.title || `Reunión ${location.meeting_id ?? ''}`.trim();
            const timestamp = location.timestamp ? `, minuto ${location.timestamp}` : '';
            return `${title}${timestamp}`;
        }
        case 'chat': {
            const sender = location.sender || 'Contacto';
            const sentAt = formatDateTime(location.sent_at);
            return sentAt ? `${sender} · ${sentAt}` : sender;
        }
        case 'container': {
            return location.name ? `Contenedor ${location.name}` : 'Contenedor seleccionado';
        }
        default:
            return '';
    }
}

/**
 * Obtener nombre del contexto para mostrar
 */
function getContextDisplayName(contextType) {
    const names = {
        'general': 'General',
        'container': 'Contenedor',
        'meeting': 'Reunión',
        'mixed': 'Mixto',
        'contact_chat': 'Chat',
        'documents': 'Documentos'
    };
    return names[contextType] || 'General';
}

/**
 * Obtener icono según tipo de archivo
 */
function getFileTypeIcon(type) {
    const icons = {
        'pdf': '📄',
        'word': '📝',
        'excel': '📊',
        'powerpoint': '📽️',
        'image': '🖼️',
        'text': '📃'
    };
    return icons[type] || '📄';
}

/**
 * Obtener estado de procesamiento
 */
function getProcessingStatus(status) {
    const statuses = {
        'pending': 'Pendiente',
        'processing': 'Procesando...',
        'completed': 'Completado',
        'failed': 'Error'
    };
    return statuses[status] || 'Desconocido';
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Formatear tiempo relativo
 */
function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));

    if (diffInMinutes < 1) return 'Ahora';
    if (diffInMinutes < 60) return `${diffInMinutes}m`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h`;

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d`;

    return date.toLocaleDateString();
}

/**
 * Formatear tiempo
 */
function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Escape HTML
 */
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

function handleCitationClick(event) {
    event.preventDefault();
    const target = event.currentTarget;

    if (!target || !target.dataset || !target.dataset.citation) {
        return;
    }

    try {
        const citation = JSON.parse(decodeURIComponent(target.dataset.citation));
        const fragment = citation.fragment || {};
        const marker = citation.marker || target.textContent || 'Referencia';
        openCitationModal(fragment, marker);
    } catch (error) {
        console.error('No se pudo interpretar la cita seleccionada:', error);
    }
}

function openCitationModal(fragment, marker) {
    let modal = document.getElementById('citationModal');

    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'citationModal';
        modal.className = 'citation-modal';
        modal.innerHTML = `
            <div class="citation-modal-backdrop" onclick="closeCitationModal()"></div>
            <div class="citation-modal-content">
                <button type="button" class="citation-modal-close" onclick="closeCitationModal()">&times;</button>
                <h4 class="citation-modal-title"></h4>
                <div class="citation-modal-body">
                    <p class="citation-modal-text"></p>
                    <p class="citation-modal-location"></p>
                </div>
                <div class="citation-modal-actions">
                    <a class="citation-modal-open" target="_blank" rel="noopener"></a>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const titleElement = modal.querySelector('.citation-modal-title');
    const textElement = modal.querySelector('.citation-modal-text');
    const locationElement = modal.querySelector('.citation-modal-location');
    const linkElement = modal.querySelector('.citation-modal-open');

    if (titleElement) {
        titleElement.textContent = marker;
    }

    if (textElement) {
        textElement.textContent = fragment.text || 'Sin fragmento disponible.';
    }

    if (locationElement) {
        const locationText = formatCitationLocation(fragment.location);
        locationElement.textContent = locationText ? `Ubicación: ${locationText}` : '';
    }

    if (linkElement) {
        if (fragment.location && fragment.location.url) {
            linkElement.textContent = 'Abrir fuente original';
            linkElement.href = fragment.location.url;
            linkElement.style.display = 'inline-flex';
        } else {
            linkElement.textContent = '';
            linkElement.removeAttribute('href');
            linkElement.style.display = 'none';
        }
    }

    modal.classList.add('active');
}

function closeCitationModal() {
    const modal = document.getElementById('citationModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function formatDateTime(dateString) {
    if (!dateString) {
        return '';
    }

    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString([], {
        dateStyle: 'short',
        timeStyle: 'short'
    });
}

/**
 * Mostrar notificación
 */
function showNotification(message, type = 'info') {
    // Implementar sistema de notificaciones
    console.log(`${type.toUpperCase()}: ${message}`);

    // Aquí podrías integrar con el sistema de notificaciones existente
    // o crear uno nuevo
}

/**
 * Formatear fecha
 */
function formatDate(dateString) {
    if (!dateString) {
        return 'Fecha no disponible';
    }

    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
        return dateString;
    }

    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Obtener nombre de contexto para mostrar
 */
function getContextDisplayName(contextType) {
    const types = {
        'general': 'General',
        'container': 'Contenedor',
        'meeting': 'Reunión',
        'mixed': 'Mixto',
        'contact_chat': 'Chat'
    };
    return types[contextType] || 'Desconocido';
}

/**
 * Manejar envío de mensaje
 */
async function handleMessageSubmit(e) {
    e.preventDefault();

    const messageInput = document.getElementById('message-input');
    if (!messageInput) return;

    const message = messageInput.value.trim();

    if (!message || isLoading) return;

    messageInput.value = '';
    adjustTextareaHeight(messageInput);

    try {
        await sendMessage(message);
    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('Error al enviar el mensaje', 'error');
    }
}

/**
 * Ajustar altura del textarea
 */
function adjustTextareaHeight(textarea) {
    if (!textarea) return;
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

/**
 * Actualizar indicador de contexto
 */
function updateContextIndicator() {
    const indicator = document.getElementById('context-indicator');
    if (!indicator) return;

    let contextText = '';

    if (currentContext.type === 'container') {
        contextText = `Contenedor: ${currentContext.data.container_name || 'Seleccionado'}`;
    } else if (currentContext.type === 'meeting') {
        contextText = `Reunión: ${currentContext.data.meeting_name || 'Seleccionada'}`;
    } else if (currentContext.type === 'mixed') {
        const items = currentContext.data && Array.isArray(currentContext.data.items) ? currentContext.data.items.length : 0;
        const label = currentContext.data && currentContext.data.label ? currentContext.data.label : 'Contexto mixto';
        contextText = items > 0 ? `${label} (${items} elementos)` : label;
    } else {
        contextText = '';
    }

    indicator.textContent = contextText;
    indicator.style.display = contextText ? 'inline-block' : 'none';
}

/**
 * Configurar pestañas de detalles
 */
function setupDetailsTabs() {
    // Ya implementado en las funciones de switchTab
}

/**
 * Configurar zona de drop para archivos
 */
function setupChatDropZone() {
    const dropZone = document.getElementById('messages-container');
    if (!dropZone) return;

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');

        const files = Array.from(e.dataTransfer.files || []);
        if (files.length > 0) {
            uploadChatAttachments(files);
        }
    });
}

async function uploadChatAttachments(files) {
    try {
        const formData = new FormData();
        files.forEach(file => formData.append('files[]', file));

        // Mantener la sesión actual (no crear otra)
        if (currentSessionId) {
            formData.append('session_id', String(currentSessionId));
        }

        // Mostrar mensajes de carga en el chat por cada archivo
        files.forEach(file => {
            addMessageToChat({
                role: 'assistant',
                content: `Cargando documento "${file.name}"…`,
                created_at: new Date().toISOString(),
            });
        });

        const response = await fetch('/api/ai-assistant/documents/upload', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: formData
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'No se pudo subir el archivo');
        }

        showNotification('Archivo(s) subido(s), procesando…', 'success');
        const uploaded = Array.isArray(data.documents) ? data.documents : [];
        const docIds = uploaded.map(d => d && (d.id ?? d.document_id)).filter(Boolean);
        // Esperar brevemente a que terminen de procesarse (si ya están listos, seguirá de inmediato)
        if (docIds.length > 0) {
            try {
                const waitResp = await fetch('/api/ai-assistant/documents/wait', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ ids: docIds, timeout_ms: 8000 })
                });
                const waitData = await waitResp.json().catch(() => ({ success: false }));
                const finishedDocs = (waitData && waitData.success ? waitData.documents : uploaded) || [];
                finishedDocs.forEach(doc => {
                    const status = doc.processing_status || 'processing';
                    const name = doc.name || 'Documento';
                    if (status === 'completed') {
                        addMessageToChat({
                            role: 'assistant',
                            content: `El documento "${name}" ya está cargado y listo para usar en esta conversación.`,
                            created_at: new Date().toISOString(),
                        });
                    } else if (status === 'failed') {
                        addMessageToChat({
                            role: 'assistant',
                            content: `Hubo un error procesando el documento "${name}".`,
                            created_at: new Date().toISOString(),
                        });
                    } else {
                        addMessageToChat({
                            role: 'assistant',
                            content: `El documento "${name}" sigue procesándose. Te avisaré cuando esté listo.`,
                            created_at: new Date().toISOString(),
                        });
                    }
                });
            } catch(_) { /* no-op */ }
        }
    } catch (error) {
        console.error('Error subiendo adjuntos del chat:', error);
        showNotification('Error al subir adjuntos', 'error');
    }
}

/**
 * Manejar subida de archivos
 */
async function handleFileUpload(files) {
    for (const file of files) {
        try {
            // Aquí implementarías la lógica de subida de archivos
            console.log('Uploading file:', file.name);
            showNotification(`Archivo ${file.name} cargado`, 'success');
        } catch (error) {
            console.error('Error uploading file:', error);
            showNotification(`Error al cargar ${file.name}`, 'error');
        }
    }
}

/**
 * Actualizar display de adjuntos
 */
function updateAttachmentsDisplay() {
    // Implementación básica para mostrar archivos adjuntos
    const attachmentsContainer = document.getElementById('attachments-display');
    if (!attachmentsContainer) return;

    if (selectedFiles.length === 0) {
        attachmentsContainer.style.display = 'none';
        return;
    }

    attachmentsContainer.style.display = 'block';
    attachmentsContainer.innerHTML = selectedFiles.map((file, index) => `
        <div class="attachment-item">
            <span class="attachment-name">${escapeHtml(file.name)}</span>
            <button class="attachment-remove" onclick="removeAttachment(${index})">✕</button>
        </div>
    `).join('');
}

/**
 * Remover adjunto
 */
function removeAttachment(index) {
    selectedFiles.splice(index, 1);
    updateAttachmentsDisplay();
}
