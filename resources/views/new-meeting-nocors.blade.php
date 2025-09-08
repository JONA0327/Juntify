<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva Reunión - Juntify (No CORS)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #94a3b8;
            font-size: 1.1rem;
        }

        .form-container {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(59, 130, 246, 0.2);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #e2e8f0;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(75, 85, 99, 0.5);
            border-radius: 8px;
            color: white;
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .recording-section {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(59, 130, 246, 0.2);
            text-align: center;
        }

        .recording-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .recording-status {
            margin: 1rem 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .recording-status.recording {
            color: #ef4444;
            animation: pulse 2s infinite;
        }

        .recording-status.stopped {
            color: #10b981;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .timer {
            font-size: 2rem;
            font-weight: bold;
            color: #3b82f6;
            margin: 1rem 0;
            font-family: monospace;
        }

        .audio-player {
            margin: 1rem 0;
        }

        .audio-player audio {
            width: 100%;
            max-width: 500px;
        }

        .logs {
            background: rgba(15, 23, 42, 0.9);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.875rem;
            color: #10b981;
            border: 1px solid rgba(75, 85, 99, 0.3);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-indicator.recording {
            background: #ef4444;
            animation: pulse 2s infinite;
        }

        .status-indicator.ready {
            background: #10b981;
        }

        .status-indicator.processing {
            background: #f59e0b;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(75, 85, 99, 0.3);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            width: 0%;
            transition: width 0.3s ease;
        }

        .note {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            color: #bfdbfe;
            font-size: 0.875rem;
        }
    </style>
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
            <h3 style="margin-bottom: 1.5rem; color: #e2e8f0;">Grabación de Audio</h3>

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

            <div class="audio-player" id="audio-container" style="display: none;">
                <audio id="audio-player" controls></audio>
            </div>

            <div class="note">
                <strong>Nota:</strong> Esta versión utiliza grabación MP4 nativa sin dependencias externas.
                No requiere FFmpeg ni genera conflictos CORS. Ideal para pruebas y desarrollo.
            </div>
        </div>

        <div class="logs" id="logs"></div>
    </div>

    <script>
        let mediaRecorder;
        let recordedChunks = [];
        let startTime;
        let timerInterval;
        let recordedBlob;

        const statusElement = document.getElementById('status');
        const statusIndicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');
        const timerElement = document.getElementById('timer');
        const startBtn = document.getElementById('start-btn');
        const stopBtn = document.getElementById('stop-btn');
        const downloadBtn = document.getElementById('download-btn');
        const audioContainer = document.getElementById('audio-container');
        const audioPlayer = document.getElementById('audio-player');
        const logsElement = document.getElementById('logs');

        function log(message) {
            const timestamp = new Date().toLocaleTimeString();
            logsElement.innerHTML += `[${timestamp}] ${message}\n`;
            logsElement.scrollTop = logsElement.scrollHeight;
            console.log(message);
        }

        function updateStatus(status, text) {
            statusIndicator.className = `status-indicator ${status}`;
            statusText.textContent = text;
            if (status === 'recording') {
                statusElement.className = 'recording-status recording';
            } else {
                statusElement.className = 'recording-status stopped';
            }
        }

        function updateTimer() {
            const elapsed = Date.now() - startTime;
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        async function startRecording() {
            try {
                log('Solicitando permisos de micrófono...');

                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        sampleRate: 44100
                    }
                });

                // Preferir MP4 si está disponible
                let mimeType = 'audio/mp4';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'audio/webm';
                    log('MP4 no soportado, usando WebM');
                } else {
                    log('Usando formato MP4');
                }

                mediaRecorder = new MediaRecorder(stream, { mimeType });
                recordedChunks = [];

                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };

                mediaRecorder.onstop = () => {
                    recordedBlob = new Blob(recordedChunks, { type: mimeType });
                    log(`Grabación completada. Tamaño: ${(recordedBlob.size / 1024).toFixed(1)} KB`);

                    // Mostrar reproductor de audio
                    audioPlayer.src = URL.createObjectURL(recordedBlob);
                    audioContainer.style.display = 'block';

                    // Habilitar botón de descarga
                    downloadBtn.disabled = false;

                    // Limpiar stream
                    stream.getTracks().forEach(track => track.stop());

                    updateStatus('ready', 'Grabación completada');
                };

                mediaRecorder.start(1000); // Datos cada segundo
                startTime = Date.now();

                // Iniciar timer
                timerInterval = setInterval(updateTimer, 1000);

                // Actualizar UI
                updateStatus('recording', 'Grabando...');
                startBtn.disabled = true;
                stopBtn.disabled = false;
                downloadBtn.disabled = true;

                log('Grabación iniciada');

            } catch (error) {
                log(`Error al iniciar grabación: ${error.message}`);
                updateStatus('ready', 'Error - Listo para reintentar');
            }
        }

        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                clearInterval(timerInterval);

                startBtn.disabled = false;
                stopBtn.disabled = true;

                updateStatus('processing', 'Procesando...');
                log('Deteniendo grabación...');
            }
        }

        function downloadRecording() {
            if (recordedBlob) {
                const url = URL.createObjectURL(recordedBlob);
                const a = document.createElement('a');
                a.href = url;

                const title = document.getElementById('meeting-title').value || 'reunion';
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const extension = recordedBlob.type.includes('mp4') ? 'mp4' : 'webm';

                a.download = `${title}_${timestamp}.${extension}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                log(`Archivo descargado: ${a.download}`);
            }
        }

        // Event listeners
        startBtn.addEventListener('click', startRecording);
        stopBtn.addEventListener('click', stopRecording);
        downloadBtn.addEventListener('click', downloadRecording);

        // Inicialización
        document.addEventListener('DOMContentLoaded', () => {
            log('Sistema inicializado - Sin dependencias externas');
            log('Formato de grabación: MP4 (preferido) o WebM (fallback)');
            log('CORS: No aplicable - Todo local');
        });
    </script>
</body>
</html>
