// Variables globales para el chat
let currentChatId = null;
let currentContactId = null;
let chatMessages = [];
let isChatLoading = false;
let lastConversationUpdate = null;
let autoRefreshInterval = null;
let lastMessageIds = new Set(); // Para trackear qué mensajes ya hemos visto
let contactsCache = [];
let pendingFile = null; // Archivo seleccionado para enviar
let conversationsCache = []; // Cache de conversaciones para búsqueda local

// Función para inicializar auto-refresh de conversaciones
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

// Función para refrescar conversaciones solo si hay cambios
async function refreshConversationsIfNeeded() {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Hacer una petición ligera para verificar si hay cambios
        const response = await fetch('/api/chats', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
            }
        });

        if (!response.ok) return;

        const conversations = await response.json();

        // Verificar si hay cambios comparando timestamps del último mensaje
        let hasNewMessages = false;
        let needsConversationUpdate = false;

        conversations.forEach(conv => {
            const lastMsg = conv.last_message;
            if (!lastMsg) return;

            const msgTime = new Date(lastMsg.created_at);

            // Si es la primera vez o hay un mensaje más reciente
            if (!lastConversationUpdate || msgTime > lastConversationUpdate) {
                needsConversationUpdate = true;

                // Si es un mensaje en el chat actual, marcar para actualizar mensajes
                if (conv.id == currentChatId) {
                    const currentLastMsg = chatMessages[chatMessages.length - 1];
                    if (!currentLastMsg || new Date(currentLastMsg.created_at) < msgTime) {
                        hasNewMessages = true;
                    }
                }
            }
        });

        if (needsConversationUpdate) {
            console.log('🔄 Actualizando conversaciones...', { hasNewMessages });

            // Actualizar conversaciones sin loading
            updateConversationsList(conversations);

            // Si hay mensajes nuevos en el chat actual, recargar mensajes silenciosamente
            if (hasNewMessages && currentChatId) {
                console.log('📨 Nuevos mensajes detectados, actualizando...');
                await loadChatMessages(true); // true = silencioso, sin loading

                // Pequeña animación visual para indicar nuevo mensaje
                const messagesContainer = document.getElementById('active-chat-messages');
                if (messagesContainer) {
                    messagesContainer.style.transform = 'scale(1.01)';
                    setTimeout(() => {
                        messagesContainer.style.transform = 'scale(1)';
                    }, 200);
                }
            }

            lastConversationUpdate = new Date();
        }
    } catch (error) {
        console.error('❌ Error en auto-refresh:', error);
    }
}

