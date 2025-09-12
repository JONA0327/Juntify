@extends('layouts.app')

@section('title', 'Asistente IA - Juntify')

@section('head')
    @vite(['resources/css/ai-assistant.css', 'resources/js/ai-assistant.js'])
@endsection

@section('content')
<div class="min-h-screen bg-slate-900 text-white">
    <div class="flex h-screen">
        <!-- Sidebar - Historial de Chats -->
        <div class="w-80 bg-slate-800 border-r border-slate-700 flex flex-col">
            <!-- Search -->
            <div class="p-4 border-b border-slate-700">
                <div class="relative">
                    <input type="text"
                           placeholder="Buscar"
                           class="w-full bg-slate-700 text-white placeholder-gray-400 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Chat History -->
            <div class="flex-1 overflow-y-auto">
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-gray-400 mb-4">Historial de Chats</h3>
                    <div class="space-y-2" id="chat-history">
                        <!-- Chat history items will be populated by JavaScript -->
                        <div class="p-3 rounded-lg bg-slate-700 hover:bg-slate-600 cursor-pointer transition-colors">
                            <div class="text-sm font-medium text-gray-300">Nombre del chat</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col">
            <!-- Chat Header -->
            <div class="bg-slate-800 px-6 py-4 border-b border-slate-700 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-sm font-bold">A</span>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">Asistente IA - Juntify</h2>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                        <span class="text-xs text-gray-400">En línea</span>
                    </div>
                </div>
                <button class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                    </svg>
                </button>
            </div>

            <!-- Top Action Bar -->
            <div class="bg-slate-800 px-6 py-3 border-b border-slate-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <input type="text"
                               placeholder="Buscar"
                               class="bg-slate-700 text-white placeholder-gray-400 rounded-lg px-4 py-2 w-96 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center space-x-3">
                        <button class="bg-slate-700 text-white px-4 py-2 rounded-lg hover:bg-slate-600 transition-colors flex items-center space-x-2" onclick="openContainerSelector()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Contenedores</span>
                        </button>
                        <button class="bg-slate-700 text-white px-4 py-2 rounded-lg hover:bg-slate-600 transition-colors flex items-center space-x-2" onclick="openContactChatSelector()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <span>Conversaciones</span>
                        </button>
                        <button class="bg-slate-700 text-white px-4 py-2 rounded-lg hover:bg-slate-600 transition-colors flex items-center space-x-2" onclick="openDocumentUploader()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Documentos</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-messages">
                <!-- Welcome Message -->
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-bold">A</span>
                    </div>
                    <div class="flex-1">
                        <div class="bg-slate-700 rounded-lg p-4 max-w-2xl">
                            <p class="text-white">Hola 👋 ¿Cómo puedo ayudarte hoy?</p>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">05:50</div>
                    </div>
                </div>
            </div>

            <!-- Message Input -->
            <div class="bg-slate-800 border-t border-slate-700 p-6">
                <div class="flex items-center space-x-4">
                    <div class="flex-1 bg-slate-700 rounded-lg flex items-center">
                        <input type="text"
                               id="message-input"
                               placeholder="Escribe tu mensaje aquí..."
                               class="flex-1 bg-transparent text-white placeholder-gray-400 px-4 py-3 focus:outline-none"
                               onkeypress="handleKeyPress(event)">
                        <div class="flex items-center space-x-2 pr-4">
                            <button class="text-gray-400 hover:text-white p-1" title="Adjuntar archivo">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </button>
                            <button class="text-gray-400 hover:text-white p-1" title="Insertar código">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                            </button>
                            <button class="text-gray-400 hover:text-white p-1" title="Insertar tabla">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0V4a1 1 0 011-1h16a1 1 0 011 1v16a1 1 0 01-1 1H4a1 1 0 01-1-1z"></path>
                                </svg>
                            </button>
                            <button class="text-gray-400 hover:text-white p-1" title="Grabación de voz">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                                </svg>
                            </button>
                            <button class="text-gray-400 hover:text-white p-1" title="Compartir">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button id="send-button"
                            onclick="sendMessage()"
                            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2">
                        <span>Hacer</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Sidebar - Meeting Details -->
        <div class="w-96 bg-slate-800 border-l border-slate-700 flex flex-col" id="meeting-details-panel">
            <div class="p-6 border-b border-slate-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Detalles de la Reunión</h3>
                <button onclick="toggleMeetingPanel()" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="p-6 border-b border-slate-700">
                <div class="flex space-x-2">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">Resumen</button>
                    <button class="text-gray-400 hover:text-white px-4 py-2 rounded-lg text-sm font-medium">Puntos Clave</button>
                    <button class="text-gray-400 hover:text-white px-4 py-2 rounded-lg text-sm font-medium">Tareas</button>
                    <button class="text-gray-400 hover:text-white px-4 py-2 rounded-lg text-sm font-medium">T</button>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="text-center text-gray-400 mt-8">
                    <div class="w-16 h-16 bg-slate-700 rounded-lg mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">Detalles de la Reunión</h4>
                    <p class="text-sm">Selecciona un contenido para ver los detalles.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales -->
@include('ai-assistant.modals.container-selector')
@include('ai-assistant.modals.contact-chat-selector')
@include('ai-assistant.modals.document-uploader')

@endsection

@section('scripts')
<script>
// Variables globales
let currentChatId = null;
let isTyping = false;

