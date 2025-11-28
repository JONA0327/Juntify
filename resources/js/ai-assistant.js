/**
 * Asistente IA - JavaScript
 * Funcionalidades principales del chat con IA
 */

// Variables globales
let currentSessionId = null;
let currentContext = { type: 'general', data: null };
let isLoading = false;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeAiAssistant();
});

/**
 * Inicializar el asistente IA
 */
function initializeAiAssistant() {
    loadUserLimits(); // Cargar límites del usuario
    loadChatSessions();
    setupEventListeners();
    initializeEmptyState();
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Formulario de chat
    const chatForm = document.getElementById('chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', handleSendMessage);
    }

    // Input de chat
    const chatInput = document.getElementById('message-input');
    if (chatInput) {
        // Habilitar/deshabilitar botón según contenido
        chatInput.addEventListener('input', function() {
            updateSendButton(isLoading);

            // Actualizar contador de caracteres
            const charCount = document.getElementById('char-count');
            if (charCount) {
                charCount.textContent = this.value.length;
            }
        });

        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSendMessage(e);
            }
        });
    }

    // Tabs del panel de detalles
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
}

/**
 * Cargar sesiones de chat
 */
async function loadChatSessions() {
    try {
        const response = await fetch('/api/ai-assistant/sessions');

        if (!response.ok) {
            const errorData = await response.json();
            console.error('Error loading sessions:', errorData);
            showError(`Error al cargar sesiones: ${errorData.message || response.status}`);
            return;
        }

        const data = await response.json();
        const sessions = data.sessions || data;

        displayChatSessions(sessions);

        // Si hay sesiones, cargar la primera
        if (sessions.length > 0) {
            loadChatSession(sessions[0].id);
        }
    } catch (error) {
        console.error('Error al cargar sesiones:', error);
        showError('Error al cargar el historial de chats');
    }
}

/**
 * Mostrar sesiones de chat en el sidebar
 */
