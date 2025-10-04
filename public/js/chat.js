// Variables globales para el chat
let currentChatId = null;
let currentContactId = null;
let chatMessages = [];
let isChatLoading = false;
let pendingVoiceBase64 = null; // Audio grabado (base64)
let pendingVoiceMime = null;  // MIME del audio grabado
let lastConversationUpdate = null;
let autoRefreshInterval = null;
let lastMessageIds = new Set(); // Para trackear qué mensajes ya hemos visto
let autoScrollEnabled = true;   // Autoscroll inteligente sólo cuando el usuario está cerca del fondo
let contactsCache = [];
let pendingFile = null; // Archivo seleccionado para enviar
// Estado de grabación de voz
let mediaRecorder = null;
let recordingStream = null;
let recordingIntervalId = null;
let recordingTimeoutId = null;
let recordingStartAt = 0;

const { debugLog, debugWarn, debugError } = (() => {
    const globalLogger = (typeof window !== 'undefined' && window.juntifyLogger) ? window.juntifyLogger : null;
    const createFallback = (method) => (...args) => {
        if (typeof window === 'undefined') {
            return;
        }
        const isDebug = Boolean(window.APP_DEBUG);
        if (!isDebug || typeof console === 'undefined') {
            return;
        }
        const fn = console[method];
        if (typeof fn === 'function') {
            fn.apply(console, args);
        }
    };
    return {
        debugLog: globalLogger?.debugLog ?? createFallback('log'),
        debugWarn: globalLogger?.debugWarn ?? createFallback('warn'),
        debugError: globalLogger?.debugError ?? createFallback('error'),
    };
})();

    // Botón y input para adjuntar archivo (si existen en la vista)
function startAutoRefresh() {
    // Limpiar interval anterior si existe
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }

    // Verificar nuevos mensajes cada 3 segundos
    autoRefreshInterval = setInterval(async () => {
        await refreshConversationsIfNeeded();
    }, 3000);
}

// Función para detener auto-refresh
function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Temporizador y control de grabación de voz (ámbito global)
let audioChunks = [];
function startTimer() {
    const timerEl = document.getElementById('active-chat-voice-timer');
    recordingStartAt = Date.now();
    if (timerEl) timerEl.classList.remove('hidden');
    if (recordingIntervalId) clearInterval(recordingIntervalId);
    recordingIntervalId = setInterval(() => {
        const elapsed = Math.floor((Date.now() - recordingStartAt) / 1000);
        const m = Math.floor(elapsed / 60).toString();
        const s = (elapsed % 60).toString().padStart(2, '0');
        if (timerEl) timerEl.textContent = `${m}:${s}`;
    }, 250);
}
function stopTimer(reset = true) {
    const timerEl = document.getElementById('active-chat-voice-timer');
    if (recordingIntervalId) clearInterval(recordingIntervalId);
    recordingIntervalId = null;
    if (timerEl) {
        if (reset) timerEl.textContent = '0:00';
        timerEl.classList.add('hidden');
    }
}
async function toggleRecording() {
    const micBtn = document.getElementById('active-chat-voice-btn');
    if (!mediaRecorder) {
        try {
            if (recordingStream) {
                try { recordingStream.getTracks().forEach(t => t.stop()); } catch (_) {}
                recordingStream = null;
            }
            recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(recordingStream);
            audioChunks = [];
            mediaRecorder.ondataavailable = (ev) => { if (ev.data.size > 0) audioChunks.push(ev.data); };
            mediaRecorder.onstop = async () => {
                try {
                    const blob = new Blob(audioChunks, { type: (mediaRecorder && mediaRecorder.mimeType) ? mediaRecorder.mimeType : 'audio/webm' });
                    try { if (recordingStream && recordingStream.getTracks) { recordingStream.getTracks().forEach(t => t.stop()); } } catch (_) {}
                    recordingStream = null;
                    stopTimer(false);
                    const arrayBuffer = await blob.arrayBuffer();
                    let binary = '';
                    const bytes = new Uint8Array(arrayBuffer);
                    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
                    pendingVoiceBase64 = btoa(binary);
                    pendingVoiceMime = blob.type || 'audio/webm';
                    const input = document.getElementById('active-chat-input');
                    if (input) input.placeholder = 'Enviando audio...';
                    if (micBtn) micBtn.classList.remove('text-yellow-400','bg-slate-600/50');
                    try { await sendMessage(); } catch (e) { debugError('Fallo al enviar audio:', e); }
                    if (input && !input.value) input.placeholder = 'Escribe un mensaje...';
                } finally {
                    mediaRecorder = null;
                    if (recordingTimeoutId) { clearTimeout(recordingTimeoutId); recordingTimeoutId = null; }
                }
            };
            mediaRecorder.start();
            startTimer();
            const input = document.getElementById('active-chat-input');
            if (input && !input.value) input.placeholder = 'Grabando audio... (Alt+V o click para detener)';
            if (micBtn) micBtn.classList.add('text-yellow-400','bg-slate-600/50');
            if (recordingTimeoutId) clearTimeout(recordingTimeoutId);
            recordingTimeoutId = setTimeout(() => {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    try { mediaRecorder.stop(); } catch (_) {}
                }
            }, 60000);
        } catch (err) {
            debugError('No se pudo iniciar la grabación de voz:', err);
        }
    } else if (mediaRecorder && mediaRecorder.state === 'recording') {
        try { mediaRecorder.stop(); } catch (e) { debugError('No se pudo detener la grabación:', e); }
    }
}

