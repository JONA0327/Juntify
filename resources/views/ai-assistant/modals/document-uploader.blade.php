<!-- Modal para subir documentos -->
<div id="documentUploaderModal" class="modal">
    <div class="modal-backdrop" onclick="closeDocumentUploader()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <svg class="modal-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Gestionar Documentos
            </h3>
            <button class="modal-close-btn" onclick="closeDocumentUploader()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="modal-body">
            <div class="document-tabs">
                <button class="tab-btn active" onclick="switchDocumentTab('upload')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Subir Nuevo
                </button>
                <button class="tab-btn" onclick="switchDocumentTab('existing')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Documentos Existentes
                </button>
            </div>

            <!-- Pestaña de subir nuevo documento -->
            <div class="tab-content active" id="uploadTab">
                <form id="documentUploadForm" class="upload-form">
                    <div class="file-drop-zone" id="fileDropZone">
                        <div class="drop-zone-content">
                            <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="drop-text">Arrastra archivos aquí o <span class="browse-link">haz clic para seleccionar</span></p>
                            <p class="file-types">PDF, Word, Excel, PowerPoint, Imágenes, TXT (Max: 10MB)</p>
                        </div>
                        <input type="file" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt" style="display: none;">
                    </div>

                    <div class="upload-options">
                        <div class="form-group">
                            <label for="driveType" class="form-label">Tipo de Drive:</label>
                            <select id="driveType" class="form-input">
                                <option value="personal">Drive Personal</option>
                                <option value="organization">Drive Organizacional</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="driveFolderId" class="form-label">Carpeta de Destino (Opcional):</label>
                            <input type="text" id="driveFolderId" class="form-input" placeholder="ID de la carpeta de Google Drive">
                            <p class="form-help">Deja vacío para usar la carpeta por defecto</p>
                        </div>
                    </div>

                    <div class="selected-files" id="selectedFiles">
                        <!-- Archivos seleccionados aparecerán aquí -->
                    </div>
                </form>
            </div>

            <!-- Pestaña de documentos existentes -->
            <div class="tab-content" id="existingTab">
                <div class="document-search">
                    <div class="search-input-container">
                        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input
                            type="text"
                            id="documentSearchInput"
                            class="search-input"
                            placeholder="Buscar documentos..."
                            oninput="filterDocuments(this.value)"
                        >
                    </div>
                </div>

                <div class="documents-list" id="documentsList">
                    <!-- Los documentos existentes se cargarán dinámicamente -->
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Cargando documentos...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDocumentUploader()">
                Cancelar
            </button>
            <button id="uploadDocumentBtn" class="btn btn-primary" onclick="uploadDocuments()" style="display: none;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Subir Documentos
            </button>
            <button id="loadDocumentsBtn" class="btn btn-primary" onclick="loadSelectedDocuments()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Cargar Seleccionados
            </button>
        </div>
    </div>
</div>
