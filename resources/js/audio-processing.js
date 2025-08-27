import { loadAudioBlob, clearAllAudio } from './idb.js';
let __lastNotification = { msg: null, type: null, ts: 0 };

// Retry helper for IndexedDB loads to avoid transient race conditions on navigation
async function loadAudioFromIdbWithRetries(key, tries = 5, delayMs = 200) {
    for (let i = 0; i < tries; i++) {
        try {
            const blob = await loadAudioBlob(key);
            if (blob) return blob;
        } catch (_) {}
        await new Promise(r => setTimeout(r, delayMs));
    }
    return null;
}

// ===== VARIABLES GLOBALES =====
let currentStep = 1;
let selectedAnalyzer = 'general';
let availableAnalyzers = [];
let audioData = null;
let audioSegments = [];
// Array que almacena la transcripción completa. Cada elemento
// representa un segmento con propiedades como:
// {
//   speaker: 'Hablante 1',
//   time: '00:00 - 00:05',    // o marca inicial en ms
//   text: 'Contenido transcrito',
//   start: 0,                 // segundos
//   end: 5
// }
let transcriptionData = [];
let audioPlayer = null;
let currentSegmentIndex = null;
let segmentEndHandler = null;
let analysisResults = null;
let finalDrivePath = '';
let finalAudioDuration = 0;
let finalSpeakerCount = 0;
let finalTasks = [];

// Mensajes que se mostrarán mientras se genera la transcripción
const typingMessages = [
    "Estoy trabajando en tu transcripción...",
    "Sé paciente, esto puede tardar un tiempo..."
];
let typingInterval = null;

// Convierte la transcripción en texto plano para incluirla en prompts
function serializeTranscription() {
    return transcriptionData
        .map(seg => `${seg.speaker} [${typeof seg.start === 'number' ? formatTime(seg.start * 1000) : seg.time}]: ${seg.text}`)
        .join('\n');
}

// Inserta la transcripción serializada en la plantilla de prompt
function buildChatGPTPrompt(template = '') {
    const serialized = serializeTranscription();
    return template.replace('{transcription}', serialized);
}

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

async function loadAvailableAnalyzers() {
    try {
        const res = await fetch('/api/analyzers');

        if (res.status === 401 || res.status === 403) {
            window.location.href = '/login';
            return;
        }

        if (!res.ok) {
            showNotification('No se pudieron cargar los analizadores', 'error');
            return;
        }

        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            showNotification('Respuesta inesperada del servidor', 'error');
            return;
        }

        availableAnalyzers = await res.json();
        renderAnalyzerCards();
    } catch (e) {
        console.error('Error fetching analyzers', e);
        showNotification('No se pudo conectar con el servidor', 'error');
    }
}