// Refrescar conversaciones sólo si hay cambios
async function refreshConversationsIfNeeded() {
    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
        const response = await fetch('/api/chats', { method: 'GET', headers });
        if (!response.ok) return;
        const conversations = await response.json();
        let hasNewMessages = false;
        let needsConversationUpdate = false;
        conversations.forEach(conv => {
            const lastMsg = conv.last_message;
            if (!lastMsg) return;
            const msgTime = new Date(lastMsg.created_at);
            if (!lastConversationUpdate || msgTime > lastConversationUpdate) {
                needsConversationUpdate = true;
                if (conv.id == currentChatId) {
                    const currentLastMsg = chatMessages[chatMessages.length - 1];
                    if (!currentLastMsg || new Date(currentLastMsg.created_at) < msgTime) {
                        hasNewMessages = true;
                    }
                }
            }
        });
        if (needsConversationUpdate) {
            debugLog('🔄 Actualizando conversaciones...', { hasNewMessages });
            updateConversationsList(conversations);
            if (hasNewMessages && currentChatId) {
                debugLog('📨 Nuevos mensajes detectados, actualizando...');
                await loadChatMessages(true);
                const messagesContainer = document.getElementById('active-chat-messages');
                if (messagesContainer) {
                    messagesContainer.style.transform = 'scale(1.01)';
                    setTimeout(() => { messagesContainer.style.transform = 'scale(1)'; }, 200);
                }
            }
            lastConversationUpdate = new Date();
        }
    } catch (error) {
        debugError('❌ Error en auto-refresh:', error);
    }
}

