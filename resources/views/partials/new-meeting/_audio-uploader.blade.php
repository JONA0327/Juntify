<!-- Interfaz de Subir Audio -->
<div class="upload-interface" id="audio-uploader" style="display: none;">
    <div class="upload-area" id="upload-area">
        <x-icon name="folder" class="upload-icon" />
        <h3 class="upload-title">Arrastra y suelta tu archivo de audio aquí</h3>
        <p class="upload-subtitle">O haz clic para seleccionar un archivo de audio</p>
        <p class="upload-formats">Formatos soportados: MP3, MP4/M4A</p>
        <p class="upload-legend">Otros formatos (por ejemplo, WebM, AAC, WAV) deben convertirse a MP3 o MP4/M4A antes de subirlos.</p>
        <input type="file" id="audio-file-input" accept=".mp3,.m4a,.mp4" style="display: none;">
        <button class="btn btn-primary upload-btn">
            <x-icon name="folder" class="btn-icon" />
            Seleccionar archivo
        </button>
    </div>

    <!-- Archivo seleccionado -->
    <div class="selected-file" id="selected-file" style="display: none;">
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
        <div class="upload-progress" id="upload-progress" style="display: none;">
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
