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
let isLoadingContext = false;
let selectedFiles = [];
let selectedDocuments = [];
// Mapa de nombres de documentos por id para mostrar chips legibles
const documentNameById = new Map();
// Seguimiento de documentos en procesamiento para actualizar UI cuando terminen
const pendingDocIds = new Set();
let pendingDocWatcher = null;
let currentDriveFolderInfo = { scope: 'personal', driveType: 'personal', label: 'Documentos' };

function addPendingDoc(id) {
    if (!Number.isFinite(Number(id))) return;
    pendingDocIds.add(Number(id));
    ensurePendingDocWatcher();
}

function removePendingDoc(id) {
    pendingDocIds.delete(Number(id));
    if (pendingDocIds.size === 0) stopPendingDocWatcher();
}

function ensurePendingDocWatcher() {
    if (pendingDocWatcher) return;
    // Revisa cada 3s el estado en servidor de los docs pendientes
    pendingDocWatcher = setInterval(async () => {
        try {
            if (pendingDocIds.size === 0) {
                stopPendingDocWatcher();
                return;
            }
            const ids = Array.from(pendingDocIds);
            const resp = await fetch('/api/ai-assistant/documents/wait', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ ids, timeout_ms: 1 })
            });
            const data = await resp.json().catch(() => ({ success: false }));
            if (!data || !data.success || !Array.isArray(data.documents)) return;
            let anyStateChange = false;
            data.documents.forEach(doc => {
                if (!doc || !doc.id) return;
                if (doc && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                if (doc.processing_status === 'completed') {
                    const name = documentNameById.get(Number(doc.id)) || doc.name || 'Documento';
                    addMessageToChat({
                        role: 'assistant',
                        content: `El documento "${name}" ya está cargado y listo para usar en esta conversación.`,
                        created_at: new Date().toISOString(),
                    });
                    addDocIdToSessionContext(Number(doc.id));
                    removePendingDoc(Number(doc.id));
                    anyStateChange = true;
                } else if (doc.processing_status === 'failed') {
                    const name = documentNameById.get(Number(doc.id)) || doc.name || 'Documento';
                    const hint = doc.processing_error ? ` Detalle: ${doc.processing_error}` : '';
                    addMessageToChat({
                        role: 'assistant',
                        content: `Hubo un error procesando el documento "${name}".${hint}`,
                        created_at: new Date().toISOString(),
                    });
                    removePendingDoc(Number(doc.id));
                    anyStateChange = true;
                }
            });
            if (anyStateChange) {
                try { loadExistingDocuments(); } catch (_) {}
                if (window.currentContextType === 'documents') { try { loadDriveDocuments(); } catch (_) {} }
                try { await loadChatSessions(); } catch (_) {}
                try { await loadMessages(); } catch (_) {}
                // Actualizar chips de contexto si aplica
                try { renderContextDocs(); } catch (_) {}
            }
        } catch (_) { /* ignore polling errors */ }
    }, 3000);
}

function stopPendingDocWatcher() {
    if (pendingDocWatcher) {
        clearInterval(pendingDocWatcher);
        pendingDocWatcher = null;
    }
}
let loadedContextItems = [];
let currentContextType = 'containers';
let allMeetings = [];
let allContainers = [];
let allDocuments = [];
let chatSessions = [];
// Menciones (@ documentos, # reuniones/contenedores)
let pendingMentions = [];
let mentionState = { active: false, symbol: null, startIndex: 0, query: '', items: [], selectedIndex: 0 };
let mentionsDropdownEl = null;

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
        await ensureCurrentSession();
        // Precargar datos básicos para menciones
        try { await preloadMentionDatasets(); } catch (e) { console.warn('No se pudieron precargar datos de menciones:', e); }
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
            handleMentionsTrigger(this);
        });
        messageInput.addEventListener('keydown', function(e) {
            if (!mentionState.active) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); moveMentionsSelection(1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); moveMentionsSelection(-1); }
            if (e.key === 'Enter') { e.preventDefault(); applySelectedMention(); }
            if (e.key === 'Escape') { closeMentionsDropdown(); }
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
 * Restablecer contexto a 'general' y limpiar selección temporal
 */
function resetContextToGeneral(clearLoaded = false) {
    currentContext = { type: 'general', id: null, data: {} };
    if (clearLoaded) {
        loadedContextItems = [];
        selectedDocuments = [];
        updateLoadedContextUI();
    }
    currentDriveFolderInfo = { scope: 'personal', driveType: 'personal', label: 'Documentos' };
    updateDriveFolderMeta(null);
    updateContextIndicator();
    renderContextDocs();
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
            // Hard delete by default to limpiar completamente de la BD
            body: JSON.stringify({ force_delete: true })
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
    // Siempre limpiar contexto para nuevas conversaciones
    resetContextToGeneral(true);
    await createNewChatSession();
}

/**
 * Crear nueva sesión de chat (función interna)
 */
async function createNewChatSession() {
    try {
        // Forzar contexto general para nuevas conversaciones
        const payloadContext = {
            type: 'general',
            id: null,
            data: {}
        };

        const response = await fetch('/api/ai-assistant/sessions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                context_type: payloadContext.type,
                context_id: payloadContext.id,
                context_data: payloadContext.data
            })
        });

        const data = await response.json();
        if (data.success) {
            currentSessionId = data.session.id;
            await loadChatSessions();
            await loadMessages();
            // Actualizar indicador (vacío para general)
            updateContextIndicator();
        }
    } catch (error) {
        console.error('Error creating new chat session:', error);
        showNotification('Error al crear nueva conversación', 'error');
    }
}

/**
 * Asegurar que exista una sesión actual: si hay previas, usar la última; si no, crear una
 */
