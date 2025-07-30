// ===== VARIABLES GLOBALES =====
let currentStep = 1;
let selectedAnalyzer = 'general';
let audioData = null;
let audioSegments = [];
let transcriptionData = [];
let audioPlayer = null;
let analysisResults = null;

// Mensajes que se mostrarán mientras se genera la transcripción
const typingMessages = [
    "Estoy trabajando en tu transcripción...",
    "Sé paciente, esto puede tardar un tiempo..."
];
let typingInterval = null;

// ===== FUNCIONES PRINCIPALES =====

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

// Función para mostrar un paso específico
function showStep(stepNumber) {
    // Ocultar todos los pasos
    document.querySelectorAll('.processing-step').forEach(step => {
        step.classList.remove('active');
    });

    // Mostrar el paso actual
    const targetStep = document.getElementById(`step-${getStepId(stepNumber)}`);
    if (targetStep) {
        targetStep.classList.add('active');
        currentStep = stepNumber;
    }
}

// Función para obtener el ID del paso
function getStepId(stepNumber) {
    const stepIds = [
        'audio-processing',
        'transcription',
        'edit-transcription',
        'select-analysis',
        'analysis-processing',
        'save-results',
        'saving',
        'completed'
    ];
    return stepIds[stepNumber - 1];
}

// ===== PASO 1: PROCESAMIENTO DE AUDIO =====

async function startAudioProcessing() {
    showStep(1);

    const progressBar = document.getElementById('audio-progress');
    const progressText = document.getElementById('audio-progress-text');
    const progressPercent = document.getElementById('audio-progress-percent');

    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    progressText.textContent = 'Unificando segmentos...';

    if (!audioSegments || audioSegments.length === 0) {
        progressBar.style.width = '100%';
        progressPercent.textContent = '100%';
        progressText.textContent = 'No hay segmentos de audio';
        document.getElementById('audio-quality-status').textContent = '⚠️';
        return;
    }

    audioData = await mergeAudioSegments(audioSegments);

    document.getElementById('audio-quality-status').textContent = '✅';
    document.getElementById('speaker-detection-status').textContent = '✅';
    document.getElementById('noise-reduction-status').textContent = '✅';

    setTimeout(() => {
        startTranscription();
    }, 500);
}

async function mergeAudioSegments(segments) {
    const progressBar = document.getElementById('audio-progress');
    const progressPercent = document.getElementById('audio-progress-percent');

    const totalBytes = segments.reduce((acc, s) => acc + s.size, 0);
    let mergedBytes = 0;
    const buffers = [];

    for (const blob of segments) {
        const buffer = await blob.arrayBuffer();
        buffers.push(buffer);
        mergedBytes += blob.size;
        const percent = (mergedBytes / totalBytes) * 100;
        progressBar.style.width = percent + '%';
        progressPercent.textContent = Math.round(percent) + '%';
    }

    return new Blob(buffers, { type: segments[0]?.type || 'audio/webm;codecs=opus' });
}

// ===== PASO 2: TRANSCRIPCIÓN =====

async function startTranscription() {
    showStep(2);

    const typingEl = document.getElementById('typing-text');
    let messageIndex = 0;
    typingEl.textContent = typingMessages[messageIndex];
    clearInterval(typingInterval);
    typingInterval = setInterval(() => {
        messageIndex = (messageIndex + 1) % typingMessages.length;
        typingEl.textContent = typingMessages[messageIndex];
    }, 3000);

    const lang = sessionStorage.getItem('transcriptionLanguage') || 'es';

    const progressBar = document.getElementById('transcription-progress');
    const progressText = document.getElementById('transcription-progress-text');
    const progressPercent = document.getElementById('transcription-progress-percent');

    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    progressText.textContent = 'Subiendo audio...';

    const formData = new FormData();
    formData.append('audio', audioData, 'recording.webm');
    formData.append('language', lang);


    try {
        const { data } = await axios.post('/transcription', formData);
        console.log("✅ Transcripción iniciada:", data);

        progressBar.style.width = '10%';
        progressPercent.textContent = '10%';

        pollTranscription(data.id);
    } catch (e) {
        console.error("❌ Error:", e);

        if (e.response) {
            console.error("📡 STATUS:", e.response.status);
            console.error("📩 HEADERS:", e.response.headers);
            console.error("📦 BODY:", e.response.data);
            alert("🧠 Error del servidor: " + JSON.stringify(e.response.data));
        } else {
            alert("❌ Error desconocido. Revisa consola.");
        }

        progressText.textContent = 'Error al subir audio';
    }
}

