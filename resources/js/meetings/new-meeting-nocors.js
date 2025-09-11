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