async function ensureCurrentSession() {
    try {
        if (!chatSessions || chatSessions.length === 0) {
            // No hay sesiones previas: crear una nueva en contexto limpio
            resetContextToGeneral(true);
            await createNewChatSession();
            return;
        }
        // Usar la última sesión activa (ordenadas desc por last_activity)
        const last = chatSessions[0];
        currentSessionId = last.id;
        // Mantener contexto visual según la sesión recuperada
        currentContext.type = last.context_type || 'general';
        currentContext.id = last.context_id || null;
        currentContext.data = last.context_data || {};
        await loadMessages();
        updateContextIndicator();
    } catch (e) {
        console.warn('Fallo al asegurar sesión actual, creando nueva:', e);
        await createNewChatSession();
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

    // Sincronizar el contexto global con el de la sesión actual
    currentContext = {
        type: session.context_type || 'general',
        id: session.context_id || null,
        data: (session.context_data && typeof session.context_data === 'object') ? session.context_data : {}
    };
    // Refrescar indicador visual del contexto
    updateContextIndicator();

    // Si el backend devuelve context_data, mantener doc_ids sincronizados
    if (session.context_data && typeof session.context_data === 'object') {
        const docIds = Array.isArray(session.context_data.doc_ids) ? session.context_data.doc_ids : [];
        if (Array.isArray(docIds)) {
            selectedDocuments = [...new Set(docIds.map(Number).filter(n => Number.isFinite(n)))];
            renderContextDocs();
        }
    }

    // Precargar nombres si el backend los envía (opcional)
    if (Array.isArray(session.recent_documents)) {
        session.recent_documents.forEach(d => {
            if (d && d.id && d.name) {
                documentNameById.set(Number(d.id), String(d.name));
            }
        });
        renderContextDocs();
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
                    <p style="margin-top:8px;opacity:.9;">
                        Tipos de referencia: usa <strong>@</strong> para mencionar documentos y seleccionarlos; usa <strong>#</strong> para referenciar reuniones o contenedores.
                    </p>
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
    const userMentions = isUser ? renderUserMentions(metadata) : '';

    return `
        <div class="message ${isUser ? 'user' : 'assistant'}">
            <div class="message-avatar ${isUser ? 'user' : 'assistant'}">
                ${avatar}
            </div>
            <div class="message-content">
                <div class="message-bubble">
                    ${formatMessageContent(message.content)}
                    ${attachments}
                    ${userMentions}
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
                attachments: attachmentsToSend.map(f => ({ name: f.name, type: 'file' })),
                metadata: pendingMentions.length ? { mentions: [...pendingMentions] } : {}
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
                attachments: attachmentsToSend,
                mentions: pendingMentions
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
        pendingMentions = [];
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
 * Actualizar contexto de la sesión actual (sin crear una nueva). Si no hay sesión, crea una.
 */
async function updateCurrentSessionContext(_retried = false) {
    try {
        // Si no hay sesión actual, intentar reutilizar alguna existente primero
        if (!currentSessionId) {
            await ensureCurrentSession();
        }

        // Si aún no hay, crear nueva, pero NO salir: continuamos para aplicar el contexto seleccionado
        if (!currentSessionId) {
            await createNewChatSession();
        }

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch(`/api/ai-assistant/sessions/${currentSessionId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
                context_type: currentContext.type,
                context_id: currentContext.id,
                context_data: currentContext.data
            })
        });

        if (!response.ok) {
            // Si la sesión ya no existe o no es válida, crear nueva y reintentar una vez
            if (!_retried) {
                await createNewChatSession();
                return await updateCurrentSessionContext(true);
            }
            throw new Error('PATCH contexto falló y reintento agotado');
        }

        const data = await response.json();
        if (data && data.success) {
            await loadChatSessions();
            await loadMessages();
        } else {
            // Fallback a crear una nueva si el backend no pudo actualizar
            await createNewChatSession();
        }
    } catch (error) {
        console.warn('No se pudo actualizar el contexto de la sesión:', error);
        // Intento de recuperación: crear y reintentar una vez si aún no reintentamos
        if (!_retried) {
            try {
                await createNewChatSession();
                return await updateCurrentSessionContext(true);
            } catch (e) {
                console.warn('Fallo al reintentar actualización de contexto:', e);
            }
        }
    }
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
    } else if (type === 'documents') {
        document.getElementById('documentsView').classList.add('active');
        document.getElementById('contextSearchInput').placeholder = 'Buscar documentos...';
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
    } else if (currentContextType === 'documents') {
        await loadDriveDocuments();
    }
}

/**
 * Cargar documentos desde la carpeta de Drive "Documentos"
 */
async function loadDriveDocuments(searchTerm = '', options = {}) {
    const grid = document.getElementById('documentsGrid');
    if (!grid) return;

    const metaEl = document.getElementById('driveFolderMeta');
    if (metaEl) {
        metaEl.style.display = 'none';
        metaEl.innerHTML = '';
    }

    if (!options.skipLoader) {
        grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Cargando documentos de Drive...</p></div>';
    }

    const forcePersonal = options.forcePersonal === true;
    const hasContainerContext = !forcePersonal && currentContext && currentContext.type === 'container' && currentContext.id;
    const params = new URLSearchParams();
    const requestedDriveType = forcePersonal ? 'personal' : (hasContainerContext ? 'organization' : 'personal');

    params.set('drive_type', requestedDriveType);
    if (hasContainerContext) {
        params.set('container_id', currentContext.id);
    }
    if (searchTerm) params.set('search', searchTerm);

    try {
        const resp = await fetch(`/api/ai-assistant/documents/drive?${params.toString()}`);

        if (!resp.ok) {
            if (hasContainerContext && !forcePersonal && resp.status >= 400 && resp.status < 500) {
                showNotification('No se pudo cargar la carpeta del contenedor. Mostrando tus documentos personales.', 'warning');
                return await loadDriveDocuments(searchTerm, { forcePersonal: true });
            }
            throw new Error(`HTTP ${resp.status}`);
        }

        const data = await resp.json();

        if (!data || !data.success) {
            if (hasContainerContext && !forcePersonal) {
                const message = data && data.message ? data.message : 'No se pudo cargar la carpeta del contenedor. Se mostrarán tus documentos personales.';
                showNotification(message, 'warning');
                return await loadDriveDocuments(searchTerm, { forcePersonal: true });
            }
            grid.innerHTML = '<div class="empty-state"><p>No se pudieron cargar los documentos de Drive</p></div>';
            updateDriveFolderMeta(data && data.folder ? data.folder : null);
            return;
        }

        const folder = data.folder || null;
        currentDriveFolderInfo = {
            scope: folder?.scope || (hasContainerContext ? 'container' : 'personal'),
            driveType: folder?.drive_type || requestedDriveType,
            label: folder?.label || (hasContainerContext ? 'Contenedor' : 'Documentos'),
            containerId: folder?.container_id || null,
            containerName: folder?.container_name || null,
            fallback: folder?.fallback || false,
            groupName: folder?.group_name || null,
        };

        if (currentDriveFolderInfo.fallback && !forcePersonal) {
            showNotification('La carpeta del contenedor no está disponible. Mostrando documentos personales.', 'warning');
        }

        updateDriveFolderMeta(folder);
        renderDriveDocuments(data.files || []);
    } catch (e) {
        console.error('Error loading Drive documents', e);
        grid.innerHTML = '<div class="empty-state"><p>Error al cargar documentos de Drive</p></div>';
        updateDriveFolderMeta(null);
    }
}