function displayChatSessions(sessions) {
    const container = document.getElementById('chatSessionsList');

    if (sessions.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">No hay chats</div>
                <div class="empty-state-description">Inicia una nueva conversación con el asistente</div>
            </div>
        `;
        return;
    }

    container.innerHTML = sessions.map(session => `
        <div class="chat-session-item" onclick="loadChatSession(${session.id})" data-session-id="${session.id}">
            <div class="session-title">${session.title}</div>
            <div class="session-preview">${session.last_message || 'Nueva conversación'}</div>
        </div>
    `).join('');
}

/**
 * Crear nueva sesión de chat
 */
async function createNewChat() {
    // Verificar límites antes de crear nueva sesión
    if (!canPerformAction('createSession')) {
        showLimitExceededModal('createSession');
        return;
    }

    try {
        const response = await fetch('/api/ai-assistant/sessions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                title: 'Nueva conversación',
                context_type: currentContext.type,
                context_data: currentContext.data
            })
        });

        if (response.status === 403) {
            // Límite alcanzado
            const errorData = await response.json();
            showLimitExceededModal('createSession');
            await loadUserLimits(); // Recargar límites actualizados
            return;
        }

        const responseData = await response.json();

        if (!response.ok) {
            console.error('Error response:', responseData);
            showError(`Error al crear sesión: ${responseData.message || 'Error desconocido'}`);
            return;
        }

        if (responseData.session && responseData.session.id) {
            await loadChatSessions(); // Recargar lista
            loadChatSession(responseData.session.id);
            await loadUserLimits(); // Recargar límites después de crear sesión exitosa
        }
    } catch (error) {
        console.error('Error al crear nueva sesión:', error);
        showError('Error al crear nueva conversación');
    }
}

/**
 * Cargar una sesión de chat específica
 */
async function loadChatSession(sessionId) {
    currentSessionId = sessionId;

    // Actualizar UI
    updateActiveSession(sessionId);

    try {
        const response = await fetch(`/api/ai-assistant/sessions/${sessionId}/messages`);
        const messages = await response.json();

        displayChatMessages(messages);
        scrollToBottom();
    } catch (error) {
        console.error('Error al cargar mensajes:', error);
        showError('Error al cargar la conversación');
    }
}

/**
 * Actualizar sesión activa en el sidebar
 */
function updateActiveSession(sessionId) {
    // Remover clase active de todas las sesiones
    document.querySelectorAll('.chat-session-item').forEach(item => {
        item.classList.remove('active');
    });

    // Agregar clase active a la sesión seleccionada
    const activeItem = document.querySelector(`[data-session-id="${sessionId}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

/**
 * Mostrar mensajes en el chat
 */
function displayChatMessages(messages) {
    const container = document.getElementById('chatMessages');

    if (messages.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">¡Hola! 👋</div>
                <div class="empty-state-description">Soy tu asistente IA. ¿En qué puedo ayudarte hoy?</div>
            </div>
        `;
        return;
    }

    container.innerHTML = messages.map(message => `
        <div class="message ${message.role}">
            <div class="message-avatar">
                ${message.role === 'user' ? 'U' : 'IA'}
            </div>
            <div class="message-content">
                <div class="message-text">${message.content}</div>
                <div class="message-time">${formatTimestamp(message.created_at)}</div>
            </div>
        </div>
    `).join('');
}

/**
 * Manejar envío de mensaje
 */
async function handleSendMessage(e) {
    e.preventDefault();

    if (isLoading) return;

    // Verificar límites antes de proceder
    if (!canPerformAction('sendMessage')) {
        showLimitExceededModal('sendMessage');
        return;
    }

    const input = document.getElementById('message-input');
    const message = input.value.trim();

    if (!message) return;

    // Si no hay sesión activa, crear una nueva
    if (!currentSessionId) {
        await createNewChat();
        if (!currentSessionId) return;
    }

    // Limpiar input y mostrar estado de carga
    input.value = '';
    isLoading = true;
    updateSendButton(true);

    // Agregar mensaje del usuario al chat
    addMessageToChat('user', message);

    try {
        const response = await fetch(`/api/ai-assistant/sessions/${currentSessionId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                message: message,
                context_type: currentContext.type,
                context_data: currentContext.data
            })
        });

        if (response.status === 403) {
            // Límite alcanzado
            try {
                const errorData = await response.json();
                if (errorData?.message) {
                    console.warn('Límite al enviar mensaje:', errorData.message);
                }
            } catch (parseError) {
                console.warn('No se pudo interpretar la respuesta del límite de mensajes.', parseError);
            }

            showLimitExceededModal('sendMessage');
            await loadUserLimits(); // Recargar límites actualizados
            return;
        }

        const result = await response.json();

        if (result.success) {
            // Agregar respuesta de la IA
            addMessageToChat('assistant', result.ai_response);

            // Actualizar panel de detalles si hay información adicional
            if (result.context_info) {
                updateDetailsPanel(result.context_info);
            }

            // Recargar límites después de enviar mensaje exitoso
            await loadUserLimits();
        } else {
            throw new Error(result.message || 'Error al procesar mensaje');
        }
    } catch (error) {
        console.error('Error al enviar mensaje:', error);
        if (isLimitErrorMessage(error?.message)) {
            showLimitExceededModal('sendMessage');
            await loadUserLimits();
        } else {
            showError('Error al comunicarse con el asistente');
            addMessageToChat('assistant', 'Lo siento, hubo un error al procesar tu mensaje. Por favor, inténtalo de nuevo.');
        }
    } finally {
        isLoading = false;
        updateSendButton(false);
        scrollToBottom();

        // Actualizar estado del botón basado en el input
        const currentInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        if (sendBtn && currentInput) {
            sendBtn.disabled = !currentInput.value.trim();
        }
    }
}

function isLimitErrorMessage(message) {
    if (!message) return false;
    const normalized = message.toLowerCase();
    return normalized.includes('límite diario') ||
        normalized.includes('límite de consultas') ||
        normalized.includes('límite de documentos');
}