// Función para cargar conversaciones
async function loadConversations() {
    const conversationsList = document.getElementById('conversations-list');

    debugLog('🔄 Iniciando carga de conversaciones...');

    try {
        conversationsList.innerHTML = `
            <div class="p-8 text-center">
                <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-slate-400">Cargando conversaciones...</p>
            </div>
        `;

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
        debugLog('📝 CSRF Token:', csrfToken ? 'Encontrado' : 'No encontrado');

        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
        const response = await fetch('/api/chats', { method: 'GET', headers });

        debugLog('📡 Response status:', response.status);
        debugLog('📡 Response headers:', Object.fromEntries(response.headers.entries()));

        if (!response.ok) {
            const errorText = await response.text();
            debugError('❌ Error response:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const conversations = await response.json();
        debugLog('💬 Conversaciones recibidas:', conversations);

        if (!Array.isArray(conversations)) {
            debugError('❌ La respuesta no es un array:', conversations);
            throw new Error('Formato de respuesta inválido');
        }

        if (conversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-slate-700/50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-300 mb-2">No se encontraron conversaciones</h3>
                    <p class="text-slate-500">Aún no tienes conversaciones iniciadas</p>
                </div>
            `;
            return;
        }

        conversationsList.innerHTML = '';

        conversations.forEach((conversation, index) => {
            debugLog(`💬 Procesando conversación ${index + 1}:`, conversation);
            const conversationElement = createConversationElement(conversation);
            conversationsList.appendChild(conversationElement);
        });

        debugLog('✅ Conversaciones cargadas exitosamente');

        // Auto-seleccionar conversación si hay chat_id en la URL
        const urlParams = new URLSearchParams(window.location.search);
        const chatId = urlParams.get('chat_id');
        if (chatId && conversations.length > 0) {
            const targetConversation = conversations.find(conv => conv.id == chatId);
            if (targetConversation) {
                await selectConversation(targetConversation);
            }
        }

    } catch (error) {
        debugError('❌ Error loading conversations:', error);
        conversationsList.innerHTML = `
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-300 mb-2">Error al cargar conversaciones</h3>
                <p class="text-slate-500 mb-4">Hubo un problema al cargar las conversaciones</p>
                <p class="text-red-400 text-sm mb-4">${error.message}</p>
                <button onclick="loadConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all">
                    Reintentar
                </button>
            </div>
        `;
    }
}

// Función para actualizar lista de conversaciones sin loading
function updateConversationsList(conversations) {
    const conversationsList = document.getElementById('conversations-list');
    if (!conversationsList) return;

    // Guardar el estado de selección actual
    const currentSelection = document.querySelector('.conversation-item.bg-slate-700\\/40');
    const currentChatIdSelected = currentSelection ? currentSelection.getAttribute('data-chat-id') : null;

    if (conversations.length === 0) {
        conversationsList.innerHTML = `
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-slate-700/50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-300 mb-2">No se encontraron conversaciones</h3>
                <p class="text-slate-500">Aún no tienes conversaciones iniciadas</p>
            </div>
        `;
        return;
    }

    conversationsList.innerHTML = '';

    conversations.forEach((conversation, index) => {
        const conversationElement = createConversationElement(conversation);
        conversationsList.appendChild(conversationElement);

        // Restaurar selección si coincide
        if (conversation.id == currentChatIdSelected) {
            conversationElement.classList.add('bg-slate-700/40', 'border-l-4', 'border-l-yellow-500');
        }
    });
}

// Función para crear elemento de conversación
function createConversationElement(conversation) {
    const div = document.createElement('div');
    div.className = 'conversation-item p-4 border-b border-slate-700/30 hover:bg-slate-700/20 cursor-pointer transition-all';
    div.setAttribute('data-chat-id', conversation.id);

    const lastMessageTime = conversation.last_message
        ? formatMessageTime(conversation.last_message.created_at)
        : '';

    let lastMessageText = 'Conversación iniciada';
    if (conversation.last_message) {
        const prefix = conversation.last_message.is_mine ? 'Tú: ' : '';
        const body = conversation.last_message.body;
        // Si body es null/ vacío, mostrar un texto representativo
        if (body && body.trim() !== '') {
            lastMessageText = prefix + body;
        } else {
            // Cuando el último mensaje fue un adjunto o voz, el backend no siempre trae flags.
            // Mostramos un genérico claro.
            lastMessageText = prefix + 'Archivo adjunto';
        }
    }

    // Indicador de mensajes no leídos
    const unreadIndicator = conversation.has_unread
        ? `<div class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-slate-800"></div>`
        : '';

    const unreadBadge = conversation.unread_count > 0
        ? `<span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">${conversation.unread_count}</span>`
        : '';

    // Avatar del usuario - usar initial si no hay avatar
    const otherA = conversation.other_user || {};
    const userAvatar = otherA.avatar || (otherA.name ? otherA.name.charAt(0).toUpperCase() : null) || '?';
    const userName = otherA.name || 'Usuario desconocido';

    div.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="relative w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold text-lg">
                ${userAvatar}
                ${unreadIndicator}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                    <h4 class="text-slate-200 font-medium truncate ${conversation.has_unread ? 'font-bold' : ''}">${userName}</h4>
                    <div class="flex items-center gap-2">
                        ${unreadBadge}
                        <span class="text-xs text-slate-500">${lastMessageTime}</span>
                        <button class="delete-chat-btn opacity-60 hover:opacity-100 text-slate-400 hover:text-red-400 transition" title="Eliminar conversación">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M9 3a1 1 0 0 0-1 1v1H5a1 1 0 1 0 0 2h.293l.853 12.79A2 2 0 0 0 8.14 22h7.72a2 2 0 0 0 1.994-2.21L18.707 7H19a1 1 0 1 0 0-2h-3V4a1 1 0 0 0-1-1H9zm2 4a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7zm4 0a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7z"/></svg>
                        </button>
                    </div>
                </div>
                <p class="text-sm ${conversation.has_unread ? 'text-slate-300 font-medium' : 'text-slate-400'} truncate mt-1">${lastMessageText}</p>
            </div>
        </div>
    `;

    // Click sobre todo el item selecciona
    div.addEventListener('click', (e) => {
        // Evitar que el click del botón borrar abra el chat
        if (e.target.closest && e.target.closest('.delete-chat-btn')) return;
        selectConversation(conversation);
    });

    // Borrar conversación
    const delBtn = div.querySelector('.delete-chat-btn');
    if (delBtn) {
        delBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const confirmed = await showConfirmModal('¿Eliminar esta conversación para ti? Si ambos usuarios la eliminan, se limpiará definitivamente.');
            if (!confirmed) return;

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
                const headers = { 'X-Requested-With': 'XMLHttpRequest' };
                if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
                const resp = await fetch(`/api/chats/${conversation.id}`, { method: 'DELETE', headers });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                // Si el chat eliminado era el activo, limpiar panel derecho
                if (currentChatId == conversation.id) {
                    currentChatId = null;
                    const active = document.getElementById('active-chat');
                    const empty = document.getElementById('no-chat-selected');
                    if (active) active.classList.add('hidden');
                    if (empty) empty.classList.remove('hidden');
                    const messagesContainer = document.getElementById('active-chat-messages');
                    if (messagesContainer) messagesContainer.innerHTML = '';
                }
                // Quitar del DOM
                div.remove();
            } catch (err) {
                alert('No se pudo eliminar la conversación.');
                debugError('DELETE /api/chats failed', err);
            }
        });
    }

    return div;
}

// Función para seleccionar una conversación
async function selectConversation(conversation) {
    debugLog('🎯 Seleccionando conversación:', conversation);

    // Remover selección anterior
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('bg-slate-700/40', 'border-l-4', 'border-l-yellow-500');
    });

    // Agregar selección actual
    const selectedItem = document.querySelector(`[data-chat-id="${conversation.id}"]`);
    if (selectedItem) {
        selectedItem.classList.add('bg-slate-700/40', 'border-l-4', 'border-l-yellow-500');

        // Remover indicadores de no leído cuando se selecciona la conversación
        const unreadIndicator = selectedItem.querySelector('.bg-red-500.rounded-full.border-2');
        const unreadBadge = selectedItem.querySelector('.bg-red-500.rounded-full:not(.border-2)');
        if (unreadIndicator) unreadIndicator.remove();
        if (unreadBadge) unreadBadge.remove();

        // Actualizar el estilo del texto para que no esté en negrita
        const nameElement = selectedItem.querySelector('h4');
        const messageElement = selectedItem.querySelector('p');
        if (nameElement) nameElement.classList.remove('font-bold');
        if (messageElement) {
            messageElement.classList.remove('text-slate-300', 'font-medium');
            messageElement.classList.add('text-slate-400');
        }
    }

    // Configurar chat activo
    currentChatId = conversation.id;
    currentContactId = (conversation.other_user && conversation.other_user.id) ? conversation.other_user.id : null;

    // Limpiar el set de mensajes vistos para la nueva conversación
    lastMessageIds.clear();

    // Actualizar UI del chat activo
    const activeChatAvatar = document.getElementById('active-chat-avatar');
    const activeChatName = document.getElementById('active-chat-name');
    const activeChatStatus = document.getElementById('active-chat-status');
    const activeChatStatusText = document.getElementById('active-chat-status-text');

    const otherB = conversation.other_user || {};
    const userAvatar = otherB.avatar || (otherB.name ? otherB.name.charAt(0).toUpperCase() : null) || '?';
    const userName = otherB.name || 'Usuario desconocido';

    if (activeChatAvatar) activeChatAvatar.textContent = userAvatar;
    if (activeChatName) activeChatName.textContent = userName;

    // Simular estado en línea
    const isOnline = getUserOnlineStatus(conversation.other_user);
    if (activeChatStatus && activeChatStatusText) {
        if (isOnline) {
            activeChatStatusText.textContent = 'En línea';
            activeChatStatus.className = 'w-2 h-2 bg-green-400 rounded-full animate-pulse';
        } else {
            const lastSeenOptions = ['Hace 5 min', 'Hace 1 hora', 'Hace 2 horas', 'Ayer', 'Hace 2 días'];
            const randomLastSeen = lastSeenOptions[Math.floor(Math.random() * lastSeenOptions.length)];
            activeChatStatusText.textContent = `Últ. vez ${randomLastSeen}`;
            activeChatStatus.className = 'w-2 h-2 bg-gray-400 rounded-full';
        }
    }

    // Mostrar área de chat y ocultar mensaje de selección
    const noChatSelected = document.getElementById('no-chat-selected');
    const activeChat = document.getElementById('active-chat');

    if (noChatSelected) noChatSelected.classList.add('hidden');
    if (activeChat) activeChat.classList.remove('hidden');

    // Cargar mensajes
    await loadChatMessages(true); // true = modo silencioso, sin loading
}

// Función para cargar mensajes del chat activo
async function loadChatMessages(silent = false) {
    if (!currentChatId) {
        debugWarn('⚠️ No hay chat activo para cargar mensajes');
        return;
    }

    const messagesContainer = document.getElementById('active-chat-messages');
    debugLog('📨 Cargando mensajes para chat:', currentChatId);

    try {
        // Solo mostrar loading si no es modo silencioso
        if (!silent) {
            messagesContainer.innerHTML = `
                <div class="text-center py-8">
                    <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                    <p class="text-slate-400">Cargando mensajes...</p>
                </div>
            `;
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
        const response = await fetch(`/api/chats/${currentChatId}`, { method: 'GET', headers });

        debugLog('📨 Messages response status:', response.status);

        if (!response.ok) {
            const errorText = await response.text();
            debugError('❌ Error loading messages:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const messages = await response.json();
        debugLog('📨 Mensajes recibidos:', messages);

        chatMessages = Array.isArray(messages) ? messages : [];

    updateChatMessagesDisplay();
    maybeScrollToBottom();

    } catch (error) {
        debugError('❌ Error loading messages:', error);
        messagesContainer.innerHTML = `
            <div class="text-center py-8">
                <p class="text-red-400">Error al cargar mensajes</p>
                <p class="text-slate-500 text-sm mt-1">${error.message}</p>
            </div>
        `;
    }
}

// Función para actualizar la visualización de mensajes
function updateChatMessagesDisplay() {
    const messagesContainer = document.getElementById('active-chat-messages');
    const userMeta = document.querySelector('meta[name="user-id"]');
    const currentUserId = userMeta ? userMeta.getAttribute('content') : null;

    debugLog('🎨 Actualizando visualización de mensajes. Total:', chatMessages.length);
    debugLog('👤 Current user ID:', currentUserId);

    if (!messagesContainer) {
        debugError('❌ No se encontró el contenedor de mensajes');
        return;
    }

    if (chatMessages.length === 0) {
        messagesContainer.innerHTML = `
            <div class="text-center py-8">
                <p class="text-slate-400">No hay mensajes aún</p>
                <p class="text-slate-500 text-sm mt-1">Envía el primer mensaje para comenzar la conversación</p>
            </div>
        `;
        lastMessageIds.clear();
        return;
    }

    messagesContainer.innerHTML = '';

    chatMessages.forEach((message, index) => {
        debugLog(`💬 Procesando mensaje ${index + 1}:`, message);

        // Verificar si es un mensaje nuevo (no temporal y no visto antes)
        const isNewMessage = !message.is_temp && !lastMessageIds.has(message.id);

    const messageElement = createMessageElement(message, currentUserId, isNewMessage);
        messagesContainer.appendChild(messageElement);

        // Agregar el ID del mensaje a los vistos (si no es temporal)
        if (!message.is_temp) {
            lastMessageIds.add(message.id);
        }
    });
}

// Función para crear elemento de mensaje
function createMessageElement(message, currentUserId, isNewMessage = false) {
    const div = document.createElement('div');
    const isMyMessage = message.sender_id == currentUserId || message.user_id == currentUserId;

    debugLog('💬 Creando mensaje:', {
        messageId: message.id,
        senderId: message.sender_id || message.user_id,
        currentUserId: currentUserId,
        isMyMessage: isMyMessage,
        body: message.body,
        isNewMessage: isNewMessage,
        hasAttachment: !!(message.original_name || message.drive_file_id || message.file_path)
    });

    div.className = `flex ${isMyMessage ? 'justify-end' : 'justify-start'} mb-3 ${isNewMessage ? 'message-fade-in' : ''}`;

    const bubbleClasses = isMyMessage
        ? 'bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-br-none'
        : 'bg-slate-700/50 text-slate-200 rounded-bl-none';

    // Construcción de adjunto
    let attachmentHtml = '';
        if (message.original_name || message.file_path || message.drive_file_id) {
        const fileName = escapeHtml(message.original_name || message.originalName || 'Archivo');
            const mime = message.mime_type || '';
            const driveLink = message.drive_file_id ? `https://drive.google.com/uc?export=download&id=${message.drive_file_id}` : null;
        const localPath = message.file_path ? `/storage/${message.file_path}` : null;
        const downloadLink = driveLink || localPath || '#';
            const preview = downloadLink && downloadLink !== '#'
                ? `<button class="mt-2 inline-flex items-center gap-2 px-3 py-2 bg-slate-800/40 rounded text-xs hover:bg-slate-700/40 file-download" data-href="${downloadLink}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
                        Descargar ${fileName}
                   </button>`
                : '';
        attachmentHtml = `
            <div class="mt-2 text-xs ${isMyMessage ? 'text-slate-800' : 'text-slate-300'}">
                    <span class="break-all">${fileName}</span>
                ${preview}
            </div>`;
    }

    // Incluir audio en base64 si existe (reproductor personalizado)
    if (message.voice_base64) {
        const mime = message.voice_mime || 'audio/webm';
        const src = `data:${mime};base64,${message.voice_base64}`;
        const playerId = 'vp_' + (message.id || Math.random().toString(36).slice(2));
        attachmentHtml += `
            <div class="mt-2 select-none">
                <div class="voice-player bg-slate-900/40 rounded p-2 w-64 text-slate-200" data-src="${src}" id="${playerId}">
                    <div class="flex items-center gap-2">
                        <button class="vp-toggle bg-yellow-500 text-slate-900 rounded px-2 py-1 text-xs font-semibold">Play</button>
                        <div class="vp-track flex-1 h-2 bg-slate-700 rounded cursor-pointer relative">
                            <div class="vp-progress h-2 bg-yellow-500 rounded" style="width:0%"></div>
                        </div>
                        <span class="vp-time text-[10px] text-slate-400 min-w-[70px] text-right">0:00 / 0:00</span>
                    </div>
                </div>
            </div>`;
        setTimeout(function(){ initVoicePlayer(playerId); }, 0);
    }

    // Acciones de mensaje (eliminar)
    const actionsHtml = message.is_temp ? '' : `
        <div class="mt-1 flex gap-1 justify-${isMyMessage ? 'end' : 'start'} text-[11px]">
            <button class="msg-del-me text-slate-400 hover:text-slate-200 px-2 py-0.5 rounded hover:bg-slate-600/40">Eliminar para mí</button>
            ${isMyMessage ? '<button class="msg-del-all text-red-400 hover:text-red-300 px-2 py-0.5 rounded hover:bg-red-600/30">Eliminar para todos</button>' : ''}
        </div>`;

    div.innerHTML = `
        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${bubbleClasses}">
            ${message.body ? `<p class="text-sm whitespace-pre-line">${escapeHtml(message.body)}</p>` : ''}
            ${attachmentHtml}
            <p class="text-xs mt-1 ${isMyMessage ? 'text-slate-800' : 'text-slate-400'}">${formatMessageTime(message.created_at)}</p>
            ${actionsHtml}
        </div>`;

    // Wire actions
    const delMeBtn = message.is_temp ? null : div.querySelector('.msg-del-me');
    if (delMeBtn) {
        delMeBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const confirmed = await showConfirmModal('¿Eliminar este mensaje para ti?');
            if (!confirmed) return;
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
                const resp = await fetch(`/api/chats/${currentChatId}/messages/${message.id}/me`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                // Mostrar "tombstone" en vez de quitarlo, para dar contexto visual
                const idx = chatMessages.findIndex(m => m.id === message.id);
                if (idx !== -1) {
                    chatMessages[idx] = { ...chatMessages[idx], body: 'Se eliminó este mensaje', original_name: null, file_path: null, drive_file_id: null, voice_base64: null, voice_path: null };
                }
                // Re-render sólo este bubble
                const replacement = createMessageElement(chatMessages[idx] || message, currentUserId, false);
                div.replaceWith(replacement);
                // Actualizar snippet de conversación
                updateCurrentConversationLastMessage(chatMessages[chatMessages.length - 1] || null);
            } catch (err) {
                debugError('Eliminar para mí falló', err);
                // Modal fallback
                showConfirmModal('No se pudo eliminar el mensaje para ti.').then(()=>{});
            }
        });
    }
    const delAllBtn = message.is_temp ? null : div.querySelector('.msg-del-all');
    if (delAllBtn) {
        delAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const ok = await showConfirmModal('¿Eliminar este mensaje para todos? Esta acción no se puede deshacer.');
            if (!ok) return;
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
                const resp = await fetch(`/api/chats/${currentChatId}/messages/${message.id}/all`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                let updated = null;
                try { updated = await resp.json(); } catch(_) {}
                const newMsg = (updated && updated.message) ? updated.message : { ...message, body: 'Se eliminó este mensaje', original_name: null, file_path: null, drive_file_id: null, voice_base64: null, voice_path: null };
                const idx = chatMessages.findIndex(m => m.id === message.id);
                if (idx !== -1) chatMessages[idx] = newMsg; else chatMessages.push(newMsg);
                const replacement = createMessageElement(newMsg, currentUserId, false);
                div.replaceWith(replacement);
                updateCurrentConversationLastMessage(newMsg);
            } catch (err) {
                debugError('Eliminar para todos falló', err);
                showConfirmModal('No se pudo eliminar el mensaje para todos.').then(()=>{});
            }
        });
    }

    return div;
}

