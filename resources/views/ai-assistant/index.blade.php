<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-id" content="{{ auth()->id() }}">
    @endauth

    <title>Asistente IA - {{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css'
    ])

    <link rel="stylesheet" href="{{ asset('css/ai-assistant.css') }}?v={{ time() }}">
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">
    <div class="flex">
        @include('partials.navbar')

        <main class="w-full pl-24 pt-6" style="margin-top:80px;">
            <!-- Contenedor Centrado -->
            <div class="container mx-auto px-4 py-2 h-screen flex flex-col">
<div class="ai-assistant-container">
    <!-- Sidebar de sesiones -->
    <div class="sessions-sidebar">
        <div class="sessions-header">
            <h2>Conversaciones</h2>
            <button id="new-chat-btn" class="new-chat-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nueva conversación
            </button>
        </div>

        <div id="sessions-list" class="sessions-list">
            <!-- Las sesiones se cargarán dinámicamente aquí -->
        </div>
    </div>

    <!-- Área principal del chat -->
    <div class="chat-main">
        <!-- Header del chat -->
        <div class="chat-header">
            <div class="chat-title">
                <h3 id="current-session-title">Asistente IA</h3>
                <span id="context-indicator" class="context-indicator"></span>
            </div>

            <div class="chat-actions">
                <button id="context-selector-btn" class="context-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Seleccionar contexto
                </button>
            </div>
        </div>

        <!-- Documentos en contexto -->
        <div id="context-docs-bar" class="context-docs-bar" style="display:none;">
            <div class="context-docs-title">Documentos en contexto:</div>
            <div id="context-docs-chips" class="context-docs-chips"></div>
        </div>

        <!-- Área de mensajes -->
        <div id="messages-container" class="messages-container">
            <div class="welcome-message">
                <div class="ai-avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="9" cy="12" r="1" fill="currentColor"/>
                        <circle cx="15" cy="12" r="1" fill="currentColor"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
                    </svg>
                </div>
                <div class="welcome-text">
                    <h4>¡Hola! Soy tu Asistente IA</h4>
                    <p>Puedo ayudarte con tareas, reuniones, documentos y mucho más. ¿En qué puedo asistirte hoy?</p>
                </div>
            </div>
        </div>

        <!-- Área de entrada de texto -->
        <div class="input-area">
            <form id="chat-form" class="chat-form">
                <div class="input-container">
                    <textarea
                        id="message-input"
                        placeholder="Escribe tu mensaje aquí..."
                        rows="1"
                        maxlength="4000"
                    ></textarea>

                    <div class="input-actions">
                        <button type="button" id="attach-file-btn" class="attach-btn" title="Adjuntar archivo">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                            </svg>
                        </button>

                        <button type="submit" id="send-btn" class="send-btn" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="character-count">
                    <span id="char-count">0</span>/4000
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Input de archivo oculto -->
<input type="file" id="file-input" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx,.pptx" style="display: none;">

<!-- Modal de selección de contexto -->
@include('ai-assistant.modals.container-selector')

<!-- Loading overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <div class="spinner"></div>
        <p>Procesando...</p>
    </div>
</div>

</div> <!-- cierre ai-assistant-container -->
            </div> <!-- cierre container mx-auto -->
        </main>
    </div>

    <!-- Modal de Upgrade para límites -->
    <div class="modal" id="postpone-locked-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="modal-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Límite alcanzado
                </h2>
            </div>
            <div class="modal-body">
                <p class="modal-description">Has alcanzado el límite de tu plan actual.</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeUpgradeModal()">Cerrar</button>
                <button class="btn btn-primary" onclick="goToPlans()">Actualizar Plan</button>
            </div>
        </div>
    </div>

    @include('partials.global-vars')

    <script>
        // Define fallback handlers only if the global helpers are not available
        if (typeof window.closeUpgradeModal === 'undefined') {
            window.closeUpgradeModal = function() {
                const modal = document.getElementById('postpone-locked-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            };
        }

        if (typeof window.goToPlans === 'undefined') {
            window.goToPlans = function() {
                window.closeUpgradeModal();
                sessionStorage.setItem('navigateToPlans', 'true');
                window.location.href = '/profile';
            };
        }
    </script>

    <script src="{{ asset('js/ai-assistant.js') }}?v={{ time() }}"></script>
</body>
</html>