function renderAnalyzerCards() {
    const grid = document.getElementById('analyzer-grid');
    const msg = document.getElementById('no-analyzers-msg');
    if (!grid) return;
    grid.innerHTML = '';

    if (!availableAnalyzers.length) {
        if (msg) msg.style.display = 'block';
        return;
    }
    if (msg) msg.style.display = 'none';

    availableAnalyzers.forEach((a, idx) => {
        const card = document.createElement('div');
        card.className = 'analyzer-card';
        card.dataset.analyzer = a.id;
        card.addEventListener('click', () => selectAnalyzer(a.id));
        if ((selectedAnalyzer && selectedAnalyzer === a.id) || (!selectedAnalyzer && idx === 0)) {
            card.classList.add('active');
            selectedAnalyzer = a.id;
        }
        card.innerHTML = `
            <div class="analyzer-icon">${a.icon || '🧠'}</div>
            <h3 class="analyzer-title">${a.name}</h3>
            <p class="analyzer-description">${a.description || ''}</p>
        `;
        grid.appendChild(card);
    });
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
        if (audioData) {
            progressBar.style.width = '100%';
            progressPercent.textContent = '100%';
            progressText.textContent = 'Archivo listo';
            document.getElementById('audio-quality-status').textContent = '✅';
            document.getElementById('speaker-detection-status').textContent = '✅';
            document.getElementById('noise-reduction-status').textContent = '✅';
            setTimeout(() => {
                startTranscription();
            }, 500);
        } else {
            progressBar.style.width = '100%';
            progressPercent.textContent = '100%';
            progressText.textContent = 'No hay segmentos de audio';
            document.getElementById('audio-quality-status').textContent = '⚠️';
        }
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

    const existingRetry = document.getElementById('retry-transcription');
    if (existingRetry) existingRetry.remove();

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
    const audioBlob = typeof audioData === 'string' ? base64ToBlob(audioData) : audioData;
    formData.append('audio', audioBlob, 'recording.webm');
    formData.append('language', lang);


    try {
        const { data } = await axios.post('/transcription', formData, {
            onUploadProgress: (e) => {
                if (e.total) {
                    const percent = (e.loaded / e.total) * 10; // 0-10% of total
                    progressBar.style.width = percent + '%';
                    progressPercent.textContent = Math.round(percent) + '%';
                }
            }
        });
        console.log("✅ Transcripción iniciada:", data);

        progressBar.style.width = '10%';
        progressPercent.textContent = '10%';
        progressText.textContent = 'En cola...';

        pollTranscription(data.id);
    } catch (e) {
        console.error("❌ Error:", e);

        let userMessage = '';
        if (e.code === 'ERR_CONNECTION') {
            userMessage = '⚠️ Problema de conexión. Verifica tu conexión e intenta nuevamente.';
        } else if (e.code === 'ERR_TIMEOUT') {
            userMessage = '⚠️ La solicitud tardó demasiado. Reintenta o revisa tu conexión.';
        } else if (e.response) {
            console.error("📡 STATUS:", e.response.status);
            console.error("📩 HEADERS:", e.response.headers);
            console.error("📦 BODY:", e.response.data);
            userMessage = "🧠 Error del servidor: " + JSON.stringify(e.response.data);
        } else {
            userMessage = "❌ Error desconocido. Revisa consola.";
        }

        alert(userMessage);

        downloadAudio();
        showNotification('Se descargó una copia de seguridad del audio', 'info');

        progressText.textContent = 'Error al subir audio. Reintenta o verifica tu conexión.';
        showRetryTranscription();
    }
}

function showRetryTranscription() {
    const progressSection = document.querySelector('#step-transcription .progress-section');
    if (!progressSection) return;

    let retry = document.getElementById('retry-transcription');
    if (retry) return;

    retry = document.createElement('button');
    retry.id = 'retry-transcription';
    retry.className = 'btn btn-link';
    retry.textContent = 'Reintentar transcripción';
    retry.addEventListener('click', (e) => {
        e.preventDefault();
        retry.remove();
        startTranscription();
    });

    progressSection.appendChild(retry);
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
                downloadAudio();
                showNotification('Se descargó una copia de seguridad del audio', 'info');
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
                <textarea class="transcript-text" placeholder="Texto de la transcripción..." readonly>${segment.text}</textarea>
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

function getPlayIcon(cls) {
    return `<svg class="${cls}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" /></svg>`;
}

function getPauseIcon(cls) {
    return `<svg class="${cls}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" /></svg>`;
}

function updateSegmentButtons(activeIndex) {
    transcriptionData.forEach((_, idx) => {
        const segmentEl = document.querySelector(`[data-segment="${idx}"]`);
        if (!segmentEl) return;
        const headerBtn = segmentEl.querySelector('.segment-controls .control-btn');
        const miniBtn = segmentEl.querySelector('.play-btn-mini');
        const isActive = idx === activeIndex;
        if (headerBtn) headerBtn.innerHTML = isActive ? getPauseIcon('btn-icon') : getPlayIcon('btn-icon');
        if (miniBtn) miniBtn.innerHTML = isActive ? getPauseIcon('play-icon') : getPlayIcon('play-icon');
    });
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

    if (currentSegmentIndex === segmentIndex && !audioPlayer.paused) {
        audioPlayer.pause();
        if (segmentEndHandler) {
            audioPlayer.removeEventListener('timeupdate', segmentEndHandler);
            segmentEndHandler = null;
        }
        updateSegmentButtons(null);
        currentSegmentIndex = null;
        return;
    }

    if (segmentEndHandler) {
        audioPlayer.removeEventListener('timeupdate', segmentEndHandler);
        segmentEndHandler = null;
    }

    if (!audioPlayer.paused) {
        audioPlayer.pause();
    }
    audioPlayer.currentTime = segment.start;
    const stopTime = segment.end;

    segmentEndHandler = () => {
        if (audioPlayer.currentTime >= stopTime) {
            audioPlayer.pause();
            audioPlayer.removeEventListener('timeupdate', segmentEndHandler);
            segmentEndHandler = null;
            updateSegmentButtons(null);
            currentSegmentIndex = null;
        }
    };

    audioPlayer.addEventListener('timeupdate', segmentEndHandler);
    if (audioPlayer.readyState < 2) {
        alert('El audio no está listo para reproducirse.');
        audioPlayer.removeEventListener('timeupdate', segmentEndHandler);
        segmentEndHandler = null;
        updateSegmentButtons(null);
        currentSegmentIndex = null;
        return;
    }
    audioPlayer.play().catch(error => {
        console.warn('Error reproduciendo segmento de audio:', error);
        alert('No se pudo reproducir este segmento de audio.');
        audioPlayer.removeEventListener('timeupdate', segmentEndHandler);
        segmentEndHandler = null;
        updateSegmentButtons(null);
        currentSegmentIndex = null;
    });
    currentSegmentIndex = segmentIndex;
    updateSegmentButtons(segmentIndex);
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
        audioPlayer = document.getElementById('recorded-audio');
    }
    if (!audioPlayer.src) {
        const src = typeof audioData === 'string' ? audioData : URL.createObjectURL(audioData);
        audioPlayer.src = src;
    }
    audioPlayer.currentTime = targetTime;
}

