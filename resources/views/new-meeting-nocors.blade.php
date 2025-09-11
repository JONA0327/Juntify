<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva Reunión - Juntify (No CORS)</title>
    @vite(['resources/css/meetings/new-meeting-nocors.css', 'resources/js/meetings/new-meeting-nocors.js'])
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nueva Reunión</h1>
            <p>Graba y procesa reuniones sin dependencias externas</p>
        </div>

        <div class="form-container">
            <div class="form-group">
                <label for="meeting-title">Título de la reunión</label>
                <input type="text" id="meeting-title" class="form-input" placeholder="Ej: Reunión de equipo semanal">
            </div>

            <div class="form-group">
                <label for="meeting-description">Descripción (opcional)</label>
                <textarea id="meeting-description" class="form-input" rows="3" placeholder="Describe el objetivo de la reunión..."></textarea>
            </div>
        </div>

        <div class="recording-section">
            <h3>Grabación de Audio</h3>

            <div class="recording-status" id="status">
                <span class="status-indicator ready" id="status-indicator"></span>
                <span id="status-text">Listo para grabar</span>
            </div>

            <div class="timer" id="timer">00:00</div>

            <div class="recording-controls">
                <button id="start-btn" class="btn btn-primary">
                    🎤 Iniciar Grabación
                </button>
                <button id="stop-btn" class="btn btn-danger" disabled>
                    ⏹️ Detener
                </button>
                <button id="download-btn" class="btn btn-success" disabled>
                    💾 Descargar MP4
                </button>
            </div>

            <div class="audio-player" id="audio-container">
                <audio id="audio-player" controls></audio>
            </div>

            <div class="note">
                <strong>Nota:</strong> Esta versión utiliza grabación MP4 nativa sin dependencias externas.
                No requiere FFmpeg ni genera conflictos CORS. Ideal para pruebas y desarrollo.
            </div>
        </div>

        <div class="logs" id="logs"></div>
    </div>
</body>
</html>
