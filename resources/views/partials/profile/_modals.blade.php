<!-- Modal para crear carpeta principal -->
<div class="modal" id="create-folder-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="modal-icon">📁</span>
                Crear Carpeta Principal
            </h3>
        </div>
        <div class="modal-body">
            <p class="modal-description">
                Esta carpeta será el directorio principal donde se almacenarán todas tus grabaciones y transcripciones.
            </p>
            <div class="form-group">
                <label class="form-label">Nombre de la carpeta</label>
                <input type="text" class="modal-input" id="folder-name-input" placeholder="Ej: Juntify-Reuniones-2025">
                <div class="input-hint">Se creará en tu Google Drive y se compartirá automáticamente con el sistema.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeCreateFolderModal()">Cancelar</button>
            <button class="btn btn-primary" id="confirm-create-btn" onclick="confirmCreateFolder()">✅ Crear Carpeta</button>
        </div>
    </div>
</div>

<!-- Modal de subcarpetas eliminado: ahora la estructura es automática (Audios, Transcripciones, Audios Pospuestos) -->

<!-- Modal de carga -->
<div class="modal" id="drive-loading-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="modal-icon">⏳</span>
                Conectando con Google Drive
            </h3>
        </div>
        <div class="modal-body">
            <p class="modal-description">
                Por favor espera mientras verificamos tu conexión...
            </p>
        </div>
    </div>
</div>
