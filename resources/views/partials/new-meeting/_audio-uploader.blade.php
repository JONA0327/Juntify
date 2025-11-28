<!-- Interfaz de Subir Audio -->
<!--
    Espacio para quienes prefieren cargar un archivo existente en lugar de grabar.
    Los mensajes guían sobre formatos aceptados y el JS controla los estados
    mediante las clases `is-hidden` y los IDs especificados aquí.
-->
<div class="upload-interface is-hidden" id="audio-uploader">
    <div class="upload-area" id="upload-area">
        <x-icon name="folder" class="upload-icon" />
        <h3 class="upload-title">Arrastra y suelta tu archivo de audio aquí</h3>
        <p class="upload-subtitle">O haz clic para seleccionar un archivo de audio</p>
        <p class="upload-formats">Formatos soportados: cualquier archivo de audio (OGG, WAV, MP3, M4A, FLAC, AAC, etc.). Las copias de respaldo se descargarán en formato .ogg.</p>
        <input type="file" id="audio-file-input" accept="audio/*" class="hidden-input">
        <button class="btn btn-primary upload-btn">
            <x-icon name="folder" class="btn-icon" />
            Seleccionar archivo
        </button>
    </div>

    <!-- Archivo seleccionado -->
    <div class="selected-file is-hidden" id="selected-file">
        <div class="file-info">
            <x-icon name="note" class="file-icon" />
            <div class="file-details">
                <div class="file-name" id="file-name"></div>
                <div class="file-size" id="file-size"></div>
            </div>
            <button class="remove-file-btn" onclick="removeSelectedFile()">
                <x-icon name="x" class="btn-icon" />
                <span class="sr-only">Eliminar</span>
            </button>
        </div>
        <div class="upload-progress is-hidden" id="upload-progress">
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <div class="progress-text" id="progress-text">0%</div>
        </div>
        <button class="btn btn-primary process-btn" onclick="processAudioFile()">
            <x-icon name="rocket" class="btn-icon" />
            Procesar archivo
        </button>
    </div>
</div>