// Función para cargar conversaciones
async function loadConversations() {
    const conversationsList = document.getElementById('conversations-list');

    console.log('🔄 Iniciando carga de conversaciones...');

    try {
        conversationsList.innerHTML = `
            <div class="p-8 text-center">
                <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-slate-400">Cargando conversaciones...</p>
            </div>
        `;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('📝 CSRF Token:', csrfToken ? 'Encontrado' : 'No encontrado');

        const response = await fetch('/api/chats', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
            }
        });

        console.log('📡 Response status:', response.status);
        console.log('📡 Response headers:', Object.fromEntries(response.headers.entries()));

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Error response:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const conversations = await response.json();
        console.log('💬 Conversaciones recibidas:', conversations);

        if (!Array.isArray(conversations)) {
            console.error('❌ La respuesta no es un array:', conversations);
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

        // Guardar en cache para búsqueda local
        conversationsCache = conversations;

        conversationsList.innerHTML = '';

        conversations.forEach((conversation, index) => {
            console.log(`💬 Procesando conversación ${index + 1}:`, conversation);
            const conversationElement = createConversationElement(conversation);
            conversationsList.appendChild(conversationElement);
        });

        console.log('✅ Conversaciones cargadas exitosamente');

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
        console.error('❌ Error loading conversations:', error);
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
    const currentChatIdSelected = currentSelection?.getAttribute('data-chat-id');

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

    // Nota: no actualizamos conversationsCache aquí para no romper el buscador

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

    const lastMessageText = conversation.last_message
        ? (conversation.last_message.is_mine ? 'Tú: ' : '') + conversation.last_message.body
        : 'Conversación iniciada';

    // Indicador de mensajes no leídos
    const unreadIndicator = conversation.has_unread
        ? `<div class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-slate-800"></div>`
        : '';

    const unreadBadge = conversation.unread_count > 0
        ? `<span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">${conversation.unread_count}</span>`
        : '';

    // Avatar del usuario - usar initial si no hay avatar
    const userAvatar = conversation.other_user?.avatar || conversation.other_user?.name?.charAt(0).toUpperCase() || '?';
    const userName = conversation.other_user?.name || 'Usuario desconocido';

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
                    </div>
                </div>
                <p class="text-sm ${conversation.has_unread ? 'text-slate-300 font-medium' : 'text-slate-400'} truncate mt-1">${lastMessageText}</p>
            </div>
        </div>
    `;

    div.addEventListener('click', () => {
        selectConversation(conversation);
    });

    return div;
}

// Función para seleccionar una conversación
async function selectConversation(conversation) {
    console.log('🎯 Seleccionando conversación:', conversation);

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
    currentContactId = conversation.other_user?.id;

    // Limpiar el set de mensajes vistos para la nueva conversación
    lastMessageIds.clear();

    // Actualizar UI del chat activo
    const activeChatAvatar = document.getElementById('active-chat-avatar');
    const activeChatName = document.getElementById('active-chat-name');
    const activeChatStatus = document.getElementById('active-chat-status');
    const activeChatStatusText = document.getElementById('active-chat-status-text');

    const userAvatar = conversation.other_user?.avatar || conversation.other_user?.name?.charAt(0).toUpperCase() || '?';
    const userName = conversation.other_user?.name || 'Usuario desconocido';

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
        console.warn('⚠️ No hay chat activo para cargar mensajes');
        return;
    }

    const messagesContainer = document.getElementById('active-chat-messages');
    console.log('📨 Cargando mensajes para chat:', currentChatId);

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

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch(`/api/chats/${currentChatId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
            }
        });

        console.log('📨 Messages response status:', response.status);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Error loading messages:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const messages = await response.json();
        console.log('📨 Mensajes recibidos:', messages);

        chatMessages = Array.isArray(messages) ? messages : [];

        updateChatMessagesDisplay();
        scrollChatToBottom();

    } catch (error) {
        console.error('❌ Error loading messages:', error);
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
    const currentUserId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');

    console.log('🎨 Actualizando visualización de mensajes. Total:', chatMessages.length);
    console.log('👤 Current user ID:', currentUserId);

    if (!messagesContainer) {
        console.error('❌ No se encontró el contenedor de mensajes');
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
        console.log(`💬 Procesando mensaje ${index + 1}:`, message);

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

    console.log('💬 Creando mensaje:', {
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
    if (message.original_name || message.file_path || message.preview_url) {
        const fileName = escapeHtml(message.original_name || message.originalName || 'Archivo');
        const mime = message.mime_type || '';
        const driveLink = message.drive_file_id ? `https://drive.google.com/file/d/${message.drive_file_id}/view` : null;
        const localPath = message.file_path ? `/storage/${message.file_path}` : null;
        const downloadLink = driveLink || localPath || '#';
        let preview = '';
        if (message.preview_url && mime.startsWith('image/')) {
            preview = `<img src="${message.preview_url}" alt="${fileName}" class="mt-2 rounded shadow max-h-48">`;
        } else if (message.preview_url && mime === 'application/pdf') {
            preview = `<iframe src="${message.preview_url}" class="mt-2 w-60 h-40 rounded" loading="lazy"></iframe>`;
        } else if (message.preview_url && mime.startsWith('audio/')) {
            preview = `<audio controls class="mt-2 w-52"><source src="${message.preview_url}"></audio>`;
        } else if (message.preview_url && mime.startsWith('video/')) {
            preview = `<iframe src="${message.preview_url}" class="mt-2 w-60 h-40 rounded" loading="lazy"></iframe>`;
        } else if (downloadLink && downloadLink !== '#') {
            preview = `<div class="mt-2 px-3 py-2 bg-slate-800/40 rounded text-xs">Adjunto listo para descargar</div>`;
        }
        attachmentHtml = `
            <div class="mt-2 text-xs ${isMyMessage ? 'text-slate-800' : 'text-slate-300'}">
                <a href="${downloadLink}" target="_blank" class="underline break-all">${fileName}</a>
                ${preview}
            </div>`;
    }

    div.innerHTML = `
        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${bubbleClasses}">
            ${message.body ? `<p class="text-sm whitespace-pre-line">${escapeHtml(message.body)}</p>` : ''}
            ${attachmentHtml}
            <p class="text-xs mt-1 ${isMyMessage ? 'text-slate-800' : 'text-slate-400'}">${formatMessageTime(message.created_at)}</p>
        </div>`;

    return div;
}

// Función para enviar mensaje
async function sendMessage() {
    if (!currentChatId) {
        console.warn('⚠️ No hay chat activo para enviar mensaje');
        return;
    }

    const messageInput = document.getElementById('active-chat-input');
    const messageText = messageInput.value.trim();

    if (!messageText && !pendingFile) {
        console.warn('⚠️ Nada que enviar');
        return;
    }

    console.log('📤 Enviando mensaje:', messageText);

    // Limpiar input inmediatamente
    messageInput.value = '';
    const fileToSend = pendingFile; // snapshot
    pendingFile = null;

    // Crear mensaje optimista (mostrar inmediatamente)
    const currentUserId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
    const optimisticMessage = {
        id: 'temp-' + Date.now(),
        body: messageText,
        sender_id: currentUserId,
        created_at: new Date().toISOString(),
        is_temp: true,
        original_name: fileToSend ? fileToSend.name : null,
        mime_type: fileToSend ? fileToSend.type : null
    };

    // Agregar mensaje optimista al array y actualizar UI inmediatamente
    chatMessages.push(optimisticMessage);
    updateChatMessagesDisplay();
    scrollChatToBottom();

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Enviar mensaje al servidor en background
        let fetchOptions = { method: 'POST' };
        if (fileToSend) {
            const formData = new FormData();
            formData.append('body', messageText);
            formData.append('file', fileToSend);
            fetchOptions.body = formData;
            fetchOptions.headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
            };
        } else {
            fetchOptions.body = JSON.stringify({ body: messageText });
            fetchOptions.headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
            };
        }

        const response = await fetch(`/api/chats/${currentChatId}/messages`, fetchOptions);

        console.log('📤 Send message response status:', response.status);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Error sending message:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const result = await response.json();
        console.log('📤 Mensaje enviado exitosamente:', result);

        // Remover mensaje optimista y agregar el real (que incluye metadata de adjunto)
        chatMessages = chatMessages.filter(msg => msg.id !== optimisticMessage.id);
        if (result.message) {
            chatMessages.push(result.message);
        }
        updateChatMessagesDisplay();

        // Actualizar conversaciones sin recargar todo (solo la actual)
        updateCurrentConversationLastMessage(result.message);

    } catch (error) {
        console.error('❌ Error sending message:', error);

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

    // Encontrar el elemento del último mensaje
    const lastMessageElement = conversationItem.querySelector('p');
    if (lastMessageElement) {
        const currentUserId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
        const isMyMessage = message.sender_id == currentUserId;
        const messagePrefix = isMyMessage ? 'Tú: ' : '';
        lastMessageElement.textContent = messagePrefix + message.body;
    }

    // Actualizar timestamp
    const timeElement = conversationItem.querySelector('.text-xs.text-slate-500');
    if (timeElement) {
        timeElement.textContent = formatMessageTime(message.created_at);
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

// Función para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando chat...');

    // Cargar conversaciones al cargar la página
    loadConversations();

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

    // Búsqueda local de conversaciones
    const searchInput = document.getElementById('chat-search');
    if (searchInput) {
        let searchTimer = null;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim().toLowerCase();
            searchTimer = setTimeout(() => filterConversations(q), 150);
        });
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
            } else {
                fileBtn.classList.remove('text-yellow-400');
            }
        });
    }

    // Cargar contactos y listeners para crear chats
    loadContacts();
    const refreshContacts = document.getElementById('refresh-contacts');
    if (refreshContacts) refreshContacts.addEventListener('click', loadContacts);
    const startChatBtn = document.getElementById('start-chat-btn');
    const startChatUser = document.getElementById('start-chat-user');
    if (startChatBtn && startChatUser) {
        // Iniciar con Enter desde el input
        startChatUser.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                startChatBtn.click();
            }
        });
        startChatBtn.addEventListener('click', async () => {
            const query = startChatUser.value.trim();
            if (!query) return;
            // Loading UI
            const originalLabel = startChatBtn.textContent;
            startChatBtn.disabled = true;
            startChatBtn.textContent = 'Creando…';
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const formData = new FormData();
            formData.append('user_query', query);
            const resp = await fetch('/api/chats/create-or-find', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }) },
                body: formData
            });
            if (resp.ok) {
                const data = await resp.json();
                await loadConversations();
                setTimeout(() => {
                    const el = document.querySelector(`[data-chat-id="${data.chat_id}"]`);
                    if (el) el.click();
                }, 400);
                startChatUser.value = '';
            } else {
                try {
                    const errText = await resp.text();
                    console.error('Start chat error:', errText);
                } catch {}
                alert('No se pudo crear el chat');
            }
            // Restore UI
            startChatBtn.disabled = false;
            startChatBtn.textContent = originalLabel;
        });
    }
});