// Voice player logic
function initVoicePlayer(id){
    const root = document.getElementById(id);
    if(!root) return;
    const src = root.getAttribute('data-src');
    const audio = new Audio(src);
    root.addEventListener('contextmenu', function(e){ e.preventDefault(); });
    const btn = root.querySelector('.vp-toggle');
    const bar = root.querySelector('.vp-track');
    const prog = root.querySelector('.vp-progress');
    const time = root.querySelector('.vp-time');
    let dragging = false;

    function fmt(s){
        s = Math.max(0, Math.floor(s));
        var m = Math.floor(s/60);
        var r = (s%60).toString().padStart(2,'0');
        return m + ':' + r;
    }

    audio.addEventListener('timeupdate', ()=>{
        if(!dragging && audio.duration){ prog.style.width = (audio.currentTime/audio.duration*100)+'%'; }
        const dur = isFinite(audio.duration) ? audio.duration : 0;
        time.textContent = fmt(audio.currentTime) + ' / ' + fmt(dur);
    });
    audio.addEventListener('loadedmetadata', ()=>{
        const dur = isFinite(audio.duration) ? audio.duration : 0;
        time.textContent = '0:00 / ' + fmt(dur);
    });
    audio.addEventListener('ended', ()=>{ btn.textContent='Play'; prog.style.width='0%'; });

    btn.addEventListener('click', ()=>{
        if(audio.paused){ audio.play(); btn.textContent='Pause'; } else { audio.pause(); btn.textContent='Play'; }
    });
    bar.addEventListener('click', (e)=>{
        const rect = bar.getBoundingClientRect();
        const ratio = (e.clientX - rect.left)/rect.width; if(audio.duration){ audio.currentTime = ratio*audio.duration; }
    });
}