function updateDriveFolderMeta(folder) {
    const metaEl = document.getElementById('driveFolderMeta');
    if (!metaEl) return;

    if (!folder) {
        metaEl.style.display = 'none';
        metaEl.innerHTML = '';
        return;
    }

    const scopeLabel = folder.scope === 'container'
        ? 'Carpeta del contenedor'
        : 'Documentos personales';
    const displayName = folder.container_name || folder.label || 'Documentos';
    const driveLabel = folder.drive_type === 'organization' ? 'Drive organizacional' : 'Mi Drive';
    const groupLabel = folder.group_name ? ` • ${escapeHtml(String(folder.group_name))}` : '';
    const fallbackMessage = folder.fallback
        ? '<div style="font-size:13px;color:#facc15;">Mostrando documentos personales porque la carpeta del contenedor no está disponible.</div>'
        : '';

    metaEl.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:6px;padding:8px 12px;border-radius:12px;background:rgba(15,23,42,0.05);">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="padding:4px 10px;border-radius:999px;background:rgba(59,130,246,0.15);color:#1d4ed8;font-weight:600;font-size:12px;">${escapeHtml(scopeLabel)}</span>
                <span style="font-weight:600;font-size:15px;color:#0f172a;">${escapeHtml(String(displayName))}</span>
            </div>
            <div style="font-size:13px;color:#64748b;">
                ${escapeHtml(driveLabel)}${groupLabel}
            </div>
            ${fallbackMessage}
        </div>
    `;
    metaEl.style.display = 'block';
}

/**
 * Renderizar documentos de Drive con selección múltiple
 */
function renderDriveDocuments(files) {
    const grid = document.getElementById('documentsGrid');
    if (!grid) return;

    if (!Array.isArray(files) || files.length === 0) {
        grid.innerHTML = '<div class="empty-state"><p>No hay documentos en la carpeta de Drive</p></div>';
        return;
    }

    grid.innerHTML = files.map(f => {
        const id = String(f.id);
        const name = f.name || `Archivo ${id}`;
        const size = f.file_size ? formatFileSize(Number(f.file_size)) : '';
        const mime = f.mime_type || '';
        const icon = getFileTypeIcon(mimeToDocType(mime));
        const modified = f.modified_time ? formatRelativeTime(f.modified_time) : '';
        const isSelected = loadedContextItems.some(it => it.type === 'document' && String(it.id) === id);

        return `
        <div class="meeting-card" onclick="toggleDriveDocSelection('${id}')">
            <div class="meeting-card-header">
                <div class="meeting-card-title" style="min-width:0;">
                    <div class="file-title-row" style="display:flex;align-items:center;gap:8px;min-width:0;">
                        <span class="file-type-icon" aria-hidden="true">${icon}</span>
                        <h4 title="${escapeHtml(name)}" style="margin:0;font-size:16px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;">
                            ${escapeHtml(name)}
                        </h4>
                    </div>
                    <div class="meeting-card-meta" style="margin-top:6px;opacity:.85;">
                        ${escapeHtml(size || '—')} ${modified ? '• ' + escapeHtml(modified) : ''}
                    </div>
                </div>
                <div class="meeting-card-actions" style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;">
                    <label class="meeting-action-btn" title="Seleccionar" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;">
                        <input type="checkbox" id="drive-doc-${id}" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); toggleDriveDocSelection('${id}')">
                    </label>
                </div>
            </div>
        </div>`;
    }).join('');
}

// Selección de documentos Drive: almacenamos por ahora sus IDs de Drive en loadedContextItems como type=document (con id driveId)
function toggleDriveDocSelection(driveId) {
    const cb = document.getElementById(`drive-doc-${driveId}`);
    if (!cb) return;
    cb.checked = !cb.checked;

    const existsIndex = loadedContextItems.findIndex(it => it.type === 'document' && it.id === driveId);
    if (cb.checked) {
        if (existsIndex === -1) {
            const labelEl = document.querySelector(`#drive-doc-${CSS.escape(driveId)}`)?.closest('.document-item')?.querySelector('h5');
            const title = labelEl ? labelEl.textContent : `Documento ${driveId}`;
            loadedContextItems.push({ type: 'document', id: driveId, title });
        }
    } else {
        if (existsIndex !== -1) loadedContextItems.splice(existsIndex, 1);
    }
    updateLoadedContextUI();
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

