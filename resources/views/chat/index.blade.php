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
        'resources/css/audio-processing.css'
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
        /* Invisible scrollbars but scrollable */
        .hide-scrollbar::-webkit-scrollbar { width: 0px; height: 0px; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <!-- Contenedor Centrado -->
            <div class="container mx-auto px-4 py-6 h-screen flex flex-col">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-200">Chat</h1>
                    <p class="text-slate-400">Conversaciones y gestión de contactos</p>
                </div>
                <div class="flex gap-2 bg-slate-800/40 p-1 rounded-lg border border-slate-700/50">
                    <button id="tab-conversaciones" class="tab-btn px-4 py-2 text-sm font-medium rounded-md bg-slate-700 text-slate-200">Conversaciones</button>
                    <button id="tab-contactos" class="tab-btn px-4 py-2 text-sm font-medium rounded-md hover:bg-slate-700 text-slate-300">Contactos</button>
                </div>
            </div>
        </div>

        <!-- Chat Container -->
    <div id="panel-conversaciones" class="flex-1 bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg overflow-hidden flex">
            <!-- Lista de Conversaciones -->
            <div class="w-1/3 border-r border-slate-700/50 flex flex-col overflow-hidden">
                <!-- Header de conversaciones -->
                <div class="p-4 border-b border-slate-700/50">
                    <h2 class="text-lg font-semibold text-slate-200">Conversaciones</h2>
                </div>

                <!-- Buscador de conversaciones -->
                <div class="p-4 border-b border-slate-700/50">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text"
                               id="chat-search"
                               placeholder="Buscar conversaciones..."
                               class="w-full pl-10 pr-4 py-2 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 focus:border-yellow-500/50 transition-all text-sm">
                    </div>
                </div>

                <!-- Lista de conversaciones -->
                <div class="flex-1 overflow-y-auto pr-1" id="conversations-list">
                    <div class="p-8 text-center">
                        <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                        <p class="text-slate-400">Cargando conversaciones...</p>
                    </div>
                </div>
                <!-- Panel inferior (opcional futuro) eliminado: contactos e invitaciones ahora en página Contactos -->
            </div>

            <!-- Área de Chat -->
            <div class="flex-1 flex flex-col" id="chat-area">
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
                <div id="active-chat" class="hidden flex-1 flex flex-col">
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
                    <div class="flex-1 overflow-y-auto p-4 space-y-3" id="active-chat-messages">
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
                                       class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 focus:border-yellow-500/50 transition-all pr-24">
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                    <button type="button" id="active-chat-emoji-btn" title="Emojis" class="p-2 text-slate-400 hover:text-slate-300 hover:bg-slate-600/50 rounded-lg transition-all">😊</button>
                                    <button type="button"
                                            id="active-chat-voice-btn"
                                            title="Grabar audio (Alt+V)"
                                            class="p-2 text-slate-400 hover:text-slate-300 hover:bg-slate-600/50 rounded-lg transition-all">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14a3 3 0 003-3V7a3 3 0 10-6 0v4a3 3 0 003 3zm0 0v4m-4 0h8" />
                                        </svg>
                                    </button>
                                    <span id="active-chat-voice-timer" class="text-[11px] text-slate-400 hidden min-w-[42px] text-right">0:00</span>
                                    <button type="button"
                                            id="active-chat-file-btn"
                                            title="Adjuntar archivo"
                                            class="p-2 text-slate-400 hover:text-slate-300 hover:bg-slate-600/50 rounded-lg transition-all">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                    </button>
                                </div>
                                <!-- Emoji panel -->
                                <div id="emoji-panel" class="hidden absolute bottom-full right-0 mb-2 bg-slate-900/95 border border-slate-700/60 rounded-lg p-2 grid grid-cols-8 gap-1 text-xl z-40">
                                    <button class="emoji">😀</button><button class="emoji">😁</button><button class="emoji">😂</button><button class="emoji">🤣</button><button class="emoji">😊</button><button class="emoji">😍</button><button class="emoji">😘</button><button class="emoji">😎</button>
                                    <button class="emoji">👍</button><button class="emoji">🙏</button><button class="emoji">👏</button><button class="emoji">🔥</button><button class="emoji">🎉</button><button class="emoji">💪</button><button class="emoji">💡</button><button class="emoji">✅</button>
                                </div>
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
                        <p class="mt-2 text-[10px] text-slate-500">Tip: presiona Alt+V para grabar/terminar audio de voz.</p>
                        <div id="upload-progress" class="hidden mt-2">
                            <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1">
                                <span id="upload-progress-name" class="truncate">Subiendo archivo…</span>
                                <span id="upload-progress-percent">0%</span>
                            </div>
                            <div class="w-full h-2 bg-slate-700/50 rounded">
                                <div id="upload-progress-bar" class="h-2 bg-yellow-500 rounded transition-[width] duration-150 ease-out" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Panel Contactos (oculto inicialmente) -->
        <div id="panel-contactos" class="hidden flex-1 bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg overflow-hidden p-6">
            <div class="w-full overflow-y-auto pr-4">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-slate-200">Mis contactos</h2>
                    <button id="add-contact-btn" class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-lg hover:from-yellow-500 hover:to-yellow-600 transition-all shadow-lg transform hover:scale-105 text-sm">Añadir</button>
                </div>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3" id="contacts-grid">
                    <div class="col-span-full text-center py-8" id="contacts-loading">
                        <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                        <p class="text-slate-400">Cargando contactos...</p>
                    </div>
                </div>

                <div class="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-6" id="contact-requests-block">
                    <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700/50">
                        <h3 class="text-sm font-semibold mb-3 text-slate-300">Solicitudes recibidas</h3>
                        <div id="received-requests-list" class="space-y-3 text-sm text-slate-400">Cargando...</div>
                    </div>
                    <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700/50">
                        <h3 class="text-sm font-semibold mb-3 text-slate-300">Solicitudes enviadas</h3>
                        <div id="sent-requests-list" class="space-y-3 text-sm text-slate-400">Cargando...</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Añadir Contacto -->
        <div id="add-contact-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-slate-900/95 border border-slate-700/50 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-slate-200">Añadir contacto</h3>
                    <button id="close-modal-btn" class="text-slate-400 hover:text-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form id="add-contact-form" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Buscar usuario</label>
                        <input id="user-search-input" type="text" placeholder="Correo o nombre..." class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-slate-200 text-sm focus:ring-2 focus:ring-yellow-500/50 focus:outline-none" autocomplete="off" />
                        <p class="text-[10px] text-slate-500 mt-1">Mínimo 3 caracteres</p>
                    </div>
                    <div id="search-results" class="hidden">
                        <div class="bg-slate-800/50 border border-slate-700/50 rounded-lg p-3 max-h-52 overflow-y-auto text-sm" id="search-results-list"></div>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" id="cancel-btn" class="flex-1 px-4 py-2 bg-slate-700 text-slate-200 rounded-lg text-sm hover:bg-slate-600">Cancelar</button>
                        <button type="submit" id="submit-btn" disabled class="flex-1 px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed">Enviar solicitud</button>
                    </div>
                </form>
            </div>
        </div>
            </div>
        </main>
    </div>

    <script src="{{ asset('js/chat.js') }}"></script>
    @vite(['resources/js/contacts.js'])
    <script>
        (function(){
            const btnConv = document.getElementById('tab-conversaciones');
            const btnCont = document.getElementById('tab-contactos');
            const panelConv = document.getElementById('panel-conversaciones');
            const panelCont = document.getElementById('panel-contactos');
            const conversationsList = document.getElementById('conversations-list');
            function activate(tab){
                if(tab==='contactos'){
                    panelConv.classList.add('hidden');
                    panelCont.classList.remove('hidden');
                    btnCont.classList.add('bg-slate-700','text-slate-200');
                    btnConv.classList.remove('bg-slate-700','text-slate-200');
                    if(window.contactsModule) window.contactsModule.init();
                } else {
                    panelCont.classList.add('hidden');
                    panelConv.classList.remove('hidden');
                    btnConv.classList.add('bg-slate-700','text-slate-200');
                    btnCont.classList.remove('bg-slate-700','text-slate-200');
                }
            }
            btnConv?.addEventListener('click', ()=>activate('conversaciones'));
            btnCont?.addEventListener('click', ()=>activate('contactos'));

            // Abrir chat desde pestaña contactos
            window.addEventListener('chat:open-from-contacts', async (e)=>{
                const { contactId } = e.detail || {};
                if(!contactId) return;
                // Cambiar a pestaña conversaciones primero
                activate('conversaciones');
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const formData = new FormData();
                    formData.append('contact_id', contactId);
                    const resp = await fetch('/api/chats/create-or-find', {
                        method: 'POST',
                        headers: { 'X-Requested-With':'XMLHttpRequest', ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }) },
                        body: formData
                    });
                    if(resp.ok){
                        const data = await resp.json();
                        // Forzar recarga de conversaciones si la función global existe
                        if(typeof loadConversations === 'function') {
                            await loadConversations();
                            setTimeout(()=>{
                                const el = document.querySelector(`[data-chat-id="${data.chat_id}"]`);
                                if(el) el.click();
                            },300);
                        } else {
                            // fallback: navegar con query param
                            const url = new URL(window.location.href);
                            url.searchParams.set('chat_id', data.chat_id);
                            window.location.href = url.toString();
                        }
                    }
                } catch(err){ console.error('Error abriendo chat desde contactos', err); }
            });
        })();
    </script>

    <!-- Modal de confirmación reutilizable -->
    <div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-slate-900/95 border border-slate-700/50 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <h3 class="text-lg font-semibold text-slate-200 mb-2">Confirmar acción</h3>
            <p id="confirm-modal-text" class="text-slate-300 mb-5">¿Confirmas?</p>
            <div class="flex justify-end gap-2">
                <button id="confirm-cancel" class="px-4 py-2 bg-slate-700 text-slate-200 rounded-lg text-sm hover:bg-slate-600">Cancelar</button>
                <button id="confirm-accept" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-lg text-sm">Aceptar</button>
            </div>
        </div>
    </div>
</body>
</html>