function pollTranscription(id) {
    const progressBar = document.getElementById('transcription-progress');
    const progressText = document.getElementById('transcription-progress-text');
    const progressPercent = document.getElementById('transcription-progress-percent');

    let percent = 10;

    const interval = setInterval(async () => {
        try {
            const { data } = await axios.get(`/transcription/${id}`);

            if (data.status === 'queued') {
                progressText.textContent = 'En cola...';
            } else if (data.status === 'processing') {
                progressText.textContent = 'Procesando...';
                if (typeof data.progress === 'number') {
                    percent = data.progress;
                } else if (typeof data.processing_percent === 'number') {
                    percent = data.processing_percent;
                } else if (data.processed_duration && data.audio_duration) {
                    percent = Math.min(99, (data.processed_duration / data.audio_duration) * 100);
                } else {
                    percent = Math.min(95, percent + 5);
                }
            } else if (data.status === 'completed') {
                progressText.textContent = 'Transcripción completada';
                percent = 100;
                clearInterval(interval);
                clearInterval(typingInterval);
                transcriptionData = data;
                showTranscriptionEditor();
            } else if (data.status === 'error') {
                progressText.textContent = 'Error en transcripción';
                const message = data.error || data.message;
                if (message) {
                    showNotification(message, 'error');
                }
                clearInterval(interval);
                clearInterval(typingInterval);
            }

            progressBar.style.width = percent + '%';
            progressPercent.textContent = Math.round(percent) + '%';
        } catch (err) {
            console.error(err);
            progressText.textContent = 'Error de servidor';
            clearInterval(interval);
            clearInterval(typingInterval);
        }
    }, 4000);
}

// ===== PASO 3: EDITOR DE TRANSCRIPCIÓN =====

function showTranscriptionEditor() {
    showStep(3);
    generateTranscriptionSegments();
}

function generateTranscriptionSegments() {
    const container = document.getElementById('transcription-segments');

    const utterances = Array.isArray(transcriptionData)
        ? transcriptionData
        : (transcriptionData.utterances || []);

    if (!utterances.length) {
        showNotification('La transcripci\u00f3n no contiene informaci\u00f3n de hablantes', 'error');
        container.innerHTML = '<p class="no-speakers">No se detectaron hablantes.</p>';
        return;
    }

    const segments = utterances.map(u => {
        const hasSpeaker = u.speaker !== undefined && u.speaker !== null;
        const speaker = hasSpeaker ? u.speaker : `Hablante ${u.speaker}`;
        const avatar = hasSpeaker
            ? u.speaker.toString().slice(0, 2).toUpperCase()
            : `H${u.speaker}`;

        return {
            speaker,
            time: `${formatTime(u.start)} - ${formatTime(u.end)}`,
            text: u.text,
            avatar,
            start: u.start / 1000,
            end: u.end / 1000,
        };
    });

    container.innerHTML = segments.map((segment, index) => `
        <div class="transcript-segment" data-segment="${index}">
            <div class="segment-header">
                <div class="speaker-info">
                    <div class="speaker-avatar">${segment.avatar}</div>
                    <div class="speaker-details">
                        <div class="speaker-name">${segment.speaker}</div>
                        <div class="speaker-time">${segment.time}</div>
                    </div>
                </div>
                <div class="segment-controls">
                    <button class="control-btn" onclick="playSegmentAudio(${index})" title="Reproducir fragmento">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" />
                        </svg>
                    </button>
                    <button class="control-btn" onclick="openChangeSpeakerModal(${index})" title="Editar hablante">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                        </svg>
                    </button>
                    <button class="control-btn" onclick="openGlobalSpeakerModal(${index})" title="Cambiar hablante globalmente">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15a3 3 0 100-6 3 3 0 000 6zm9 0a3 3 0 100-6 3 3 0 000 6zm-9 1.5a4.5 4.5 0 00-4.5 4.5v1.5h9v-1.5a4.5 4.5 0 00-4.5-4.5zm9 0a4.5 4.5 0 014.5 4.5v1.5h-9v-1.5a4.5 4.5 0 014.5-4.5z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="segment-audio">
                <div class="audio-player-mini">
                    <button class="play-btn-mini" onclick="playSegmentAudio(${index})">
                        <svg class="play-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" />
                        </svg>
                    </button>
                    <div class="audio-timeline-mini" onclick="seekAudio(${index}, event)">
                        <div class="timeline-progress-mini" style="width: 0%"></div>
                    </div>
                    <span class="audio-duration-mini">${segment.time.split(' - ')[1]}</span>
                </div>
            </div>

            <div class="segment-content">
                <textarea class="transcript-text" placeholder="Texto de la transcripción...">${segment.text}</textarea>
            </div>
        </div>
    `).join('');

    transcriptionData = segments;
    const speakerSet = new Set(segments.map(s => s.speaker));
    const speakerCountEl = document.getElementById('speaker-count');
    if (speakerCountEl) {
        speakerCountEl.textContent = speakerSet.size;
    }
}

