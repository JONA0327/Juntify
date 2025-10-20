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
    initializeChatInterface();
});

/**
 * Función de prueba para la barra de progreso (DEBUG)
 */
window.testProgressBar = function() {
    console.log('🧪 Iniciando prueba de barra de progreso...');

    // Crear archivo de prueba falso
    const fakeFile = {
        name: 'test-document.pdf',
        size: 1024 * 50, // 50KB
        type: 'application/pdf'
    };

    // Simular subida con progreso
    const progressId = 'test_' + Date.now();

    // Mostrar barra inicial
    showUploadProgress(progressId, fakeFile.name, 0);

    // Simular progreso
    let progress = 0;
    const interval = setInterval(() => {
        progress += 20;
        if (progress <= 100) {
            updateUploadProgress(progressId, progress, `Subiendo... ${progress}%`);
        }

        if (progress >= 100) {
            clearInterval(interval);
            setTimeout(() => {
                completeUploadProgress(progressId, 'Archivo de prueba listo!');
            }, 1000);
        }
    }, 500);

    console.log('🧪 Prueba iniciada, revisa la interfaz');
};

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

    // Botón de adjuntar archivo
    const attachBtn = document.getElementById('attach-file-btn');
    const fileInput = document.getElementById('file-input');

    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', handleFileAttachment);
    }
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
                context_data: currentContext.data,
                attached_files: getAttachedFileIds()
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

            // Limpiar archivos adjuntos después del envío exitoso
            clearAttachedFiles();
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
    // Esta función se implementará con el modal correspondiente
    console.log('Abriendo selector de contenedores...');
}

/**
 * Abrir selector de chats con contactos
 */
function openContactChatSelector() {
    // Esta función se implementará con el modal correspondiente
    console.log('Abriendo selector de chats...');
}

/**
 * Abrir uploader de documentos
 */
function openDocumentUploader() {
    // Esta función se implementará con el modal correspondiente
    console.log('Abriendo uploader de documentos...');
}

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
        negocios: 'Plan Negocios',
        business: 'Plan Negocios',
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

/**
 * Variables globales para archivos adjuntos
 */
let attachedFiles = [];

/**
 * Manejar archivos adjuntos
 */
async function handleFileAttachment(event) {
    const files = Array.from(event.target.files);

    if (files.length === 0) return;

    console.log('📎 handleFileAttachment iniciado con', files.length, 'archivo(s):', files.map(f => f.name));

    // Verificar límites
    if (!canPerformAction('uploadDocument')) {
        showLimitExceededModal('uploadDocument');
        return;
    }

    try {
        for (const file of files) {
            // Validar archivo
            if (!validateFileForChat(file)) {
                continue;
            }

            // Subir archivo temporalmente con progreso
            const uploadedFile = await uploadTemporaryFileWithProgress(file);
            if (uploadedFile) {
                attachedFiles.push(uploadedFile);
                displayAttachedFile(uploadedFile);
            }
        }

        // Actualizar interfaz
        updateAttachmentIndicator();

    } catch (error) {
        console.error('Error handling file attachment:', error);
        showError('Error al adjuntar archivo: ' + error.message);
    }

    // Limpiar input
    event.target.value = '';
}

/**
 * Validar archivo para chat
 */
function validateFileForChat(file) {
    const maxSize = 100 * 1024 * 1024; // 100MB
    const allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];

    if (file.size > maxSize) {
        showError(`El archivo ${file.name} es demasiado grande. Máximo 100MB.`);
        return false;
    }

    if (!allowedTypes.includes(file.type)) {
        showError(`Tipo de archivo no permitido: ${file.name}`);
        return false;
    }

    return true;
}

/**
 * Subir archivo temporalmente
 */
/**
 * Subir archivo temporal con barra de progreso
 */
async function uploadTemporaryFileWithProgress(file) {
    const progressId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    console.log('🚀 Iniciando subida con progreso:', file.name, 'ID:', progressId);

    try {
        // Mostrar barra de progreso
        console.log('📊 Mostrando barra de progreso...');
        showUploadProgress(progressId, file.name, 0);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('session_id', currentSessionId);
        formData.append('temporary', '1'); // Siempre temporal desde el chat

        // Crear XMLHttpRequest para tener control del progreso
        const result = await uploadWithProgress(formData, progressId);

        // Actualizar progreso a 100% y cambiar a "Procesando..."
        updateUploadProgress(progressId, 100, 'Procesando archivo...');

        // Simular tiempo de procesamiento (el servidor ya procesó, pero damos feedback)
        await new Promise(resolve => setTimeout(resolve, 1500));

        // Finalizar progreso
        completeUploadProgress(progressId, 'Archivo listo!');

        return result;

    } catch (error) {
        console.error('Error uploading temporary file:', error);
        failUploadProgress(progressId, error.message);
        throw error;
    }
}

/**
 * Función original mantenida para compatibilidad
 */