async function preloadMentionDatasets() {
    // Documentos (para @)
    try {
        const resp = await fetch('/api/ai-assistant/documents');
        const data = await resp.json();
        if (data && data.success && Array.isArray(data.documents)) {
            allDocuments = data.documents.map(d => ({ id: d.id, name: d.name || d.original_filename }));
            window.cachedDocuments = allDocuments;
        }
    } catch (e) { /* ignore */ }
    // Reuniones y contenedores ya se cargan bajo demanda en modales, pero intentamos un fetch ligero si aún vacíos
    try {
        if (!allMeetings.length) { const r = await fetch('/api/ai-assistant/meetings'); const j = await r.json(); if (j && j.success) allMeetings = j.meetings || []; }
    } catch (e) { /* ignore */ }
    try {
        if (!allContainers.length) { const r = await fetch('/api/ai-assistant/containers'); const j = await r.json(); if (j && j.success) allContainers = j.containers || []; }
    } catch (e) { /* ignore */ }
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

// Utilidad: mapear mime a tipo de documento para iconos
function mimeToDocType(mime) {
    if (!mime) return 'text';
    if (mime.includes('pdf')) return 'pdf';
    if (mime.includes('presentation')) return 'powerpoint';
    if (mime.includes('spreadsheet') || mime.includes('sheet')) return 'excel';
    if (mime.includes('word') || mime.includes('document')) return 'word';
    if (mime.startsWith('image/')) return 'image';
    return 'text';
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
                    <div class="context-item-type">${item.type === 'meeting' ? 'Reunión' : item.type === 'container' ? 'Contenedor' : 'Documento'}</div>
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
        // Evitar múltiples envíos mientras se pre-carga/crea el contexto
        if (isLoadingContext) return;
        isLoadingContext = true;
        const loadBtn = document.getElementById('loadContextBtn');
        setButtonLoading(loadBtn, true, 'Cargar Contexto', 'Cargando...');

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

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const preloadOnce = async () => {
                    const resp = await fetch(`/api/ai-assistant/containers/${containerId}/preload`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({})
                    });
                    return resp.json().catch(() => ({ success: false, meetings_preloaded: [], errors: [{ error: 'JSON parse error' }] }));
                };

                let result = await preloadOnce();
                const expected = container?.meetings_count ?? null;
                let processed = Array.isArray(result.meetings_preloaded) ? result.meetings_preloaded.length : 0;
                const hadErrors = Array.isArray(result.errors) && result.errors.length > 0;
                if ((expected && processed < expected) || hadErrors) {
                    const retry = await preloadOnce();
                    if (Array.isArray(retry.errors) && retry.errors.length) {
                        result.errors = [...(result.errors || []), ...retry.errors];
                    }
                    if (Array.isArray(retry.meetings_preloaded)) {
                        result.meetings_preloaded = retry.meetings_preloaded;
                        processed = result.meetings_preloaded.length;
                    }
                }
                if (expected && processed < expected) {
                    showNotification(`Precarga incompleta: ${processed}/${expected} reuniones procesadas. Intentaré continuar igualmente.`, 'warning');
                }
                if (Array.isArray(result.errors) && result.errors.length) {
                    showNotification(`Algunas reuniones no pudieron precargarse (${result.errors.length}).`, 'warning');
                    console.warn('Errores preload contenedor', result.errors);
                }
                if (result && result.payload) {
                    window.cachedContainerPayloads = window.cachedContainerPayloads || {};
                    window.cachedContainerPayloads[containerId] = result.payload;
                }
            } catch (e) {
                console.warn('Precarga de contenedor falló; continuaré de todos modos', e);
            }
            currentContext = {
                type: 'container',
                id: containerId,
                data: {
                    container_name: container ? container.name : 'Contenedor seleccionado'
                }
            };
        } else if (serializedItems.length === 1 && serializedItems[0].type === 'meeting') {
            // Precargar .ju antes de fijar el contexto de una reunión
            const meetingId = serializedItems[0].id;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                await fetch(`/api/ai-assistant/meetings/${meetingId}/preload`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf }
                }).then(r => r.json()).catch(() => ({}));
            } catch (_) { /* ignore */ }
            const meeting = allMeetings.find(m => m.id === meetingId);
            currentContext = {
                type: 'meeting',
                id: meetingId,
                data: meeting ? meeting : { meeting_name: 'Reunión seleccionada' }
            };
        } else if (serializedItems.length === 1 && serializedItems[0].type === 'document') {
            // Si se seleccionó un único documento (Drive), lo adjuntamos como documento de asistente
            const driveIds = [String(serializedItems[0].id)];
            await attachDriveDocsAndSetContext(driveIds);
            return;
        } else if (serializedItems.length > 0 && serializedItems.every(it => it.type === 'document')) {
            // Todos son documentos de Drive: adjuntar y establecer contexto de documentos
            const driveIds = serializedItems.map(it => String(it.id));
            await attachDriveDocsAndSetContext(driveIds);
            return;
        } else {
            // Contexto mixto: precargar .ju y adjuntar documentos antes de fijar el contexto
            const meetings = serializedItems.filter(it => it.type === 'meeting').map(it => it.id);
            const containers = serializedItems.filter(it => it.type === 'container').map(it => it.id);
            const driveDocs = serializedItems.filter(it => it.type === 'document').map(it => String(it.id));

            // 1) Preload containers (server will iterate their meetings) usando preload consolidado
            for (const containerId of containers) {
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    const containerInfo = allContainers.find(c => c.id === containerId);
                    const expected = containerInfo?.meetings_count ?? null;
                    const preloadOnce = async () => {
                        const r = await fetch(`/api/ai-assistant/containers/${containerId}/preload`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({})
                        });
                        return r.json().catch(() => ({ success: false, meetings_preloaded: [], errors: [{ error: 'JSON parse error' }] }));
                    };
                    let result = await preloadOnce();
                    let processed = Array.isArray(result.meetings_preloaded) ? result.meetings_preloaded.length : 0;
                    const hadErrors = Array.isArray(result.errors) && result.errors.length > 0;
                    if ((expected && processed < expected) || hadErrors) {
                        const retry = await preloadOnce();
                        if (Array.isArray(retry.errors) && retry.errors.length) {
                            result.errors = [...(result.errors || []), ...retry.errors];
                        }
                        if (Array.isArray(retry.meetings_preloaded)) {
                            result.meetings_preloaded = retry.meetings_preloaded;
                            processed = result.meetings_preloaded.length;
                        }
                    }
                    if (expected && processed < expected) {
                        showNotification(`Precarga de contenedor ${containerInfo?.name || containerId}: ${processed}/${expected} reuniones.`, 'warning');
                    }
                    if (Array.isArray(result.errors) && result.errors.length) {
                        console.warn('Errores preload contenedor (mixto)', result.errors);
                    }
                    if (result && result.payload) {
                        window.cachedContainerPayloads = window.cachedContainerPayloads || {};
                        window.cachedContainerPayloads[containerId] = result.payload;
                    }
                } catch (_) { /* ignore */ }
            }

            // 2) Preload individual meetings
            for (const meetingId of meetings) {
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    await fetch(`/api/ai-assistant/meetings/${meetingId}/preload`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf }
                    }).then(r => r.json()).catch(() => ({}));
                } catch (_) { /* ignore */ }
            }

            // 3) Adjuntar documentos de Drive (crear AiDocument y disparar procesamiento)
            if (driveDocs.length > 0) {
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    const resp = await fetch('/api/ai-assistant/documents/drive/attach', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ drive_file_ids: driveDocs, drive_type: 'personal', session_id: currentSessionId || null })
                    });
                    const data = await resp.json();
                    if (data && data.success) {
                        const docs = Array.isArray(data.documents) ? data.documents : [];
                        docs.forEach(d => { if (d && d.id && d.name) documentNameById.set(Number(d.id), String(d.name)); });
                        // Añadir al contexto explícito
                        const ids = docs.map(d => Number(d.id)).filter(Boolean);
                        selectedDocuments = Array.from(new Set([...(selectedDocuments || []), ...ids]));
                        // Esperar hasta 20s a que terminen de procesar antes de fijar el contexto mixto
                        if (ids.length > 0) {
                            try {
                                const waitResp = await fetch('/api/ai-assistant/documents/wait', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                                    body: JSON.stringify({ ids, timeout_ms: 20000 })
                                });
                                const waitData = await waitResp.json().catch(() => ({ success: false }));
                                const finished = (waitData && waitData.success ? waitData.documents : docs) || [];
                                finished.forEach(doc => {
                                    if (doc && doc.id && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                                });
                            } catch (_) { /* ignore */ }
                        }
                    }
                } catch (e) { /* ignore */ }
            }

            // 4) Ahora sí, fijar el contexto mixto (items se mantienen) y persistir
            currentContext = {
                type: 'mixed',
                id: null,
                data: {
                    items: serializedItems,
                    // También empujamos doc_ids explícitos si adjuntamos algo
                    doc_ids: Array.isArray(selectedDocuments) ? selectedDocuments : []
                }
            };
        }

    await updateCurrentSessionContext();
        closeContextSelector();
        updateContextIndicator();

        if (currentContext && currentContext.type === 'container') {
            try {
                await loadDriveDocuments('', { skipLoader: currentContextType !== 'documents' });
            } catch (err) {
                console.warn('No se pudo cargar la carpeta del contenedor al fijar el contexto', err);
            }
        }

        // Limpiar contexto cargado
        loadedContextItems = [];
        updateLoadedContextUI();

        showNotification('Contexto cargado exitosamente', 'success');
    } catch (error) {
        console.error('Error loading context:', error);
        showNotification('Error al cargar el contexto', 'error');
    } finally {
        // Restaurar estado del botón
        const loadBtn = document.getElementById('loadContextBtn');
        setButtonLoading(loadBtn, false, 'Cargar Contexto', 'Cargando...');
        isLoadingContext = false;
    }
}

