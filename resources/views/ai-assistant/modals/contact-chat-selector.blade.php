<!-- Modal para seleccionar conversaciones con contactos -->
<div id="contactChatSelectorModal" class="modal">
    <div class="modal-backdrop" onclick="closeContactChatSelector()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <svg class="modal-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8z"></path>
                </svg>
                Seleccionar Conversaciones
            </h3>
            <button class="modal-close-btn" onclick="closeContactChatSelector()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="modal-body">
            <div class="chat-search">
                <div class="search-input-container">
                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input
                        type="text"
                        id="chatSearchInput"
                        class="search-input"
                        placeholder="Buscar conversaciones..."
                        oninput="filterContactChats(this.value)"
                    >
                </div>
            </div>

            <div class="contact-chats-list" id="contactChatsList">
                <!-- Las conversaciones se cargarán dinámicamente -->
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Cargando conversaciones...</p>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeContactChatSelector()">
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="loadAllContactChats()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.418 8-9.899 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.418-8 9.899-8s9.899 3.582 9.899 8z"></path>
                </svg>
                Todas las Conversaciones
            </button>
        </div>
    </div>
</div>
