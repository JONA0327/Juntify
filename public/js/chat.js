// Variables globales para el chat
let currentChatId = null;
let currentContactId = null;
let chatMessages = [];
let isChatLoading = false;
let lastConversationUpdate = null;
let autoRefreshInterval = null;
let lastMessageIds = new Set(); // Para trackear qué mensajes ya hemos visto
let pendingFile = null; // Archivo seleccionado para enviar
let contactsInitialized = false;
let contactsSearchTimeout = null;
let contactSelectedUser = null;

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

    // Tabs principales (Mensajes / Contactos)
    const mainTabs = document.querySelectorAll('.chat-main-tab');
    const mainSections = {};
    const messagesHeader = document.getElementById('chat-messages-header');

    mainTabs.forEach(tab => {
        const targetId = tab.dataset.target;
        if (targetId) {
            mainSections[targetId] = document.getElementById(targetId);
        }
    });

    function setActiveMainTab(targetId) {
        mainTabs.forEach(tab => {
            const isActive = tab.dataset.target === targetId;
            tab.setAttribute('aria-pressed', String(isActive));
            tab.classList.toggle('bg-yellow-500', isActive);
            tab.classList.toggle('text-slate-900', isActive);
            tab.classList.toggle('shadow-lg', isActive);
            tab.classList.toggle('shadow-yellow-400/30', isActive);
            tab.classList.toggle('border-yellow-400/50', isActive);
            tab.classList.toggle('bg-slate-800/50', !isActive);
            tab.classList.toggle('text-slate-200', !isActive);
            tab.classList.toggle('border-slate-700/60', !isActive);
        });

        Object.entries(mainSections).forEach(([id, section]) => {
            if (!section) return;
            if (id === targetId) {
                section.classList.remove('hidden');
            } else {
                section.classList.add('hidden');
            }
        });

        if (targetId === 'chat-contacts-section') {
            if (!contactsInitialized) {
                initializeContactsSection();
            } else {
                loadContacts();
            }
        }

        if (messagesHeader) {
            messagesHeader.classList.toggle('hidden', targetId !== 'chat-messages-section');
        }
    }

    if (mainTabs.length) {
        mainTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetId = tab.dataset.target;
                if (targetId) {
                    setActiveMainTab(targetId);
                }
            });
        });

        const defaultMainTab = document.querySelector('.chat-main-tab[data-target="chat-messages-section"]');
        if (defaultMainTab?.dataset.target) {
            setActiveMainTab(defaultMainTab.dataset.target);
        }
    }

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
    const sidebarContactsPanel = document.getElementById('sidebar-contacts');
    if (sidebarContactsPanel) {
        loadContacts();
        const refreshContacts = document.getElementById('refresh-contacts');
        if (refreshContacts) refreshContacts.addEventListener('click', loadContacts);
    }
    const startChatBtn = document.getElementById('start-chat-btn');
    const startChatUser = document.getElementById('start-chat-user');
    if (startChatBtn && startChatUser) {
        startChatBtn.addEventListener('click', async () => {
            const query = startChatUser.value.trim();
            if (!query) return;
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
            } else {
                alert('No se pudo crear el chat');
            }
        });
    }

    // Tabs de la barra lateral
    const sidebarTabs = document.querySelectorAll('.chat-sidebar-tab');
    const tabPanels = {};
    sidebarTabs.forEach(btn => {
        const target = btn.dataset.target;
        if (target) {
            tabPanels[target] = document.getElementById(target);
        }
    });

    function setActiveSidebarTab(targetId) {
        sidebarTabs.forEach(btn => {
            const isActive = btn.dataset.target === targetId;
            btn.classList.toggle('bg-slate-700/50', isActive);
        });

        Object.entries(tabPanels).forEach(([id, panel]) => {
            if (!panel) return;
            if (id === targetId) {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        });
    }

    if (sidebarTabs.length) {
        sidebarTabs.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.dataset.target;
                if (targetId) {
                    setActiveSidebarTab(targetId);
                }
            });
        });

        const defaultTab = document.querySelector('.chat-sidebar-tab[data-target="sidebar-conversations"]');
        if (defaultTab?.dataset.target) {
            setActiveSidebarTab(defaultTab.dataset.target);
        }
    }

    // Botón para ocultar/mostrar la lista de conversaciones
    const toggleSidebarBtn = document.getElementById('toggle-sidebar');
    const conversationSidebar = document.getElementById('conversation-sidebar');
    const chatLayout = document.getElementById('chat-layout');
    if (toggleSidebarBtn && conversationSidebar && chatLayout) {
        const icons = {
            hide: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>',
            show: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>'
        };

        toggleSidebarBtn.addEventListener('click', () => {
            const willHide = !chatLayout.classList.contains('sidebar-hidden');
            chatLayout.classList.toggle('sidebar-hidden', willHide);
            toggleSidebarBtn.setAttribute('aria-expanded', String(!willHide));
            toggleSidebarBtn.innerHTML = willHide ? icons.show : icons.hide;
            toggleSidebarBtn.setAttribute('aria-label', willHide ? 'Mostrar conversaciones' : 'Ocultar conversaciones');
        });
    }
});