function seekFullAudio(event) {
    if (!audioPlayer) {
        audioPlayer = document.getElementById('recorded-audio');
    }
    if (!audioPlayer || !audioPlayer.duration) return;

    const timeline = event.currentTarget;
    const rect = timeline.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const percentage = (clickX / rect.width);

    const progress = timeline.querySelector('.timeline-progress');
    if (progress) progress.style.width = (percentage * 100) + '%';

    audioPlayer.currentTime = audioPlayer.duration * percentage;
}

function saveTranscriptionAndContinue() {
    let segments = Array.from(document.querySelectorAll('.transcript-segment'));
    const domCount = segments.length;
    const dataCount = transcriptionData.length;

    if (domCount !== dataCount) {
        // Si hay más segmentos en la UI, recorta el NodeList para que coincida con transcriptionData
        if (domCount > dataCount) {
            segments = segments.slice(0, dataCount);

            console.warn(`Había más segmentos en la interfaz de los que hay en memoria. Solo se guardarán los primeros ${dataCount}.`);
        } else {
            // Si hay menos en la UI, solo muestra advertencia en consola y no guarda
            const diff = dataCount - domCount;
            console.warn(`No se puede guardar la transcripción: hay ${domCount} segmento(s) visibles y ${dataCount} en memoria; faltan ${diff} segmento(s).`);
            return;
        }
    }

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
    const errorEl = document.getElementById('analysis-error-message');
    if (errorEl) {
        errorEl.style.display = 'none';
        errorEl.textContent = '';
    }
    if (!selectedAnalyzer) {
        showNotification('Por favor selecciona un tipo de análisis', 'error');
        return;
    }
    showNotification(`Iniciando análisis: ${selectedAnalyzer}`, 'info');
    processAnalysis();
}

// ===== PASO 5: PROCESAMIENTO DE ANÁLISIS =====

async function processAnalysis() {
    showStep(5);

    const progressBar = document.getElementById('analysis-progress');
    const progressText = document.getElementById('analysis-progress-text');
    const progressPercent = document.getElementById('analysis-progress-percent');

    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    progressText.textContent = 'Analizando contenido...';

    let progress = 0;
    const interval = setInterval(() => {
        progress = Math.min(95, progress + 3);
        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';
    }, 1000);

    try {
        const res = await axios.post('/analysis', {
            analyzer_id: selectedAnalyzer,
            transcript: serializeTranscription(),
        });
        analysisResults = res.data;

        document.getElementById('summary-status').textContent = '✅';
        document.getElementById('keypoints-status').textContent = '✅';
        document.getElementById('tasks-status').textContent = '✅';

        progress = 100;
        progressBar.style.width = '100%';
        progressPercent.textContent = '100%';
        progressText.textContent = 'Análisis completado';
    } catch (e) {
        console.error('Error en análisis', e);
        progressText.textContent = 'Error en análisis';
        showNotification('Error al analizar la reunión', 'error');
        downloadAudio();
        showNotification('Se descargó una copia de seguridad del audio', 'info');
        clearInterval(interval);
        return;
    }

    clearInterval(interval);
    setTimeout(() => {
        showSaveResults();
    }, 1000);
}

// ===== PASO 6: GUARDAR RESULTADOS =====

async function showSaveResults() {
    await loadDriveFolders();
    showStep(6);
    updateAnalysisPreview();
}