async function uploadTemporaryFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('session_id', currentSessionId);
    formData.append('temporary', '1');

    try {
        const response = await fetch('/api/ai-assistant/documents/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Error al subir archivo');
        }

        if (result.success && result.documents && result.documents.length > 0) {
            return result.documents[0];
        } else {
            throw new Error('No se recibió información del documento subido');
        }

    } catch (error) {
        console.error('Error uploading temporary file:', error);
        throw error;
    }
}

/**
 * Subir con progreso usando XMLHttpRequest
 */
function uploadWithProgress(formData, progressId) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                updateUploadProgress(progressId, Math.round(percentComplete), 'Subiendo archivo...');
            }
        });

        xhr.addEventListener('load', () => {
            try {
                const result = JSON.parse(xhr.responseText);

                if (xhr.status >= 200 && xhr.status < 300 && result.success) {
                    if (result.documents && result.documents.length > 0) {
                        resolve(result.documents[0]);
                    } else {
                        reject(new Error('No se recibió información del documento subido'));
                    }
                } else {
                    reject(new Error(result.message || 'Error al subir archivo'));
                }
            } catch (e) {
                reject(new Error('Error al procesar respuesta del servidor'));
            }
        });

        xhr.addEventListener('error', () => {
            reject(new Error('Error de conexión al subir archivo'));
        });

        xhr.open('POST', '/api/ai-assistant/documents/upload');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
        xhr.send(formData);
    });
}

/**
 * Mostrar barra de progreso de subida
 */
function showUploadProgress(progressId, fileName, percentage) {
    console.log('📊 showUploadProgress llamada:', { progressId, fileName, percentage });

    const container = getOrCreateAttachmentsContainer();
    if (!container) {
        console.error('❌ No se pudo obtener/crear el contenedor de archivos adjuntos');
        return;
    }

    console.log('✅ Contenedor encontrado:', container);

    const progressHtml = `
        <div class="upload-progress-item" id="progress_${progressId}">
            <div class="upload-progress-content">
                <div class="upload-progress-icon">
                    <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div class="upload-progress-info">
                    <div class="upload-progress-name">${fileName}</div>
                    <div class="upload-progress-status">Preparando subida...</div>
                </div>
                <div class="upload-progress-percentage">${percentage}%</div>
            </div>
            <div class="upload-progress-bar">
                <div class="upload-progress-fill" style="width: ${percentage}%"></div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', progressHtml);

    // Mostrar contenedor
    console.log('📋 HTML insertado, actualizando visibilidad...');
    updateAttachmentsVisibility();
    console.log('✅ showUploadProgress completado');
}

/**
 * Actualizar progreso de subida
 */
function updateUploadProgress(progressId, percentage, status) {
    const progressElement = document.getElementById(`progress_${progressId}`);
    if (!progressElement) return;

    const fillElement = progressElement.querySelector('.upload-progress-fill');
    const statusElement = progressElement.querySelector('.upload-progress-status');
    const percentageElement = progressElement.querySelector('.upload-progress-percentage');

    if (fillElement) fillElement.style.width = `${percentage}%`;
    if (statusElement) statusElement.textContent = status;
    if (percentageElement) percentageElement.textContent = `${percentage}%`;
}

/**
 * Completar progreso de subida exitosamente
 */
function completeUploadProgress(progressId, finalStatus) {
    const progressElement = document.getElementById(`progress_${progressId}`);
    if (!progressElement) return;

    const iconElement = progressElement.querySelector('.upload-progress-icon svg');
    const statusElement = progressElement.querySelector('.upload-progress-status');
    const percentageElement = progressElement.querySelector('.upload-progress-percentage');

    // Cambiar icono a check
    if (iconElement) {
        iconElement.outerHTML = `
            <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        `;
    }

    if (statusElement) statusElement.textContent = finalStatus;
    if (percentageElement) percentageElement.textContent = '✓';

    // Remover después de 3 segundos
    setTimeout(() => {
        if (progressElement && progressElement.parentNode) {
            progressElement.remove();
            updateAttachmentsVisibility();
        }
    }, 3000);
}

/**
 * Marcar progreso como fallido
 */
function failUploadProgress(progressId, errorMessage) {
    const progressElement = document.getElementById(`progress_${progressId}`);
    if (!progressElement) return;

    const iconElement = progressElement.querySelector('.upload-progress-icon svg');
    const statusElement = progressElement.querySelector('.upload-progress-status');
    const percentageElement = progressElement.querySelector('.upload-progress-percentage');
    const fillElement = progressElement.querySelector('.upload-progress-fill');

    // Cambiar icono a error
    if (iconElement) {
        iconElement.outerHTML = `
            <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        `;
    }

    if (statusElement) statusElement.textContent = errorMessage || 'Error al subir';
    if (percentageElement) percentageElement.textContent = '✗';
    if (fillElement) fillElement.style.backgroundColor = '#ef4444';

    // Agregar clase de error
    progressElement.classList.add('upload-progress-error');

    // Remover después de 5 segundos
    setTimeout(() => {
        if (progressElement && progressElement.parentNode) {
            progressElement.remove();
            updateAttachmentsVisibility();
        }
    }, 5000);
}

/**
 * Mostrar archivo adjunto en la interfaz
 */
function displayAttachedFile(file) {
    const container = getOrCreateAttachmentsContainer();

    const fileElement = document.createElement('div');
    fileElement.className = 'attached-file';
    fileElement.setAttribute('data-file-id', file.id);

    const handleLine = file.reference_handle
        ? `<div class="file-handle">${file.reference_handle}</div>`
        : '';

    fileElement.innerHTML = `
        <div class="file-info">
            <div class="file-icon">
                ${getFileIcon(file.document_type)}
            </div>
            <div class="file-details">
                <div class="file-name">${file.original_filename}</div>
                ${handleLine}
                <div class="file-size">${formatFileSize(file.file_size)}</div>
            </div>
        </div>
        <button class="remove-file-btn" onclick="removeAttachedFile(${file.id})">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;

    container.appendChild(fileElement);

    // Mostrar contenedor si hay archivos
    updateAttachmentsVisibility();
}