function playSegmentAudio(segmentIndex) {
    const segment = transcriptionData[segmentIndex];
    if (!segment) return;

    if (!audioPlayer) {
        audioPlayer = document.getElementById('recorded-audio');
    }
    if (!audioPlayer.src) {
        const src = typeof audioData === 'string' ? audioData : URL.createObjectURL(audioData);
        audioPlayer.src = src;
    }

    audioPlayer.pause();
    audioPlayer.currentTime = segment.start;
    const stopTime = segment.end;

    const onTimeUpdate = () => {
        if (audioPlayer.currentTime >= stopTime) {
            audioPlayer.pause();
            audioPlayer.removeEventListener('timeupdate', onTimeUpdate);
        }
    };

    audioPlayer.addEventListener('timeupdate', onTimeUpdate);
    audioPlayer.play();
}

let selectedSegmentIndex = null;

function openChangeSpeakerModal(segmentIndex) {
    selectedSegmentIndex = segmentIndex;
    const currentName = transcriptionData[segmentIndex].speaker;
    document.getElementById('speaker-name-input').value = currentName;
    document.getElementById('change-speaker-modal').classList.add('show');
}

function closeChangeSpeakerModal() {
    document.getElementById('change-speaker-modal').classList.remove('show');
}

function confirmSpeakerChange() {
    const input = document.getElementById('speaker-name-input');
    const newName = input.value.trim();
    if (!newName) {
        showNotification('Debes ingresar un nombre válido', 'warning');
        return;
    }

    transcriptionData[selectedSegmentIndex].speaker = newName;
    const element = document.querySelector(`[data-segment="${selectedSegmentIndex}"] .speaker-name`);
    if (element) {
        element.textContent = newName;
    }

    closeChangeSpeakerModal();
    showNotification('Hablante actualizado correctamente', 'success');
}

function openGlobalSpeakerModal(segmentIndex) {
    selectedSegmentIndex = segmentIndex;
    const currentName = transcriptionData[segmentIndex].speaker;
    document.getElementById('current-speaker-name').value = currentName;
    document.getElementById('global-speaker-name-input').value = '';
    document.getElementById('change-global-speaker-modal').classList.add('show');
}

function closeGlobalSpeakerModal() {
    document.getElementById('change-global-speaker-modal').classList.remove('show');
}

function confirmGlobalSpeakerChange() {
    const newName = document.getElementById('global-speaker-name-input').value.trim();
    const currentName = document.getElementById('current-speaker-name').value;
    if (!newName) {
        showNotification('Debes ingresar un nombre válido', 'warning');
        return;
    }

    transcriptionData.forEach((segment, idx) => {
        if (segment.speaker === currentName) {
            segment.speaker = newName;
            const el = document.querySelector(`[data-segment="${idx}"] .speaker-name`);
            if (el) {
                el.textContent = newName;
            }
        }
    });

    closeGlobalSpeakerModal();
    showNotification('Hablantes actualizados correctamente', 'success');
}