function initializeContactsSection() {
    if (contactsInitialized) return;
    contactsInitialized = true;

    const addContactBtn = document.getElementById('add-contact-btn');
    if (addContactBtn) {
        addContactBtn.addEventListener('click', () => openAddContactModal());
    }

    const addContactForm = document.getElementById('add-contact-form');
    if (addContactForm) {
        addContactForm.addEventListener('submit', sendContactRequest);
    }

    const closeModalBtn = document.getElementById('close-modal-btn');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeAddContactModal);
    }

    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeAddContactModal);
    }

    const modal = document.getElementById('add-contact-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAddContactModal();
            }
        });
    }

    const userSearchInput = document.getElementById('user-search-input');
    if (userSearchInput) {
        userSearchInput.addEventListener('input', (e) => {
            clearTimeout(contactsSearchTimeout);
            const value = e.target.value;
            contactsSearchTimeout = setTimeout(() => {
                searchUser(value);
            }, 300);
        });
    }

    const contactSearchInput = document.getElementById('contact-search');
    if (contactSearchInput) {
        contactSearchInput.addEventListener('input', (e) => {
            filterContacts(e.target.value);
        });
    }

    loadContacts();
}

async function loadContacts() {
    const list = document.getElementById('contacts-list');
    const userList = document.getElementById('organization-users-list');
    const organizationSection = document.getElementById('organization-section');
    const organizationTitle = document.getElementById('organization-section-title');

    if (list) {
        list.innerHTML = '<div class="loading-state flex flex-col items-center justify-center py-8"><div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mb-3"></div><p class="text-slate-400 text-center">Cargando contactos...</p></div>';
    }

    try {
        const response = await fetch('/api/contacts', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) throw new Error('Error al cargar contactos');

        const data = await response.json();

        if (organizationSection) {
            if (data.show_organization_section) {
                organizationSection.style.display = 'block';

                if (organizationTitle) {
                    if (data.has_organization && data.has_groups) {
                        organizationTitle.textContent = 'Usuarios de mi organización y grupos';
                    } else if (data.has_organization) {
                        organizationTitle.textContent = 'Usuarios de mi organización';
                    } else if (data.has_groups) {
                        organizationTitle.textContent = 'Usuarios de mis grupos';
                    } else {
                        organizationTitle.textContent = 'Otros usuarios';
                    }
                }
            } else {
                organizationSection.style.display = 'none';
            }
        }

        renderContacts(data.contacts || [], data.users || []);

        await checkUnreadMessagesForContacts();
    } catch (error) {
        console.error('Error loading contacts:', error);
        if (list) list.innerHTML = '<div class="text-center py-8 text-red-400">Error al cargar contactos</div>';
        if (organizationSection) {
            organizationSection.style.display = 'none';
        }
    }

    await loadContactRequests();
}

