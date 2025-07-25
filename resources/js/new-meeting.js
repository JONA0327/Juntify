// ===== VARIABLES GLOBALES =====
let isRecording = false;
let isPaused = false;
let recordingTimer = null;
let startTime = null;
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
            setupFileUpload();
            break;
        case 'meeting':
            meetingRecorder.style.display = 'block';
            recorderTitle.innerHTML = '📹 Grabador de reunión';
            setupMeetingRecorder();
            break;
    }
}

// Función para alternar opciones avanzadas
function toggleAdvancedOptions() {
    const content = document.getElementById('advanced-content');
    const icon = document.getElementById('expand-icon');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        icon.textContent = '▲';
    } else {
        content.classList.add('collapsed');
        icon.textContent = '▼';
    }
}

// Función para iniciar/detener grabación
function toggleRecording() {
    if (!isRecording) {
        startRecording();
    } else {
        stopRecording();
    }
}

// ===== FUNCIONES DE GRABACIÓN =====

// Función para iniciar grabación
async function startRecording() {
    try {
        // Solicitar acceso al micrófono
        const stream = await navigator.mediaDevices.getUserMedia({ 
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                sampleRate: 44100
            } 
        });
        
        // Configurar Web Audio API para análisis de frecuencias
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioContext.createAnalyser();
        const source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);
        
        // Configuración del analizador
        analyser.fftSize = 256;
        analyser.smoothingTimeConstant = 0.8;
        const bufferLength = analyser.frequencyBinCount;
        dataArray = new Uint8Array(bufferLength);
        
        // Configurar MediaRecorder
        mediaRecorder = new MediaRecorder(stream, {
            mimeType: 'audio/webm;codecs=opus'
        });
        
        mediaRecorder.ondataavailable = function(event) {
            if (event.data.size > 0) {
                // Aquí se procesaría el audio grabado
                console.log('Chunk de audio recibido:', event.data.size, 'bytes');
            }
        };
        
        mediaRecorder.start(1000); // Guardar chunks cada segundo
        
        isRecording = true;
        startTime = Date.now();
        
        // Actualizar UI
        updateRecordingUI(true);
        
        // Iniciar timer y análisis de audio
        recordingTimer = setInterval(updateTimer, 100);
        startAudioAnalysis();
        
    } catch (error) {
        console.error('Error al acceder al micrófono:', error);
        showError('No se pudo acceder al micrófono. Por favor, permite el acceso.');
    }
}

// Función para detener grabación
function stopRecording() {
    isRecording = false;
    isPaused = false;
    
    // Detener MediaRecorder y AudioContext
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    if (audioContext && audioContext.state !== 'closed') {
        audioContext.close();
    }
    
    // Limpiar timer y animación
    if (recordingTimer) {
        clearInterval(recordingTimer);
        recordingTimer = null;
    }
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }
    
    // Actualizar UI
    updateRecordingUI(false);
    resetAudioVisualizer();
    
    // Simular procesamiento
    setTimeout(() => {
        showSuccess('¡Grabación finalizada! Procesando transcripción...');
    }, 500);
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
    const button = document.getElementById('start-recording');
    const micCircle = document.getElementById('mic-circle');
    const timerCounter = document.getElementById('timer-counter');
    const timerLabel = document.getElementById('timer-label');
    const visualizer = document.getElementById('audio-visualizer');
    
    if (recording) {
        button.innerHTML = '⏹️ Detener grabación';
        button.classList.add('recording');
        micCircle.classList.add('recording');
        timerCounter.classList.add('recording');
        timerLabel.textContent = 'Grabando...';
        timerLabel.classList.add('recording');
        visualizer.classList.add('active');
    } else {
        button.innerHTML = '▶️ Iniciar grabación';
        button.classList.remove('recording');
        micCircle.classList.remove('recording');
        timerCounter.classList.remove('recording');
        timerLabel.textContent = 'Listo para grabar';
        timerLabel.classList.remove('recording');
        timerCounter.textContent = '00:00';
        visualizer.classList.remove('active');
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

// ===== EVENT LISTENERS =====

// Actualizar valor del slider de sensibilidad
document.addEventListener('DOMContentLoaded', function() {
    const sensitivitySlider = document.getElementById('mic-sensitivity');
    if (sensitivitySlider) {
        sensitivitySlider.addEventListener('input', function() {
            const valueDisplay = document.querySelector('.slider-value');
            if (valueDisplay) {
                valueDisplay.textContent = this.value + '%';
            }
        });
    }
    
    // Inicializar partículas
    createParticles();
    
    // Colapsar opciones avanzadas por defecto
    const advancedContent = document.getElementById('advanced-content');
    if (advancedContent) {
        advancedContent.classList.add('collapsed');
        document.getElementById('expand-icon').textContent = '▼';
    }
    
    // Inicializar con modo de audio por defecto
    showRecordingInterface('audio');
});

// ===== FUNCIONES PARA SUBIR ARCHIVO =====

// Configurar la funcionalidad de subir archivo
function setupFileUpload() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('audio-file-input');
    
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
}

// Procesar archivo de audio
function processAudioFile() {
    const progressContainer = document.getElementById('upload-progress');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    
    progressContainer.style.display = 'block';
    
    // Simular progreso de subida
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
        
        if (progress >= 100) {
            clearInterval(interval);
            setTimeout(() => {
                showSuccess('¡Archivo procesado exitosamente! Generando transcripción...');
                progressContainer.style.display = 'none';
            }, 500);
        }
    }, 200);
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
        // Solicitar acceso a las fuentes de audio
        if (microphoneAudioEnabled) {
            microphoneAudioStream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100
                } 
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
function stopMeetingRecording() {
    meetingRecording = false;
    
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
    
    // Actualizar UI
    updateMeetingRecordingUI(false);
    resetMeetingAudioVisualizers();
    
    showSuccess('¡Grabación de reunión finalizada! Procesando...');
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
    const buttonText = button.querySelector('.btn-text');
    const buttonIcon = button.querySelector('.btn-icon');
    const timerCounter = document.getElementById('meeting-timer-counter');
    const timerLabel = document.getElementById('meeting-timer-label');
    
    if (recording) {
        button.classList.add('recording');
        buttonIcon.textContent = '⏹️';
        buttonText.textContent = 'Detener grabación';
        timerCounter.classList.add('recording');
        timerLabel.textContent = 'Grabando reunión...';
        timerLabel.classList.add('recording');
    } else {
        button.classList.remove('recording');
        buttonIcon.textContent = '🎬';
        buttonText.textContent = 'Seleccionar fuente de audio';
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
window.toggleAdvancedOptions = toggleAdvancedOptions;
window.toggleRecording = toggleRecording;
window.toggleMobileNavbar = toggleMobileNavbar;
window.removeSelectedFile = removeSelectedFile;
window.processAudioFile = processAudioFile;

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