function seekAudio(segmentIndex, event) {
    const timeline = event.currentTarget;
    const rect = timeline.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const percentage = (clickX / rect.width) * 100;

    const progress = timeline.querySelector('.timeline-progress-mini');
    progress.style.width = percentage + '%';

    const segment = transcriptionData[segmentIndex];
    if (!segment) return;
    const duration = segment.end - segment.start;
    const targetTime = segment.start + (duration * (percentage / 100));

    if (!audioPlayer) {
        const src = typeof audioData === 'string' ? audioData : URL.createObjectURL(audioData);
        audioPlayer = new Audio(src);
    }
    audioPlayer.currentTime = targetTime;
}

function playFullAudio() {
    console.log('Reproduciendo audio completo');
    showNotification('Reproduciendo audio completo de la reunión', 'info');
}

function saveTranscriptionAndContinue() {
    const segments = document.querySelectorAll('.transcript-segment');

    segments.forEach((segment, index) => {
        const nameEl = segment.querySelector('.speaker-name');
        const textEl = segment.querySelector('.transcript-text');

        if (!transcriptionData[index]) {
            console.warn(`Segmento faltante en transcriptionData[${index}]`);
            return;
        }

        const speakerName = nameEl ? nameEl.textContent : transcriptionData[index].speaker;
        const transcriptText = textEl ? textEl.value : transcriptionData[index].text;

        transcriptionData[index].speaker = speakerName;
        transcriptionData[index].text = transcriptText;
    });

    showNotification('Transcripción guardada correctamente', 'success');
    setTimeout(() => {
        showAnalysisSelector();
    }, 1000);
}


// ===== PASO 4: SELECTOR DE ANÁLISIS =====

function showAnalysisSelector() {
    showStep(4);
}

function selectAnalyzer(analyzerType) {
    // Remover selección anterior
    document.querySelectorAll('.analyzer-card').forEach(card => {
        card.classList.remove('active');
    });

    // Seleccionar nuevo analizador
    document.querySelector(`[data-analyzer="${analyzerType}"]`).classList.add('active');
    selectedAnalyzer = analyzerType;

    console.log('Analizador seleccionado:', analyzerType);
}

function startAnalysis() {
    if (!selectedAnalyzer) {
        showNotification('Por favor selecciona un tipo de análisis', 'error');
        return;
    }

    showNotification(`Iniciando análisis: ${selectedAnalyzer}`, 'info');
    setTimeout(() => {
        processAnalysis();
    }, 1000);
}

// ===== PASO 5: PROCESAMIENTO DE ANÁLISIS =====

function processAnalysis() {
    showStep(5);

    const progressBar = document.getElementById('analysis-progress');
    const progressText = document.getElementById('analysis-progress-text');
    const progressPercent = document.getElementById('analysis-progress-percent');

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 6 + 2;
        if (progress > 100) progress = 100;

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';

        // Actualizar estados de análisis
        if (progress > 25) {
            document.getElementById('summary-status').textContent = '✅';
            progressText.textContent = 'Identificando puntos clave...';
        }
        if (progress > 60) {
            document.getElementById('keypoints-status').textContent = '✅';
            progressText.textContent = 'Extrayendo tareas y acciones...';
        }
        if (progress > 90) {
            document.getElementById('tasks-status').textContent = '✅';
            progressText.textContent = 'Finalizando análisis...';
        }

        if (progress >= 100) {
            clearInterval(interval);
            progressText.textContent = 'Análisis completado';
            setTimeout(() => {
                showSaveResults();
            }, 1000);
        }
    }, 180);
}

// ===== PASO 6: GUARDAR RESULTADOS =====

function showSaveResults() {
    showStep(6);
    loadDriveFolders();
}

function loadDriveFolders() {
    // Simular carga de carpetas desde Drive
    console.log('Cargando carpetas de Drive...');
    // Aquí iría la llamada AJAX para obtener las carpetas reales
}

const playPath = 'M5.25 5.25l13.5 6.75-13.5 6.75V5.25z';
const pausePath = 'M15.75 5.25v13.5m-7.5-13.5v13.5';