async function attachDriveDocsAndSetContext(driveIds) {
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const resp = await fetch('/api/ai-assistant/documents/drive/attach', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ drive_file_ids: driveIds, drive_type: 'personal', session_id: currentSessionId || null })
        });
        const data = await resp.json();
        if (!data || !data.success) {
            showNotification(data && data.message ? data.message : 'No se pudieron adjuntar documentos de Drive', 'error');
            return;
        }
        // Registrar nombres y doc_ids, y esperar procesamiento antes de fijar contexto
        const docs = Array.isArray(data.documents) ? data.documents : [];
        const ids = [];
        docs.forEach(d => { if (d && d.id) { ids.push(Number(d.id)); if (d.name) documentNameById.set(Number(d.id), String(d.name)); } });
        if (ids.length > 0) {
            try {
                // Esperar hasta 20s a que terminen (poll interno en backend)
                const waitResp = await fetch('/api/ai-assistant/documents/wait', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ ids, timeout_ms: 20000 })
                });
                const waitData = await waitResp.json().catch(() => ({ success: false }));
                const finished = (waitData && waitData.success ? waitData.documents : docs) || [];
                finished.forEach(doc => {
                    if (doc && doc.id && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                });
            } catch (_) { /* ignore */ }
        }
        selectedDocuments = Array.from(new Set([...(selectedDocuments || []), ...ids]));
        // Establecer contexto de documentos con esos IDs SOLO después de esperar
        currentContext = { type: 'documents', id: 'selected', data: { doc_ids: selectedDocuments } };
        await updateCurrentSessionContext();
        closeContextSelector();
        updateContextIndicator();
        loadedContextItems = [];
        updateLoadedContextUI();
        renderContextDocs();
        showNotification('Documentos agregados al contexto', 'success');
        // Refrescar listas
        try { loadExistingDocuments(); } catch (_) {}
        if (currentContextType === 'documents') { try { loadDriveDocuments(); } catch (_) {} }
    } catch (e) {
        console.error('Error adjuntando documentos Drive:', e);
        showNotification('Error al adjuntar documentos de Drive', 'error');
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
    } else if (currentContextType === 'documents') {
        loadDriveDocuments(term);
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

// Eliminado: opción "Todas las reuniones" ya no está disponible en el selector

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

    updateCurrentSessionContext();
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

    updateCurrentSessionContext();
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
    const maxSize = 100 * 1024 * 1024; // 100MB, alineado con backend
    const allowedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',      // .xlsx
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
        'image/jpeg', // .jpg/.jpeg
        'image/png'   // .png
    ];
    const allowedExt = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png'];

    // Algunas plataformas devuelven type vacío o genérico: validar por extensión también
    const name = (file && file.name) ? String(file.name) : '';
    const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
    const typeOk = allowedTypes.includes(file.type) || allowedExt.includes(ext);
    const sizeOk = (Number(file.size) || 0) <= maxSize;

    return typeOk && sizeOk;
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
            // precargar mapa id->name
            uploaded.forEach(d => { if (d && d.id && d.name) documentNameById.set(Number(d.id), String(d.name)); });
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
                        if (doc && doc.id && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                        const name = (doc && doc.id && documentNameById.get(Number(doc.id))) || doc.name || 'Documento';
                        if (status === 'completed') {
                            addMessageToChat({
                                role: 'assistant',
                                content: `El documento "${name}" ya está cargado y listo para usar en esta conversación.`,
                                created_at: new Date().toISOString(),
                            });
                            if (doc && doc.id) { addDocIdToSessionContext(Number(doc.id)); }
                            // Refrescar listas visibles
                            try { loadExistingDocuments(); } catch (_) {}
                            if (currentContextType === 'documents') { try { loadDriveDocuments(); } catch (_) {} }
                        } else if (status === 'failed') {
                            const hint = doc.processing_error ? ` Detalle: ${doc.processing_error}` : ' Sugerencia: si es un PDF escaneado, habilita OCR o sube una versión con texto seleccionable.';
                            addMessageToChat({
                                role: 'assistant',
                                content: `Hubo un error procesando el documento "${name}".${hint}`,
                                created_at: new Date().toISOString(),
                            });
                        } else {
                            addMessageToChat({
                                role: 'assistant',
                                content: `El documento "${name}" sigue procesándose. Te avisaré cuando esté listo.`,
                                created_at: new Date().toISOString(),
                            });
                            if (doc && doc.id) addPendingDoc(Number(doc.id));
                        }
                    });

                    // Poll corto adicional para notificar sin refrescar si alguno sigue en processing
                    const pendingIds = finishedDocs.filter(d => (d.processing_status || 'processing') === 'processing').map(d => d.id).filter(Boolean);
                    if (pendingIds.length > 0) {
                        let polls = 0;
                        const maxPolls = 5; // ~5 * 2s = 10s
                        while (polls < maxPolls) {
                            await new Promise(r => setTimeout(r, 2000));
                            try {
                                const pr = await fetch('/api/ai-assistant/documents/wait', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    },
                                    body: JSON.stringify({ ids: pendingIds, timeout_ms: 1 })
                                });
                                const pd = await pr.json().catch(() => ({ success: false }));
                                if (pd && pd.success) {
                                    pd.documents.forEach(doc => {
                                        if (doc && doc.id && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                                        if (doc.processing_status === 'completed') {
                                            addMessageToChat({
                                                role: 'assistant',
                                                content: `El documento "${(doc && doc.id && documentNameById.get(Number(doc.id))) || doc.name || 'Documento'}" ya está cargado y listo para usar en esta conversación.`,
                                                created_at: new Date().toISOString(),
                                            });
                                            if (doc && doc.id) { addDocIdToSessionContext(Number(doc.id)); }
                                            try { loadExistingDocuments(); } catch (_) {}
                                            if (currentContextType === 'documents') { try { loadDriveDocuments(); } catch (_) {} }
                                        } else if (doc.processing_status === 'failed') {
                                            const hint = doc.processing_error ? ` Detalle: ${doc.processing_error}` : '';
                                            addMessageToChat({
                                                role: 'assistant',
                                                content: `Hubo un error procesando el documento "${(doc && doc.id && documentNameById.get(Number(doc.id))) || doc.name || 'Documento'}".${hint}`,
                                                created_at: new Date().toISOString(),
                                            });
                                        }
                                    });
                                    // Si todos ya no est1n en processing, salir
                                    const anyProcessing = pd.documents.some(d => (d.processing_status || 'processing') === 'processing');
                                    if (!anyProcessing) break;
                                    // Agregar los que aún sigan en processing al watcher
                                    pd.documents.filter(d => (d.processing_status || 'processing') === 'processing').forEach(d => { if (d && d.id) addPendingDoc(Number(d.id)); });
                                }
                            } catch (_) { /* ignore */ }
                            polls++;
                        }
                    }
                    // Refrescar sesiones y mensajes para reflejar actividad reciente
                    try { await loadChatSessions(); } catch (_) {}
                    try { await loadMessages(); } catch (_) {}
                    // Refrescar sesiones y mensajes para reflejar actividad reciente
                    try { await loadChatSessions(); } catch (_) {}
                    try { await loadMessages(); } catch (_) {}
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

    list.innerHTML = documents.map(doc => {
        const statusText = doc.status_label || getProcessingStatus(doc.processing_status);
        const stepText = doc.step_label || '';
        const progress = typeof doc.processing_progress === 'number' ? Math.max(0, Math.min(100, doc.processing_progress)) : null;
        const isProcessing = (doc.processing_status === 'processing');
        const isFailed = (doc.processing_status === 'failed');
        const hasError = !!doc.processing_error;

        // Bloque de progreso simple sin depender de CSS externos
        const progressBar = isProcessing && progress !== null ? `
            <div class="doc-progress" style="margin-top:6px;">
                <div style="height:6px;background:#1f2937;border-radius:4px;overflow:hidden;">
                    <div style="height:6px;width:${progress}%;background:#3b82f6;"></div>
                </div>
                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">${stepText} • ${progress}%</div>
            </div>
        ` : '';

        const errorBlock = isFailed && hasError ? `
            <div class="doc-error" style="margin-top:6px;color:#fca5a5;font-size:12px;">
                Error: ${escapeHtml(String(doc.processing_error))}
            </div>
        ` : '';

        return `
        <div class="document-item" onclick="toggleDocumentSelection(${doc.id})">
            <input type="checkbox" id="doc-${doc.id}" style="margin-right: 0.75rem;">
            <div class="file-info">
                <div class="file-icon">
                    ${getFileTypeIcon(doc.document_type)}
                </div>
                <div class="file-details">
                    <h5>${escapeHtml(doc.name)}</h5>
                    <div class="file-size">${formatFileSize(doc.file_size)} • ${escapeHtml(statusText)}</div>
                    ${progressBar}
                    ${errorBlock}
                </div>
            </div>
        </div>`;
    }).join('');
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

    updateCurrentSessionContext();
    closeDocumentUploader();
    updateContextIndicator();
}