function updateAnalysisPreview() {
    if (!analysisResults) return;

    // Mostrar resumen, keypoints y tareas como antes
    const summaryEl = document.getElementById('analysis-summary');
    if (summaryEl) summaryEl.textContent = analysisResults.summary || '';

    const kpList = document.getElementById('analysis-keypoints');
    if (kpList) {
        kpList.innerHTML = '';
        (analysisResults.keyPoints || []).forEach(p => {
            const li = document.createElement('li');
            li.textContent = p;
            kpList.appendChild(li);
        });
    }

    const tasksList = document.getElementById('analysis-tasks');
    if (tasksList) {
        tasksList.innerHTML = '';
        (analysisResults.tasks || []).forEach(t => {
            const div = document.createElement('div');
            div.className = 'task-item';
            div.innerHTML = `
                <div class="task-info">
                    <span class="task-title">${t.text || t.title || ''}</span>
                    ${t.assignee ? `<span class="task-assignee">Asignado a: ${t.assignee}</span>` : ''}
                </div>
                ${t.due_date ? `<span class="task-deadline">📅 ${t.due_date}</span>` : ''}
            `;
            tasksList.appendChild(div);
        });
    }

    // Mostrar transcripción real (no hardcodeada)
    const transcriptEl = document.getElementById('analysis-transcript');
    if (transcriptEl) {
        transcriptEl.innerHTML = '';
        // Usar transcriptionData real
        if (Array.isArray(transcriptionData) && transcriptionData.length > 0) {
            transcriptEl.innerHTML = transcriptionData.map(seg =>
                `<div class="transcript-line"><span class="transcript-speaker">${seg.speaker}:</span> <span class="transcript-text">${seg.text}</span></div>`
            ).join('');
        } else {
            transcriptEl.innerHTML = '<p>No hay transcripción disponible.</p>';
        }
    }

    // Mostrar reproductor de audio original completo y botón de descarga con tiempo real
    const audioSection = document.getElementById('analysis-audio');
    if (audioSection) {
        let audioUrl = '';
        if (typeof audioData === 'string') {
            // Si es base64
            const blob = base64ToBlob(audioData);
            audioUrl = URL.createObjectURL(blob);
        } else if (audioData instanceof Blob) {
            audioUrl = URL.createObjectURL(audioData);
        }
        audioSection.innerHTML = `
            <audio id="full-audio-player" controls src="${audioUrl}"></audio>
            <span id="audio-time" style="margin-left:10px; color:#aaa;"></span>
            <button id="download-audio-btn" class="download-audio-btn">Descargar audio original</button>
        `;
        const audioPlayer = document.getElementById('full-audio-player');
        const timeEl = document.getElementById('audio-time');
        if (audioPlayer && timeEl) {
            const updateTime = () => {
                const current = formatTime(audioPlayer.currentTime * 1000);
                const total = formatTime(audioPlayer.duration * 1000);
                timeEl.textContent = `${current} / ${total}`;
            };
            audioPlayer.addEventListener('timeupdate', updateTime);
            audioPlayer.addEventListener('loadedmetadata', updateTime);
            updateTime();
        }
        const downloadBtn = document.getElementById('download-audio-btn');
        if (downloadBtn) {
            downloadBtn.onclick = function() {
                let blob = (typeof audioData === 'string') ? base64ToBlob(audioData) : audioData;
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'audio_reunion.webm';
                document.body.appendChild(a);
                a.click();
                setTimeout(() => {
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }, 100);
            };
        }
    }
}

async function loadDriveFolders() {
    try {
        const res = await fetch('/drive/sync-subfolders');

        if (res.status === 401 || res.status === 403) {
            window.location.href = '/login';
            return;
        }

        if (!res.ok) {
            showNotification('No se pudieron cargar las carpetas de Drive', 'error');
            return;
        }

        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            showNotification('Respuesta inesperada del servidor', 'error');
            return;
        }

        const data = await res.json();

        const rootSelect = document.getElementById('root-folder-select');
        const transcriptionSelect = document.getElementById('transcription-subfolder-select');
        const audioSelect = document.getElementById('audio-subfolder-select');

        if (rootSelect) {
            rootSelect.innerHTML = '';
            if (data.root_folder) {
                const opt = document.createElement('option');
                opt.value = data.root_folder.google_id; // Usar google_id en lugar de id
                opt.textContent = `\uD83D\uDCC1 ${data.root_folder.name}`;
                rootSelect.appendChild(opt);
            }
        }

        const populateSubSelect = (select) => {
            if (!select) return;
            select.innerHTML = '';
            const list = data.subfolders || [];
            if (list.length) {
                const noneOpt = document.createElement('option');
                noneOpt.value = '';
                noneOpt.textContent = 'Sin subcarpeta';
                select.appendChild(noneOpt);
                list.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.google_id; // Usar google_id para Drive
                    opt.textContent = `\uD83D\uDCC2 ${f.name}`;
                    select.appendChild(opt);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No se encontraron subcarpetas';
                select.appendChild(opt);
            }
        };

        populateSubSelect(transcriptionSelect);
        populateSubSelect(audioSelect);
    } catch (e) {
        console.error('Error syncing subfolders', e);
        showNotification('No se pudo conectar con el servidor', 'error');
    }
}