// Función para enviar mensaje
async function sendMessage() {
    if (!currentChatId) {
        debugWarn('⚠️ No hay chat activo para enviar mensaje');
        return;
    }

    const messageInput = document.getElementById('active-chat-input');
    const messageText = messageInput.value.trim();

        if (!messageText && !pendingFile && !pendingVoiceBase64) {
        debugWarn('⚠️ Nada que enviar');
        return;
    }

    debugLog('📤 Enviando mensaje:', messageText);

    // Limpiar input inmediatamente
    messageInput.value = '';
    const fileToSend = pendingFile; // snapshot
        const voiceToSend = pendingVoiceBase64; // snapshot
        const voiceMime = pendingVoiceMime; // snapshot
    pendingFile = null;
        pendingVoiceBase64 = null;
        pendingVoiceMime = null;

    // Crear mensaje optimista (mostrar inmediatamente)
    const userMetaDisplay = document.querySelector('meta[name="user-id"]');
    const currentUserId = userMetaDisplay ? userMetaDisplay.getAttribute('content') : null;
    const optimisticMessage = {
        id: 'temp-' + Date.now(),
        body: messageText,
        sender_id: currentUserId,
        created_at: new Date().toISOString(),
        is_temp: true,
        original_name: fileToSend ? fileToSend.name : null,
        mime_type: fileToSend ? fileToSend.type : (voiceMime || null),
        preview_url: null,
        ...(voiceToSend ? { voice_base64: voiceToSend, voice_mime: voiceMime || 'audio/webm' } : {})
    };

    // Agregar mensaje optimista al array y actualizar UI inmediatamente
    chatMessages.push(optimisticMessage);
    updateChatMessagesDisplay();
    maybeScrollToBottom();

    try {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

        // Enviar mensaje al servidor en background
        let fetchOptions = { method: 'POST' };
        if (fileToSend) {
            // Para mostrar progreso usamos XMLHttpRequest en lugar de fetch
            await new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('body', messageText);
                formData.append('file', fileToSend);

                const xhr = new XMLHttpRequest();
                const url = `/api/chats/${currentChatId}/messages`;
                xhr.open('POST', url, true);
                if (csrfToken) xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                const box = document.getElementById('upload-progress');
                const name = document.getElementById('upload-progress-name');
                const bar = document.getElementById('upload-progress-bar');
                const pct = document.getElementById('upload-progress-percent');
                if (box) box.classList.remove('hidden');
                if (name) name.textContent = `Subiendo: ${fileToSend.name}`;

                xhr.upload.onprogress = (e) => {
                    if (!e.lengthComputable) return;
                    const percent = Math.round((e.loaded / e.total) * 100);
                    if (bar) bar.style.width = percent + '%';
                    if (pct) pct.textContent = percent + '%';
                };
                xhr.onreadystatechange = () => {
                    if (xhr.readyState === 4) {
                        if (box) box.classList.add('hidden');
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                const json = JSON.parse(xhr.responseText);
                                resolve(json);
                            } catch (err) {
                                resolve({});
                            }
                        } else {
                            reject(new Error('Upload failed: ' + xhr.status));
                        }
                    }
                };
                xhr.send(formData);
            });
            // usamos el mismo flujo de abajo para refrescar UI (no seteamos fetchOptions)
            fetchOptions = null;
            } else if (voiceToSend) {
                // Enviar audio en base64 como JSON (simple y rápido)
                fetchOptions.body = JSON.stringify({ body: messageText, voice_base64: voiceToSend, voice_mime: voiceMime || 'audio/webm' });
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };
                if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
                fetchOptions.headers = headers;
        } else {
            fetchOptions.body = JSON.stringify({ body: messageText });
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
            fetchOptions.headers = headers;
        }
        let result = {};
        if (fetchOptions) {
            const response = await fetch(`/api/chats/${currentChatId}/messages`, fetchOptions);
            debugLog('📤 Send message response status:', response.status);
            if (!response.ok) {
                const errorText = await response.text();
                debugError('❌ Error sending message:', errorText);
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            result = await response.json();
        } else {
            // En el flujo de XHR ya enviamos; leave result vacío y recarga fallback abajo
        }
        debugLog('📤 Mensaje enviado exitosamente:', result);

        // Remover mensaje optimista
        chatMessages = chatMessages.filter(msg => msg.id !== optimisticMessage.id);

        // Intentar obtener el mensaje devuelto por la API; algunos endpoints no lo retornan
        let deliveredMessage = null;
        if (result && typeof result === 'object') {
            if (result.message && (result.message.id || result.message.body)) {
                deliveredMessage = result.message;
            } else if (result.data && result.data.message) {
                deliveredMessage = result.data.message;
            } else if (result.id && result.body) {
                // API devolvió directamente el mensaje
                deliveredMessage = result;
            }
        }

        if (deliveredMessage) {
            chatMessages.push(deliveredMessage);
            updateChatMessagesDisplay();
        } else {
            // Si no recibimos el mensaje, recargar mensajes en modo silencioso como fallback
            await loadChatMessages(true);
            deliveredMessage = chatMessages[chatMessages.length - 1] || null;
        }

        // Actualizar la vista de la conversación con el último mensaje disponible (si existe)
        if (deliveredMessage) {
            updateCurrentConversationLastMessage(deliveredMessage);
        }

    } catch (error) {
        debugError('❌ Error sending message:', error);

        // Remover mensaje optimista si falló
        chatMessages = chatMessages.filter(msg => msg.id !== optimisticMessage.id);
        updateChatMessagesDisplay();

        // Restaurar texto en el input y archivo si existía
        messageInput.value = messageText;
        pendingFile = fileToSend; // permitir reintento
        alert('Error al enviar el mensaje. Inténtalo de nuevo.');
    }
}

