import { saveAudioBlob, clearAllAudio } from './idb.js';

// ===== VARIABLES GLOBALES =====
let isRecording = false;
let isPaused = false;
let recordingTimer = null;
let startTime = null;
let pauseStart = null;
let selectedMode = 'audio';
let mediaRecorder = null;
let audioContext = null;
let analyser = null;
let dataArray = null;
let animationId = null;
let systemAudioEnabled = false;
let microphoneAudioEnabled = true;
let systemAudioMuted = false;
let microphoneAudioMuted = false;
let meetingRecording = false;
let meetingTimer = null;
let meetingStartTime = null;
let systemAudioStream = null;
let microphoneAudioStream = null;
let systemAnalyser = null;
let microphoneAnalyser = null;
let systemDataArray = null;
let microphoneDataArray = null;
let meetingAnimationId = null;
let discardRequested = false;
let uploadedFile = null;
let fileUploadInitialized = false;
let pendingAudioBlob = null;
let pendingSaveContext = null;
let postponeMode = false;
window.postponeMode = postponeMode;

// SVG paths for dynamic icons
const ICON_PATHS = {
    play: 'M5.25 5.25l13.5 6.75-13.5 6.75V5.25z',
    pause: 'M15.75 5.25v13.5m-7.5-13.5v13.5',
    stop: 'M5.25 5.25h13.5v13.5H5.25z',
    video: 'M15 10.5l6-4.5v11l-6-4.5M3 6.75A2.25 2.25 0 015.25 4.5h6A2.25 2.25 0 0113.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-6A2.25 2.25 0 013 17.25V6.75z'
};

