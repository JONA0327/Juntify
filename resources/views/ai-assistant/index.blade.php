@extends('layouts.app')

@section('head')
    @vite(['resources/css/ai-assistant.css', 'resources/js/ai-assistant.js'])
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="particles" id="particles"></div>

    <div class="ai-assistant-container">
        <!-- Sidebar izquierdo - Historial de chats -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h3 class="sidebar-title">
                    <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                    </svg>
                    Historial de Chats
                </h3>
                <button class="new-chat-btn btn btn-primary" onclick="createNewChat()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Nuevo Chat
                </button>
            </div>

            <div class="chat-sessions-list" id="chatSessionsList">
                <!-- Las sesiones se cargarán dinámicamente -->
            </div>
        </div>

        <!-- Área central - Chat -->
        <div class="chat-area">
            <div class="chat-header">
                <div class="chat-header-info">
                    <h2 id="chatTitle">Asistente IA - Juntify</h2>
                    <div class="context-indicator" id="contextIndicator">
                        <span class="context-type">General</span>
                    </div>
                </div>

                <div class="chat-controls">
                    <!-- Selector de contenedor -->
                    <div class="context-selector">
                        <button class="context-btn container-btn" onclick="openContainerSelector()">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Contenedores
                        </button>
                    </div>

                    <!-- Selector de conversaciones con contactos -->
                    <button class="context-btn contact-chat-btn" onclick="openContactChatSelector()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8z"></path>
                        </svg>
                        Conversaciones
                    </button>

                    <!-- Subir documentos -->
                    <button class="context-btn document-btn" onclick="openDocumentUploader()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Documentos
                    </button>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
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
            </div>

            <div class="chat-input-area">
                <div class="input-attachments" id="inputAttachments">
                    <!-- Archivos adjuntos aparecerán aquí -->
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
@endsection

@section('modals')
@include('ai-assistant.modals.container-selector')
@include('ai-assistant.modals.contact-chat-selector')
@include('ai-assistant.modals.document-uploader')
@endsection
