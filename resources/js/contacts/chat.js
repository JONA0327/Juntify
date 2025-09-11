document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('chat-app');
    if (!container) return;

    const chatId = container.dataset.chatId;
    const currentUserId = parseInt(container.dataset.currentUserId, 10);

    let messages = [];
    let isLoading = false;

    loadMessages();
    setupEventListeners();

    function setupEventListeners() {
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const fileBtn = document.getElementById('file-btn');
        const fileInput = document.getElementById('file-input');
        const voiceBtn = document.getElementById('voice-btn');

        messageInput.addEventListener('keypress', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        messageForm.addEventListener('submit', e => {
            e.preventDefault();
            sendMessage();
        });

        fileBtn.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                sendFile(this.files[0]);
            }
        });

        voiceBtn.addEventListener('click', () => {
            showNotification('Funcionalidad de voz próximamente', 'info');
        });
    }

    async function loadMessages() {
        try {
            isLoading = true;
            updateMessagesDisplay();

            const response = await fetch(`/api/chats/${chatId}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) throw new Error('Error al cargar mensajes');

            messages = await response.json();
            updateMessagesDisplay();
            scrollToBottom();
        } catch (error) {
            console.error('Error loading messages:', error);
            showNotification('Error al cargar mensajes', 'error');
        } finally {
            isLoading = false;
        }
    }

    async function sendMessage() {
        const messageInput = document.getElementById('message-input');
        const messageText = messageInput.value.trim();

        if (!messageText) return;

        try {
            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true;

            const response = await fetch(`/api/chats/${chatId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ body: messageText })
            });

            if (!response.ok) throw new Error('Error al enviar mensaje');

            const newMessage = await response.json();
            messages.push(newMessage);
            messageInput.value = '';
            updateMessagesDisplay();
            scrollToBottom();
        } catch (error) {
            console.error('Error sending message:', error);
            showNotification('Error al enviar mensaje', 'error');
        } finally {
            document.getElementById('send-btn').disabled = false;
        }
    }

    async function sendFile(file) {
        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch(`/api/chats/${chatId}/messages`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: formData
            });

            if (!response.ok) throw new Error('Error al enviar archivo');

            const newMessage = await response.json();
            messages.push(newMessage);
            updateMessagesDisplay();
            scrollToBottom();

            document.getElementById('file-input').value = '';
        } catch (error) {
            console.error('Error sending file:', error);
            showNotification('Error al enviar archivo', 'error');
        }
    }

    function updateMessagesDisplay() {
        const messagesList = document.getElementById('messages-list');

        if (isLoading) {
            messagesList.innerHTML = `
            <div class="text-center py-8">
                <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-slate-400">Cargando mensajes...</p>
            </div>`;
            return;
        }

        if (messages.length === 0) {
            messagesList.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                </svg>
                <p class="text-slate-400">No hay mensajes aún</p>
                <p class="text-slate-500 text-sm mt-1">Envía el primer mensaje para comenzar la conversación</p>
            </div>`;
            return;
        }

        messagesList.innerHTML = messages.map(message => {
            const isOwn = Number(message.sender_id) === currentUserId;
            const time = new Date(message.created_at).toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });

            return `
            <div class="flex ${isOwn ? 'justify-end' : 'justify-start'}">
                <div class="max-w-xs lg:max-w-md ${isOwn ? 'bg-blue-600' : 'bg-slate-700'} rounded-lg px-4 py-2">
                    ${message.body ? `<p class="text-white">${escapeHtml(message.body)}</p>` : ''}
                    ${message.file_path ? `
                        <div class="mt-2">
                            <a href="/storage/${message.file_path}" target="_blank"
                               class="flex items-center gap-2 text-blue-200 hover:text-blue-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                Ver archivo
                            </a>
                        </div>
                    ` : ''}
                    <div class="flex ${isOwn ? 'justify-end' : 'justify-start'} mt-1">
                        <span class="text-xs ${isOwn ? 'text-blue-200' : 'text-slate-400'}">${time}</span>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function scrollToBottom() {
        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm w-full transform transition-all duration-300 translate-x-full`;

        switch (type) {
            case 'success':
                notification.classList.add('bg-green-500', 'text-white');
                break;
            case 'error':
                notification.classList.add('bg-red-500', 'text-white');
                break;
            case 'info':
            default:
                notification.classList.add('bg-blue-500', 'text-white');
                break;
        }

        notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white/80 hover:text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>`;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }
});