// Función para actualizar el último mensaje de la conversación actual
function updateCurrentConversationLastMessage(message) {
    const conversationItem = document.querySelector(`[data-chat-id="${currentChatId}"]`);
    if (!conversationItem) return;

    // Seguridad: si no hay mensaje, intentar usar el último cargado en memoria
    const safeMessage = message || chatMessages[chatMessages.length - 1];
    if (!safeMessage) return;

    // Encontrar el elemento del último mensaje
    const lastMessageElement = conversationItem.querySelector('p');
    if (lastMessageElement) {
        const uidMeta2 = document.querySelector('meta[name="user-id"]');
        const currentUserId = uidMeta2 ? uidMeta2.getAttribute('content') : null;
        const sender = safeMessage.sender_id ?? safeMessage.user_id;
        const isMyMessage = sender == currentUserId;
        const messagePrefix = isMyMessage ? 'Tú: ' : '';
        let label = safeMessage && safeMessage.body ? safeMessage.body : '';
        if (!label || label.trim() === '') {
            if (safeMessage.voice_base64 || safeMessage.voice_path) label = 'Audio de voz';
            else if (safeMessage.preview_url || safeMessage.original_name || safeMessage.file_path || safeMessage.drive_file_id) label = 'Archivo adjunto';
            else label = '';
        }
        lastMessageElement.textContent = messagePrefix + label;
    }

    // Actualizar timestamp
    const timeElement = conversationItem.querySelector('.text-xs.text-slate-500');
    if (timeElement) {
        timeElement.textContent = formatMessageTime(safeMessage.created_at || new Date().toISOString());
    }

    // Mover conversación al top de la lista
    const conversationsList = document.getElementById('conversations-list');
    if (conversationsList && conversationItem) {
        conversationsList.insertBefore(conversationItem, conversationsList.firstChild);
    }
}