/**
 * Crear o obtener contenedor de archivos adjuntos
 */
function getOrCreateAttachmentsContainer() {
    console.log('🔍 Buscando contenedor de archivos adjuntos...');
    let container = document.getElementById('attachments-container');

    if (!container) {
        console.log('➕ Contenedor no existe, creándolo...');
        container = document.createElement('div');
        container.id = 'attachments-container';
        container.className = 'attachments-container';

        // Insertar antes del área de entrada
        const inputArea = document.querySelector('.input-area');
        if (inputArea && inputArea.parentNode) {
            inputArea.parentNode.insertBefore(container, inputArea);
            console.log('✅ Contenedor creado e insertado correctamente');
        } else {
            console.error('❌ No se encontró el área de entrada (.input-area)');
        }
    } else {
        console.log('✅ Contenedor ya existe');
    }

    return container;
}

/**
 * Actualizar visibilidad del contenedor de archivos adjuntos
 */
function updateAttachmentsVisibility() {
    const container = document.getElementById('attachments-container');
    if (!container) {
        console.log('⚠️ updateAttachmentsVisibility: contenedor no encontrado');
        return;
    }

    const hasContent = container.children.length > 0;
    const shouldShow = hasContent ? 'block' : 'none';
    container.style.display = shouldShow;
    console.log('👁️ updateAttachmentsVisibility:', { hasContent, children: container.children.length, display: shouldShow });
}

/**
 * Remover archivo adjunto
 */
async function removeAttachedFile(fileId) {
    try {
        // Remover del servidor
        const response = await fetch(`/api/ai-assistant/documents/${fileId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        if (response.ok) {
            // Remover de la lista local
            attachedFiles = attachedFiles.filter(file => file.id !== fileId);

            // Remover del DOM
            const fileElement = document.querySelector(`[data-file-id="${fileId}"]`);
            if (fileElement) {
                fileElement.remove();
            }

            // Actualizar indicador y visibilidad
            updateAttachmentIndicator();
            updateAttachmentsVisibility();
        }

    } catch (error) {
        console.error('Error removing attached file:', error);
        showError('Error al remover archivo');
    }
}

/**
 * Actualizar indicador de archivos adjuntos
 */
function updateAttachmentIndicator() {
    const container = document.getElementById('attachments-container');
    const attachBtn = document.getElementById('attach-file-btn');

    if (attachedFiles.length === 0) {
        if (container) {
            container.style.display = 'none';
        }
        if (attachBtn) {
            attachBtn.classList.remove('has-files');
        }
    } else {
        if (container) {
            container.style.display = 'block';
        }
        if (attachBtn) {
            attachBtn.classList.add('has-files');
        }
    }
}

/**
 * Obtener icono de archivo según tipo
 */
function getFileIcon(type) {
    const icons = {
        'pdf': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
        'image': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
        'default': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 18.75h-1.5A1.125 1.125 0 0112 18v-5.25m6 5.25V13.5a1.125 1.125 0 00-1.125-1.125H12m6.75 0V7.875c0-.621-.504-1.125-1.125-1.125H12M3.375 7.125c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125H4.5A1.125 1.125 0 013.375 8.625V7.125z" /></svg>'
    };

    if (type === 'pdf') return icons.pdf;
    if (type === 'image') return icons.image;
    return icons.default;
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
 * Obtener IDs de archivos adjuntos
 */
function getAttachedFileIds() {
    return attachedFiles.map(file => file.id);
}

/**
 * Limpiar archivos adjuntos
 */
function clearAttachedFiles() {
    attachedFiles = [];
    const container = document.getElementById('attachments-container');
    if (container) {
        container.innerHTML = '';
        container.style.display = 'none';
    }
    updateAttachmentIndicator();
}