/**
 * Agregar mensaje al chat
 */
function addMessageToChat(role, content) {
    const container = document.getElementById('messages-container');

    // Si hay mensaje de bienvenida, removerlo
    const welcomeMessage = container.querySelector('.welcome-message');
    if (welcomeMessage) {
        welcomeMessage.remove();
    }

    const messageElement = document.createElement('div');
    messageElement.className = `message ${role}`;
    messageElement.innerHTML = `
        <div class="message-avatar">
            ${role === 'user' ? 'U' : 'IA'}
        </div>
        <div class="message-content">
            <div class="message-text">${content}</div>
            <div class="message-time">${formatTimestamp(new Date())}</div>
        </div>
    `;

    container.appendChild(messageElement);
}

/**
 * Actualizar botón de envío
 */
function updateSendButton(loading) {
    const btn = document.getElementById('send-btn');
    if (!btn) return;

    const messageInput = document.getElementById('message-input');
    const hasMessage = messageInput && messageInput.value.trim().length > 0;

    btn.disabled = loading || !hasMessage;
    btn.innerHTML = loading
        ? '<div class="spinner"></div>'
        : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>';
}

/**
 * Scroll al final del chat
 */
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

/**
 * Cambiar tab en el panel de detalles
 */
function switchTab(tabName) {
    // Actualizar botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Actualizar contenido
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Cargar contenido específico del tab
    loadTabContent(tabName);
}

/**
 * Cargar contenido de tab
 */
function loadTabContent(tabName) {
    const content = document.querySelector(`[data-tab="${tabName}"] div`);

    switch (tabName) {
        case 'summary':
            content.innerHTML = '<div class="empty-state"><div class="empty-state-description">El resumen se generará automáticamente durante la conversación</div></div>';
            break;
        case 'keypoints':
            content.innerHTML = '<div class="empty-state"><div class="empty-state-description">Los puntos clave aparecerán aquí</div></div>';
            break;
        case 'tasks':
            content.innerHTML = '<div class="empty-state"><div class="empty-state-description">Las tareas se extraerán de la conversación</div></div>';
            break;
        case 'transcription':
            content.innerHTML = '<div class="empty-state"><div class="empty-state-description">Las transcripciones aparecerán cuando analices reuniones</div></div>';
            break;
    }
}

/**
 * Actualizar panel de detalles
 */
function updateDetailsPanel(info) {
    if (info.summary) {
        document.getElementById('summaryContent').innerHTML = `<div class="content-section">${info.summary}</div>`;
    }

    if (info.keypoints) {
        document.getElementById('keypointsContent').innerHTML = `
            <div class="content-section">
                <ul class="keypoints-list">
                    ${info.keypoints.map(point => `<li>${point}</li>`).join('')}
                </ul>
            </div>
        `;
    }

    if (info.tasks) {
        document.getElementById('tasksContent').innerHTML = `
            <div class="content-section">
                <div class="tasks-list">
                    ${info.tasks.map(task => `<div class="task-item">${task}</div>`).join('')}
                </div>
            </div>
        `;
    }
}

/**
 * Abrir selector de contenedores
 */