// Función para determinar estado en línea
function getUserOnlineStatus(user) {
    if (!user || !user.email) return false;

    const now = new Date();
    const hour = now.getHours();
    const emailHash = user.email.split('').reduce((a, b) => {
        a = ((a << 5) - a) + b.charCodeAt(0);
        return a & a;
    }, 0);

    // Simular patrones de actividad basados en hora y hash del email
    const baseActivity = Math.abs(emailHash) % 100;
    const hourBonus = (hour >= 9 && hour <= 18) ? 30 : 0; // Más activo en horario laboral
    const activity = (baseActivity + hourBonus) % 100;

    return activity > 70; // 30% chance de estar en línea
}

// Función para formatear tiempo de mensaje
function formatMessageTime(timestamp) {
    if (!timestamp) return '';

    const now = new Date();
    const messageTime = new Date(timestamp);
    const diffInMinutes = Math.floor((now - messageTime) / (1000 * 60));

    if (diffInMinutes < 1) return 'Ahora';
    if (diffInMinutes < 60) return `${diffInMinutes}m`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h`;

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d`;

    return messageTime.toLocaleDateString();
}

// Función para hacer scroll al final del chat
function scrollChatToBottom() {
    const messagesContainer = document.getElementById('active-chat-messages');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

function isNearBottom(container, thresholdPx = 120) {
    return (container.scrollHeight - container.clientHeight - container.scrollTop) <= thresholdPx;
}

function maybeScrollToBottom() {
    const container = document.getElementById('active-chat-messages');
    if (!container) return;
    if (autoScrollEnabled || isNearBottom(container)) {
        scrollChatToBottom();
    }
}

// Función para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    debugLog('🚀 Inicializando chat...');

    // Cargar conversaciones al cargar la página
    loadConversations();
    // Autoscroll inteligente: detectar cuando el usuario hace scroll manual
    const msgBox = document.getElementById('active-chat-messages');
    if (msgBox) {
        msgBox.addEventListener('scroll', () => {
            // Desactivar autoscroll si el usuario se aleja del fondo; reactivar cuando vuelva cerca
            autoScrollEnabled = isNearBottom(msgBox);
        });
        // Estilos de scrollbar oculto pero scrollable
        msgBox.style.scrollbarWidth = 'thin';
        msgBox.style.msOverflowStyle = 'none';
        msgBox.classList.add('hide-scrollbar');
    }

    // Inicializar auto-refresh después de cargar conversaciones
    setTimeout(() => {
        startAutoRefresh();
        lastConversationUpdate = new Date();
    }, 2000);

    // Event listener para enviar mensaje con Enter
    const messageInput = document.getElementById('active-chat-input');
    if (messageInput) {
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    // Event listener para botón de enviar
    const sendButton = document.getElementById('send-message-btn');
    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }

    // Botón y input para adjuntar archivo (si existen en la vista)
    const fileBtn = document.getElementById('active-chat-file-btn');
    const fileInput = document.getElementById('active-chat-file-input');
    if (fileBtn && fileInput) {
        fileBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => {
            pendingFile = e.target.files[0];
            if (pendingFile) {
                fileBtn.classList.add('text-yellow-400');
                const input = document.getElementById('active-chat-input');
                if (input && !input.value) input.placeholder = `Archivo: ${pendingFile.name}`;
            } else {
                fileBtn.classList.remove('text-yellow-400');
                const input = document.getElementById('active-chat-input');
                if (input) input.placeholder = 'Escribe un mensaje...';
            }
        });
    }

    // Grabación de voz: botón y atajo Alt+V (usa los handlers globales con temporizador y auto-envío)
    const micBtn = document.getElementById('active-chat-voice-btn');
    if (micBtn) micBtn.addEventListener('click', () => toggleRecording());
    document.addEventListener('keydown', (e) => {
        if (e.altKey && (e.key === 'v' || e.key === 'V')) {
            e.preventDefault();
            toggleRecording();
        }
        if (e.key === 'Escape' && mediaRecorder && mediaRecorder.state === 'recording') {
            e.preventDefault();
            try { mediaRecorder.stop(); } catch (_) {}
        }
    });

    // Cargar contactos y listeners para crear chats
    loadContacts();
    const refreshContacts = document.getElementById('refresh-contacts');
    if (refreshContacts) refreshContacts.addEventListener('click', loadContacts);
    const startChatBtn = document.getElementById('start-chat-btn');
    const startChatUser = document.getElementById('start-chat-user');
    if (startChatBtn && startChatUser) {
        startChatBtn.addEventListener('click', async () => {
            const query = startChatUser.value.trim();
            if (!query) return;
            const csrfMeta3 = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta3 ? csrfMeta3.getAttribute('content') : null;
            const formData = new FormData();
            formData.append('user_query', query);
            const resp = await fetch('/api/chats/create-or-find', {
                method: 'POST',
                headers: (function(){ const h={'X-Requested-With':'XMLHttpRequest'}; if (csrfToken) h['X-CSRF-TOKEN']=csrfToken; return h; })(),
                body: formData
            });
            if (resp.ok) {
                const data = await resp.json();
                await loadConversations();
                setTimeout(() => {
                    const el = document.querySelector(`[data-chat-id="${data.chat_id}"]`);
                    if (el) el.click();
                }, 400);
            } else {
                alert('No se pudo crear el chat');
            }
        });
    }
    // Emoji picker simple
    const emojiBtn = document.getElementById('active-chat-emoji-btn');
    const emojiPanel = document.getElementById('emoji-panel');
    const msgInput = document.getElementById('active-chat-input');
    if (emojiBtn && emojiPanel && msgInput) {
        emojiBtn.addEventListener('click', (e)=>{
            e.preventDefault();
            emojiPanel.classList.toggle('hidden');
        });
        emojiPanel.querySelectorAll('.emoji').forEach(el=>{
            el.addEventListener('click', ()=>{
                const emoji = el.textContent || '';
                const start = msgInput.selectionStart || msgInput.value.length;
                const end = msgInput.selectionEnd || msgInput.value.length;
                msgInput.value = msgInput.value.slice(0,start) + emoji + msgInput.value.slice(end);
                msgInput.focus();
                emojiPanel.classList.add('hidden');
            });
        });
        document.addEventListener('click', (e)=>{
            if (!emojiPanel.contains(e.target) && e.target !== emojiBtn) {
                emojiPanel.classList.add('hidden');
            }
        });
    }
});