function setIcon(svgEl, name) {
    if (!svgEl) return;
    const path = ICON_PATHS[name];
    if (path) {
        if (svgEl.classList.contains('nav-icon-xxl')) {
            // Use fill icons and a 24x24 viewBox so paths scale to the large size
            svgEl.setAttribute('viewBox', '0 0 24 24');
            svgEl.setAttribute('fill', 'currentColor');
            svgEl.removeAttribute('stroke');
            svgEl.innerHTML = `<path d="${path}" />`;
        } else {
            // Default small icons: strokes on 24x24
            svgEl.setAttribute('viewBox', '0 0 24 24');
            svgEl.setAttribute('fill', 'none');
            svgEl.setAttribute('stroke', 'currentColor');
            svgEl.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="${path}" />`;
        }
    }
}

// ===== CONFIGURACIÓN DE GRABACIÓN =====
const MAX_DURATION_MS = 3 * 60 * 60 * 1000; // 3 horas
const SEGMENT_MS = 10 * 60 * 1000; // 10 minutos
let recordedSegments = [];
let segmentTimeout = null;
let recordingStream = null;

// ===== FUNCIONES DE LIMPIEZA =====

// Función para limpiar completamente todos los datos de audio anteriores
async function clearPreviousAudioData() {
    try {
        console.log('🧹 Limpiando datos de audio anteriores...');

        // Limpiar IndexedDB
        await clearAllAudio();

        // Limpiar sessionStorage de audio
        sessionStorage.removeItem('uploadedAudioKey');
        sessionStorage.removeItem('recordingBlob');
        sessionStorage.removeItem('recordingSegments');
        sessionStorage.removeItem('recordingMetadata');
        sessionStorage.removeItem('pendingAudioBlob');

        // Limpiar localStorage de audios pendientes
        localStorage.removeItem('pendingAudioData');

        // Limpiar variables locales
        uploadedFile = null;
        pendingAudioBlob = null;
        recordedSegments = [];

        console.log('✅ Datos de audio limpiados correctamente');

    } catch (error) {
        console.error('❌ Error al limpiar datos de audio:', error);
        // No lanzar error para no interrumpir el flujo
    }
}

// ===== FUNCIONES PRINCIPALES =====

// Función para seleccionar modo de grabación
function selectRecordingMode(mode) {
    document.querySelectorAll('.mode-option').forEach(option => {
        option.classList.remove('active');
    });
    document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
    selectedMode = mode;

    // Mostrar la interfaz correspondiente
    showRecordingInterface(mode);
}

// Función para mostrar la interfaz correcta según el modo
function showRecordingInterface(mode) {
    const audioRecorder = document.getElementById('audio-recorder');
    const audioUploader = document.getElementById('audio-uploader');
    const meetingRecorder = document.getElementById('meeting-recorder');
    const recorderTitle = document.getElementById('recorder-title');

    // Ocultar todas las interfaces
    audioRecorder.style.display = 'none';
    audioUploader.style.display = 'none';
    meetingRecorder.style.display = 'none';

    // Mostrar la interfaz correspondiente
    switch(mode) {
        case 'audio':
            audioRecorder.style.display = 'block';
            recorderTitle.innerHTML = '🎙️ Grabador de audio';
            break;
        case 'upload':
            audioUploader.style.display = 'block';
            recorderTitle.innerHTML = '📁 Subir archivo de audio';
            break;
        case 'meeting':
            meetingRecorder.style.display = 'block';
            recorderTitle.innerHTML = '📹 Grabador de reunión';
            setupMeetingRecorder();
            break;
    }
}


// Función para iniciar/detener grabación
function toggleRecording() {
    if (!isRecording) {
        startRecording();
        document.getElementById('pause-recording').style.display = 'inline-block';
        document.getElementById('discard-recording').style.display = 'inline-block';
        document.getElementById('resume-recording').style.display = 'none';
        const mp = document.getElementById('meeting-pause');
        const md = document.getElementById('meeting-discard');
        const mr = document.getElementById('meeting-resume');
        if (mp) mp.style.display = 'inline-block';
        if (md) md.style.display = 'inline-block';
        if (mr) mr.style.display = 'none';
        const postponeContainer = document.getElementById('postpone-switch');
        const postponeToggle = document.getElementById('postpone-toggle');
        if (postponeContainer) postponeContainer.style.display = 'none';
        if (postponeToggle) postponeToggle.disabled = true;
    } else {
        stopRecording();
        document.getElementById('pause-recording').style.display = 'none';
        document.getElementById('resume-recording').style.display = 'none';
        document.getElementById('discard-recording').style.display = 'none';
        const mp = document.getElementById('meeting-pause');
        const md = document.getElementById('meeting-discard');
        const mr = document.getElementById('meeting-resume');
        if (mp) mp.style.display = 'none';
        if (md) md.style.display = 'none';
        if (mr) mr.style.display = 'none';
    }
}

function setPostponeMode(on) {
    postponeMode = !!on;
    const label = document.getElementById('postpone-mode-label');
    const checkbox = document.getElementById('postpone-toggle');
    if (label) label.textContent = `Modo posponer: ${postponeMode ? 'Encendido' : 'Apagado'}`;
    if (checkbox && checkbox.checked !== postponeMode) checkbox.checked = postponeMode;
    window.postponeMode = postponeMode;
}

function togglePostponeMode() {
    const checkbox = document.getElementById('postpone-toggle');
    const next = checkbox ? checkbox.checked : !postponeMode;
    setPostponeMode(next);
}

document.addEventListener('DOMContentLoaded', () => {
    const checkbox = document.getElementById('postpone-toggle');
    if (checkbox) {
        checkbox.addEventListener('change', () => setPostponeMode(checkbox.checked));
        // Sync initial
        setPostponeMode(checkbox.checked);
    }
});

// ===== FUNCIONES DE GRABACIÓN =====

// Obtener las restricciones de audio basadas en las opciones avanzadas
async function getAudioConstraints() {
    const deviceSelect = document.getElementById('microphone-device');

    const constraints = {
        echoCancellation: true,
        noiseSuppression: true,
        sampleRate: 44100
    };

    if (deviceSelect && deviceSelect.value) {
        constraints.deviceId = { exact: deviceSelect.value };
    }

    return constraints;
}

// Función para iniciar grabación
async function startRecording() {
    try {
        // LIMPIAR DATOS ANTERIORES ANTES DE INICIAR NUEVA GRABACIÓN
        await clearPreviousAudioData();

        const audioConstraints = await getAudioConstraints();
        // Solicitar acceso al micrófono
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: audioConstraints
        });

        recordingStream = stream;

        // Configurar Web Audio API para análisis de frecuencias
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioContext.createAnalyser();
        const source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);

        analyser.fftSize = 256;
        analyser.smoothingTimeConstant = 0.8;
        const bufferLength = analyser.frequencyBinCount;
        dataArray = new Uint8Array(bufferLength);

        recordedSegments = [];
        startTime = Date.now();
        isRecording = true;

        updateRecordingUI(true);

        recordingTimer = setInterval(updateTimer, 100);
        startAudioAnalysis();

        startNewSegment();
    } catch (error) {
        console.error('Error al acceder al micrófono:', error);
        showError('No se pudo acceder al micrófono. Por favor, permite el acceso.');
    }
}

// Inicia la grabación de un nuevo segmento de 10 minutos
function startNewSegment() {
    if (!recordingStream) return;

    let chunks = [];
    let bitsPerSecond = 128000; // calidad media por defecto

    mediaRecorder = new MediaRecorder(recordingStream, {
        mimeType: 'audio/webm;codecs=opus',
        audioBitsPerSecond: bitsPerSecond
    });

    mediaRecorder.ondataavailable = event => {
        if (event.data && event.data.size > 0) {
            chunks.push(event.data);
        }
    };

    mediaRecorder.onstop = () => {
        clearTimeout(segmentTimeout);
        const blob = new Blob(chunks, { type: 'audio/webm;codecs=opus' });
        if (blob.size > 0 && !discardRequested) {
            recordedSegments.push(blob);
        }

        if (discardRequested) {
            discardRequested = false;
            return;
        }

        const elapsed = Date.now() - startTime;
        if (isRecording && elapsed < MAX_DURATION_MS) {
            startNewSegment();
        } else {
            isRecording = false;
            finalizeRecording();
        }
    };

    mediaRecorder.start();
    segmentTimeout = setTimeout(() => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
    }, SEGMENT_MS);
}

// Pausar grabación
function pauseRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.pause();
        isPaused = true;
        pauseStart = Date.now();
        const label = document.getElementById('timer-label');
        if (label) label.textContent = 'Grabación pausada';
        document.getElementById('pause-recording').style.display = 'none';
        document.getElementById('resume-recording').style.display = 'inline-block';
        const mp = document.getElementById('meeting-pause');
        const mr = document.getElementById('meeting-resume');
        if (mp) mp.style.display = 'none';
        if (mr) mr.style.display = 'inline-block';
    }
}

// Reanudar grabación
function resumeRecording() {
    if (mediaRecorder && mediaRecorder.state === 'paused') {
        mediaRecorder.resume();
        isPaused = false;
        if (pauseStart) {
            startTime += Date.now() - pauseStart;
            pauseStart = null;
        }
        const label = document.getElementById('timer-label');
        if (label) label.textContent = 'Grabando...';
        document.getElementById('resume-recording').style.display = 'none';
        document.getElementById('pause-recording').style.display = 'inline-block';
        const mp = document.getElementById('meeting-pause');
        const mr = document.getElementById('meeting-resume');
        if (mp) mp.style.display = 'inline-block';
        if (mr) mr.style.display = 'none';
    }
}

// Descartar grabación
function discardRecording() {
    discardRequested = true;
    isRecording = false;
    isPaused = false;
    recordedSegments = [];

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    if (audioContext && audioContext.state !== 'closed') {
        audioContext.close();
    }
    if (recordingTimer) {
        clearInterval(recordingTimer);
        recordingTimer = null;
    }
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }

    updateRecordingUI(false);
    resetAudioVisualizer();
    resetRecordingControls();
}

function resetRecordingControls() {
    document.getElementById('pause-recording').style.display = 'none';
    document.getElementById('resume-recording').style.display = 'none';
    document.getElementById('discard-recording').style.display = 'none';
    const mp = document.getElementById('meeting-pause');
    const md = document.getElementById('meeting-discard');
    const mr = document.getElementById('meeting-resume');
    if (mp) mp.style.display = 'none';
    if (md) md.style.display = 'none';
    if (mr) mr.style.display = 'none';
    const postponeContainer = document.getElementById('postpone-switch');
    const postponeToggle = document.getElementById('postpone-toggle');
    if (postponeContainer) postponeContainer.style.display = 'flex'; // o ''
    if (postponeToggle) postponeToggle.disabled = false;
}


// Función para detener grabación
function stopRecording() {
    isRecording = false;
    isPaused = false;

    if (segmentTimeout) {
        clearTimeout(segmentTimeout);
        segmentTimeout = null;
    }

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    } else {
        finalizeRecording();
    }
}

// Unir todos los segmentos y subir en segundo plano
async function finalizeRecording() {
    if (recordingStream) {
        recordingStream.getTracks().forEach(track => track.stop());
        recordingStream = null;
    }
    if (audioContext && audioContext.state !== 'closed') {
        await audioContext.close();
    }
    if (recordingTimer) {
        clearInterval(recordingTimer);
        recordingTimer = null;
    }
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }

    updateRecordingUI(false);
    resetAudioVisualizer();
    resetRecordingControls();

    const finalBlob = new Blob(recordedSegments, { type: 'audio/webm;codecs=opus' });

    if (postponeMode) {
        const now = new Date();
        const name = `grabacion-${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}_${String(now.getHours()).padStart(2, '0')}-${String(now.getMinutes()).padStart(2, '0')}-${String(now.getSeconds()).padStart(2, '0')}`;

        uploadInBackground(finalBlob, name)
            .then(response => {
                if (!response || (!response.saved && !response.pending_recording)) {
                    throw new Error('Invalid upload response');
                }
                showSuccess('Grabación subida a Drive');
            })
            .catch(e => {
                console.error('Error al subir la grabación', e);
                showError('Error al subir la grabación. Se descargará el audio');
                downloadBlob(finalBlob, name + '.webm');
            });

        showSuccess('La subida continuará en segundo plano. El estado final se mostrará mediante uploadNotifications.');
        handlePostActionCleanup(true);
    } else {
        pendingAudioBlob = finalBlob;
        pendingSaveContext = 'recording';
        analyzeNow();
    }
}

// ===== FUNCIONES DE VISUALIZACIÓN =====

// Función para analizar audio en tiempo real
function startAudioAnalysis() {
    if (!isRecording || !analyser) return;

    analyser.getByteFrequencyData(dataArray);

    // Calcular volumen promedio
    let sum = 0;
    for (let i = 0; i < dataArray.length; i++) {
        sum += dataArray[i];
    }
    const average = sum / dataArray.length;
    const volumeLevel = average / 255;

    // Actualizar visualizador de barras
    updateAudioBars(dataArray);

    // Actualizar anillos de volumen
    updateVolumeRings(volumeLevel);

    // Continuar análisis
    animationId = requestAnimationFrame(startAudioAnalysis);
}

// Función para actualizar las barras de audio
function updateAudioBars(frequencyData) {
    const bars = document.querySelectorAll('.audio-bar');
    const step = Math.floor(frequencyData.length / bars.length);

    bars.forEach((bar, index) => {
        const value = frequencyData[index * step] || 0;
        const height = Math.max((value / 255) * 100, 8);

        bar.style.height = height + '%';

        // Aplicar clases según intensidad
        bar.classList.remove('low', 'medium', 'high', 'peak');

        if (height > 80) {
            bar.classList.add('peak');
        } else if (height > 60) {
            bar.classList.add('high');
        } else if (height > 30) {
            bar.classList.add('medium');
        } else if (height > 8) {
            bar.classList.add('low');
        }
    });
}

// Función para actualizar los anillos de volumen
function updateVolumeRings(volumeLevel) {
    const rings = document.getElementById('volume-rings');

    if (volumeLevel > 0.1) {
        rings.classList.add('active');

        // Ajustar opacidad de los anillos según el volumen
        const ring1 = rings.querySelector('.ring-1');
        const ring2 = rings.querySelector('.ring-2');
        const ring3 = rings.querySelector('.ring-3');

        ring1.style.opacity = Math.min(volumeLevel * 2, 1);
        ring2.style.opacity = Math.min(volumeLevel * 1.5, 0.8);
        ring3.style.opacity = Math.min(volumeLevel, 0.6);
    } else {
        rings.classList.remove('active');
    }
}

// Función para actualizar la UI de grabación
function updateRecordingUI(recording) {
    const micCircle = document.getElementById('mic-circle');
    const timerCounter = document.getElementById('timer-counter');
    const timerLabel = document.getElementById('timer-label');
    const visualizer = document.getElementById('audio-visualizer');
    const actions = document.getElementById('recorder-actions');

    if (recording) {
        micCircle.classList.add('recording');
        timerCounter.classList.add('recording');
        timerLabel.textContent = 'Grabando...';
        timerLabel.classList.add('recording');
        visualizer.classList.add('active');
        if (actions) actions.classList.add('show');
    } else {
        micCircle.classList.remove('recording');
        timerCounter.classList.remove('recording');
        timerLabel.textContent = 'Listo para grabar';
        timerLabel.classList.remove('recording');
        timerCounter.textContent = '00:00';
        visualizer.classList.remove('active');
        if (actions) actions.classList.remove('show');
    }
}

// Función para resetear el visualizador de audio
function resetAudioVisualizer() {
    const bars = document.querySelectorAll('.audio-bar');
    const rings = document.getElementById('volume-rings');

    bars.forEach(bar => {
        bar.style.height = '8px';
        bar.classList.remove('low', 'medium', 'high', 'peak');
    });

    rings.classList.remove('active');
}

// ===== FUNCIONES AUXILIARES =====

// Función para actualizar el timer
function updateTimer() {
    if (isPaused || !startTime) return;

    const elapsed = Date.now() - startTime;
    const minutes = Math.floor(elapsed / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);

    const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    document.getElementById('timer-counter').textContent = timeString;
}

// Función para mostrar errores
function showError(message) {
    // Crear notificación de error
    const notification = document.createElement('div');
    notification.className = 'notification error';
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">❌</span>
            <span class="notification-message">${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Función para mostrar éxito
function showSuccess(message) {
    const notification = document.createElement('div');
    notification.className = 'notification success';
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">✅</span>
            <span class="notification-message">${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Sube un blob de audio en segundo plano
function uploadInBackground(blob, name, onProgress) {
    const formData = new FormData();
    formData.append('audioFile', blob, `${name}.webm`);
    formData.append('meetingName', name);

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const taskId = window.uploadNotifications.add(name);

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/drive/upload-pending-audio');
        xhr.setRequestHeader('X-CSRF-TOKEN', token);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                window.uploadNotifications.progress(taskId, e.loaded, e.total);
                if (onProgress) {
                    onProgress(e.loaded, e.total);
                }
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                window.uploadNotifications.processing(taskId);
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (err) {
                    response = xhr.responseText;
                }
                if (response?.pending_recording) {
                    pollPendingRecordingStatus(response.pending_recording, taskId);
                } else {
                    window.uploadNotifications.success(taskId);
                    showSuccess('Subida completada');
                }
                resolve(response);
            } else {
                window.uploadNotifications.error(taskId);
                showError('Fallo al subir el audio');
                reject(new Error('Upload failed'));
            }
        };

        xhr.onerror = () => {
            console.error('Error uploading audio');
            window.uploadNotifications.error(taskId);
            showError('Fallo al subir el audio');
            reject(new Error('Upload failed'));
        };

        xhr.send(formData);
    });
}

function pollPendingRecordingStatus(id, taskId) {
    const check = () => {
        fetch(`/api/pending-recordings/${id}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'COMPLETED') {
                    window.uploadNotifications.success(taskId, 'Procesado');
                    showSuccess('Grabación procesada correctamente');
                } else if (data.status === 'FAILED') {
                    const msg = data.error_message ? `Error: ${data.error_message}` : 'Error';
                    window.uploadNotifications.error(taskId, msg);
                    showError('Error al procesar la grabación en Drive');
                } else {
                    setTimeout(check, 5000);
                }
            })
            .catch(() => setTimeout(check, 5000));
    };
    check();
}