function toggleAudioPlayback() {
    const playBtn = document.querySelector('.play-btn');
    const iconPath = playBtn.querySelector('path');

    if (!audioPlayer) {
        audioPlayer = document.getElementById('recorded-audio');
    }

    if (!audioPlayer.src) {
        const src = typeof audioData === 'string' ? audioData : URL.createObjectURL(audioData);
        audioPlayer.src = src;
    }

    if (audioPlayer.paused) {
        audioPlayer.play();
        if (iconPath) iconPath.setAttribute('d', pausePath);
    } else {
        audioPlayer.pause();
        if (iconPath) iconPath.setAttribute('d', playPath);
    }
}

function stopAudioPlayback() {
    const playBtn = document.querySelector('.play-btn');
    const iconPath = playBtn.querySelector('path');
    if (!audioPlayer) return;
    audioPlayer.pause();
    audioPlayer.currentTime = 0;
    resetAudioProgress();
    if (iconPath) iconPath.setAttribute('d', playPath);
}

function resetAudioProgress() {
    const timeline = document.querySelector('.timeline-progress');
    if (timeline) timeline.style.width = '0%';
}

function updateAudioProgress() {
    const timeline = document.querySelector('.timeline-progress');
    if (!audioPlayer || !audioPlayer.duration) return;
    const percent = (audioPlayer.currentTime / audioPlayer.duration) * 100;
    if (timeline) timeline.style.width = percent + '%';
}

function downloadAudio() {
    showNotification('Descargando archivo de audio...', 'info');
    // Aquí iría la lógica para descargar el audio
}

function saveToDatabase() {
    const meetingName = document.getElementById('meeting-name').value.trim();
    const rootFolder = document.getElementById('root-folder-select').value;
    const subfolder = document.getElementById('subfolder-select').value;

    if (!meetingName) {
        showNotification('Por favor ingresa un nombre para la reunión', 'error');
        return;
    }

    showNotification('Iniciando guardado en base de datos...', 'info');
    setTimeout(() => {
        processDatabaseSave();
    }, 1000);
}

// ===== PASO 7: GUARDANDO EN BD =====

function processDatabaseSave() {
    showStep(7);

    const progressBar = document.getElementById('save-progress');
    const progressText = document.getElementById('save-progress-text');
    const progressPercent = document.getElementById('save-progress-percent');

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 8 + 3;
        if (progress > 100) progress = 100;

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';

        // Actualizar estados de guardado
        if (progress > 20) {
            document.getElementById('audio-upload-status').textContent = '✅';
            progressText.textContent = 'Guardando transcripción...';
        }
        if (progress > 50) {
            document.getElementById('transcription-save-status').textContent = '✅';
            progressText.textContent = 'Almacenando análisis...';
        }
        if (progress > 80) {
            document.getElementById('analysis-save-status').textContent = '✅';
            progressText.textContent = 'Creando tareas...';
        }
        if (progress > 95) {
            document.getElementById('tasks-save-status').textContent = '✅';
            progressText.textContent = 'Finalizando guardado...';
        }

        if (progress >= 100) {
            clearInterval(interval);
            progressText.textContent = 'Guardado completado';
            setTimeout(() => {
                showCompletion();
            }, 1000);
        }
    }, 200);
}

// ===== PASO 8: COMPLETADO =====

function showCompletion() {
    showStep(8);
}

// ===== FUNCIONES DE NAVEGACIÓN =====

function goBackToRecording() {
    if (confirm('¿Estás seguro de que quieres volver a grabar? Se perderá el progreso actual.')) {
        window.location.href = '/new-meeting';
    }
}

function goBackToTranscription() {
    showStep(3);
}

function goBackToAnalysis() {
    showStep(4);
}

function cancelProcess() {
    if (confirm('¿Estás seguro de que quieres cancelar? Se perderá todo el progreso.')) {
        window.location.href = '/new-meeting';
    }
}

function viewMeetingDetails() {
    showNotification('Redirigiendo a detalles de la reunión...', 'info');
    // Aquí iría la redirección a la vista de detalles
}

function goToMeetings() {
    showNotification('Redirigiendo a lista de reuniones...', 'info');
    // Aquí iría la redirección a la lista de reuniones
}