function renderContacts(contacts, users) {
    const list = document.getElementById('contacts-list');
    const userList = document.getElementById('organization-users-list');
    const countElement = document.getElementById('contacts-count');

    if (countElement) {
        countElement.textContent = `${contacts.length} contacto${contacts.length !== 1 ? 's' : ''}`;
    }

    if (list) {
        list.innerHTML = '';
        if (!contacts.length) {
            list.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="text-slate-400">No tienes contactos aún</p>
                    <p class="text-slate-500 text-sm mt-1">Añade tu primer contacto para empezar</p>
                </div>
            `;
        } else {
            for (const contact of contacts) {
                const contactElement = document.createElement('div');
                contactElement.className = 'contact-card';
                contactElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold">
                                ${contact.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="text-slate-200 font-medium">${contact.name}</h4>
                                <p class="text-slate-400 text-sm">${contact.email}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="startChat('${contact.id}')"
                                    class="relative p-2 text-blue-400 hover:text-blue-300 hover:bg-blue-400/10 rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/20 transform hover:scale-105"
                                    title="Iniciar chat"
                                    id="chat-btn-${contact.id}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                                </svg>
                                <div id="unread-indicator-${contact.id}" class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-slate-800 hidden"></div>
                            </button>
                            <button onclick="deleteContact('${contact.contact_record_id}')"
                                    class="p-2 text-red-400 hover:text-red-300 hover:bg-red-400/10 rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-red-500/20 transform hover:scale-105"
                                    title="Eliminar contacto">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
                list.appendChild(contactElement);
            }
        }
    }

    if (userList) {
        userList.innerHTML = '';
        if (!users.length) {
            userList.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <p class="text-slate-400">No hay otros usuarios en tu organización</p>
                </div>
            `;
        } else {
            for (const user of users) {
                const userElement = document.createElement('div');
                userElement.className = 'contact-card';

                const getGroupColor = (groupName) => {
                    if (!groupName || groupName === 'Sin grupo') {
                        return 'bg-gray-500/20 text-gray-300';
                    }
                    const colors = {
                        'DEVS': 'bg-blue-500/20 text-blue-300',
                        'ADMIN': 'bg-red-500/20 text-red-300',
                        'MARKETING': 'bg-green-500/20 text-green-300',
                        'VENTAS': 'bg-yellow-500/20 text-yellow-300',
                        'SOPORTE': 'bg-purple-500/20 text-purple-300'
                    };
                    return colors[groupName.toUpperCase()] || 'bg-indigo-500/20 text-indigo-300';
                };

                const getRoleColor = (role) => {
                    const roleColors = {
                        'administrador': 'bg-red-600/20 text-red-400',
                        'colaborador': 'bg-blue-600/20 text-blue-400',
                        'invitado': 'bg-gray-600/20 text-gray-400'
                    };
                    return roleColors[role] || 'bg-gray-500/20 text-gray-300';
                };

                userElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold">
                                ${user.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="text-slate-200 font-medium">${user.name}</h4>
                                <p class="text-slate-400 text-sm">${user.email}</p>
                                <div class="flex gap-2 mt-1">
                                    <span class="inline-block ${getGroupColor(user.group_name)} text-xs px-2 py-1 rounded-full">
                                        📂 ${user.group_name || 'Sin grupo'}
                                    </span>
                                    ${user.group_role ? `
                                        <span class="inline-block ${getRoleColor(user.group_role)} text-xs px-2 py-1 rounded-full">
                                            👤 ${user.group_role}
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        <button onclick="openAddContactModal('${user.email}')"
                                class="px-3 py-1 text-yellow-400 hover:text-yellow-300 hover:bg-yellow-400/10 rounded-lg transition-all text-sm font-medium">
                            Añadir
                        </button>
                    </div>
                `;
                userList.appendChild(userElement);
            }
        }
    }
}

async function checkUnreadMessagesForContacts() {
    const contactElements = document.querySelectorAll('[id^="chat-btn-"]');

    for (const element of contactElements) {
        const contactId = element.id.replace('chat-btn-', '');
        try {
            const response = await fetch('/api/chats/unread-count', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ contact_id: contactId })
            });

            if (!response.ok) throw new Error('Error obteniendo mensajes no leídos');

            const data = await response.json();
            const indicator = document.getElementById(`unread-indicator-${contactId}`);
            if (indicator) {
                if (data.unread_count > 0) {
                    indicator.classList.remove('hidden');
                } else {
                    indicator.classList.add('hidden');
                }
            }
        } catch (error) {
            console.error('Error checking unread messages:', error);
        }
    }
}

