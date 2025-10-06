<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-id" content="{{ auth()->id() }}">
    @endauth
    <title>Chat - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ])

    <style>
        #active-chat-messages {
            transition: transform 0.2s ease-out;
        }
        .message-fade-in {
            animation: fadeInMessage 0.3s ease-out;
        }
        @keyframes fadeInMessage {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .scrollbar-hide::-webkit-scrollbar {
            width: 0;
            height: 0;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        #toggle-sidebar svg {
            transition: transform 0.2s ease-out;
        }
        #chat-layout.sidebar-hidden #conversation-sidebar {
            display: none;
        }
        #chat-layout.sidebar-hidden #toggle-sidebar svg {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <!-- Contenedor Centrado -->
            <div class="container mx-auto px-4 py-6 h-screen flex flex-col min-h-0">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div id="chat-messages-header" class="space-y-1">
                    <h1 class="text-2xl font-bold text-slate-200">Mensajes</h1>
                    <p class="text-slate-400">Conversaciones con tus contactos</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="chat-main-tab px-4 py-2 rounded-lg border text-sm font-medium transition-all bg-slate-800/50 text-slate-200 border-slate-700/60 hover:bg-slate-700/50" data-target="chat-messages-section">Mensajes</button>
                    <button type="button" class="chat-main-tab px-4 py-2 rounded-lg border text-sm font-medium transition-all bg-slate-800/50 text-slate-200 border-slate-700/60 hover:bg-slate-700/50" data-target="chat-contacts-section">Contactos</button>
                </div>
            </div>
        </div>

        <div id="chat-messages-section" class="flex-1 flex flex-col min-h-0">
            <!-- Chat Container -->
            <div id="chat-layout" class="flex-1 bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg overflow-hidden flex min-h-0">
                <!-- Lista de Conversaciones -->
                <div class="w-1/3 border-r border-slate-700/50 flex flex-col relative min-h-0" id="conversation-sidebar">
                    <div class="p-4 border-b border-slate-700/50">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text"
                                   id="chat-search"
                                   placeholder="Buscar conversaciones..."
                                   class="w-full pl-10 pr-4 py-2 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 focus:border-yellow-500/50 transition-all text-sm">
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto scrollbar-hide" id="conversations-list">
                        <div class="p-8 text-center">
                            <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                            <p class="text-slate-400">Cargando conversaciones...</p>
                        </div>
                    </div>
                </div>

                <button id="toggle-sidebar" aria-expanded="true" aria-controls="conversation-sidebar" aria-label="Ocultar conversaciones" class="bg-slate-800/60 hover:bg-slate-700/60 text-slate-300 px-2 transition-all focus:outline-none focus:ring-2 focus:ring-yellow-500/50 flex items-center justify-center h-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Área de Chat -->
                <div class="flex-1 flex flex-col min-h-0" id="chat-area">
                    <!-- Estado inicial cuando no hay chat seleccionado -->
                    <div id="no-chat-selected" class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="w-20 h-20 bg-slate-700/50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-300 mb-2">Selecciona una conversación</h3>
                        <p class="text-slate-500">Elige una conversación para comenzar a chatear</p>
                    </div>
                </div>

                <!-- Chat activo (inicialmente oculto) -->
                <div id="active-chat" class="hidden flex-1 flex flex-col min-h-0">
                    <!-- Header del chat activo -->
                    <div class="p-4 border-b border-slate-700/50 bg-slate-800/50">
                        <div class="flex items-center gap-3">
                            <div id="active-chat-avatar" class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold">
                                ?
                            </div>
                            <div>
                                <h3 id="active-chat-name" class="text-lg font-semibold text-slate-200">Nombre del contacto</h3>
                                <div class="flex items-center gap-2">
                                    <div id="active-chat-status" class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                    <span id="active-chat-status-text" class="text-sm text-slate-400">En línea</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mensajes del chat -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-hide" id="active-chat-messages">
                        <div class="text-center py-8">
                            <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                            <p class="text-slate-400">Cargando mensajes...</p>
                        </div>
                    </div>

                    <!-- Indicador de escritura -->
                    <div id="active-chat-typing" class="hidden px-4 py-2 border-t border-slate-700/50">
                        <div class="flex items-center gap-2 text-slate-400 text-sm">
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                            </div>
                            <span>Escribiendo...</span>
                        </div>
                    </div>

                    <!-- Input de mensaje -->
                    <div class="border-t border-slate-700/50 p-4 bg-slate-800/50">
                        <div id="active-chat-form" class="flex items-center gap-3">
                            <div class="flex-1 relative">
                                <input type="text"
                                       id="active-chat-input"
                                       placeholder="Escribe un mensaje..."
                                       class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 focus:border-yellow-500/50 transition-all pr-12">
                                <button type="button"
                                        id="active-chat-file-btn"
                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 p-2 text-slate-400 hover:text-slate-300 hover:bg-slate-600/50 rounded-lg transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                    </svg>
                                </button>
                            </div>
                            <button type="button"
                                    id="send-message-btn"
                                    class="px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-lg hover:from-yellow-500 hover:to-yellow-600 transition-all shadow-lg transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>
                        <input type="file" id="active-chat-file-input" class="hidden" accept="*/*">
                    </div>
                </div>
            </div>
        </div>

        <div id="chat-contacts-section" class="hidden flex-1 flex-col overflow-y-auto">
            @include('contacts.index')
        </div>
            </div>
        </main>
    </div>

    <script src="{{ asset('js/chat.js') }}"></script>
</body>
</html>