/**
 * =========================================
 * FUNCIONES DE UTILIDAD
 * =========================================
 */

/**
 * Habilita/deshabilita un botón mostrando estado de carga
 */
function setButtonLoading(buttonEl, loading, defaultText = 'Enviar', loadingText = 'Cargando...') {
    if (!buttonEl) return;
    buttonEl.disabled = !!loading;
    if (loading) {
        buttonEl.dataset._originalText = buttonEl.dataset._originalText || buttonEl.innerHTML;
        buttonEl.innerHTML = `
            <span class="btn-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid #cbd5e1;border-top-color:transparent;border-radius:50%;margin-right:8px;vertical-align:-2px;"></span>
            ${loadingText}
        `;
    } else {
        if (buttonEl.dataset._originalText) {
            buttonEl.innerHTML = buttonEl.dataset._originalText;
            delete buttonEl.dataset._originalText;
        } else {
            buttonEl.textContent = defaultText;
        }
    }
}

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
// Eliminado: detalles para "todas las reuniones" (ya no se usa)

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

function renderUserMentions(metadata) {
    const list = metadata && Array.isArray(metadata.mentions) ? metadata.mentions : [];
    if (!list.length) return '';
    const pills = list.map(m => {
        const type = m.type;
        const id = m.id;
        const title = m.title || (type === 'document' ? `Doc #${id}` : type === 'meeting' ? `Reunión #${id}` : `Contenedor #${id}`);
        const bg = '#60a5fa'; // azul claro
        const text = '#ffffff'; // letras en blanco
        const border = '#3b82f6'; // borde azul un poco más intenso
        return `<span class=\"mention-pill\" style=\"display:inline-block;background:${bg};color:${text};border:1px solid ${border};border-radius:999px;padding:2px 10px;font-size:12px;margin:2px;\">${escapeHtml(title)}</span>`;
    }).join('');
    return `<div class="message-mentions" style="margin-top:8px;">${pills}</div>`;
}

