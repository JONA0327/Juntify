@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header del chat -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('contacts.index') }}"
               class="flex items-center gap-2 px-3 py-2 text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver a contactos
            </a>
            <div class="w-px h-6 bg-slate-700"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
                    {{ substr($otherUser->full_name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-200">{{ $otherUser->full_name }}</h1>
                    <p class="text-slate-400 text-sm">{{ $otherUser->email }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor del chat -->
    <div class="bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg overflow-hidden flex flex-col" style="height: 70vh;">
        <!-- Área de mensajes -->
        <div id="messages-container" class="flex-1 p-4 overflow-y-auto space-y-3">
            <div id="messages-list">
                <div class="text-center py-8">
                    <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                    <p class="text-slate-400">Cargando mensajes...</p>
                </div>
            </div>
        </div>

        <!-- Área de entrada de mensaje -->
        <div class="border-t border-slate-700/50 p-4 bg-slate-800/50">
            <form id="message-form" class="flex items-center gap-3">
                <div class="flex-1 relative">
                    <input type="text"
                           id="message-input"
                           placeholder="Escribe tu mensaje..."
                           class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all pr-12">
                    <button type="button"
                            id="emoji-btn"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors">
                        😊
                    </button>
                </div>

                <input type="file"
                       id="file-input"
                       accept="image/*,video/*,.pdf,.doc,.docx"
                       class="hidden">
                <button type="button"
                        id="file-btn"
                        class="p-3 text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                </button>

                <button type="button"
                        id="voice-btn"
                        class="p-3 text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                    </svg>
                </button>

                <button type="submit"
                        id="send-btn"
                        class="px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales del chat
const chatId = {{ $chat->id }};
const currentUserId = {{ auth()->id() }};
let messages = [];
let isLoading = false;

// Cargar mensajes al iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadMessages();
    setupEventListeners();
});

// Configurar event listeners
function setupEventListeners() {
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    const fileBtn = document.getElementById('file-btn');
    const fileInput = document.getElementById('file-input');
    const voiceBtn = document.getElementById('voice-btn');

    // Enviar mensaje con Enter
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Enviar mensaje con botón
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage();
    });

    // Seleccionar archivo
    fileBtn.addEventListener('click', function() {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            sendFile(this.files[0]);
        }
    });

    // Botón de voz (funcionalidad futura)
    voiceBtn.addEventListener('click', function() {
        showNotification('Funcionalidad de voz próximamente', 'info');
    });
}

// Cargar mensajes del chat
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

// Enviar mensaje
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
            body: JSON.stringify({
                body: messageText
            })
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

// Enviar archivo
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

        // Limpiar input
        document.getElementById('file-input').value = '';

    } catch (error) {
        console.error('Error sending file:', error);
        showNotification('Error al enviar archivo', 'error');
    }
}

// Actualizar visualización de mensajes
function updateMessagesDisplay() {
    const messagesList = document.getElementById('messages-list');

    if (isLoading) {
        messagesList.innerHTML = `
            <div class="text-center py-8">
                <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-slate-400">Cargando mensajes...</p>
            </div>
        `;
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
            </div>
        `;
        return;
    }

    messagesList.innerHTML = messages.map(message => {
        const isOwn = message.sender_id === currentUserId;
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
            </div>
        `;
    }).join('');
}

// Hacer scroll hacia abajo
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
}

// Escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Función de notificación (reutilizada del otro archivo)
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
        </div>
    `;

    document.body.appendChild(notification);

    // Animar entrada
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    // Remover después de 5 segundos
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}
</script>

<style>
.loading-spinner {
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

#messages-container::-webkit-scrollbar {
    width: 6px;
}

#messages-container::-webkit-scrollbar-track {
    background: rgba(30, 41, 59, 0.3);
    border-radius: 3px;
}

#messages-container::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.5);
    border-radius: 3px;
}

#messages-container::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.7);
}
</style>
@endsection