const playPath = 'M5.25 5.25l13.5 6.75-13.5 6.75V5.25z';
const pausePath = 'M15.75 5.25v13.5m-7.5-13.5v13.5';

function toggleAudioPlayback() {
    const playBtn = document.querySelector('.processing-step.active .play-btn');
    const iconPath = playBtn ? playBtn.querySelector('path') : null;

    if (!audioPlayer) {
        audioPlayer = document.getElementById('recorded-audio');
    }

    if (!audioPlayer.src) {
        const src = typeof audioData === 'string' ? audioData : URL.createObjectURL(audioData);
        audioPlayer.src = src;
    }

    const progress = document.querySelector('.processing-step.active .timeline-progress');
    if (audioPlayer.paused) {
        audioPlayer.play();
        if (iconPath) iconPath.setAttribute('d', pausePath);
        if (progress) progress.classList.add('playing');
    } else {
        audioPlayer.pause();
        if (iconPath) iconPath.setAttribute('d', playPath);
        if (progress) progress.classList.remove('playing');
    }
}

function stopAudioPlayback() {
    const playBtn = document.querySelector('.processing-step.active .play-btn');
    const iconPath = playBtn ? playBtn.querySelector('path') : null;
    if (!audioPlayer) return;
    audioPlayer.pause();
    audioPlayer.currentTime = 0;
    resetAudioProgress();
    if (iconPath) iconPath.setAttribute('d', playPath);
}

function resetAudioProgress() {
    const timeline = document.querySelector('.processing-step.active .timeline-progress');
    if (timeline) timeline.style.width = '0%';
    const timeEl = document.getElementById('full-audio-time');
    if (timeEl) {
        const total = audioPlayer && audioPlayer.duration ? formatTime(audioPlayer.duration * 1000) : '00:00';
        timeEl.textContent = `00:00 / ${total}`;
    }
}

function updateAudioProgress() {
    const timeline = document.querySelector('.processing-step.active .timeline-progress');
    const timeEl = document.getElementById('full-audio-time');
    if (!audioPlayer || !audioPlayer.duration) return;
    const percent = (audioPlayer.currentTime / audioPlayer.duration) * 100;
    if (timeline) timeline.style.width = percent + '%';
    if (timeEl) {
        const current = formatTime(audioPlayer.currentTime * 1000);
        const total = formatTime(audioPlayer.duration * 1000);
        timeEl.textContent = `${current} / ${total}`;
    }
}

function downloadAudio() {
    if (!audioData) {
        showNotification('No hay audio para descargar', 'error');
        return;
    }

    // Convertir a Blob si es una cadena base64
    const blob = (typeof audioData === 'string') ? base64ToBlob(audioData) : audioData;
    const url = URL.createObjectURL(blob);

    // Crear un enlace temporal para iniciar la descarga
    const a = document.createElement('a');
    a.href = url;
    a.download = 'audio_reunion.webm';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    // Revocar el ObjectURL después de la descarga
    URL.revokeObjectURL(url);

    showNotification('Descarga iniciada', 'success');
}

async function saveToDatabase() {
    const meetingName = document.getElementById('meeting-name').value.trim();
    const rootFolder = document.getElementById('root-folder-select').value;
    const transcriptionSubfolder = document.getElementById('transcription-subfolder-select').value;
    const audioSubfolder = document.getElementById('audio-subfolder-select').value;

    if (!meetingName) {
        showNotification('Por favor ingresa un nombre para la reunión', 'error');
        return;
    }

    showStep(7);
    const result = await processDatabaseSave(meetingName, rootFolder, transcriptionSubfolder, audioSubfolder);
    if (!result.success) {
        const errorEl = document.getElementById('analysis-error-message');
        if (errorEl) {
            errorEl.textContent = result.message || 'Error al guardar los datos';
            errorEl.style.display = 'block';
        }
        showStep(4);
    }
}