// ===================== MENCIONES (@ y #) =====================
function handleMentionsTrigger(textarea) {
    const value = textarea.value;
    const cursor = textarea.selectionStart || value.length;
    // Buscar el símbolo más reciente antes del cursor que no tenga espacios
    let start = cursor - 1;
    while (start >= 0 && !['\n', ' '].includes(value[start])) {
        start--;
    }
    start++;
    const segment = value.slice(start, cursor);
    if (!segment) { closeMentionsDropdown(); return; }
    const first = segment[0];
    if (first !== '@' && first !== '#') { closeMentionsDropdown(); return; }
    const query = segment.slice(1);
    mentionState.active = true;
    mentionState.symbol = first;
    mentionState.startIndex = start;
    mentionState.query = query;
    mentionState.selectedIndex = 0;
    updateMentionsItems(query, first);
    openMentionsDropdown(textarea);
}

function updateMentionsItems(query, symbol) {
    const q = (query || '').toLowerCase();
    let items = [];
    if (symbol === '@') {
        // Sugerir documentos
        items = (window.cachedDocuments || allDocuments || []).map(d => ({ type: 'document', id: d.id, title: d.name || d.original_filename || `Documento #${d.id}` }));
    } else {
        // Sugerir reuniones y contenedores
        const meetings = (allMeetings || []).map(m => ({ type: 'meeting', id: m.id, title: m.meeting_name || `Reunión #${m.id}` }));
        const containers = (allContainers || []).map(c => ({ type: 'container', id: c.id, title: c.name || `Contenedor #${c.id}` }));
        items = meetings.concat(containers);
    }
    if (q) {
        items = items.filter(it => String(it.title).toLowerCase().includes(q));
    }
    mentionState.items = items.slice(0, 8);
    renderMentionsDropdown();
}

function openMentionsDropdown(textarea) {
    if (!mentionsDropdownEl) {
        mentionsDropdownEl = document.createElement('div');
        mentionsDropdownEl.className = 'mentions-dropdown';
        mentionsDropdownEl.style.position = 'absolute';
        mentionsDropdownEl.style.zIndex = 1000;
        mentionsDropdownEl.style.background = '#0b1220';
        mentionsDropdownEl.style.border = '1px solid #1f2937';
        mentionsDropdownEl.style.borderRadius = '8px';
        mentionsDropdownEl.style.padding = '6px 0';
        mentionsDropdownEl.style.boxShadow = '0 10px 25px rgba(0,0,0,0.4)';
        document.body.appendChild(mentionsDropdownEl);
    }
    const rect = textarea.getBoundingClientRect();
    mentionsDropdownEl.style.left = (rect.left + 20) + 'px';
    mentionsDropdownEl.style.top = (rect.top - 8) + 'px';
    renderMentionsDropdown();
}

function renderMentionsDropdown() {
    if (!mentionState.active || !mentionsDropdownEl) return;
    const items = mentionState.items || [];
    if (items.length === 0) { mentionsDropdownEl.style.display = 'none'; return; }
    mentionsDropdownEl.style.display = 'block';
    mentionsDropdownEl.innerHTML = items.map((it, idx) => {
        const active = idx === mentionState.selectedIndex;
        const icon = it.type === 'document' ? '📄' : (it.type === 'meeting' ? '🗣️' : '🗂️');
        return `<div class="mention-item${active ? ' active' : ''}" data-index="${idx}" style="padding:6px 10px;cursor:pointer;${active ? 'background:#111827;' : ''}" onclick="selectMentionItem(${idx})">${icon} ${escapeHtml(it.title)} <span style="opacity:.7;font-size:12px;">(${it.type})</span></div>`;
    }).join('');
}

function closeMentionsDropdown() {
    mentionState.active = false;
    mentionState.items = [];
    mentionState.query = '';
    if (mentionsDropdownEl) mentionsDropdownEl.style.display = 'none';
}

function moveMentionsSelection(delta) {
    if (!mentionState.items.length) return;
    const len = mentionState.items.length;
    mentionState.selectedIndex = (mentionState.selectedIndex + delta + len) % len;
    renderMentionsDropdown();
}

function selectMentionItem(index) {
    mentionState.selectedIndex = index;
    applySelectedMention();
}