// Función para manejar teclas presionadas
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Función para enviar mensaje
function sendMessage() {
    const input = document.getElementById('message-input');
    const message = input.value.trim();

    if (!message) return;

    // Agregar mensaje del usuario al chat
    addMessageToChat('user', message);

    // Limpiar input
    input.value = '';

    // Simular respuesta del asistente (aquí iría la lógica real)
    setTimeout(() => {
        addMessageToChat('assistant', 'Esta es una respuesta simulada del asistente IA.');
    }, 1000);
}

// Función para agregar mensaje al chat
function addMessageToChat(sender, message) {
    const chatMessages = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start space-x-3 message-item';

    const time = new Date().toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
    });

    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div class="flex-1 flex justify-end">
                <div class="bg-blue-600 rounded-lg p-4 max-w-2xl">
                    <p class="text-white">${message}</p>
                </div>
            </div>
            <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold">U</span>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold">A</span>
            </div>
            <div class="flex-1">
                <div class="bg-slate-700 rounded-lg p-4 max-w-2xl">
                    <p class="text-white">${message}</p>
                </div>
                <div class="text-xs text-gray-400 mt-1">${time}</div>
            </div>
        `;
    }

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Función para toggle del panel de detalles
function toggleMeetingPanel() {
    const panel = document.getElementById('meeting-details-panel');
    panel.classList.toggle('open');
}

// Funciones para abrir modales (estas se implementarán según sea necesario)
function openContainerSelector() {
    console.log('Abrir selector de contenedores');
}

function openContactChatSelector() {
    console.log('Abrir selector de conversaciones');
}

function openDocumentUploader() {
    console.log('Abrir subidor de documentos');
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    console.log('Asistente IA cargado');
});
</script>
@endsection
                </div>

                <div class="chat-input-container">
                    <textarea
                        id="messageInput"
                        class="message-input"
                        placeholder="Escribe tu mensaje aquí..."
                        rows="1"
                        onkeydown="handleKeyDown(event)"
                        oninput="adjustTextareaHeight(this)"
                    ></textarea>

                    <div class="input-actions">
                        <button class="attachment-btn" onclick="toggleAttachmentMenu()">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </button>

                        <button id="sendButton" class="send-btn btn btn-primary" onclick="sendMessage()">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel derecho - Detalles -->
        <div class="details-panel">
            <div class="details-header">
                <h3 class="details-title">
                    <svg class="details-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Detalles del Contexto
                </h3>
            </div>

            <div class="details-content" id="detailsContent">
                <div class="empty-details">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p>Selecciona un contexto para ver los detalles</p>
                </div>
            </div>

            <!-- Pestañas de detalles -->
            <div class="details-tabs" id="detailsTabs" style="display: none;">
                <button class="tab-btn active" data-tab="summary">Resumen</button>
                <button class="tab-btn" data-tab="keypoints">Puntos Clave</button>
                <button class="tab-btn" data-tab="tasks">Tareas</button>
                <button class="tab-btn" data-tab="transcription">Transcripción</button>
            </div>

            <div class="tab-content" id="tabContent" style="display: none;">
                <div class="tab-pane active" data-tab="summary">
                    <div id="summaryContent">
                        <!-- Contenido del resumen -->
                    </div>
                </div>
                <div class="tab-pane" data-tab="keypoints">
                    <div id="keypointsContent">
                        <!-- Puntos clave -->
                    </div>
                </div>
                <div class="tab-pane" data-tab="tasks">
                    <div id="tasksContent">
                        <!-- Tareas -->
                    </div>
                </div>
                <div class="tab-pane" data-tab="transcription">
                    <div id="transcriptionContent">
                        <!-- Transcripción -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales -->
@include('ai-assistant.modals.container-selector')
@include('ai-assistant.modals.contact-chat-selector')
@include('ai-assistant.modals.document-uploader')
@endsection

@section('scripts')
<script>
// Variables globales
let currentChatId = null;
let isTyping = false;

// Función para manejar teclas presionadas
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Función para enviar mensaje
function sendMessage() {
    const input = document.getElementById('message-input');
    const message = input.value.trim();

    if (!message) return;

    // Agregar mensaje del usuario al chat
    addMessageToChat('user', message);

    // Limpiar input
    input.value = '';

    // Simular respuesta del asistente (aquí iría la lógica real)
    setTimeout(() => {
        addMessageToChat('assistant', 'Esta es una respuesta simulada del asistente IA.');
    }, 1000);
}

// Función para agregar mensaje al chat
function addMessageToChat(sender, message) {
    const chatMessages = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-start space-x-3 message-item';

    const time = new Date().toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
    });

    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div class="flex-1 flex justify-end">
                <div class="bg-blue-600 rounded-lg p-4 max-w-2xl">
                    <p class="text-white">${message}</p>
                </div>
            </div>
            <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold">U</span>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold">A</span>
            </div>
            <div class="flex-1">
                <div class="bg-slate-700 rounded-lg p-4 max-w-2xl">
                    <p class="text-white">${message}</p>
                </div>
                <div class="text-xs text-gray-400 mt-1">${time}</div>
            </div>
        `;
    }

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Función para toggle del panel de detalles
function toggleMeetingPanel() {
    const panel = document.getElementById('meeting-details-panel');
    panel.classList.toggle('open');
}

// Funciones para abrir modales (estas se implementarán según sea necesario)
function openContainerSelector() {
    console.log('Abrir selector de contenedores');
}

function openContactChatSelector() {
    console.log('Abrir selector de conversaciones');
}

function openDocumentUploader() {
    console.log('Abrir subidor de documentos');
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    console.log('Asistente IA cargado');
});
</script>
@endsection