// ===== PASO 7: GUARDANDO EN BD =====

async function processDatabaseSave(meetingName, rootFolder, transcriptionSubfolder, audioSubfolder) {
    const progressBar = document.getElementById('save-progress');
    const progressText = document.getElementById('save-progress-text');
    const progressPercent = document.getElementById('save-progress-percent');
    const setProgress = (value, text) => {
        progressBar.style.width = value + '%';
        progressPercent.textContent = Math.round(value) + '%';
        if (text) progressText.textContent = text;
    };

    // Contenedor de mensajes detallados
    const addMessage = (msg) => {
        const section = document.querySelector('#step-saving .progress-section');
        if (!section) return;
        let log = document.getElementById('save-progress-log');
        if (!log) {
            log = document.createElement('div');
            log.id = 'save-progress-log';
            log.className = 'progress-log';
            section.appendChild(log);
        }
        const p = document.createElement('p');
        p.textContent = msg;
        log.appendChild(p);
    };

    const resetUI = () => {
        setProgress(0, 'Preparando guardado...');
        document.getElementById('audio-upload-status').textContent = '⏳';
        document.getElementById('transcription-save-status').textContent = '⏳';
        document.getElementById('analysis-save-status').textContent = '⏳';
        document.getElementById('tasks-save-status').textContent = '⏳';
        const log = document.getElementById('save-progress-log');
        if (log) log.innerHTML = '';
    };

    resetUI();
    addMessage('Iniciando guardado de resultados');

    const transcription = transcriptionData;
    const analysis = analysisResults;

    let audio = audioData;
    let audioMimeType = 'audio/webm';
    if (audio && typeof audio !== 'string') {
        audioMimeType = audio.type || audioMimeType;
        try {
            audio = await blobToBase64(audio);
        } catch (e) {
            console.error('Error al convertir audio a base64', e);
            showNotification('No se pudo procesar el audio', 'error');
            resetUI();
            showStep(4);
            return { success: false, message: 'No se pudo procesar el audio' };
        }
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        console.error('❌ CSRF token no encontrado');
        showNotification('Error de configuración: Token de seguridad no encontrado', 'error');
        resetUI();
        showStep(4);
        return { success: false, message: 'Token de seguridad no encontrado' };
    }

    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken.getAttribute('content')
    };

    try {
        setProgress(10, 'Guardando resultados...');
        addMessage('Enviando datos al servidor...');

        // Verificar si es un audio pendiente
        if (window.pendingAudioInfo) {
            addMessage('Completando procesamiento de audio pendiente...');

            console.log('📦 Datos a enviar:', {
                pending_id: window.pendingAudioInfo.pendingId,
                meeting_name: meetingName,
                root_folder: rootFolder,
                transcription_subfolder: transcriptionSubfolder,
                audio_subfolder: audioSubfolder,
                transcription_data: transcription,
                analysis_results: analysis
            });

            const response = await fetch('/api/pending-meetings/complete', {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    pending_id: window.pendingAudioInfo.pendingId,
                    meeting_name: meetingName,
                    root_folder: rootFolder,
                    transcription_subfolder: transcriptionSubfolder,
                    audio_subfolder: audioSubfolder,
                    transcription_data: transcription,
                    analysis_results: analysis
                })
            });

            console.log('📡 Respuesta del servidor:', {
                status: response.status,
                statusText: response.statusText,
                headers: response.headers,
                ok: response.ok
            });

            if (!response.ok) {
                let errorMsg = 'Error al completar audio pendiente';
                try {
                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        const err = await response.json();
                        if (err && err.error) errorMsg = err.error;
                    } else {
                        // Si no es JSON, probablemente es una página de error HTML
                        const errorText = await response.text();
                        console.error('Respuesta no JSON recibida:', errorText.substring(0, 500));
                        errorMsg = `Error del servidor (${response.status}): ${response.statusText}`;
                    }
                } catch (parseError) {
                    console.error('Error al parsear respuesta de error:', parseError);
                    errorMsg = `Error del servidor (${response.status}): ${response.statusText}`;
                }

                addMessage(`⚠️ ${errorMsg}`);
                showNotification(errorMsg, 'error');
                resetUI();
                showStep(4);
                return { success: false, message: errorMsg };
            }

            let result;
            try {
                result = await response.json();
            } catch (jsonError) {
                console.error('Error al parsear JSON de respuesta exitosa:', jsonError);
                const responseText = await response.text();
                console.error('Contenido de respuesta:', responseText.substring(0, 500));
                addMessage('⚠️ Error al procesar respuesta del servidor');
                showNotification('Error al procesar respuesta del servidor', 'error');
                resetUI();
                showStep(4);
                return { success: false, message: 'Error al procesar respuesta del servidor' };
            }

            finalDrivePath = result.drive_path || '';
            finalAudioDuration = result.audio_duration || 0;
            finalSpeakerCount = result.speaker_count || 0;
            finalTasks = result.tasks || [];

            // Limpiar datos pendientes
            window.pendingAudioInfo = null;

        } else {
            // Flujo normal
            const response = await fetch('/drive/save-results', {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    meetingName,
                    rootFolder,
                    transcriptionSubfolder,
                    audioSubfolder,
                    transcriptionData: transcription,
                    analysisResults: analysis,
                    audioData: audio,
                    audioMimeType
                })
            });

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = '/login';
                    return { success: false, message: 'No autorizado' };
                }

                let errorMsg = 'Error al guardar los datos';
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    try {
                        const err = await response.json();
                        if (err && err.message) errorMsg = err.message;
                    } catch (_) {}
                } else {
                    errorMsg = 'Respuesta inesperada del servidor';
                }

                addMessage(`⚠️ ${errorMsg}`);
                showNotification(errorMsg, 'error');
                resetUI();
                showStep(4);
                return { success: false, message: errorMsg };
            }

            const result = await response.json();
            finalDrivePath = result.drive_path || '';
            finalAudioDuration = result.audio_duration || 0;
            finalSpeakerCount = result.speaker_count || 0;
            finalTasks = result.tasks || [];
        }

        document.getElementById('audio-upload-status').textContent = '✅';
        document.getElementById('transcription-save-status').textContent = '✅';
        document.getElementById('analysis-save-status').textContent = '✅';
        document.getElementById('tasks-save-status').textContent = '✅';

        setProgress(100, 'Guardado completado');
        addMessage('Resultados almacenados con éxito');
        setTimeout(async () => {
            // Cleanup local temporary audio after successful save
            try {
                await clearAllAudio();
            } catch (_) {}
            try { sessionStorage.removeItem('uploadedAudioKey'); } catch (_) {}
            try { sessionStorage.removeItem('recordingBlob'); } catch (_) {}
            try { localStorage.removeItem('pendingAudioData'); } catch (_) {}
            showCompletion({
                drivePath: finalDrivePath,
                audioDuration: finalAudioDuration,
                speakerCount: finalSpeakerCount,
                tasks: finalTasks
            });
        }, 500);

        return { success: true };
    } catch (e) {
        console.error('Error al guardar en base de datos', e);
        const msg = e.message || 'No se pudo conectar con el servidor';
        addMessage(`⚠️ ${msg}`);
        showNotification(msg, 'error');
        downloadAudio();
        showNotification('Se descargó una copia de seguridad del audio', 'info');
        resetUI();
        showStep(4);
        return { success: false, message: msg };
    }
}