async function loadContactRequests() {
    try {
        const response = await fetch('/api/contacts/requests', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        if (!response.ok) throw new Error('Error al cargar solicitudes de contacto');

        const data = await response.json();

        const receivedList = document.getElementById('received-requests-list');
        const sentList = document.getElementById('sent-requests-list');

        if (receivedList) {
            receivedList.innerHTML = '';
            const received = data.received || [];
            if (!received.length) {
                receivedList.innerHTML = '<p class="text-slate-500 text-center py-4">No tienes solicitudes pendientes</p>';
            } else {
                for (const request of received) {
                    const requestElement = document.createElement('div');
                    requestElement.className = 'request-card';
                    requestElement.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                                    ${request.sender.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <h5 class="text-slate-200 font-medium">${request.sender.name}</h5>
                                    <p class="text-slate-400 text-sm">${request.sender.email}</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="respondContactRequest('${request.id}', 'accept')"
                                        class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-all">
                                    Aceptar
                                </button>
                                <button onclick="respondContactRequest('${request.id}', 'reject')"
                                        class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-all">
                                    Rechazar
                                </button>
                            </div>
                        </div>
                    `;
                    receivedList.appendChild(requestElement);
                }
            }
        }

        if (sentList) {
            sentList.innerHTML = '';
            const sent = data.sent || [];
            if (!sent.length) {
                sentList.innerHTML = '<p class="text-slate-500 text-center py-4">No hay solicitudes enviadas</p>';
            } else {
                for (const request of sent) {
                    const requestElement = document.createElement('div');
                    requestElement.className = 'request-card';
                    requestElement.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                                    ${request.receiver.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <h5 class="text-slate-200 font-medium">${request.receiver.name}</h5>
                                    <p class="text-slate-400 text-sm">${request.receiver.email}</p>
                                    <span class="inline-block bg-yellow-500/20 text-yellow-300 text-xs px-2 py-1 rounded-full mt-1">Pendiente</span>
                                </div>
                            </div>
                        </div>
                    `;
                    sentList.appendChild(requestElement);
                }
            }
        }
    } catch (error) {
        console.error('Error loading contact requests:', error);
        const receivedList = document.getElementById('received-requests-list');
        const sentList = document.getElementById('sent-requests-list');
        if (receivedList) receivedList.innerHTML = '<p class="text-red-400 text-center py-4">Error al cargar solicitudes</p>';
        if (sentList) sentList.innerHTML = '<p class="text-red-400 text-center py-4">Error al cargar solicitudes</p>';
    }
}

async function respondContactRequest(id, action) {
    try {
        const response = await fetch(`/api/contacts/requests/${id}/respond`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action })
        });
        if (!response.ok) throw new Error('Error al responder solicitud');
        await loadContacts();
        showNotification(
            action === 'accept' ? 'Solicitud aceptada' : 'Solicitud rechazada',
            'success'
        );
    } catch (error) {
        console.error('Error responding to contact request:', error);
        showNotification('Error al procesar la solicitud', 'error');
    }
}

async function deleteContact(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este contacto?')) return;
    try {
        const response = await fetch(`/api/contacts/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        if (!response.ok) throw new Error('Error al eliminar contacto');
        await loadContacts();
        showNotification('Contacto eliminado correctamente', 'success');
    } catch (error) {
        console.error('Error deleting contact:', error);
        showNotification('Error al eliminar contacto', 'error');
    }
}

async function startChat(contactId) {
    try {
        showNotification('Iniciando chat...', 'info');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch('/api/chats/create-or-find', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
            },
            body: JSON.stringify({
                contact_id: contactId
            })
        });

        if (!response.ok) {
            throw new Error('Error al crear/buscar el chat');
        }

        const data = await response.json();

        await loadConversations();

        setTimeout(() => {
            const conversationElement = document.querySelector(`[data-chat-id="${data.chat_id}"]`);
            if (conversationElement) {
                conversationElement.click();
            }
        }, 300);

        const messagesTab = document.querySelector('.chat-main-tab[data-target="chat-messages-section"]');
        if (messagesTab) {
            messagesTab.click();
        }

        showNotification('Chat listo', 'success');
    } catch (error) {
        console.error('Error starting chat:', error);
        showNotification('Error al iniciar el chat', 'error');
    }
}

function openAddContactModal(prefilledEmail = '') {
    const modal = document.getElementById('add-contact-modal');
    const input = document.getElementById('user-search-input');
    const searchResults = document.getElementById('search-results');
    const submitBtn = document.getElementById('submit-btn');

    if (!modal || !input || !searchResults || !submitBtn) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (prefilledEmail) {
        input.value = prefilledEmail;
        searchUser(prefilledEmail);
    } else {
        input.value = '';
        searchResults.classList.add('hidden');
        submitBtn.disabled = true;
        contactSelectedUser = null;
    }

    input.focus();
}

function closeAddContactModal() {
    const modal = document.getElementById('add-contact-modal');
    const input = document.getElementById('user-search-input');
    const searchResults = document.getElementById('search-results');
    const submitBtn = document.getElementById('submit-btn');

    if (!modal || !input || !searchResults || !submitBtn) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    input.value = '';
    searchResults.classList.add('hidden');
    submitBtn.disabled = true;
    contactSelectedUser = null;
}

async function searchUser(query) {
    const trimmed = query.trim();
    const searchResults = document.getElementById('search-results');
    const submitBtn = document.getElementById('submit-btn');

    if (!searchResults || !submitBtn) return;

    if (!trimmed) {
        searchResults.classList.add('hidden');
        submitBtn.disabled = true;
        contactSelectedUser = null;
        return;
    }

    if (trimmed.length < 3) {
        searchResults.classList.add('hidden');
        submitBtn.disabled = true;
        contactSelectedUser = null;
        return;
    }

    try {
        const response = await fetch('/api/users/search', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query: trimmed })
        });

        if (!response.ok) {
            renderSearchResults([]);
            return;
        }

        const data = await response.json().catch(() => ({ users: [] }));
        renderSearchResults(data.users || []);
    } catch (error) {
        console.error('Error searching users:', error);
        renderSearchResults([]);
    }
}

function renderSearchResults(users) {
    const searchResults = document.getElementById('search-results');
    const searchResultsList = document.getElementById('search-results-list');
    const submitBtn = document.getElementById('submit-btn');

    if (!searchResults || !searchResultsList || !submitBtn) return;

    searchResultsList.innerHTML = '';

    if (!users.length) {
        searchResultsList.innerHTML = '<p class="text-slate-400 text-sm">No se encontraron usuarios</p>';
        searchResults.classList.remove('hidden');
        submitBtn.disabled = true;
        return;
    }

    for (const user of users) {
        const userElement = document.createElement('div');
        userElement.className = 'user-search-item p-3 rounded-lg border border-slate-700/50 bg-slate-800/40 hover:border-yellow-500/50 hover:bg-yellow-500/10 cursor-pointer transition-all';
        userElement.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-medium text-sm">
                    ${user.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <h5 class="text-slate-200 font-medium">${user.name}</h5>
                    <p class="text-slate-400 text-sm">${user.email}</p>
                </div>
            </div>
            <div class="text-xs text-slate-500">
                ${user.exists_in_juntify ? 'Usuario de Juntify' : 'Usuario externo'}
            </div>
        `;

        userElement.addEventListener('click', () => selectUser(user));
        searchResultsList.appendChild(userElement);
    }

    searchResults.classList.remove('hidden');
}

function selectUser(user) {
    contactSelectedUser = user;
    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) {
        submitBtn.disabled = false;
    }

    const items = document.querySelectorAll('.user-search-item');
    items.forEach(item => item.classList.remove('bg-yellow-500/20', 'border-yellow-500/50'));

    const selectedItem = Array.from(items).find(item =>
        item.textContent.includes(user.email)
    );
    if (selectedItem) {
        selectedItem.classList.add('bg-yellow-500/20', 'border-yellow-500/50');
    }
}