async function analyzeNow() {
    if (!pendingAudioBlob) return;
    try {
        // Guardar el blob en IndexedDB y almacenar la clave en sessionStorage
        const key = await saveAudioBlob(pendingAudioBlob);
        sessionStorage.setItem('uploadedAudioKey', key);

        // Respaldo: conversión a base64 deshabilitada
        // const base64 = await blobToBase64(pendingAudioBlob);
        // sessionStorage.setItem('recordingBlob', base64);

        sessionStorage.removeItem('recordingBlob');
        sessionStorage.removeItem('recordingSegments');
        sessionStorage.removeItem('recordingMetadata');
    } catch (e) {
        console.error('Error preparando audio', e);
    }

    handlePostActionCleanup();
    window.location.href = '/audio-processing';
}

function handlePostActionCleanup(uploaded) {
    if (pendingSaveContext === 'recording') {
        recordedSegments = [];
        startTime = null;
    } else if (pendingSaveContext === 'meeting') {
        recordedSegments = [];
        meetingStartTime = null;
    } else if (pendingSaveContext === 'upload' && !uploaded) {
        removeSelectedFile();
    }
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Función para alternar navbar móvil
function toggleMobileNavbar() {
    const navbar = document.querySelector('.mobile-navbar');
    const button = document.getElementById('mobile-navbar-btn');

    if (navbar) {
        navbar.classList.toggle('active');
        button.classList.toggle('active');
    }
}

// Función para crear partículas animadas
function createParticles() {
    const particles = document.getElementById('particles');
    const particleCount = window.innerWidth < 768 ? 30 : 50;

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 20 + 's';
        particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
        particles.appendChild(particle);
    }
}