// Modal confirm helper
function showConfirmModal(text){
    return new Promise((resolve)=>{
        const modal = document.getElementById('confirm-modal');
        const body = document.getElementById('confirm-modal-text');
        const ok = document.getElementById('confirm-accept');
        const cancel = document.getElementById('confirm-cancel');
        if(!modal || !body || !ok || !cancel){ resolve(window.confirm(text)); return; }
        body.textContent = text;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        const done = (val)=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); resolve(val); };
        const onOk=()=>{ cleanup(); done(true); };
        const onCancel=()=>{ cleanup(); done(false); };
        function cleanup(){ ok.removeEventListener('click', onOk); cancel.removeEventListener('click', onCancel); }
        ok.addEventListener('click', onOk);
        cancel.addEventListener('click', onCancel);
    });
}

// Cargar contactos desde API
async function loadContacts() {
    try {
        const resp = await fetch('/api/contacts', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) throw new Error('Error al cargar contactos');
        const data = await resp.json();
        contactsCache = data.contacts || [];
        const list = document.getElementById('contacts-list');
        if (!list) return;
        if (!contactsCache.length) {
            list.innerHTML = '<div class="p-3 text-xs text-slate-500">Sin contactos</div>';
            return;
        }
        list.innerHTML = contactsCache.map(c => `
            <button data-user-id="${c.id}" class="w-full text-left px-3 py-2 hover:bg-slate-700/40 flex items-center gap-2 text-xs">
                <span class="w-6 h-6 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold">${c.name ? c.name.charAt(0).toUpperCase() : '?'}</span>
                <span class="truncate">${c.name || 'Usuario'}</span>
            </button>`).join('');
        list.querySelectorAll('button').forEach(btn => btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-user-id');
            const csrfMeta4 = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta4 ? csrfMeta4.getAttribute('content') : null;
            const formData = new FormData();
            formData.append('contact_id', id);
            const resp2 = await fetch('/api/chats/create-or-find', {
                method: 'POST',
                headers: (function(){ const h={'X-Requested-With':'XMLHttpRequest'}; if (csrfToken) h['X-CSRF-TOKEN']=csrfToken; return h; })(),
                body: formData
            });
            if (resp2.ok) {
                const data = await resp2.json();
                await loadConversations();
                setTimeout(() => {
                    const el = document.querySelector(`[data-chat-id="${data.chat_id}"]`);
                    if (el) el.click();
                }, 300);
            }
        }));
    } catch (e) {
        debugError('Error: loadContacts()', e);
    }
}

// Permitir refresco desde otros módulos (invitaciones)
window.addEventListener('chat:refresh-contacts', () => {
    loadContacts();
});

// Detener auto-refresh cuando la ventana se cierra o cambia de pestaña
window.addEventListener('beforeunload', stopAutoRefresh);
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});