// ===== PASO 8: COMPLETADO =====

function showCompletion({ drivePath, audioDuration, speakerCount, tasks }) {
    showStep(8);

    const pathEl = document.getElementById('completion-drive-path');
    if (pathEl) pathEl.textContent = drivePath || '';

    const durationEl = document.getElementById('completion-audio-duration');
    if (durationEl) durationEl.textContent = `${formatTime((audioDuration || 0) * 1000)} minutos`;

    const speakersEl = document.getElementById('completion-speaker-count');
    if (speakersEl) speakersEl.textContent = `${speakerCount} participante${speakerCount === 1 ? '' : 's'}`;

    const tasksEl = document.getElementById('completion-task-count');
    if (tasksEl) tasksEl.textContent = `${(tasks ? tasks.length : 0)} tarea${tasks && tasks.length === 1 ? '' : 's'}`;
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
    // Deduplicate frequent identical notifications and avoid overlaps
    const now = Date.now();
    if (__lastNotification.msg === message && __lastNotification.type === type && (now - __lastNotification.ts) < 1000) {
        return;
    }
    __lastNotification = { msg: message, type, ts: now };

    // Remove existing notifications to prevent stacking overlays
    document.querySelectorAll('.notification').forEach(n => n.remove());

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

function base64ToBlob(base64, mimeType = null) {
    // Si el base64 incluye el prefijo data:mime/type;base64,
    if (base64.includes(',')) {
        const parts = base64.split(',');
        const mime = mimeType || (parts[0].match(/:(.*?);/)?.[1] || 'audio/webm');
        const binary = atob(parts[1]);
        const len = binary.length;
        const buffer = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            buffer[i] = binary.charCodeAt(i);
        }
        return new Blob([buffer], { type: mime });
    } else {
        // Base64 sin prefijo
        const mime = mimeType || 'audio/webm';
        const binary = atob(base64);
        const len = binary.length;
        const buffer = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            buffer[i] = binary.charCodeAt(i);
        }
        return new Blob([buffer], { type: mime });
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

document.addEventListener('DOMContentLoaded', async function() {
    createParticles();
    await loadAvailableAnalyzers();

    audioPlayer = document.getElementById('recorded-audio');
    if (audioPlayer) {
        audioPlayer.addEventListener('timeupdate', updateAudioProgress);
        audioPlayer.addEventListener('loadedmetadata', updateAudioProgress);
        audioPlayer.addEventListener('ended', stopAudioPlayback);
    }

    const toggleSubfolders = document.getElementById('toggle-subfolders');
    const subfolderFields = document.getElementById('subfolder-fields');
    if (toggleSubfolders && subfolderFields) {
        toggleSubfolders.addEventListener('change', (e) => {
            subfolderFields.style.display = e.target.checked ? 'block' : 'none';
        });
    }

    // Verificar si es un audio pendiente
    const pendingAudioData = localStorage.getItem('pendingAudioData');
    if (pendingAudioData) {
        try {
            window.pendingAudioInfo = JSON.parse(pendingAudioData);
            console.log("✅ Audio pendiente detectado:", window.pendingAudioInfo);

            // Cargar el audio desde el servidor usando el tempFile
            if (window.pendingAudioInfo.tempFile) {
                try {
                    const response = await fetch(`/api/pending-meetings/audio/${window.pendingAudioInfo.tempFile}`);
                    const result = await response.json();

                    if (result.success && result.audioData) {
                        // Convertir base64 a blob
                        audioData = base64ToBlob(result.audioData, result.mimeType || 'audio/mpeg');
                        console.log("✅ Audio pendiente cargado desde servidor");

                        // Limpiar datos temporales
                        localStorage.removeItem('pendingAudioData');

                        // Iniciar procesamiento después de 1s
                        setTimeout(() => {
                            startAudioProcessing();
                        }, 1000);
                        return;
                    } else {
                        throw new Error(result.error || 'Error al cargar audio del servidor');
                    }
                } catch (fetchError) {
                    console.error("❌ Error al obtener audio del servidor:", fetchError);
                    showNotification('Error al cargar el archivo de audio: ' + fetchError.message, 'error');
                    return;
                }
            } else {
                console.error("❌ No se encontró el tempFile para el audio pendiente");
                showNotification('Error: Información de archivo incompleta', 'error');
                return;
            }
        } catch (e) {
            console.error("❌ Error al parsear datos del audio pendiente:", e);
            localStorage.removeItem('pendingAudioData');
        }
    }

    // Flujo normal: cargar audio desde IndexedDB o sessionStorage
    const uploadedKey = sessionStorage.getItem('uploadedAudioKey');
    if (uploadedKey) {
        try {
            audioData = await loadAudioFromIdbWithRetries(uploadedKey, 5, 250);
            if (!audioData) {
                showNotification('No se encontró el audio guardado. Intentando respaldo...', 'warning');
            }
        } catch (e) {
            console.error('Error loading audio from IndexedDB', e);
            showNotification('Error al cargar el audio guardado', 'error');
        }
    }

    // Si no se pudo cargar desde IndexedDB, intentar respaldos en sessionStorage
    if (!audioData) {
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

        if (!audioData && (!audioSegments || audioSegments.length === 0)) {
            showNotification('No se encontró audio para procesar', 'error');
            return;
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
window.saveTranscriptionAndContinue = saveTranscriptionAndContinue;
window.goBackToRecording = goBackToRecording;
window.goBackToTranscription = goBackToTranscription;
window.goBackToAnalysis = goBackToAnalysis;
window.cancelProcess = cancelProcess;
window.toggleAudioPlayback = toggleAudioPlayback;
window.seekFullAudio = seekFullAudio;
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
// Hacer accesible la transcripción para otros scripts o para depuración
window.transcriptionData = transcriptionData;