// Filtrar conversaciones por nombre de usuario o último mensaje
function filterConversations(query) {
    const list = document.getElementById('conversations-list');
    if (!list) return;

    if (!query) {
        // Restaurar lista completa
        updateConversationsList(conversationsCache);
        return;
    }

    const filtered = (conversationsCache || []).filter(c => {
        const name = (c.other_user?.name || '').toLowerCase();
        const email = (c.other_user?.email || '').toLowerCase();
        const last = (c.last_message?.body || '').toLowerCase();
        return name.includes(query) || email.includes(query) || last.includes(query);
    });

    updateConversationsList(filtered);
}

// Cargar contactos desde API
async function loadContacts() {
    try {
        const list = document.getElementById('contacts-list');
        if (list) list.innerHTML = '<div class="p-3 text-xs text-slate-500">Cargando…</div>';

        let resp = await fetch('/api/chats/contacts', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
        let data;
        if (resp.ok) {
            const ct = resp.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                data = await resp.json();
            } else {
                // Probable sesión expirada o redirección al login
                console.warn('Contacts endpoint devolvió contenido no-JSON; intentando fallback /api/contacts');
            }
        }

        // Fallback: usar /api/contacts si el endpoint anterior falla o no es JSON
        if (!Array.isArray(data)) {
            const resp2 = await fetch('/api/contacts', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (resp2.ok && (resp2.headers.get('content-type') || '').includes('application/json')) {
                const payload = await resp2.json();
                if (payload && payload.success) {
                    const a = Array.isArray(payload.contacts) ? payload.contacts : [];
                    const b = Array.isArray(payload.users) ? payload.users : [];
                    data = [...a, ...b].map(u => ({ id: u.id, name: u.name || u.full_name || u.email, email: u.email }));
                }
            }
        }

        if (!Array.isArray(data)) {
            throw new Error('No se pudieron obtener contactos (verifica tu sesión)');
        }
        contactsCache = data;
        if (!list) return;
        if (!contactsCache.length) {
            list.innerHTML = '<div class="p-3 text-xs text-slate-500">Sin contactos</div>';
            return;
        }
        list.innerHTML = contactsCache.map(c => `
            <button data-user-id="${c.id}" class="w-full text-left px-3 py-2 hover:bg-slate-700/40 flex items-center gap-2 text-xs">
                <span class="w-6 h-6 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold">${(c.avatar || (c.name ? c.name.charAt(0).toUpperCase() : '?'))}</span>
                <span class="truncate">${c.name || 'Usuario'}</span>
            </button>`).join('');
        list.querySelectorAll('button').forEach(btn => btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-user-id');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const formData = new FormData();
            formData.append('contact_id', id);
            const resp2 = await fetch('/api/chats/create-or-find', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }) },
                body: formData
            });
            if (resp2.ok) {
                const data = await resp2.json();
                await loadConversations();
                setTimeout(() => {
                    const el = document.querySelector(`[data-chat-id="${data.chat_id}"]`);
                    if (el) el.click();
                }, 300);
            } else {
                try {
                    const errText = await resp2.text();
                    console.error('Create/find chat error:', errText);
                } catch {}
                alert('No se pudo crear el chat');
            }
        }));
    } catch (e) {
        console.error('Error cargando contactos:', e);
        const list = document.getElementById('contacts-list');
        if (list) {
            list.innerHTML = '<div class="p-3 text-xs text-red-400">Error al cargar contactos. Revisa tu conexión o vuelve a iniciar sesión.</div>';
        }
    }
}

// Detener auto-refresh cuando la ventana se cierra o cambia de pestaña
window.addEventListener('beforeunload', stopAutoRefresh);
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});