async function sendContactRequest(event) {
    event.preventDefault();

    if (!contactSelectedUser) {
        alert('Por favor selecciona un usuario');
        return;
    }

    const submitBtn = document.getElementById('submit-btn');
    if (!submitBtn) return;

    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';

    try {
        const response = await fetch('/api/contacts', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: contactSelectedUser.email
            })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Error al enviar solicitud');
        }

        closeAddContactModal();
        await loadContacts();

        showNotification('Solicitud enviada correctamente', 'success');
    } catch (error) {
        console.error('Error sending contact request:', error);
        showNotification(error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

function showNotification(message, type = 'info') {
    let container = document.getElementById('global-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'global-toast-container';
        container.style.position = 'fixed';
        container.style.top = '1rem';
        container.style.right = '1rem';
        container.style.zIndex = '9999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '0.75rem';
        document.body.appendChild(container);
    }

    const notification = document.createElement('div');
    notification.className = `p-4 rounded-lg shadow-lg w-full transform transition-all duration-300 opacity-0 translate-y-2`;

    switch (type) {
        case 'success':
            notification.classList.add('bg-green-500', 'text-white');
            break;
        case 'error':
            notification.classList.add('bg-red-500', 'text-white');
            break;
        case 'info':
            notification.classList.add('bg-blue-500', 'text-white');
            break;
        case 'warning':
            notification.classList.add('bg-yellow-500', 'text-slate-900');
            break;
        default:
            notification.classList.add('bg-slate-700', 'text-slate-200');
    }

    notification.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 18a9 9 0 110-18 9 9 0 010 18z"/></svg>
            </div>
            <div class="text-sm leading-5">${escapeHtml(message)}</div>
        </div>
    `;

    notification.style.pointerEvents = 'auto';
    container.appendChild(notification);

    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-y-2');
    }, 10);

    setTimeout(() => {
        notification.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => notification.remove(), 200);
    }, 4000);
}

function filterContacts(searchTerm) {
    const contactCards = document.querySelectorAll('#contacts-list .contact-card');
    const searchLower = (searchTerm || '').toLowerCase();

    contactCards.forEach(card => {
        const name = card.querySelector('h4')?.textContent.toLowerCase() || '';
        const email = card.querySelector('p')?.textContent.toLowerCase() || '';

        if (name.includes(searchLower) || email.includes(searchLower)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
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