function createNewMeeting() {
    window.location.href = '/new-meeting';
}

// ===== FUNCIONES AUXILIARES =====

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;

    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };

    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${icons[type]}</span>
            <span class="notification-message">${message}</span>
        </div>
    `;

    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        z-index: 3000;
        animation: slideIn 0.3s ease;
        color: white;
        font-weight: 500;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function base64ToBlob(base64) {
    const parts = base64.split(',');
    const mime = parts[0].match(/:(.*?);/)[1] || 'audio/webm';
    const binary = atob(parts[1]);
    const len = binary.length;
    const buffer = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        buffer[i] = binary.charCodeAt(i);
    }
    return new Blob([buffer], { type: mime });
}

function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
}

// Funciones para navbar móvil
function toggleMobileDropdown() {
    const dropdown = document.getElementById('mobile-dropdown');
    const overlay = document.getElementById('mobile-dropdown-overlay');

    dropdown.classList.toggle('show');
    overlay.classList.toggle('show');
}

function closeMobileDropdown() {
    const dropdown = document.getElementById('mobile-dropdown');
    const overlay = document.getElementById('mobile-dropdown-overlay');

    dropdown.classList.remove('show');
    overlay.classList.remove('show');
}

// ===== INICIALIZACIÓN =====

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeChangeSpeakerModal();
        closeGlobalSpeakerModal();
    }
});

// Cerrar modales al hacer click fuera
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal')) {
        closeChangeSpeakerModal();
        closeGlobalSpeakerModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    createParticles();

    audioPlayer = document.getElementById('recorded-audio');
    if (audioPlayer) {
        audioPlayer.addEventListener('timeupdate', updateAudioProgress);
        audioPlayer.addEventListener('ended', stopAudioPlayback);
    }

    const storedAudio = sessionStorage.getItem('recordingBlob');
    if (storedAudio) {
        audioData = storedAudio;
    }

    const segments = sessionStorage.getItem('recordingSegments');
    if (segments) {
        try {
            const arr = JSON.parse(segments);
            audioSegments = arr.map(base64ToBlob);
        } catch (e) {
            console.error('Error al cargar segmentos de audio', e);
        }
    }

    const meta = sessionStorage.getItem('recordingMetadata');
    if (meta) {
        try {
            const parsed = JSON.parse(meta);
            console.log('Segmentos grabados:', parsed.segmentCount, 'Duración:', parsed.durationMs, 'ms');
        } catch (e) {
            console.error('Error al leer metadata de grabación', e);
        }
    }

    // Iniciar automáticamente el procesamiento de audio
    setTimeout(() => {
        startAudioProcessing();
    }, 1000);
});

// Agregar estilos de animación para las notificaciones
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Exponer funciones globalmente
window.selectAnalyzer = selectAnalyzer;
window.startAnalysis = startAnalysis;
window.playFullAudio = playFullAudio;
window.saveTranscriptionAndContinue = saveTranscriptionAndContinue;
window.goBackToRecording = goBackToRecording;
window.goBackToTranscription = goBackToTranscription;
window.goBackToAnalysis = goBackToAnalysis;
window.cancelProcess = cancelProcess;
window.toggleAudioPlayback = toggleAudioPlayback;
window.stopAudioPlayback = stopAudioPlayback;
window.resetAudioProgress = resetAudioProgress;
window.downloadAudio = downloadAudio;
window.saveToDatabase = saveToDatabase;
window.viewMeetingDetails = viewMeetingDetails;
window.goToMeetings = goToMeetings;
window.createNewMeeting = createNewMeeting;
window.toggleMobileDropdown = toggleMobileDropdown;
window.closeMobileDropdown = closeMobileDropdown;
window.playSegmentAudio = playSegmentAudio;
window.openChangeSpeakerModal = openChangeSpeakerModal;
window.closeChangeSpeakerModal = closeChangeSpeakerModal;
window.confirmSpeakerChange = confirmSpeakerChange;
window.openGlobalSpeakerModal = openGlobalSpeakerModal;
window.closeGlobalSpeakerModal = closeGlobalSpeakerModal;
window.confirmGlobalSpeakerChange = confirmGlobalSpeakerChange;
window.seekAudio = seekAudio;
