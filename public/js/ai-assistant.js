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
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            adjustTextareaHeight(this);
        });
    }

    // Drag and drop para documentos
    setupFileDropZone();

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
            renderChatSessions(data.sessions);
        }
    } catch (error) {
        console.error('Error loading chat sessions:', error);
    }
}

/**
 * Renderizar sesiones de chat en la sidebar
 */
function renderChatSessions(sessions) {
    const sessionsList = document.getElementById('chatSessionsList');
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
            <div class="session-title">${escapeHtml(session.title)}</div>
            ${session.last_message ? `
                <div class="session-preview">${escapeHtml(session.last_message.content)}</div>
            ` : ''}
            <div class="session-meta">
                <span class="session-context">${getContextDisplayName(session.context_type)}</span>
                <span class="session-time">${formatRelativeTime(session.last_activity)}</span>
            </div>
        </div>
    `).join('');
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
 * Renderizar mensajes en el chat
 */
function renderMessages(messages) {
    const chatMessages = document.getElementById('chatMessages');
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

    return `
        <div class="message ${isUser ? 'user' : 'assistant'}">
            <div class="message-avatar ${isUser ? 'user' : 'assistant'}">
                ${avatar}
            </div>
            <div class="message-content">
                <div class="message-bubble">
                    ${formatMessageContent(message.content)}
                    ${message.attachments && message.attachments.length > 0 ? renderAttachments(message.attachments) : ''}
                </div>
                <div class="message-time">${formatTime(message.created_at)}</div>
            </div>
        </div>
    `;
}

/**
 * Enviar mensaje
 */
async function sendMessage() {
    if (!currentSessionId || isLoading) return;

    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();

    if (!message && selectedFiles.length === 0) return;

    try {
        isLoading = true;
        updateSendButton(true);

        // Agregar mensaje del usuario inmediatamente
        if (message) {
            addMessageToChat({
                role: 'user',
                content: message,
                created_at: new Date().toISOString(),
                attachments: selectedFiles.map(f => ({ name: f.name, type: 'file' }))
            });
        }

        // Limpiar input
        messageInput.value = '';
        adjustTextareaHeight(messageInput);
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
                attachments: selectedFiles
            })
        });

        const data = await response.json();
        if (data.success) {
            // Agregar respuesta de la IA
            addMessageToChat(data.assistant_message);
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
    const chatMessages = document.getElementById('chatMessages');

    // Remover mensaje de bienvenida si existe
    const welcomeMessage = chatMessages.querySelector('.welcome-message');
    if (welcomeMessage) {
        welcomeMessage.remove();
    }

    // Agregar nuevo mensaje
    const messageElement = document.createElement('div');
    messageElement.innerHTML = renderMessage(message);
    chatMessages.appendChild(messageElement.firstElementChild);

    scrollToBottom();
}

/**
 * Enviar sugerencia predefinida
 */
function sendSuggestion(suggestion) {
    const messageInput = document.getElementById('messageInput');
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
 * Abrir selector de contenedores
 */
async function openContainerSelector() {
    const modal = document.getElementById('containerSelectorModal');
    modal.classList.add('active');

    try {
        const response = await fetch('/api/ai-assistant/containers');
        const data = await response.json();

        if (data.success) {
            renderContainers(data.containers);
        }
    } catch (error) {
        console.error('Error loading containers:', error);
        showNotification('Error al cargar contenedores', 'error');
    }
}

/**
 * Cerrar selector de contenedores
 */
function closeContainerSelector() {
    const modal = document.getElementById('containerSelectorModal');
    modal.classList.remove('active');
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
        <div class="container-card" onclick="selectContainer(${container.id})">
            <h4>${escapeHtml(container.name)}</h4>
            <div class="container-meta">
                ${container.meetings_count} reuniones
            </div>
        </div>
    `).join('');
}

/**
 * Seleccionar contenedor
 */
async function selectContainer(containerId) {
    try {
        // Obtener datos del contenedor
        const response = await fetch(`/api/ai-assistant/containers/${containerId}/meetings`);
        const data = await response.json();

        if (data.success) {
            currentContext = {
                type: 'container',
                id: containerId,
                data: {
                    meetings: data.meetings,
                    container_name: data.container_name
                }
            };

            await createNewChatSession();
            closeContainerSelector();
            updateContextIndicator();
            loadDetailsForContainer(containerId);
        }
    } catch (error) {
        console.error('Error selecting container:', error);
        showNotification('Error al seleccionar contenedor', 'error');
    }
}

/**
 * Seleccionar todas las reuniones
 */
async function selectAllMeetings() {
    try {
        const response = await fetch('/api/ai-assistant/meetings');
        const data = await response.json();

        if (data.success) {
            currentContext = {
                type: 'meeting',
                id: 'all',
                data: {
                    meetings: data.meetings
                }
            };

            await createNewChatSession();
            closeContainerSelector();
            updateContextIndicator();
            loadDetailsForAllMeetings();
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

        const response = await fetch('/api/ai-assistant/documents/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            showNotification('Documentos subidos correctamente', 'success');
            selectedFiles = [];
            updateFileDisplay();
            closeDocumentUploader();

            // Actualizar contexto para incluir documentos
            currentContext = {
                type: 'documents',
                id: 'uploaded',
                data: { documents: data.documents }
            };

            await createNewChatSession();
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
        data: { document_ids: selectedDocuments }
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
    const sendBtn = document.getElementById('sendButton');
    if (!sendBtn) return;

    sendBtn.disabled = loading;
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
    const chatMessages = document.getElementById('chatMessages');
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

/**
 * Obtener nombre del contexto para mostrar
 */
function getContextDisplayName(contextType) {
    const names = {
        'general': 'General',
        'container': 'Contenedor',
        'meeting': 'Reunión',
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

/**
 * Mostrar notificación
 */
function showNotification(message, type = 'info') {
    // Implementar sistema de notificaciones
    console.log(`${type.toUpperCase()}: ${message}`);

    // Aquí podrías integrar con el sistema de notificaciones existente
    // o crear uno nuevo
}
