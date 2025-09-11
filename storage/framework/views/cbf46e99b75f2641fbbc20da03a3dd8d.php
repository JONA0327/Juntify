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

<!-- Modal para crear subcarpeta -->
<div class="modal" id="create-subfolder-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="modal-icon">📂</span>
                Crear Subcarpeta
            </h3>
        </div>
        <div class="modal-body">
            <p class="modal-description">
                Las subcarpetas te ayudan a organizar tus reuniones por proyecto, fecha o tema.
            </p>
            <div class="form-group">
                <label class="form-label">Nombre de la subcarpeta</label>
                <input type="text" class="modal-input" id="subfolder-name-input" placeholder="Ej: Reuniones-Enero-2025">
                <div class="input-hint">Se creará dentro de tu carpeta principal de grabaciones.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeCreateSubfolderModal()">Cancelar</button>
            <button class="btn btn-primary" id="confirm-create-sub-btn" onclick="confirmCreateSubfolder()">✅ Crear Subcarpeta</button>
        </div>
    </div>
</div>

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
<?php /**PATH C:\laragon\www\Juntify\resources\views/partials/profile/_modals.blade.php ENDPATH**/ ?>