function openContainerSelector() {
    // Esta función se implementará con el modal correspondiente}

/**
 * Abrir selector de chats con contactos
 */
function openContactChatSelector() {
    // Esta función se implementará con el modal correspondiente}

/**
 * Abrir uploader de documentos
 */
function openDocumentUploader() {
    // Esta función se implementará con el modal correspondiente}

/**
 * Inicializar estado vacío
 */
function initializeEmptyState() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages && !chatMessages.children.length) {
        chatMessages.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">¡Hola! 👋</div>
                <div class="empty-state-description">Soy tu asistente IA. ¿En qué puedo ayudarte hoy?</div>
            </div>
        `;
    }
}

/**
 * Formatear timestamp
 */
function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Mostrar error
 */
function showError(message) {
    // Por ahora, usar console.error. Más tarde se puede implementar un toast
    console.error(message);

    // Opcional: agregar mensaje de error al chat
    addMessageToChat('assistant', `❌ ${message}`);
}

/**
 * Utilidades para el contexto
 */
function setContext(type, data) {
    currentContext = { type, data };

    // Actualizar indicador visual
    const indicator = document.getElementById('contextIndicator');
    if (indicator) {
        const typeElement = indicator.querySelector('.context-type');
        if (typeElement) {
            typeElement.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        }
    }
}

/**
 * Funciones para manejo de límites de plan FREE
 */

// Variable global para límites del usuario
let userLimits = null;

function inferPlanName(planCode, planRole) {
    const normalizedCode = (planCode || '').toString().toLowerCase();
    const normalizedRole = (planRole || '').toString().toLowerCase();
    const lookup = {
        free: 'Plan Free',
        freemium: 'Plan Free',
        basic: 'Plan Basic',
        basico: 'Plan Basic',
        negocios: 'Plan Business',
        business: 'Plan Business',
        enterprise: 'Plan Enterprise',
        empresas: 'Plan Empresas',
    };

    if (lookup[normalizedCode]) {
        return lookup[normalizedCode];
    }

    if (lookup[normalizedRole]) {
        return lookup[normalizedRole];
    }

    if (normalizedCode.includes('basic')) {
        return 'Plan Basic';
    }

    if (normalizedCode.includes('free')) {
        return 'Plan Free';
    }

    return normalizedCode ? `Plan ${normalizedCode.charAt(0).toUpperCase()}${normalizedCode.slice(1)}` : 'tu plan actual';
}

function normalizeLimitsResponse(data) {
    if (!data) return null;
    const raw = data.limits ?? data;
    const planCode = (data.planCode ?? raw.planCode ?? data.user_plan ?? '').toString();
    const planRole = (data.planRole ?? raw.planRole ?? '').toString();
    const planName = data.planName ?? raw.planName ?? inferPlanName(planCode, planRole);

    const dailyMessages = raw.dailyMessageCount ?? raw.daily_messages ?? 0;
    const dailyDocuments = raw.dailyDocumentCount ?? raw.daily_documents ?? 0;
    const maxMessages = raw.maxDailyMessages ?? raw.message_limit ?? raw.max_daily_messages ?? null;
    const maxDocuments = raw.maxDailyDocuments ?? raw.document_limit ?? raw.max_daily_documents ?? null;
    const canSend = raw.canSendMessage ?? raw.can_send_message;
    const canUpload = raw.canUploadDocument ?? raw.can_upload_document;
    const canCreateSession = raw.canCreateSession ?? (raw.can_send_message ?? true);

    return {
        allowed: raw.allowed ?? data.allowed ?? false,
        planCode,
        planRole,
        planName,
        dailyMessageCount: dailyMessages,
        dailyDocumentCount: dailyDocuments,
        maxDailyMessages: typeof maxMessages === 'number' ? maxMessages : null,
        maxDailyDocuments: typeof maxDocuments === 'number' ? maxDocuments : null,
        canSendMessage: typeof canSend === 'boolean' ? canSend : true,
        canCreateSession: typeof canCreateSession === 'boolean' ? canCreateSession : true,
        canUploadDocument: typeof canUpload === 'boolean' ? canUpload : true,
    };
}

/**
 * Cargar límites del usuario desde el backend
 */
async function loadUserLimits() {
    try {
        const response = await fetch('/api/ai-assistant/limits', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            const data = await response.json();
            userLimits = normalizeLimitsResponse(data);
            updateUIBasedOnLimits();
        } else {
            console.error('Error al cargar límites del usuario');
        }
    } catch (error) {
        console.error('Error al cargar límites:', error);
    }
}

/**
 * Verificar si el usuario puede realizar una acción específica
 */
function canPerformAction(action) {
    if (!userLimits) return true; // Si no hay límites cargados, permitir

    switch (action) {
        case 'sendMessage':
            return userLimits.canSendMessage;
        case 'createSession':
            return userLimits.canCreateSession;
        case 'uploadDocument':
            return userLimits.canUploadDocument;
        default:
            return true;
    }
}

/**
 * Mostrar modal cuando se exceden los límites
 */
function showLimitExceededModal(action) {
    const limits = userLimits || {};
    let title = 'Límite alcanzado';
    let message = '';

    const messageLimit = limits.maxDailyMessages ?? limits.message_limit ?? limits.max_daily_messages;
    const documentLimit = limits.maxDailyDocuments ?? limits.document_limit ?? limits.max_daily_documents;
    const planLabel = limits.planName || inferPlanName(limits.planCode, limits.planRole);

    switch (action) {
        case 'sendMessage':
        case 'createSession':
        case 'message':
        case 'send_message':
            title = 'Límite de consultas diarias alcanzado';
            if (messageLimit) {
                message = `Has alcanzado el límite de ${messageLimit} consultas diarias del ${planLabel}.<br><br>Actualiza tu plan para tener acceso ilimitado a conversaciones con IA.`;
            } else {
                message = `Has alcanzado el límite diario de consultas del ${planLabel}.<br><br>Actualiza tu plan para tener acceso ilimitado a conversaciones con IA.`;
            }
            break;
        case 'uploadDocument':
        case 'document':
        case 'upload_document':
            title = 'Límite de documentos alcanzado';
            if (documentLimit) {
                message = `Has alcanzado el límite diario de ${documentLimit} documento(s) para el ${planLabel}.<br><br>Actualiza tu plan para tener acceso ilimitado.`;
            } else {
                message = `Has alcanzado el límite diario de documentos del ${planLabel}.<br><br>Actualiza tu plan para tener acceso ilimitado.`;
            }
            break;
    }

    // Usar el modal global mejorado
    if (typeof window.showUpgradeModal === 'function') {
        window.showUpgradeModal({
            title: title,
            message: message,
            icon: 'lock',
            hideCloseButton: false
        });
    } else {
        // Fallback al modal anterior si no está disponible
        const modal = document.getElementById('postpone-locked-modal');
        if (modal) {
            const titleElement = modal.querySelector('h3');
            const messageElement = modal.querySelector('p');

            if (titleElement) titleElement.textContent = title;
            if (messageElement) messageElement.innerHTML = message;

            modal.classList.add('active');
        }
    }
}

/**
 * Actualizar interfaz basada en los límites del usuario
 */
function updateUIBasedOnLimits() {
    if (!userLimits) return;

    // Actualizar contadores en la interfaz si existen
    const messageCountElement = document.getElementById('daily-message-count');
    if (messageCountElement) {
        if (typeof userLimits.maxDailyMessages === 'number') {
            messageCountElement.textContent = `${userLimits.dailyMessageCount}/${userLimits.maxDailyMessages}`;
        } else {
            messageCountElement.textContent = `${userLimits.dailyMessageCount}/∞`;
        }
    }

    const documentCountElement = document.getElementById('daily-document-count');
    if (documentCountElement) {
        if (typeof userLimits.maxDailyDocuments === 'number') {
            documentCountElement.textContent = `${userLimits.dailyDocumentCount}/${userLimits.maxDailyDocuments}`;
        } else {
            documentCountElement.textContent = `${userLimits.dailyDocumentCount}/∞`;
        }
    }

    // Deshabilitar botones si se alcanzaron los límites
    if (userLimits.canSendMessage === false) {
        const sendButton = document.getElementById('send-btn');
        if (sendButton) {
            sendButton.disabled = true;
            sendButton.title = 'Límite de consultas diarias alcanzado';
        }
    }

    if (userLimits.canCreateSession === false) {
        const newChatButton = document.querySelector('.new-chat-btn');
        if (newChatButton) {
            newChatButton.disabled = true;
            newChatButton.title = 'Límite de consultas diarias alcanzado';
        }
    }
}
