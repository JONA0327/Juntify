<!-- Modal para seleccionar contexto -->
<div id="contextSelectorModal" class="modal">
    <div c        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeContextSelector()">
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="selectAllMeetings()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 01 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 01 9.288 0M15 7a3 3 0 11-6 0 3 3 0 01 6 0zm6 3a2 2 0 11-4 0 2 2 0 01 4 0zM7 10a2 2 0 11-4 0 2 2 0 01 4 0z"></path>
                </svg>
                Todas las Reuniones
            </button>
        </div>               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 01 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 01 9.288 0M15 7a3 3 0 11-6 0 3 3 0 01 6 0zm6 3a2 2 0 11-4 0 2 2 0 01 4 0zM7 10a2 2 0 11-4 0 2 2 0 01 4 0z"></path>                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 01 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 01 9.288 0M15 7a3 3 0 11-6 0 3 3 0 01 6 0zm6 3a2 2 0 11-4 0 2 2 0 01 4 0zM7 10a2 2 0 11-4 0 2 2 0 01 4 0z"></path>                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 9 19.288 0M15 7a3 3 0 11-6 0 3 3 0 6 16 0zm6 3a2 2 0 11-4 0 2 2 0 4 14 0zM7 10a2 2 0 11-4 0 2 2 0 4 14 0z"></path>odal-backdrop" onclick="closeContextSelector()"></div>
    <div class="modal-content context-selector-modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <svg class="modal-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Seleccionar Contexto
            </h3>
            <button class="modal-close-btn" onclick="closeContextSelector()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="modal-body context-selector-body">
            <!-- Navegación lateral -->
            <div class="context-navigation">
                <h4>Tipo de Contexto</h4>
                <div class="context-nav-items">
                    <button class="context-nav-item active" onclick="switchContextType('containers')" data-type="containers">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        Contenedores
                    </button>
                    <button class="context-nav-item" onclick="switchContextType('meetings')" data-type="meetings">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Reuniones
                    </button>
                </div>
            </div>

            <!-- Área central de contenido -->
            <div class="context-content">
                <div class="context-search">
                    <div class="search-input-container">
                        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input
                            type="text"
                            id="contextSearchInput"
                            class="search-input"
                            placeholder="Buscar..."
                            oninput="filterContextItems(this.value)"
                        >
                    </div>
                </div>

                <!-- Vista de contenedores -->
                <div id="containersView" class="context-view active">
                    <div class="context-grid" id="containersGrid">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            <p>Cargando contenedores...</p>
                        </div>
                    </div>
                </div>

                <!-- Vista de reuniones -->
                <div id="meetingsView" class="context-view">
                    <div class="context-grid" id="meetingsGrid">
                        <div class="loading-state">
                            <div class="spinner"></div>
                            <p>Cargando reuniones...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel lateral de contexto cargado -->
            <div class="context-panel">
                <h4>Contexto Cargado</h4>
                <div id="loadedContextItems" class="loaded-context-items">
                    <div class="empty-context">
                        <p>No hay contexto cargado</p>
                    </div>
                </div>

                <div class="context-actions">
                    <button id="loadContextBtn" class="btn btn-primary" onclick="loadSelectedContext()" disabled>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Cargar Contexto
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeContextSelector()">
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="selectAllMeetings()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 5 15.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Todas las Reuniones
        </button>
    </div>
</div>

<!-- Modal de detalles de reunión -->
<div id="meetingDetailsModal" class="modal">
    <div class="modal-backdrop" onclick="closeMeetingDetailsModal()"></div>
    <div class="modal-content meeting-details-modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <svg class="modal-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span id="meetingDetailsTitle">Detalles de la Reunión</span>
            </h3>
            <button class="modal-close-btn" onclick="closeMeetingDetailsModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="modal-body meeting-details-body">
            <!-- Pestañas de navegación -->
            <div class="details-tabs">
                <button class="tab-button active" onclick="switchTab('summary')" data-tab="summary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Resumen
                </button>
                <button class="tab-button" onclick="switchTab('keypoints')" data-tab="keypoints">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Puntos Clave
                </button>
                <button class="tab-button" onclick="switchTab('tasks')" data-tab="tasks">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    Tareas
                </button>
                <button class="tab-button" onclick="switchTab('transcription')" data-tab="transcription">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                    Transcripción
                </button>
            </div>

            <!-- Contenido de las pestañas -->
            <div class="tab-content">
                <div id="summaryTab" class="tab-pane active">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Cargando resumen...</p>
                    </div>
                </div>
                <div id="keypointsTab" class="tab-pane">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Cargando puntos clave...</p>
                    </div>
                </div>
                <div id="tasksTab" class="tab-pane">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Cargando tareas...</p>
                    </div>
                </div>
                <div id="transcriptionTab" class="tab-pane">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Cargando transcripción...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeMeetingDetailsModal()">
                Cerrar
            </button>
        </div>
    </div>
</div>
