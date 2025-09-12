<div class="space-y-6">
    <!-- Header con título y botón de agregar -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-slate-200">Mis contactos</h1>
        <button id="add-contact-btn"
                class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-lg hover:from-yellow-500 hover:to-yellow-600 transition-all shadow-lg transform hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Añadir contacto
        </button>
    </div>

    <!-- Barra de búsqueda -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <input type="text"
               id="contact-search"
               placeholder="Buscar en mis contactos..."
               class="w-full pl-10 pr-4 py-3 bg-slate-800/50 border border-slate-700/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 focus:border-yellow-500/50 transition-all">
    </div>

    <!-- Lista de contactos -->
    <div class="bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-slate-200">Contactos</h2>
            <span id="contacts-count" class="text-sm text-slate-400">0 contactos</span>
        </div>
        <div id="contacts-list" class="space-y-3">
            <div class="loading-state flex flex-col items-center justify-center py-8">
                <div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                <p class="text-slate-400 text-center">Cargando contactos...</p>
            </div>
        </div>
    </div>

    <!-- Solicitudes de contacto -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Solicitudes recibidas -->
        <div class="bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg p-6">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8v.01M6 8v.01"></path>
                </svg>
                <h3 class="text-lg font-semibold text-slate-200">Solicitudes recibidas</h3>
            </div>
            <div id="received-requests-list" class="space-y-3">
                <p class="text-slate-400">Cargando...</p>
            </div>
        </div>

        <!-- Solicitudes enviadas -->
        <div class="bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg p-6">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
                <h3 class="text-lg font-semibold text-slate-200">Solicitudes enviadas</h3>
            </div>
            <div id="sent-requests-list" class="space-y-3">
                <p class="text-slate-400">Cargando...</p>
            </div>
        </div>
    </div>

    <!-- Usuarios de la organización -->
    <div id="organization-section" class="bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg p-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 id="organization-section-title" class="text-lg font-semibold text-slate-200">Usuarios de mi organización</h3>
        </div>
        <div id="organization-users-list" class="space-y-3">
            <p class="text-slate-400">Cargando...</p>
        </div>
    </div>
</div>

<!-- Modal para añadir contacto -->
<div id="add-contact-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-slate-900/95 backdrop-blur-md border border-slate-700/50 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-slate-200">Añadir contacto</h3>
            <button id="close-modal-btn" class="text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="add-contact-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Buscar usuario</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <input type="text"
                           id="user-search-input"
                           name="search"
                           placeholder="Buscar por correo o nombre de usuario..."
                           class="w-full pl-10 pr-4 py-3 bg-slate-800/50 border border-slate-700/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-500/50 focus:border-yellow-500/50 transition-all"
                           autocomplete="off">
                </div>
                <p class="text-xs text-slate-500 mt-2">Puedes buscar por correo electrónico o nombre de usuario</p>
            </div>

            <!-- Resultados de búsqueda -->
            <div id="search-results" class="hidden">
                <div class="bg-slate-800/30 border border-slate-700/50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-slate-300 mb-3">Resultados de búsqueda</h4>
                    <div id="search-results-list" class="space-y-2"></div>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button"
                        id="cancel-btn"
                        class="flex-1 px-4 py-2 bg-slate-700 text-slate-200 font-medium rounded-lg hover:bg-slate-600 transition-all">
                    Cancelar
                </button>
                <button type="submit"
                        id="submit-btn"
                        class="flex-1 px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-lg hover:from-yellow-500 hover:to-yellow-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    Enviar solicitud
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Chat -->
<div id="chat-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/30 backdrop-blur-sm opacity-0 transition-all duration-300 p-6">
    <div class="bg-slate-900/85 backdrop-blur-xl border border-slate-700/40 rounded-xl w-full max-w-4xl h-auto max-h-[80vh] shadow-2xl flex flex-col transform scale-95 transition-all duration-300 my-auto">
        <!-- Header del Modal -->
        <div class="flex items-center justify-between p-6 border-b border-slate-700/50">
            <div class="flex items-center gap-3">
                <div id="chat-contact-avatar" class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-bold text-lg">
                    ?
                </div>
                <div>
                    <h3 id="chat-contact-name" class="text-xl font-semibold text-slate-200">Chat</h3>
                    <div class="flex items-center gap-2">
                        <div id="chat-status-indicator" class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span id="chat-contact-email" class="text-sm text-slate-400">En línea</span>
                    </div>
                </div>
            </div>
            <button id="close-chat-modal" class="text-slate-400 hover:text-slate-200 transition-colors hover:bg-slate-800 p-2 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>        <!-- Área de mensajes -->
        <div id="chat-messages-container" class="flex-1 p-4 overflow-y-auto space-y-3 min-h-[300px] max-h-[400px]">
            <div id="chat-messages-list">
                <div class="text-center py-8">
                    <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                    <p class="text-slate-400">Cargando mensajes...</p>
                </div>
            </div>
        </div>

        <!-- Indicador de escritura -->
        <div id="typing-indicator" class="hidden px-6 py-2 border-t border-slate-700/50">
            <div class="flex items-center gap-2 text-slate-400 text-sm">
                <div class="flex space-x-1">
                    <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
                    <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                    <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
                <span>Escribiendo...</span>
            </div>
        </div>

        <!-- Área de entrada de mensaje -->
        <div class="border-t border-slate-700/50 p-4 bg-slate-800/50">
            <form id="chat-message-form" class="flex items-center gap-3">
                <div class="flex-1 relative">
                    <input type="text"
                           id="chat-message-input"
                           placeholder="Escribe tu mensaje..."
                           class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all pr-12">
                    <button type="button"
                            id="chat-emoji-btn"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors">
                        😊
                    </button>
                </div>

                <input type="file"
                       id="chat-file-input"
                       accept="image/*,video/*,.pdf,.doc,.docx"
                       class="hidden">
                <button type="button"
                        id="chat-file-btn"
                        class="p-3 text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                </button>

                <button type="submit"
                        id="chat-send-btn"
                        class="px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

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

.contact-card {
    @apply bg-slate-800/50 border border-slate-700/50 rounded-lg p-4 hover:bg-slate-800/70 transition-all;
}

.contact-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.request-card {
    @apply bg-slate-800/40 border border-slate-700/50 rounded-lg p-4;
}

.user-search-item {
    @apply flex items-center justify-between p-3 bg-slate-700/30 border border-slate-600/50 rounded-lg hover:bg-slate-700/50 transition-all cursor-pointer;
}

.user-search-item:hover {
    transform: translateY(-1px);
}
</style>
<?php /**PATH C:\Users\Admin\Desktop\Cerounocero\Juntify\resources\views/contacts/index.blade.php ENDPATH**/ ?>