// Enumerar dispositivos de micrófono y poblar el selector
async function populateMicrophoneDevices() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const select = document.getElementById('microphone-device');
        if (!select) return;

        // Conservar la opción por defecto
        const placeholder = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (placeholder) select.appendChild(placeholder);

        let count = 1;
        devices.filter(d => d.kind === 'audioinput').forEach(device => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Micrófono ${count++}`;
            select.appendChild(option);
        });
    } catch (e) {
        console.error('No se pudieron enumerar los micrófonos', e);
    }
}

// ===== EVENT LISTENERS =====

// Actualizar valor del slider de sensibilidad
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar partículas
    createParticles();

    // Cargar dispositivos de micrófono
    populateMicrophoneDevices();

    // Configurar subida de archivos una sola vez
    setupFileUpload();

    // Inicializar con modo de audio por defecto
    showRecordingInterface('audio');
});

// ===== FUNCIONES PARA SUBIR ARCHIVO =====

// Configurar la funcionalidad de subir archivo
function setupFileUpload() {
    if (fileUploadInitialized) return;
    fileUploadInitialized = true;

    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('audio-file-input');
    const uploadButton = uploadArea.querySelector('.upload-btn');

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelection(files[0]);
        }
    });

    // Click para seleccionar archivo
    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    uploadButton.addEventListener('click', (event) => {
        event.stopPropagation();
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelection(e.target.files[0]);
        }
    });
}

// Manejar la selección de archivo
function handleFileSelection(file) {
    // Validar tipo de archivo
    const validTypes = ['audio/mp3', 'audio/wav', 'audio/m4a', 'audio/flac', 'audio/ogg', 'audio/aac', 'audio/mpeg'];
    if (!validTypes.includes(file.type) && !file.name.match(/\.(mp3|wav|m4a|flac|ogg|aac)$/i)) {
        showError('Tipo de archivo no soportado. Por favor selecciona un archivo de audio válido.');
        return;
    }

    // Validar tamaño (máximo 100MB)
    if (file.size > 100 * 1024 * 1024) {
        showError('El archivo es demasiado grande. El tamaño máximo es 100MB.');
        return;
    }

    uploadedFile = file;

    // Mostrar información del archivo
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatFileSize(file.size);
    document.getElementById('selected-file').style.display = 'block';
    document.getElementById('upload-area').style.display = 'none';

    showSuccess('Archivo seleccionado correctamente');
}

// Remover archivo seleccionado
function removeSelectedFile() {
    document.getElementById('selected-file').style.display = 'none';
    document.getElementById('upload-area').style.display = 'block';
    document.getElementById('audio-file-input').value = '';
    uploadedFile = null;
}

// Procesar archivo de audio
async function processAudioFile() {
    if (!uploadedFile) {
        showError('Primero selecciona un archivo de audio');
        return;
    }

    try {
        // Mostrar progreso
        const progressContainer = document.getElementById('upload-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');

        if (progressContainer) {
            progressContainer.style.display = 'block';
            progressFill.style.width = '5%';
            progressText.textContent = 'Limpiando datos anteriores...';
        }

        // LIMPIAR DATOS ANTERIORES ANTES DE PROCESAR EL NUEVO ARCHIVO
        await clearPreviousAudioData();

        if (progressContainer) {
            progressFill.style.width = '20%';
            progressText.textContent = 'Preparando archivo para procesamiento...';
        }

        // Guardar el archivo en IndexedDB
        const audioKey = await saveAudioBlob(uploadedFile);
        console.log('Audio guardado en IndexedDB con clave:', audioKey);

        if (progressContainer) {
            progressFill.style.width = '70%';
            progressText.textContent = 'Archivo guardado...';
        }

        // Guardar la clave en sessionStorage para que audio-processing.js la pueda usar
        sessionStorage.setItem('uploadedAudioKey', audioKey);

        if (progressContainer) {
            progressFill.style.width = '90%';
            progressText.textContent = 'Redirigiendo al procesamiento...';
        }

        // Limpiar variables
        uploadedFile = null;

        // Pequeña pausa para que se vea el progreso
        setTimeout(() => {
            // Redireccionar a audio-processing
            window.location.href = '/audio-processing';
        }, 500);

    } catch (error) {
        console.error('Error al procesar archivo de audio:', error);
        showError('Error al procesar el archivo de audio: ' + error.message);

        // Ocultar progreso en caso de error
        const progressContainer = document.getElementById('upload-progress');
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
    }
}

// Formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ===== FUNCIONES PARA REUNIÓN =====

// Alternar audio del sistema
function toggleSystemAudio() {
    systemAudioEnabled = !systemAudioEnabled;
    const btn = document.getElementById('system-audio-btn');
    const text = btn.querySelector('.source-text');

    if (systemAudioEnabled) {
        btn.classList.add('active');
        text.textContent = 'Sistema activado';
    } else {
        btn.classList.remove('active');
        text.textContent = 'Sistema desactivado';
    }
}

// Alternar audio del micrófono
function toggleMicrophoneAudio() {
    microphoneAudioEnabled = !microphoneAudioEnabled;
    const btn = document.getElementById('microphone-audio-btn');
    const text = btn.querySelector('.source-text');

    if (microphoneAudioEnabled) {
        btn.classList.add('active');
        text.textContent = 'Micrófono activado';
    } else {
        btn.classList.remove('active');
        text.textContent = 'Micrófono desactivado';
    }
}

// Mutear audio del sistema
function muteSystemAudio() {
    systemAudioMuted = !systemAudioMuted;
    const btn = document.getElementById('system-mute-btn');
    const icon = btn.querySelector('.mute-icon');

    if (systemAudioMuted) {
        btn.classList.add('muted');
        icon.textContent = '🔇';
    } else {
        btn.classList.remove('muted');
        icon.textContent = '🔊';
    }
}

// Mutear audio del micrófono
function muteMicrophoneAudio() {
    microphoneAudioMuted = !microphoneAudioMuted;
    const btn = document.getElementById('microphone-mute-btn');
    const icon = btn.querySelector('.mute-icon');

    if (microphoneAudioMuted) {
        btn.classList.add('muted');
        icon.textContent = '🔇';
    } else {
        btn.classList.remove('muted');
        icon.textContent = '🔊';
    }
}

// Alternar grabación de reunión
function toggleMeetingRecording() {
    // Verificar soporte del navegador
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
        showError('Tu navegador no soporta grabación de reuniones. Usa Chrome, Edge o Firefox actualizado.');
        return;
    }

    // Verificar que se ejecute en HTTPS (requerido para getDisplayMedia)
    if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
        showError('La grabación de reuniones requiere HTTPS. Asegúrate de estar en una conexión segura.');
        return;
    }

    if (!meetingRecording) {
        startMeetingRecording();
    } else {
        stopMeetingRecording();
    }
}
// Iniciar grabación de reunión
async function startMeetingRecording() {
    if (!systemAudioEnabled && !microphoneAudioEnabled) {
        showError('Debes activar al menos una fuente de audio');
        return;
    }

    try {
        // Limpiar datos de audio previos antes de iniciar nueva reunión
        await clearPreviousAudioData();

        // Solicitar acceso a las fuentes de audio
        const audioConstraints = await getAudioConstraints();
        if (microphoneAudioEnabled) {
            microphoneAudioStream = await navigator.mediaDevices.getUserMedia({
                audio: audioConstraints
            });
        }

        if (systemAudioEnabled) {
            // Solicitar captura de pantalla para obtener audio del sistema
            systemAudioStream = await navigator.mediaDevices.getDisplayMedia({
                video: false,
                audio: {
                    echoCancellation: false,
                    noiseSuppression: false,
                    sampleRate: 44100
                }
            });
        }

        // Configurar análisis de audio
        setupMeetingAudioAnalysis();

        meetingRecording = true;
        meetingStartTime = Date.now();

        // Actualizar UI
        updateMeetingRecordingUI(true);

        // Iniciar timer y análisis
        meetingTimer = setInterval(updateMeetingTimer, 100);
        startMeetingAudioAnalysis();

        showSuccess('¡Grabación de reunión iniciada!');

    } catch (error) {
        console.error('Error al iniciar grabación de reunión:', error);
        showError('No se pudo acceder a las fuentes de audio. Verifica los permisos.');
    }
}

// Detener grabación de reunión
async function stopMeetingRecording() {
    meetingRecording = false;

    // Detener MediaRecorder si está activo
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }

    // Detener streams
    if (systemAudioStream) {
        systemAudioStream.getTracks().forEach(track => track.stop());
        systemAudioStream = null;
    }
    if (microphoneAudioStream) {
        microphoneAudioStream.getTracks().forEach(track => track.stop());
        microphoneAudioStream = null;
    }

    // Limpiar timer y animación
    if (meetingTimer) {
        clearInterval(meetingTimer);
        meetingTimer = null;
    }
    if (meetingAnimationId) {
        cancelAnimationFrame(meetingAnimationId);
        meetingAnimationId = null;
    }
    updateMeetingRecordingUI(false);
    resetMeetingAudioVisualizers();
    resetRecordingControls();
}

// Configurar análisis de audio para reunión
function setupMeetingAudioAnalysis() {
    audioContext = new (window.AudioContext || window.webkitAudioContext)();

    if (systemAudioStream) {
        systemAnalyser = audioContext.createAnalyser();
        const systemSource = audioContext.createMediaStreamSource(systemAudioStream);
        systemSource.connect(systemAnalyser);
        systemAnalyser.fftSize = 256;
        systemAnalyser.smoothingTimeConstant = 0.8;
        systemDataArray = new Uint8Array(systemAnalyser.frequencyBinCount);
    }

    if (microphoneAudioStream) {
        microphoneAnalyser = audioContext.createAnalyser();
        const microphoneSource = audioContext.createMediaStreamSource(microphoneAudioStream);
        microphoneSource.connect(microphoneAnalyser);
        microphoneAnalyser.fftSize = 256;
        microphoneAnalyser.smoothingTimeConstant = 0.8;
        microphoneDataArray = new Uint8Array(microphoneAnalyser.frequencyBinCount);
    }
}

// Iniciar análisis de audio para reunión
function startMeetingAudioAnalysis() {
    if (!meetingRecording) return;

    // Analizar audio del sistema
    if (systemAnalyser && systemDataArray && !systemAudioMuted) {
        systemAnalyser.getByteFrequencyData(systemDataArray);
        updateMeetingAudioBars('system-audio-visualizer', systemDataArray);
    }

    // Analizar audio del micrófono
    if (microphoneAnalyser && microphoneDataArray && !microphoneAudioMuted) {
        microphoneAnalyser.getByteFrequencyData(microphoneDataArray);
        updateMeetingAudioBars('microphone-audio-visualizer', microphoneDataArray);
    }

    meetingAnimationId = requestAnimationFrame(startMeetingAudioAnalysis);
}

// Actualizar barras de audio para reunión
function updateMeetingAudioBars(visualizerId, frequencyData) {
    const visualizer = document.getElementById(visualizerId);
    if (!visualizer) return;

    const bars = visualizer.querySelectorAll('.meeting-audio-bar');
    const step = Math.floor(frequencyData.length / bars.length);

    bars.forEach((bar, index) => {
        const value = frequencyData[index * step] || 0;
        const height = Math.max((value / 255) * 100, 8);

        bar.style.height = height + '%';

        // Aplicar clases según intensidad
        bar.classList.remove('active', 'high');

        if (height > 70) {
            bar.classList.add('high');
        } else if (height > 30) {
            bar.classList.add('active');
        }
    });

    // Activar visualizador si hay audio
    const hasAudio = Array.from(frequencyData).some(value => value > 30);
    if (hasAudio) {
        visualizer.classList.add('active');
    } else {
        visualizer.classList.remove('active');
    }
}

// Actualizar UI de grabación de reunión
function updateMeetingRecordingUI(recording) {
    const button = document.getElementById('meeting-record-btn');
    const buttonIcon = button.querySelector('.nav-icon');
    const timerCounter = document.getElementById('meeting-timer-counter');
    const timerLabel = document.getElementById('meeting-timer-label');

    if (recording) {
        button.classList.add('recording');
        setIcon(buttonIcon, 'stop');
        timerCounter.classList.add('recording');
        timerLabel.textContent = 'Grabando reunión...';
        timerLabel.classList.add('recording');
    } else {
        button.classList.remove('recording');
        setIcon(buttonIcon, 'video');
        timerCounter.classList.remove('recording');
        timerLabel.textContent = 'Listo para grabar';
        timerLabel.classList.remove('recording');
        timerCounter.textContent = '00:00';
    }
}

// Actualizar timer de reunión
function updateMeetingTimer() {
    if (!meetingStartTime) return;

    const elapsed = Date.now() - meetingStartTime;
    const minutes = Math.floor(elapsed / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);

    const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    document.getElementById('meeting-timer-counter').textContent = timeString;
}

// Resetear visualizadores de audio de reunión
function resetMeetingAudioVisualizers() {
    const visualizers = document.querySelectorAll('.meeting-audio-visualizer');

    visualizers.forEach(visualizer => {
        const bars = visualizer.querySelectorAll('.meeting-audio-bar');
        bars.forEach(bar => {
            bar.style.height = '8px';
            bar.classList.remove('active', 'high');
        });
        visualizer.classList.remove('active');
    });
}

// Limpiar recursos al salir de la página
window.addEventListener('beforeunload', function() {
    if (isRecording) {
        stopRecording();
    }
});


// Hacer las funciones globales para que funcionen con onclick en el HTML
window.selectRecordingMode = selectRecordingMode;
window.toggleRecording = toggleRecording;
window.toggleMobileNavbar = toggleMobileNavbar;
window.removeSelectedFile = removeSelectedFile;
window.processAudioFile = processAudioFile;
window.pauseRecording = pauseRecording;
window.resumeRecording = resumeRecording;
window.discardRecording = discardRecording;
window.togglePostponeMode = togglePostponeMode;

// Funciones del grabador de reuniones que faltaban
window.toggleSystemAudio = toggleSystemAudio;
window.toggleMicrophoneAudio = toggleMicrophoneAudio;
window.muteSystemAudio = muteSystemAudio;
window.muteMicrophoneAudio = muteMicrophoneAudio;
window.toggleMeetingRecording = toggleMeetingRecording;

// Funciones para navbar móvil
window.toggleMobileDropdown = function() {
  const dropdown = document.getElementById('mobile-dropdown');
  const overlay = document.getElementById('mobile-dropdown-overlay');

  dropdown.classList.toggle('show');
  overlay.classList.toggle('show');
};

window.closeMobileDropdown = function() {
  const dropdown = document.getElementById('mobile-dropdown');
  const overlay = document.getElementById('mobile-dropdown-overlay');

  dropdown.classList.remove('show');
  overlay.classList.remove('show');
};