function applySelectedMention() {
    const textarea = document.getElementById('message-input');
    const chosen = mentionState.items[mentionState.selectedIndex];
    if (!textarea || !chosen) { closeMentionsDropdown(); return; }
    const val = textarea.value;
    const before = val.slice(0, mentionState.startIndex);
    const after = val.slice((textarea.selectionStart || val.length));
    const label = chosen.title || `${chosen.type} #${chosen.id}`;
    const inserted = `[ ${mentionState.symbol}${label}]`;
    textarea.value = before + inserted + after;
    // Registrar mención para enviar al backend
    pendingMentions.push({ type: chosen.type, id: chosen.id, title: chosen.title });
    adjustTextareaHeight(textarea);
    updateSendButton(isLoading);
    closeMentionsDropdown();
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

    switch (currentContext.type) {
        case 'container':
            contextText = `Contenedor: ${currentContext.data?.container_name || 'Seleccionado'}`;
            break;
        case 'meeting':
            contextText = `Reunión: ${currentContext.data?.meeting_name || 'Seleccionada'}`;
            break;
        case 'documents':
            contextText = 'Documentos seleccionados';
            break;
        case 'contact_chat':
            contextText = 'Conversación seleccionada';
            break;
        case 'mixed': {
            const items = currentContext.data && Array.isArray(currentContext.data.items) ? currentContext.data.items.length : 0;
            const label = currentContext.data && currentContext.data.label ? currentContext.data.label : 'Contexto mixto';
            contextText = items > 0 ? `${label} (${items} elementos)` : label;
            break;
        }
        case 'general':
        default:
            contextText = '';
            break;
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
        // Filtrar tipos no permitidos
        const validFiles = files.filter(isValidFile);
        const rejected = files.filter(f => !isValidFile(f));
        if (rejected.length) {
            showNotification(`Se omitieron ${rejected.length} archivo(s) no permitidos. Tipos válidos: PDF, JPG/JPEG, PNG, XLSX, DOCX, PPTX.`, 'warning');
        }
        if (!validFiles.length) return;

        const formData = new FormData();
        validFiles.forEach(file => formData.append('files[]', file));

        // Mantener la sesión actual (no crear otra)
        if (currentSessionId) {
            formData.append('session_id', String(currentSessionId));
        }

        // Mostrar mensajes de carga en el chat por cada archivo
        validFiles.forEach(file => {
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
        // Pre-cargar nombres
        uploaded.forEach(d => { if (d && d.id && d.name) documentNameById.set(Number(d.id), String(d.name)); });
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
                    if (doc && doc.id && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                    const name = (doc && doc.id && documentNameById.get(Number(doc.id))) || doc.name || 'Documento';
                    if (status === 'completed') {
                        addMessageToChat({
                            role: 'assistant',
                            content: `El documento "${name}" ya está cargado y listo para usar en esta conversación.`,
                            created_at: new Date().toISOString(),
                        });
                        // Añadir a doc_ids del contexto y persistir en la sesión
                        if (doc && doc.id) {
                            addDocIdToSessionContext(Number(doc.id));
                        }
                    } else if (status === 'failed') {
                        const hint = doc.processing_error ? ` Detalle: ${doc.processing_error}` : ' Sugerencia: si es un PDF escaneado, habilita OCR o sube una versión con texto seleccionable.';
                        addMessageToChat({
                            role: 'assistant',
                            content: `Hubo un error procesando el documento "${name}".${hint}`,
                            created_at: new Date().toISOString(),
                        });
                    } else {
                        addMessageToChat({
                            role: 'assistant',
                            content: `El documento "${name}" sigue procesándose. Te avisaré cuando esté listo.`,
                            created_at: new Date().toISOString(),
                        });
                        if (doc && doc.id) addPendingDoc(Number(doc.id));
                    }
                });
                // Poll corto adicional para notificar sin refrescar
                const pendingIds = finishedDocs.filter(d => (d.processing_status || 'processing') === 'processing').map(d => d.id).filter(Boolean);
                if (pendingIds.length > 0) {
                    let polls = 0;
                    const maxPolls = 5;
                    while (polls < maxPolls) {
                        await new Promise(r => setTimeout(r, 2000));
                        try {
                            const pr = await fetch('/api/ai-assistant/documents/wait', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ ids: pendingIds, timeout_ms: 1 })
                            });
                            const pd = await pr.json().catch(() => ({ success: false }));
                            if (pd && pd.success) {
                                pd.documents.forEach(doc => {
                                    if (doc && doc.id && doc.name) documentNameById.set(Number(doc.id), String(doc.name));
                                    if (doc.processing_status === 'completed') {
                                        addMessageToChat({
                                            role: 'assistant',
                                            content: `El documento "${(doc && doc.id && documentNameById.get(Number(doc.id))) || doc.name || 'Documento'}" ya está cargado y listo para usar en esta conversación.`,
                                            created_at: new Date().toISOString(),
                                        });
                                        if (doc && doc.id) { addDocIdToSessionContext(Number(doc.id)); }
                                        try { loadExistingDocuments(); } catch (_) {}
                                        if (currentContextType === 'documents') { try { loadDriveDocuments(); } catch (_) {} }
                                    } else if (doc.processing_status === 'failed') {
                                        const hint = doc.processing_error ? ` Detalle: ${doc.processing_error}` : '';
                                        addMessageToChat({
                                            role: 'assistant',
                                            content: `Hubo un error procesando el documento "${(doc && doc.id && documentNameById.get(Number(doc.id))) || doc.name || 'Documento'}".${hint}`,
                                            created_at: new Date().toISOString(),
                                        });
                                    }
                                });
                                const anyProcessing = pd.documents.some(d => (d.processing_status || 'processing') === 'processing');
                                if (!anyProcessing) break;
                                // Añadir los que siguen en processing al watcher
                                pd.documents.filter(d => (d.processing_status || 'processing') === 'processing').forEach(d => { if (d && d.id) addPendingDoc(Number(d.id)); });
                            }
                        } catch (_) { /* ignore */ }
                        polls++;
                    }
                }
            } catch(_) { /* no-op */ }
        }
    } catch (error) {
        console.error('Error subiendo adjuntos del chat:', error);
        showNotification('Error al subir adjuntos', 'error');
    }
}

/**
 * ================================
 * UI: Documentos en contexto
 * ================================
 */
function renderContextDocs() {
    const bar = document.getElementById('context-docs-bar');
    const chips = document.getElementById('context-docs-chips');
    if (!bar || !chips) return;

    const ids = Array.from(new Set((selectedDocuments || []).map(Number).filter(n => Number.isFinite(n))));
    if (ids.length === 0) {
        bar.style.display = 'none';
        chips.innerHTML = '';
        return;
    }

    bar.style.display = 'flex';
    chips.innerHTML = ids.map(id => {
        const name = documentNameById.get(Number(id));
        const label = name ? `${escapeHtml(name)}` : `Doc #${id}`;
        return `
        <span class="doc-chip" data-doc-id="${id}">
            ${label}
            <button class="chip-remove" title="Quitar" onclick="removeDocFromContext(${id})">×</button>
        </span>`;
    }).join('');
}

function addDocIdToSessionContext(id) {
    if (!Number.isFinite(id)) return;
    const data = currentContext.data || {};
    const docIds = Array.isArray(data.doc_ids) ? data.doc_ids : [];
    if (!docIds.includes(id)) {
        docIds.push(id);
    }
    data.doc_ids = docIds;
    currentContext.data = data;
    selectedDocuments = [...new Set(docIds.map(Number))];
    renderContextDocs();
    // Persistir en el backend
    debounceUpdateSessionContext();
}

function removeDocFromContext(id) {
    const data = currentContext.data || {};
    let docIds = Array.isArray(data.doc_ids) ? data.doc_ids : [];
    docIds = docIds.filter(d => Number(d) !== Number(id));
    data.doc_ids = docIds;
    currentContext.data = data;
    selectedDocuments = [...new Set(docIds.map(Number))];
    renderContextDocs();
    debounceUpdateSessionContext();
}

let updateCtxTimer = null;
function debounceUpdateSessionContext() {
    clearTimeout(updateCtxTimer);
    updateCtxTimer = setTimeout(() => {
        updateCurrentSessionContext();
    }, 300);
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
