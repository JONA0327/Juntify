import { saveAudioBlob, loadAudioBlob, clearAllAudio } from './idb.js';
import { showError, showSuccess } from './utils/alerts.js';

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
let systemAudioEnabled = true;
let microphoneAudioEnabled = true;
let systemAudioMuted = false;
let microphoneAudioMuted = false;
let meetingRecording = false;
let microphoneMutedBeforePause = null;
let meetingTimer = null;
let meetingStartTime = null;
let systemAudioStream = null;
let microphoneAudioStream = null;
let systemAnalyser = null;
let microphoneAnalyser = null;
let systemDataArray = null;
let microphoneDataArray = null;
let meetingAnimationId = null;
let systemSpectrogramCtx = null;
let microphoneSpectrogramCtx = null;
let systemGainNode = null;
let microphoneGainNode = null;
let meetingDestination = null;
let lastRecordingContext = null; // 'recording' | 'meeting' | 'upload'
let discardRequested = false;
let uploadedFile = null;
let fileUploadInitialized = false;
let pendingAudioBlob = null;
let pendingSaveContext = null;
let postponeMode = false;
window.postponeMode = postponeMode;
let limitWarningShown = false;
let timeWarnNotified = false; // evitar notificar múltiples veces
let currentRecordingFormat = null; // Almacenar el formato usado en la grabación actual
let failedAudioBlob = null; // Almacenar blob que falló al subir
let failedAudioName = null; // Nombre del archivo que falló
let retryAttempts = 0; // Contador de intentos de resubida
const MAX_RETRY_ATTEMPTS = 3; // Máximo número de reintentos
let pendingNavigationUrl = null;

function getUserPlanInfo() {
    const planCode = (window.userPlanCode || '').toString().toLowerCase();
    const role = (window.userRole || '').toString().toLowerCase();
    const isBasic = role === 'basic' || planCode === 'basic' || planCode === 'basico' || planCode.includes('basic');
    const isFree = role === 'free' || planCode === '' || planCode === 'free' || planCode.includes('free');
    const businessKeywords = ['negocios', 'business', 'buisness', 'negocio'];
    const isBusiness = businessKeywords.some(keyword => keyword && (role.includes(keyword) || planCode.includes(keyword)));

    let planName = 'tu plan actual';
    if (isBasic) {
        planName = 'Plan Basic';
    } else if (isBusiness) {
        planName = 'Plan Business';
    } else if (isFree) {
        planName = 'Plan Free';
    }

    return {
        planCode,
        role,
        isBasic,
        isBusiness,
        isFree,
        planName,
        belongsToOrg: !!window.userBelongsToOrganization,
    };
}

function getUploadLimitBytes(planInfo, hasPremium) {
    if (planInfo.isBusiness) {
        return 100 * 1024 * 1024;
    }

    if (planInfo.isBasic) {
        return 60 * 1024 * 1024;
    }

    if (planInfo.isFree) {
        return 50 * 1024 * 1024;
    }

    if (hasPremium) {
        return null;
    }

    return null;
}

// Función para obtener el mejor formato de audio disponible priorizando OGG
function getOptimalAudioFormat() {
    const formats = [
        'audio/ogg;codecs=opus',    // OGG/Opus - PRIORIDAD MÁXIMA para compatibilidad abierta
        'audio/ogg',                // OGG genérico como respaldo principal
        'audio/mp4',                // MP4 audio como alternativa
        'audio/mpeg',               // MP3 como último recurso tradicional
        'audio/webm;codecs=opus',   // WebM solo si es la única opción (evitar si es posible)
        'audio/webm'                // WebM genérico como último recurso
    ];

    for (const format of formats) {
        if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(format)) {
            console.log(`🎵 [Recording] Formato seleccionado: ${format}`);

            // Advertir si se usa Opus para que sepas que puede haber problemas de compatibilidad
            if (format.includes('opus')) {
                console.warn(`⚠️ [Recording] ADVERTENCIA: Usando Opus codec. Puede tener problemas de compatibilidad en reproductores móviles.`);
            }

            return format;
        }
    }

    console.error('🎵 [Recording] ERROR: Navegador no compatible con formatos de audio estándares');
    throw new Error('Este navegador no soporta los formatos de audio requeridos. Por favor, usa un navegador más reciente.');
}

// Función para obtener la extensión correcta del archivo basada en el formato usado
function getCorrectFileExtension(blob) {
    // Primero, intentar detectar por el tipo del blob convertido
    if (blob && blob.type) {
        if (blob.type.includes('ogg')) return 'ogg';
        if (blob.type.includes('mpeg')) return 'mp3';
        if (blob.type.includes('mp4')) return 'mp4';
        if (blob.type.includes('webm')) return 'webm';
    }

    // Si no, usar el formato almacenado de la grabación original
    if (currentRecordingFormat) {
        if (currentRecordingFormat.includes('ogg')) return 'ogg';
        if (currentRecordingFormat.includes('mpeg')) return 'mp3';
        if (currentRecordingFormat.includes('mp4')) return 'mp4';
        if (currentRecordingFormat.includes('webm')) return 'webm';
    }

    // Fallback: usar OGG como default abierto
    return 'ogg';
}

// Función mejorada para descargar con formato correcto
function downloadAudioWithCorrectFormat(blob, baseName) {
    const extension = getCorrectFileExtension(blob);
    const fileName = `${baseName}.${extension}`;
    downloadBlob(blob, fileName);
    console.log(`💾 [Download] Descargando audio en formato ${extension}: ${fileName}`);
    return fileName;
}

// Función específica para descargar siempre en OGG cuando hay errores
async function downloadAudioAsOgg(blob, baseName) {
    try {
        let oggBlob = blob;

        // Si no es OGG, intentar convertir
        if (!blob.type.includes('ogg')) {
            console.log('🎵 [Download] Convirtiendo a OGG para descarga...');
            oggBlob = await convertToOgg(blob);
        }

        const fileName = `${baseName}.ogg`;
        downloadBlob(oggBlob, fileName);
        console.log(`💾 [Download] Audio descargado como OGG: ${fileName}`);
        return fileName;
    } catch (error) {
        console.error('❌ [Download] Error al convertir a OGG:', error);
        // Fallback: usar la función normal si la conversión falla
        return downloadAudioWithCorrectFormat(blob, baseName);
    }
}

// Helper para convertir blobs a OGG usando MediaRecorder
// Función mejorada para conversión real a OGG
async function convertToOgg(blob) {
    try {
        console.log(`🎵 [Convert] Iniciando conversión a OGG...`);
        console.log(`🎵 [Convert] Blob original: ${blob.type}, Tamaño: ${(blob.size / 1024).toFixed(1)} KB`);

        // Si ya es OGG, devolver tal como está
        if (blob.type.includes('ogg')) {
            console.log(`✅ [Convert] Ya es OGG, no se requiere conversión`);
            return blob;
        }

        if (!window.MediaRecorder || !MediaRecorder.isTypeSupported || !MediaRecorder.isTypeSupported('audio/ogg')) {
            console.warn('⚠️ [Convert] MediaRecorder no soporta audio/ogg. Ajustando MIME type como respaldo.');
            const arrayBuffer = await blob.arrayBuffer();
            return new Blob([arrayBuffer], { type: 'audio/ogg' });
        }

        const ConversionAudioContext = window.AudioContext || window.webkitAudioContext;
        if (!ConversionAudioContext) {
            console.warn('⚠️ [Convert] AudioContext no disponible. Ajustando MIME type como respaldo.');
            const arrayBuffer = await blob.arrayBuffer();
            return new Blob([arrayBuffer], { type: 'audio/ogg' });
        }

        const conversionContext = new ConversionAudioContext();
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await conversionContext.decodeAudioData(arrayBuffer.slice(0));

        console.log(`🎵 [Convert] Audio decodificado: ${audioBuffer.duration.toFixed(2)}s, ${audioBuffer.sampleRate}Hz`);

        const destination = conversionContext.createMediaStreamDestination();
        const source = conversionContext.createBufferSource();
        source.buffer = audioBuffer;
        source.connect(destination);

        const recordedChunks = [];

        return await new Promise((resolve, reject) => {
            let settled = false;
            const recorder = new MediaRecorder(destination.stream, { mimeType: 'audio/ogg' });

            recorder.ondataavailable = event => {
                if (event.data && event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };

            recorder.onerror = event => {
                if (!settled) {
                    settled = true;
                    try { recorder.stop(); } catch (_) {}
                    conversionContext.close().catch(() => {});
                    reject(event.error || new Error('Error desconocido al convertir a OGG'));
                }
            };

            recorder.onstop = () => {
                if (!settled) {
                    settled = true;
                    const oggBlob = new Blob(recordedChunks, { type: 'audio/ogg' });
                    console.log(`✅ [Convert] Conversión a OGG completada: ${(oggBlob.size / 1024).toFixed(1)} KB`);
                    resolve(oggBlob);
                }
                conversionContext.close().catch(() => {});
            };

            source.onended = () => {
                if (recorder.state !== 'inactive') {
                    recorder.stop();
                }
            };

            recorder.start();
            conversionContext.resume()
                .then(() => {
                    source.start(0);
                })
                .catch(error => {
                    if (!settled) {
                        settled = true;
                        recorder.stop();
                        reject(error);
                    }
                });
        });
    } catch (error) {
        console.error('❌ [Convert] Error en conversión a OGG:', error);

        // Último recurso: devolver blob original con MIME type OGG
        console.log(`🔄 [Convert] Aplicando MIME type OGG como último recurso...`);
        const arrayBuffer = await blob.arrayBuffer();
        const fallbackBlob = new Blob([arrayBuffer], { type: 'audio/ogg' });
        console.log(`✅ [Convert] Conversión de emergencia a OGG completada`);
        return fallbackBlob;
    }
}

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
let MAX_DURATION_MS = 2 * 60 * 60 * 1000; // 2 horas (dinámico por plan)
let WARN_BEFORE_MINUTES = 5; // dinámico por plan
let PLAN_LIMITS = {
    role: (window.userRole || 'free'),
    max_meetings_per_month: null,
    used_this_month: 0,
    remaining: null,
    max_duration_minutes: 120,
    allow_postpone: window.hasPremiumAccess ? window.hasPremiumAccess() : false, // Solo premium o con organización
    warn_before_minutes: 5,
};

// ===== SISTEMA DE AUDIO DE NOTIFICACIONES =====
let notificationAudio = null;
const NOTIFICATION_SOUNDS = {
    timeWarning: '/audio/notifications/time-warning.m4a' // Solo usar archivo de advertencia
};
const SEGMENT_MS = 10 * 60 * 1000; // 10 minutos
// Se almacenan todos los trozos generados por una única sesión del MediaRecorder
let recordedChunks = [];
let recordingStream = null;
let currentRecordingId = null;
let chunkIndex = 0;

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
        sessionStorage.removeItem('audioDiscarded');

        // Limpiar localStorage de audios pendientes
        localStorage.removeItem('pendingAudioData');

        // Limpiar variables locales
        uploadedFile = null;
        pendingAudioBlob = null;
        recordedChunks = [];

        console.log('✅ Datos de audio limpiados correctamente');

    } catch (error) {
        console.error('❌ Error al limpiar datos de audio:', error);
        // No lanzar error para no interrumpir el flujo
    }
}

// ===== FUNCIONES DE AUDIO DE NOTIFICACIONES =====

// Función para reproducir sonido de advertencia de tiempo
function playNotificationSound(soundType) {
    // Solo procesar advertencia de tiempo
    if (soundType !== 'timeWarning') {
        console.warn(`Sonido no soportado: ${soundType}. Solo se soporta 'timeWarning'`);
        playFallbackBeep();
        return;
    }

    try {
        // Detener audio anterior si está reproduciéndose
        if (notificationAudio) {
            notificationAudio.pause();
            notificationAudio.currentTime = 0;
        }

        // Crear nuevo elemento de audio
        notificationAudio = new Audio(NOTIFICATION_SOUNDS.timeWarning);
        notificationAudio.volume = 0.7; // Volumen al 70%

        // Configurar eventos
        notificationAudio.addEventListener('canplaythrough', () => {
            notificationAudio.play().catch(error => {
                console.warn('No se pudo reproducir el archivo de audio, usando beep de fallback:', error);
                playFallbackBeep();
            });
        });

        notificationAudio.addEventListener('error', (error) => {
            console.warn('Error al cargar el archivo de audio, usando beep de fallback:', error);
            playFallbackBeep();
        });

        // Cargar el audio
        notificationAudio.load();

        console.log(`🔊 Reproduciendo advertencia de tiempo: ${NOTIFICATION_SOUNDS.timeWarning}`);

    } catch (error) {
        console.warn('Error en sistema de audio de notificaciones, usando fallback:', error);
        playFallbackBeep();
    }
}

// Función de fallback para generar beep de advertencia usando Web Audio API
function playFallbackBeep() {
    try {
        // Crear contexto de audio
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();

        console.log('🎵 Generando beep de advertencia de fallback (800Hz, beep doble)');

        // Generar beep doble para advertencia
        const frequency = 800;
        const duration = 0.2;
        const beepCount = 2;

        for (let i = 0; i < beepCount; i++) {
            setTimeout(() => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
                oscillator.type = 'sine';

                // Envelope para evitar clics
                gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.01);
                gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + duration - 0.01);
                gainNode.gain.linearRampToValueAtTime(0, audioContext.currentTime + duration);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + duration);
            }, i * (duration + 0.1) * 1000); // Pausa entre beeps
        }

    } catch (error) {
        console.error('Error generando beep de fallback:', error);
        // Último recurso: log llamativo
        console.log('🚨🚨🚨 ADVERTENCIA: QUEDAN 5 MINUTOS PARA EL LÍMITE 🚨🚨🚨');
    }
}

// Función para verificar si existe el archivo de audio de advertencia
async function checkNotificationAudioFiles() {
    try {
        const response = await fetch(NOTIFICATION_SOUNDS.timeWarning, { method: 'HEAD' });
        const exists = response.ok;
        console.log(`📁 Archivo de advertencia (${NOTIFICATION_SOUNDS.timeWarning}):`, exists ? '✅ Encontrado' : '❌ No encontrado');
        return { timeWarning: exists };
    } catch {
        console.log(`📁 Archivo de advertencia (${NOTIFICATION_SOUNDS.timeWarning}): ❌ Error al verificar`);
        return { timeWarning: false };
    }
}

// Función para agregar botón de prueba (solo en desarrollo)
function addTestAudioButton() {
    const testContainer = document.createElement('div');
    testContainer.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 9999;
        background: rgba(59, 130, 246, 0.9);
        padding: 10px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(10px);
    `;

    testContainer.innerHTML = `
        <div style="color: white; font-size: 12px; margin-bottom: 5px;">🔊 Test Advertencia:</div>
        <button id="test-warning-btn" style="margin: 2px; padding: 5px 8px; font-size: 10px; background: #f59e0b; border: none; border-radius: 4px; color: white; cursor: pointer;">
            ⚠️ Probar Audio
        </button>
        <button id="close-test-btn" style="margin: 2px; padding: 5px 8px; font-size: 10px; background: #6b7280; border: none; border-radius: 4px; color: white; cursor: pointer;">
            ✕
        </button>
    `;

    document.body.appendChild(testContainer);

    // Event listeners
    document.getElementById('test-warning-btn').addEventListener('click', () => {
        console.log('🧪 Probando sonido de advertencia de tiempo...');
        playNotificationSound('timeWarning');
        showWarning('Prueba: Quedan 5 minutos para el límite de grabación');
    });

    document.getElementById('close-test-btn').addEventListener('click', () => {
        testContainer.remove();
    });

    console.log('🧪 Botón de prueba de audio agregado (desarrollo)');
}

// Función de debug para simular advertencia de tiempo (solo desarrollo)
window.debugForceTimeWarning = function() {
    console.log('🧪 DEBUG: Forzando advertencia de tiempo...');
    console.log(`Variables actuales:
        - MAX_DURATION_MS: ${MAX_DURATION_MS}
        - WARN_BEFORE_MINUTES: ${WARN_BEFORE_MINUTES}
        - limitWarningShown: ${limitWarningShown}
        - isRecording: ${isRecording}
        - meetingRecording: ${meetingRecording}
    `);

    limitWarningShown = false; // Reset flag
    showWarning(`DEBUG: Quedan ${WARN_BEFORE_MINUTES} minutos para el límite de grabación`);

    return 'Advertencia de tiempo forzada - revisar consola para detalles';
};

// ===== FUNCIONES PRINCIPALES =====

// Función para seleccionar modo de grabación
function selectRecordingMode(mode) {
    document.querySelectorAll('.mode-option').forEach(option => {
        option.classList.remove('active');
    });
    document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
    selectedMode = mode;

    if (mode === 'meeting') {
        syncMeetingSourceButtons();
    }

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

async function rebuildDriveSelectOptions() {
    const driveSelect = document.getElementById('drive-select');

    // Si ya no existe en la fase de configuración, salir silenciosamente
    if (!driveSelect) {
        return;
    }

    if (!driveSelect) {
        console.warn('🔍 [new-meeting] Drive select element not found');
        return;
    }

    const organizationId = window.currentOrganizationId;
    const organizationName = window.currentOrganizationName;

    driveSelect.innerHTML = '';

    const personalOption = document.createElement('option');
    personalOption.value = 'personal';
    personalOption.textContent = 'Personal';

    try {
        const response = await fetch('/drive/sync-subfolders');
        console.log('🔍 [new-meeting] Personal drive response status:', response.status);

        if (response.ok) {
            const data = await response.json();
            const personalName = data?.root_folder?.name;

            if (personalName) {
                personalOption.textContent = `🏠 ${personalName}`;
                console.log('✅ [new-meeting] Added personal option:', personalName);
            }
        } else {
            console.warn('⚠️ [new-meeting] Failed to fetch personal drive label:', await response.text());
        }
    } catch (error) {
        console.warn('⚠️ [new-meeting] Error fetching personal drive label:', error);
    }

    driveSelect.appendChild(personalOption);

    if (organizationId) {
        const organizationOption = document.createElement('option');
        organizationOption.value = 'organization';
        const label = organizationName ? `🏢 ${organizationName}` : 'Organization';
        organizationOption.textContent = label;
        driveSelect.appendChild(organizationOption);
        console.log('✅ [new-meeting] Added organization option:', label);
    }
}

// ===== CONFIGURACIÓN DE EVENT LISTENERS PARA MODALS =====
function setupModalEventListeners() {
    console.log('🔧 Configurando event listeners para modals...');

    // Event listeners para modal de descarte de grabación
    const discardModal = document.getElementById('discard-recording-modal');
    if (discardModal) {
        const confirmBtn = discardModal.querySelector('#confirm-discard-btn');
        const cancelBtn = discardModal.querySelector('#cancel-discard-btn');

        if (confirmBtn && !confirmBtn.hasAttribute('data-listener-added')) {
            confirmBtn.addEventListener('click', () => {
                // Esta función ya existe en el código
                if (typeof confirmDiscardRecording === 'function') {
                    confirmDiscardRecording();
                }
            });
            confirmBtn.setAttribute('data-listener-added', 'true');
        }

        if (cancelBtn && !cancelBtn.hasAttribute('data-listener-added')) {
            cancelBtn.addEventListener('click', () => {
                closeDiscardRecordingModal();
            });
            cancelBtn.setAttribute('data-listener-added', 'true');
        }
    }

    // Event listeners para modal de navegación durante grabación
    const navModal = document.getElementById('recording-navigation-modal');
    if (navModal) {
        const stayBtn = navModal.querySelector('#stay-recording-btn');
        const leaveBtn = navModal.querySelector('#leave-recording-btn');

        if (stayBtn && !stayBtn.hasAttribute('data-listener-added')) {
            stayBtn.addEventListener('click', () => {
                closeRecordingNavigationModal();
            });
            stayBtn.setAttribute('data-listener-added', 'true');
        }

        if (leaveBtn && !leaveBtn.hasAttribute('data-listener-added')) {
            leaveBtn.addEventListener('click', () => {
                if (typeof confirmLeaveRecording === 'function') {
                    confirmLeaveRecording();
                }
            });
            leaveBtn.setAttribute('data-listener-added', 'true');
        }
    }

    console.log('✅ Event listeners para modals configurados');
}

document.addEventListener('DOMContentLoaded', async () => {
    // Limpiar estado de descarte de audio al llegar a nueva reunión
    try {
        sessionStorage.removeItem('audioDiscarded');
        console.log('✅ [new-meeting] Estado de descarte limpiado al iniciar nueva reunión');
    } catch (e) {
        console.warn('No se pudo limpiar estado de descarte:', e);
    }

    const checkbox = document.getElementById('postpone-toggle');
    if (checkbox) {
        checkbox.addEventListener('change', () => setPostponeMode(checkbox.checked));
        // Sync initial
        setPostponeMode(checkbox.checked);
    }

    const driveSelect = document.getElementById('drive-select');
    if (driveSelect) {
        // En esta pantalla ya no debería existir el selector; este bloque quedará para compatibilidad si persiste en cache
        await rebuildDriveSelectOptions();

        let saved = null;
        try {
            saved = sessionStorage.getItem('selectedDrive');
        } catch (error) {
            console.warn('⚠️ [new-meeting] Could not read saved drive selection:', error);
        }

        if (saved && driveSelect.querySelector(`option[value="${saved}"]`)) {
            driveSelect.value = saved;
        }

        driveSelect.addEventListener('change', () => {
            try {
                sessionStorage.setItem('selectedDrive', driveSelect.value);
            } catch (error) {
                console.warn('⚠️ [new-meeting] Could not persist drive selection:', error);
            }
        });
    }

    // Cargar límites del plan y aplicarlos a la UI/funcionalidad
    try {
        console.log('🔄 Cargando límites del plan...');
        const resp = await fetch('/api/plan/limits', { credentials: 'include' });
        if (resp.ok) {
            const limits = await resp.json();
            console.log('📋 Límites del plan cargados:', limits);

            // Actualizar allow_postpone basado en acceso premium
            limits.allow_postpone = window.hasPremiumAccess ? window.hasPremiumAccess() : false;
            console.log('🔒 Allow postpone actualizado:', limits.allow_postpone);

            PLAN_LIMITS = limits;
            // Duración máxima por reunión
            const minutes = Number(limits.max_duration_minutes || 120);
            MAX_DURATION_MS = minutes * 60 * 1000;
            WARN_BEFORE_MINUTES = Number(limits.warn_before_minutes || 5);

            console.log('⏱️ Configuración de tiempo:\n' +
                `                - Duración máxima: ${minutes} minutos (${MAX_DURATION_MS}ms)\n` +
                `                - Advertencia: ${WARN_BEFORE_MINUTES} minutos antes\n` +
                `                - Umbral advertencia: ${MAX_DURATION_MS - WARN_BEFORE_MINUTES * 60 * 1000}ms`);
            // Actualizar mensajes de UI
            const hintAudio = document.getElementById('max-duration-hint-audio');
            const hintMeeting = document.getElementById('max-duration-hint-meeting');
            const warn = WARN_BEFORE_MINUTES;
            const hint = `Puedes grabar hasta ${minutes} minutos continuos. Se notificará cuando queden ${warn} min para el límite.`;
            if (hintAudio) hintAudio.textContent = hint;
            if (hintMeeting) hintMeeting.textContent = hint;

            // Postponer: habilitar/deshabilitar
            const postponeToggle = document.getElementById('postpone-toggle');
            const postponeContainer = document.getElementById('postpone-switch');
            if (postponeContainer) postponeContainer.style.display = 'flex';
            if (postponeToggle) {
                if (!limits.allow_postpone) {
                    postponeToggle.checked = false;
                    postponeToggle.disabled = false; // Permitimos click para mostrar el modal informativo
                    setPostponeMode(false);
                    // Hook para mostrar modal de upgrade cuando intente activarlo
                    postponeToggle.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            e.preventDefault();
                            e.target.checked = false;
                            setPostponeMode(false);
                            showPostponeLockedModal();
                        }
                    });
                    const postponeBtn = document.getElementById('postpone-btn');
                    if (postponeBtn) {
                        postponeBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            showPostponeLockedModal();
                        });
                    }
                }
            }

            // Actualizar banner de análisis mensual
            try {
                const countEl = document.querySelector('.analysis-count');
                const subtitle = document.querySelector('.analysis-subtitle');
                const used = Number(limits.used_this_month || 0);
                const max = limits.max_meetings_per_month;
                if (countEl) {
                    countEl.textContent = `${used}/${max ?? '∞'}`;
                }
                if (subtitle) {
                    if (max !== null && used >= max) {
                        subtitle.textContent = 'Has alcanzado el límite de reuniones para este mes.';
                        // Deshabilitar inicio de nuevas grabaciones
                        const micBtn = document.getElementById('mic-circle');
                        const meetBtn = document.getElementById('meeting-mic-circle');
                        if (micBtn) { micBtn.disabled = true; micBtn.classList.add('disabled'); }
                        if (meetBtn) { meetBtn.disabled = true; meetBtn.classList.add('disabled'); }
                        // Mensaje visual rápido
                        showWarning('Has alcanzado tu límite mensual de reuniones. Actualiza tu plan para continuar.');
                    } else if (max !== null) {
                        const remaining = Math.max(0, max - used);
                        subtitle.textContent = `Te quedan ${remaining} reuniones este mes.`;
                    } else {
                        subtitle.textContent = 'Reuniones ilimitadas este mes.';
                    }
                }
            } catch (_) {}
        }
    } catch (e) {
        console.warn('No se pudieron cargar los límites del plan:', e);
    }

    // Verificar archivo de audio de advertencia
    setTimeout(async () => {
        console.log('🔊 Verificando archivo de advertencia de tiempo...');
        const audioFiles = await checkNotificationAudioFiles();

        if (!audioFiles.timeWarning) {
            console.warn('⚠️ Archivo de advertencia no encontrado. Se usará beep de fallback');
        } else {
            console.log('✅ Archivo de advertencia encontrado y listo');
        }

        // Agregar botón de prueba temporal (solo en desarrollo)
        if (window.location.hostname === 'localhost' || window.location.hostname.includes('laragon')) {
            addTestAudioButton();
        }
    }, 1000);

    // Configurar event listeners para el modal (una sola vez)
    setupModalEventListeners();

    setupRecordingNavigationGuards();
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
        discardRequested = false;
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

        recordedChunks = [];
        currentRecordingId = crypto.randomUUID();
        chunkIndex = 0;
        startTime = Date.now();
        limitWarningShown = false;
        isRecording = true;
        lastRecordingContext = 'recording';

        updateRecordingUI(true);

        recordingTimer = setInterval(updateTimer, 100);
        startAudioAnalysis();

        let bitsPerSecond = 128000; // calidad media por defecto

        // Usar la función global para obtener el formato
        const optimalFormat = getOptimalAudioFormat();
        currentRecordingFormat = optimalFormat; // Almacenar para uso posterior

        mediaRecorder = new MediaRecorder(recordingStream, {
            mimeType: optimalFormat,
            audioBitsPerSecond: bitsPerSecond
        });

        mediaRecorder.ondataavailable = event => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
                sendChunkToServer(event.data, chunkIndex++);
            }
        };

        mediaRecorder.onstop = () => {
            if (discardRequested) {
                discardRequested = false;
                recordingStream = null;
                return;
            }
            finalizeRecording();
        };

        // Genera datos periódicos sin reiniciar el MediaRecorder
        mediaRecorder.start(SEGMENT_MS);
    } catch (error) {
        console.error('Error al acceder al micrófono:', error);
        showError('No se pudo acceder al micrófono. Por favor, permite el acceso.');
    }
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

        if (meetingRecording) {
            microphoneMutedBeforePause = microphoneAudioMuted;
            if (!microphoneAudioMuted) {
                setMicrophoneMuteState(true);
            }
        }
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

        if (meetingRecording && microphoneMutedBeforePause === false) {
            setMicrophoneMuteState(false);
        }
        microphoneMutedBeforePause = null;
    }
}

// Descartar grabación
function performDiscardRecording() {
    const wasMeetingRecording = meetingRecording || lastRecordingContext === 'meeting';

    discardRequested = true;
    isRecording = false;
    isPaused = false;
    recordedChunks = [];

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch(_) {}
        try { mediaRecorder.stream.getTracks().forEach(track => track.stop()); } catch(_) {}
    }

    if (wasMeetingRecording) {
        meetingRecording = false;
        meetingStartTime = null;
        microphoneMutedBeforePause = null;

        if (systemAudioStream) {
            try { systemAudioStream.getTracks().forEach(track => track.stop()); } catch(_) {}
            systemAudioStream = null;
        }

        if (microphoneAudioStream) {
            try { microphoneAudioStream.getTracks().forEach(track => track.stop()); } catch(_) {}
            microphoneAudioStream = null;
        }

        if (meetingTimer) {
            clearInterval(meetingTimer);
            meetingTimer = null;
        }

        if (meetingAnimationId) {
            cancelAnimationFrame(meetingAnimationId);
            meetingAnimationId = null;
        }

        meetingDestination = null;
        updateMeetingRecordingUI(false);
        resetMeetingAudioVisualizers();
    }

    recordingStream = null;
    try {
        sessionStorage.setItem('audioDiscarded', 'true');
    } catch (e) {
        console.warn('No se pudo guardar estado de descarte:', e);
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

function closeDiscardRecordingModal() {
    const modal = document.getElementById('discard-recording-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function requestDiscardRecording() {
    const modal = document.getElementById('discard-recording-modal');

    if (!modal) {
        performDiscardRecording();
        return;
    }

    modal.style.display = 'flex';
}

function confirmDiscardRecording() {
    closeDiscardRecordingModal();
    performDiscardRecording();
}

function cancelDiscardRecording() {
    closeDiscardRecordingModal();
}

function showRecordingNavigationModal() {
    const modal = document.getElementById('recording-navigation-modal');

    if (!modal) {
        confirmRecordingNavigationChange();
        return;
    }

    modal.style.display = 'flex';
}

function closeRecordingNavigationModal() {
    const modal = document.getElementById('recording-navigation-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function confirmRecordingNavigationChange() {
    const url = pendingNavigationUrl;
    pendingNavigationUrl = null;

    closeRecordingNavigationModal();

    if (!url) {
        return;
    }

    performDiscardRecording();

    if (typeof window.closeMobileDropdown === 'function') {
        window.closeMobileDropdown();
    }

    window.location.href = url;
}

function cancelRecordingNavigationChange() {
    pendingNavigationUrl = null;
    closeRecordingNavigationModal();
}

function handleRecordingNavigationClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const anchor = event.currentTarget;
    if (!anchor || anchor.target === '_blank') {
        return;
    }

    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
        return;
    }

    if (!isRecording && !meetingRecording) {
        return;
    }

    const targetUrl = anchor.href;
    const currentUrl = window.location.href;

    if (targetUrl.replace(/#.*$/, '') === currentUrl.replace(/#.*$/, '')) {
        return;
    }

    event.preventDefault();

    pendingNavigationUrl = targetUrl;

    showRecordingNavigationModal();
}

function setupRecordingNavigationGuards() {
    const selectors = ['.nav a', '#mobile-menu a', '#mobile-header a'];
    const links = document.querySelectorAll(selectors.join(', '));

    links.forEach(link => {
        if (!link.dataset.recordingGuardBound) {
            link.addEventListener('click', handleRecordingNavigationClick);
            link.dataset.recordingGuardBound = 'true';
        }
    });
}

function resetRecordingControls() {
    document.getElementById('pause-recording').style.display = 'none';
    document.getElementById('resume-recording').style.display = 'none';
    document.getElementById('discard-recording').style.display = 'none';
    const mp = document.getElementById('meeting-pause');
    const md = document.getElementById('meeting-discard');
    const mr = document.getElementById('meeting-resume');
    const meetingActions = document.getElementById('meeting-recorder-actions');
    if (mp) mp.style.display = 'none';
    if (md) md.style.display = 'none';
    if (mr) mr.style.display = 'none';
    if (meetingActions) meetingActions.classList.remove('show');
    const postponeContainer = document.getElementById('postpone-switch');
    const postponeToggle = document.getElementById('postpone-toggle');
    if (postponeContainer) postponeContainer.style.display = 'flex'; // o ''
    if (postponeToggle) postponeToggle.disabled = false;
}


// Función para detener grabación
function stopRecording() {
    isRecording = false;
    isPaused = false;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    } else {
        finalizeRecording();
    }
}

// Unir todos los segmentos y subir en segundo plano
async function finalizeRecording() {
    // Si fue descartada, no procesar ni descargar
    try {
        const discardedFlag = sessionStorage.getItem('audioDiscarded') === 'true';
        if (discardRequested || discardedFlag) {
            console.log('🛑 [finalizeRecording] Cancelado por descarte del usuario');
            discardRequested = false;
            try { sessionStorage.removeItem('audioDiscarded'); } catch (_) {}
            // Limpieza mínima de UI/estado
            updateRecordingUI(false);
            resetAudioVisualizer();
            resetRecordingControls();
            return;
        }
    } catch (_) {
        if (discardRequested) {
            console.log('🛑 [finalizeRecording] Cancelado por descarte (sin sessionStorage)');
            discardRequested = false;
            updateRecordingUI(false);
            resetAudioVisualizer();
            resetRecordingControls();
            return;
        }
    }
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

    let finalBlob;

    // Usar directamente el blob de la grabación con el formato que se usó durante la grabación
    const blobType = currentRecordingFormat || 'audio/mp4'; // Fallback a MP4 si no hay formato almacenado
    finalBlob = new Blob(recordedChunks, { type: blobType });

    // Determinar MIME real del primer chunk para registro
    const realMime = recordedChunks[0]?.type || blobType;
    console.log('🎵 [finalizeRecording] Formato final detectado:', realMime);
    currentRecordingFormat = realMime;

    console.log('🎵 [finalizeRecording] Preparando audio para procesamiento...');
    console.log('🎵 [finalizeRecording] Using blob for processing');
    console.log('🎵 [finalizeRecording] Blob size:', (finalBlob.size / (1024 * 1024)).toFixed(2), 'MB');
    console.log('🎵 [finalizeRecording] Blob type:', finalBlob.type);
    const sizeMB = finalBlob.size / (1024 * 1024);

    const now = new Date();
    const name = `grabacion-${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}_${String(now.getHours()).padStart(2, '0')}-${String(now.getMinutes()).padStart(2, '0')}-${String(now.getSeconds()).padStart(2, '0')}`;

    // Determinar contexto actual de la grabación
    const context = lastRecordingContext || (selectedMode === 'meeting' ? 'meeting' : 'recording');
    if (sizeMB > 200) {
        showError('La grabación supera el límite de 200 MB.');
        const upload = confirm('¿Deseas subirla en segundo plano? Cancelar para descargarla.');
        pendingSaveContext = context;
        if (upload) {
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
                    downloadAudioWithCorrectFormat(finalBlob, name);
                });

            showSuccess('La subida continuará en segundo plano. Revisa el panel de notificaciones para el estado final.');
            handlePostActionCleanup(true);
        } else {
            downloadAudioWithCorrectFormat(finalBlob, name);
            handlePostActionCleanup();
        }
        return;
    }

    if (postponeMode) {
        pendingSaveContext = context;
        let key;
        try {
            key = await saveAudioBlob(finalBlob);
            sessionStorage.setItem('uploadedAudioKey', key);
        } catch (e) {
            console.error('Error al guardar el audio para subida en segundo plano', e);
            showError('No se pudo guardar el audio localmente. Se descargará el archivo.');
            downloadAudioWithCorrectFormat(finalBlob, name);
            handlePostActionCleanup();
            return;
        }

        uploadInBackground(finalBlob, name)
            .then(async response => {
                if (!response || (!response.saved && !response.pending_recording)) {
                    throw new Error('Invalid upload response');
                }
                showSuccess('Grabación subida a Drive');
                try {
                    await clearAllAudio();
                } catch (err) {
                    console.error('Error al limpiar audio local:', err);
                }
                sessionStorage.removeItem('uploadedAudioKey');
            })
            .catch(e => {
                console.error('Error al subir la grabación', e);
                showError('Error al subir la grabación. Se mantendrá guardada localmente para reintentos o descarga manual.');
            });

        showSuccess('La subida continuará en segundo plano. Revisa el panel de notificaciones para el estado final.');
        handlePostActionCleanup(true);
    } else {
        console.log('🎯 [finalizeRecording] Preparando audio para análisis inmediato...');
        pendingAudioBlob = finalBlob;
        pendingSaveContext = context;
        console.log('🎯 [finalizeRecording] Llamando a analyzeNow()...');
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

function updateVolumeRingsFor(ringsId, volumeLevel) {
    const rings = document.getElementById(ringsId);
    if (!rings) return;

    if (volumeLevel > 0.1) {
        rings.classList.add('active');

        const ring1 = rings.querySelector('.ring-1');
        const ring2 = rings.querySelector('.ring-2');
        const ring3 = rings.querySelector('.ring-3');

        if (ring1) ring1.style.opacity = Math.min(volumeLevel * 2, 1);
        if (ring2) ring2.style.opacity = Math.min(volumeLevel * 1.5, 0.8);
        if (ring3) ring3.style.opacity = Math.min(volumeLevel, 0.6);
    } else {
        rings.classList.remove('active');
    }
}

// Función para actualizar los anillos de volumen del grabador estándar
function updateVolumeRings(volumeLevel) {
    updateVolumeRingsFor('volume-rings', volumeLevel);
}

// Función para actualizar los anillos de volumen de reunión
function updateMeetingVolumeRings(volumeLevel) {
    updateVolumeRingsFor('meeting-volume-rings', volumeLevel);
}

function getAverageVolumeLevel(dataArray) {
    if (!dataArray || dataArray.length === 0) return 0;
    let sum = 0;
    for (let i = 0; i < dataArray.length; i++) {
        sum += dataArray[i];
    }
    return (sum / dataArray.length) / 255;
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
        timerCounter.textContent = '00:00:00';
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
    const elapsedMinutes = Math.floor(elapsed / 60000);
    const maxMinutes = Math.floor(MAX_DURATION_MS / 60000);
    const warningThreshold = MAX_DURATION_MS - WARN_BEFORE_MINUTES * 60 * 1000;

    // Debug logging cada 30 segundos
    if (elapsedMinutes > 0 && elapsed % 30000 < 100) {
        console.log(`⏱️ Timer Audio: ${elapsedMinutes}/${maxMinutes} min - Límite advertencia: ${Math.floor(warningThreshold/60000)} min`);
    }

    if (elapsed >= MAX_DURATION_MS) {
        console.log('🛑 LÍMITE DE TIEMPO ALCANZADO - Deteniendo grabación automáticamente');
        // Solo usar beep de fallback para límite alcanzado
        playFallbackBeep();
        stopRecording();
        return;
    }

    if (!limitWarningShown && elapsed >= warningThreshold) {
        console.log(`🚨 ACTIVANDO ADVERTENCIA: ${elapsedMinutes} min transcurridos de ${maxMinutes} max`);
        showWarning(`Quedan ${WARN_BEFORE_MINUTES} minutos para el límite de grabación`);
        limitWarningShown = true;
    }

    const hours = Math.floor(elapsed / 3600000);
    const minutes = Math.floor((elapsed % 3600000) / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);

    const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    const timerEl = document.getElementById('timer-counter');
    if (timerEl) {
        timerEl.textContent = timeString;

        // Cambiar color cuando se acerque al límite
        if (elapsed >= warningThreshold) {
            timerEl.style.color = '#ff4444';
            timerEl.style.fontWeight = 'bold';
        }
    }
}

// Función para mostrar advertencias
function showWarning(message) {
    // 🔊 Reproducir sonido para advertencias de tiempo límite
    if (message.includes('minutos') && message.includes('límite')) {
        playNotificationSound('timeWarning');
        console.log(`🚨 ADVERTENCIA DE TIEMPO: ${message}`);
    }

    const notification = document.createElement('div');
    notification.className = 'notification warning time-warning';
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">⚠️</span>
            <span class="notification-message">${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Hacer la notificación más visible para advertencias de tiempo
    if (message.includes('minutos') && message.includes('límite')) {
        notification.style.cssText = `
            background: linear-gradient(135deg, #ff6b35, #f7931e) !important;
            border: 2px solid #ff4444 !important;
            box-shadow: 0 8px 25px rgba(255, 68, 68, 0.3) !important;
            animation: pulse-warning 1s infinite !important;
            z-index: 10001 !important;
        `;
    }

    setTimeout(() => {
        notification.remove();
    }, 8000); // Mantener más tiempo visible para advertencias críticas

    // Enviar notificación al backend solo para advertencia de tiempo restante
    if (!timeWarnNotified && message.includes('minutos') && message.includes('límite')) {
        timeWarnNotified = true;
        try {
            fetch('/api/notifications', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    type: 'time_limit_warning',
                    message: message,
                    data: { context: selectedMode }
                })
            }).catch(() => {});
        } catch (_) {}
    }
}

// Sube un blob de audio a Drive
function uploadAudioToDrive(blob, name, onProgress) {
    const formData = new FormData();

    // Use correct file extension based on blob type
    const fileExtension = getCorrectFileExtension(blob);
    const fileName = `${name}.${fileExtension}`;

    // Get selected drive type (organization or personal)
    // En flujo nuevo no hay drive-select aquí; el tipo se selecciona en paso de guardado (audio-processing)
    const driveSelect = document.getElementById('drive-select');
    const driveType = driveSelect ? driveSelect.value : 'personal';

    formData.append('audioFile', blob, fileName);
    formData.append('meetingName', name);
    formData.append('driveType', driveType); // Send drive type to backend

    console.log(`🗂️ [Upload] Subiendo a Drive tipo: ${driveType}`);

    // Remove the default rootFolder - let backend handle folder creation
    // formData.append('rootFolder', 'default');

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/drive/upload-pending-audio');
        xhr.setRequestHeader('X-CSRF-TOKEN', token);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable && onProgress) {
                onProgress(e.loaded, e.total);
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (err) {
                    response = xhr.responseText;
                }

                console.log('✅ [Upload] Audio subido exitosamente:', response);

                // Mostrar mensaje específico del tipo de drive usado
                if (response && typeof response === 'object') {
                    const driveType = response.drive_type || 'personal';
                    const driveTypeName = driveType === 'organization' ? 'organizacional' : 'personal';
                    const folderPath = response.folder_info?.full_path || 'Grabaciones/Audios Pospuestos';

                    showSuccess(`Audio subido exitosamente a Drive ${driveTypeName} en: ${folderPath}`);
                }

                // Descarga automática para usuarios BNI después de subir a Drive
                const userRole = (window.userRole || '').toString().toLowerCase();
                if (userRole === 'bni' && response?.pending_recording) {
                    console.log('Usuario BNI detectado - programando descarga automática después de procesamiento');
                    // Esperar un momento para que se procese y luego intentar descargar
                    setTimeout(() => {
                        checkAndDownloadForBNI(response.pending_recording);
                    }, 3000);
                }

                if (response?.pending_recording) {
                    pollPendingRecordingStatus(response.pending_recording);
                }
                if (window.notifications) {
                    window.notifications.refresh();
                }
                // Limpiar datos de fallo si la subida fue exitosa
                clearFailedUploadData();
                resolve(response);
            } else {
                console.error('Upload failed with status:', xhr.status, xhr.responseText);
                // Almacenar datos para reintento con conversión automática a OGG
                storeFailedUploadData(blob, name).then(() => {
                    showUploadRetryUI();
                    showError(`Fallo al subir el audio (Error ${xhr.status}). Audio convertido a OGG para próximo intento.`);
                }).catch(() => {
                    showUploadRetryUI();
                    showError(`Fallo al subir el audio (Error ${xhr.status}). Puedes reintentarlo más tarde.`);
                });
                reject(new Error('Upload failed'));
            }
        };

        xhr.onerror = () => {
            console.error('Error uploading audio - Network error');
            // Almacenar datos para reintento con conversión automática a OGG
            storeFailedUploadData(blob, name).then(() => {
                showUploadRetryUI();
                showError('Error de conexión al subir el audio. Audio convertido a OGG para próximo intento.');
            }).catch(() => {
                showUploadRetryUI();
                showError('Error de conexión al subir el audio. Puedes reintentarlo más tarde.');
            });
            reject(new Error('Upload failed'));
        };

        xhr.send(formData);
    });
}

// Guarda un blob de audio temporalmente para planes FREE
function saveAudioTemporarily(blob, name, onProgress) {
    const formData = new FormData();

    // Use correct file extension based on blob type
    const fileExtension = getCorrectFileExtension(blob);
    const fileName = `${name}.${fileExtension}`;

    formData.append('audioFile', blob, fileName);
    formData.append('meetingName', name);
    formData.append('description', document.getElementById('meeting-description')?.value || '');
    formData.append('duration', Math.round((blob.size / 16000) * 8)); // Estimación aproximada

    console.log(`💾 [TempSave] Guardando temporalmente: ${fileName}`);

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/transcriptions-temp');
        xhr.setRequestHeader('X-CSRF-TOKEN', token);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable && onProgress) {
                onProgress(e.loaded, e.total);
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (err) {
                    response = xhr.responseText;
                }

                console.log('✅ [TempSave] Audio guardado temporalmente:', response);

                if (response?.success) {
                    const retentionDays = Number(response?.retention_days ?? window.tempRetentionDays ?? 7);
                    const retentionLabel = `${retentionDays} ${retentionDays === 1 ? 'día' : 'días'}`;
                    const storageReason = response?.storage_reason || (!window.userCanUseDrive ? 'plan_restricted' : 'drive_not_connected');
                    const baseMessage = storageReason === 'drive_not_connected'
                        ? `Conecta tu Google Drive para conservarla permanentemente.`
                        : `Actualiza tu plan para guardarla permanentemente en Drive.`;

                    showSuccess(`Reunión guardada temporalmente. Se eliminará automáticamente en ${retentionLabel}. ${baseMessage}`);

                    const modalMessage = storageReason === 'drive_not_connected'
                        ? `Tu reunión se guardó correctamente pero <strong>se eliminará en ${retentionLabel}</strong>. Conecta tu cuenta de Google Drive para moverla a tu almacenamiento permanente.`
                        : `Tu reunión se guardó correctamente pero <strong>se eliminará en ${retentionLabel}</strong>. Actualiza a un plan superior para guardar permanentemente en Google Drive y acceder a todas las funciones premium.`;

                    // Mostrar modal específico para guardado temporal después de 3 segundos
                    setTimeout(() => {
                        showUpgradeModal({
                            title: 'Reunión guardada temporalmente',
                            message: modalMessage,
                            icon: 'file'
                        });
                    }, 3000);
                }
                // Descarga automática para usuarios BNI después de guardar temporalmente
                const userRole = (window.userRole || '').toString().toLowerCase();
                if (userRole === 'bni' && response?.pending_recording) {
                    console.log('Usuario BNI detectado - programando descarga automática después de procesamiento temporal');
                    // Esperar un momento para que se procese y luego intentar descargar
                    setTimeout(() => {
                        checkAndDownloadForBNI(response.pending_recording, true);
                    }, 3000);
                }

                if (window.notifications) {
                    window.notifications.refresh();
                }

                clearFailedUploadData();
                resolve(response);
            } else {
                console.error('Temp save failed with status:', xhr.status, xhr.responseText);
                showError(`Error al guardar temporalmente (Error ${xhr.status}).`);
                reject(new Error('Temp save failed'));
            }
        };

        xhr.onerror = () => {
            console.error('Error saving temporarily - Network error');
            showError('Error de conexión al guardar temporalmente.');
            reject(new Error('Temp save failed'));
        };

        xhr.send(formData);
    });
}

// Función existente para compatibilidad - ahora con lógica de planes
function uploadInBackground(blob, name, onProgress) {
    const userPlan = window.userPlanCode || 'free';
    const hasPremium = window.hasPremiumAccess ? window.hasPremiumAccess() : userPlan !== 'free';

    console.log(`📋 [Upload] Plan del usuario: ${userPlan}, Premium Access: ${hasPremium}`);

    // Si no tiene acceso premium (plan FREE sin organización), usar guardado temporal
    if (!hasPremium) {
        console.log('💾 [Upload] Usando guardado temporal para usuario sin acceso premium');
        return saveAudioTemporarily(blob, name, onProgress);
    }

    // Para usuarios con acceso premium, usar Drive
    console.log('☁️ [Upload] Usando Drive para usuario con acceso premium');
    return uploadAudioToDrive(blob, name, onProgress);
}

function pollPendingRecordingStatus(id) {
    const check = () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch(`/api/pending-recordings/${id}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            }
        })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'COMPLETED') {
                    showSuccess('Grabación procesada correctamente');
                    if (window.notifications) {
                        window.notifications.refresh();
                    }
                } else if (data.status === 'FAILED') {
                    showError('Error al procesar la grabación en Drive');
                    if (window.notifications) {
                        window.notifications.refresh();
                    }
                } else {
                    setTimeout(check, 5000);
                }
            })
            .catch((error) => {
                console.error('Error checking pending recording status:', error);
                setTimeout(check, 5000);
            });
    };
    check();
}

// Funciones para manejar subidas fallidas con conversión automática a OGG
async function storeFailedUploadData(blob, name) {
    console.log('📦 [Failed Upload] Procesando datos para reintento:', {
        size: (blob.size / (1024 * 1024)).toFixed(2) + ' MB',
        type: blob.type,
        name: name
    });

    // Intentar convertir a OGG para mejorar compatibilidad en reintento
    try {
        if (!blob.type.includes('ogg')) {
            console.log('🎵 [Failed Upload] Convirtiendo a OGG para mejorar compatibilidad...');
            const oggBlob = await convertToOgg(blob);

            failedAudioBlob = oggBlob;
            failedAudioName = name.replace(/\.(mp4|webm|wav|mp3|m4a)$/i, '.ogg'); // Cambiar extensión a OGG

            console.log('✅ [Failed Upload] Audio convertido a OGG para reintento:', {
                originalSize: (blob.size / (1024 * 1024)).toFixed(2) + ' MB',
                oggSize: (oggBlob.size / (1024 * 1024)).toFixed(2) + ' MB',
                newName: failedAudioName
            });

            showSuccess('Audio convertido a OGG para mejorar compatibilidad en próximo intento');
        } else {
            // Ya es OGG, usar tal como está
            failedAudioBlob = blob;
            failedAudioName = name;
            console.log('✅ [Failed Upload] Audio ya está en formato OGG');
        }
    } catch (conversionError) {
        console.warn('⚠️ [Failed Upload] Error al convertir a OGG, usando audio original:', conversionError);
        failedAudioBlob = blob;
        failedAudioName = name;
    }

    retryAttempts = 0;
    console.log('📦 [Failed Upload] Datos finales almacenados para reintento:', {
        size: (failedAudioBlob.size / (1024 * 1024)).toFixed(2) + ' MB',
        type: failedAudioBlob.type,
        name: failedAudioName
    });
}

function clearFailedUploadData() {
    failedAudioBlob = null;
    failedAudioName = null;
    retryAttempts = 0;
    hideUploadRetryUI();
    console.log('🧹 [Failed Upload] Datos de subida fallida limpiados');
}

function showUploadRetryUI() {
    // Verificar si ya existe el UI de reintento
    let retryUI = document.getElementById('retry-upload-container');

    if (!retryUI) {
        // Crear el UI de reintento
        retryUI = document.createElement('div');
        retryUI.id = 'retry-upload-container';
        retryUI.className = 'retry-upload-container my-4';
        retryUI.innerHTML = `
            <div class="retry-upload-card">
                <div class="retry-header">
                    <div class="retry-icon">⚠️</div>
                    <div class="retry-title">Subida Fallida</div>
                </div>
                <div class="retry-content">
                    <p class="retry-message">La grabación no se pudo subir a Drive, pero está guardada localmente.</p>
                    <div class="retry-details">
                        <span class="retry-filename" id="retry-filename">archivo.mp4</span>
                        <span class="retry-filesize" id="retry-filesize">0 MB</span>
                    </div>
                </div>
                <div class="retry-actions">
                    <button class="retry-btn btn btn-primary" onclick="retryUpload()" id="retry-upload-btn">
                        🔄 Reintentar Subida
                    </button>
                    <button class="retry-btn btn btn-secondary" onclick="downloadFailedAudio()">
                        💾 Descargar
                    </button>
                    <button class="retry-btn btn btn-danger" onclick="discardFailedAudio()">
                        🗑️ Descartar
                    </button>
                </div>
                <div class="retry-progress" id="retry-progress" style="display: none;">
                    <div class="retry-progress-bar" id="retry-progress-bar"></div>
                    <span class="retry-progress-text" id="retry-progress-text">Subiendo...</span>
                </div>
            </div>
        `;

        // Buscar donde insertar el UI (después del botón de posponer)
        const postponeSection = document.querySelector('.postpone-section');
        if (postponeSection) {
            postponeSection.parentNode.insertBefore(retryUI, postponeSection.nextSibling);
        } else {
            // Fallback: insertar al final del contenedor principal
            const container = document.querySelector('.recording-container') || document.body;
            container.appendChild(retryUI);
        }

        // Agregar estilos CSS si no existen
        addRetryUploadStyles();
    }

    // Actualizar información del archivo
    if (failedAudioBlob && failedAudioName) {
        const sizeInMB = (failedAudioBlob.size / (1024 * 1024)).toFixed(2);
        document.getElementById('retry-filename').textContent = `${failedAudioName}.${getFileExtension()}`;
        document.getElementById('retry-filesize').textContent = `${sizeInMB} MB`;
    }

    retryUI.style.display = 'block';
    console.log('🔄 [Retry UI] Interfaz de reintento mostrada');
}

function hideUploadRetryUI() {
    const retryUI = document.getElementById('retry-upload-container');
    if (retryUI) {
        retryUI.style.display = 'none';
    }
}

function getFileExtension() {
    if (!failedAudioBlob) return 'mp4';
    return getCorrectFileExtension(failedAudioBlob);
}

function addRetryUploadStyles() {
    // Verificar si los estilos ya existen
    if (document.getElementById('retry-upload-styles')) return;

    const styles = document.createElement('style');
    styles.id = 'retry-upload-styles';
    styles.textContent = `
        .retry-progress {
            margin-top: 15px;
            background: var(--surface-light);
            border-radius: 6px;
            padding: 10px;
        }

        .retry-progress-bar {
            width: 100%;
            height: 6px;
            background: var(--surface-light);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .retry-progress-bar::after {
            content: '';
            display: block;
            height: 100%;
            background: var(--primary-color);
            width: 0%;
            transition: width 0.3s ease;
        }

        .retry-progress-text {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

    `;
    document.head.appendChild(styles);
}

async function analyzeNow() {
    console.log('🎯 [analyzeNow] Iniciando análisis del audio...');
    console.log('🎯 [analyzeNow] pendingAudioBlob existe:', !!pendingAudioBlob);

    if (!pendingAudioBlob) {
        console.error('❌ [analyzeNow] No hay audio pendiente para analizar');
        return;
    }

    console.log('🎯 [analyzeNow] Tamaño del blob:', (pendingAudioBlob.size / 1024).toFixed(1), 'KB');
    console.log('🎯 [analyzeNow] Tipo del blob:', pendingAudioBlob.type);

    try {
        console.log('💾 [analyzeNow] Guardando audio en IndexedDB...');
        // Guardar el blob en IndexedDB y almacenar la clave en sessionStorage
        const key = await saveAudioBlob(pendingAudioBlob);
        console.log('✅ [analyzeNow] Audio guardado con clave:', key);
        sessionStorage.setItem('uploadedAudioKey', key);

        // Verificar que la clave funcione recargando el blob
        try {
            console.log('🔍 [analyzeNow] Verificando audio guardado...');
            const testBlob = await loadAudioBlob(key);
            if (!testBlob) {
                throw new Error('Blob no encontrado tras guardar');
            }
            console.log('✅ [analyzeNow] Verificación exitosa - blob encontrado');
        } catch (err) {
            console.error('❌ [analyzeNow] Error al validar audio guardado:', err);
            showError('Error al guardar el audio. Intenta nuevamente.');
            handlePostActionCleanup();
            return;
        }
        const planInfo = getUserPlanInfo();
        const hasPremium = window.hasPremiumAccess ? window.hasPremiumAccess() : false;
        const planLimitBytes = getUploadLimitBytes(planInfo, hasPremium);
        const fallbackLimitBytes = planLimitBytes ?? (60 * 1024 * 1024);

        // Respaldo: guardar base64 si el blob no es muy grande
        try {
            if (pendingAudioBlob.size <= fallbackLimitBytes) {
                const base64 = await blobToBase64(pendingAudioBlob);
                sessionStorage.setItem('recordingBlob', base64);
            } else {
                sessionStorage.removeItem('recordingBlob');
            }
        } catch (_) {
            // Si falla respaldo, continuar con la clave de IDB
        }
        sessionStorage.removeItem('recordingSegments');
        sessionStorage.removeItem('recordingMetadata');
    } catch (e) {
        // Descargar en OGG cuando hay un error para contar con un mejor respaldo
        console.error('❌ [analyzeNow] Error preparando audio:', e);
        downloadAudioAsOgg(pendingAudioBlob, 'grabacion_error').catch(() => {
            downloadAudioWithCorrectFormat(pendingAudioBlob, 'grabacion_error');
        });
        console.error('Error preparando audio', e);
        showError('Error al analizar la grabación. Usa el archivo descargado para reintentar.');
        handlePostActionCleanup();
        return;
    }

    console.log('🧹 [analyzeNow] Limpiando y preparando redirección...');
    handlePostActionCleanup();
    console.log('🚀 [analyzeNow] Redirigiendo a audio-processing...');
    window.location.href = '/audio-processing';
}

function handlePostActionCleanup(uploaded) {
    if (pendingSaveContext === 'recording') {
        recordedChunks = [];
        startTime = null;
        currentRecordingFormat = null; // Limpiar formato de grabación
    } else if (pendingSaveContext === 'meeting') {
        recordedChunks = [];
        meetingStartTime = null;
        currentRecordingFormat = null; // Limpiar formato de grabación
    } else if (pendingSaveContext === 'upload' && !uploaded) {
        removeSelectedFile();
    }
}

function sendChunkToServer(chunk, index) {
    const formData = new FormData();
    formData.append('chunk', chunk);
    formData.append('recording_id', currentRecordingId);
    formData.append('index', index);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('/api/recordings/chunk', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token },
        body: formData
    }).catch(err => console.error('Error enviando segmento', err));
}

async function fetchRemuxedBlob() {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const response = await fetch('/api/recordings/concat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({ recording_id: currentRecordingId })
    });
    if (!response.ok) {
        throw new Error('Remux failed');
    }
    const buffer = await response.arrayBuffer();
    // Convertir a OGG para descargas/compatibilidad si se usa este flujo
    const webmBlob = new Blob([buffer], { type: 'audio/webm;codecs=opus' });
    try {
        const oggBlob = await convertToOgg(webmBlob);
        return oggBlob;
    } catch (_) {
        // Fallback: devolver blob original pero marcando ogg para evitar descargas .webm
        return new Blob([buffer], { type: 'audio/ogg' });
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
    const isAudioType = file.type && file.type.startsWith('audio/');
    const looksLikeAudio = /\.(ogg|oga|wav|mp3|m4a|aac|flac|aiff|aif|wma|opus|weba|webm)$/i.test(file.name || '');

    if (!isAudioType && !looksLikeAudio) {
        showError('❌ Tipo de archivo no soportado. Selecciona un archivo de audio válido.');
        return;
    }

    // Validar tamaño (máximo 200MB)
    if (file.size > 200 * 1024 * 1024) {
        showError('El archivo es demasiado grande. El tamaño máximo es 200MB.');
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

    // Verificar límite de tamaño según plan
    const fileSize = uploadedFile.size;
    const hasPremium = window.hasPremiumAccess ? window.hasPremiumAccess() : false;
    const planInfo = getUserPlanInfo();
    const planLimitBytes = getUploadLimitBytes(planInfo, hasPremium);

    if (planLimitBytes !== null && fileSize > planLimitBytes) {
        console.log(`🚫 Archivo excede límite para el ${planInfo.planName}: ${fileSize} bytes > ${planLimitBytes} bytes`);

        // Mostrar modal específico para límite de tamaño
        showFileSizeLimitModal(fileSize, planLimitBytes / (1024 * 1024), planInfo.planName);
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

        // Guardar temporalmente el archivo antes de limpiar datos previos
        const fileToProcess = uploadedFile;

        // LIMPIAR DATOS ANTERIORES ANTES DE PROCESAR EL NUEVO ARCHIVO
        await clearPreviousAudioData();
        uploadedFile = fileToProcess;

        if (progressContainer) {
            progressFill.style.width = '20%';
            progressText.textContent = 'Preparando archivo para procesamiento...';
        }

        // Guardar el archivo en IndexedDB
        const audioKey = await saveAudioBlob(uploadedFile);
        console.log('Audio guardado en IndexedDB con clave:', audioKey);

        // Validar que se pueda recargar el blob
        try {
            const testBlob = await loadAudioBlob(audioKey);
            if (!testBlob) {
                throw new Error('Blob no encontrado tras guardar');
            }
        } catch (err) {
            console.error('Error al validar audio subido:', err);
            showError('Error al guardar el audio. Intenta nuevamente.');
            if (progressContainer) {
                progressContainer.style.display = 'none';
            }
            return;
        }

        if (progressContainer) {
            progressFill.style.width = '70%';
            progressText.textContent = 'Archivo guardado...';
        }

        // Guardar la clave en sessionStorage para que audio-processing.js la pueda usar
        sessionStorage.setItem('uploadedAudioKey', audioKey);

        const fallbackLimitBytes = planLimitBytes ?? (60 * 1024 * 1024);
        // Respaldo: guardar una copia base64 si el archivo no es demasiado grande
        try {
            if (uploadedFile && typeof uploadedFile.size === 'number' && uploadedFile.size <= fallbackLimitBytes) {
                const base64 = await blobToBase64(uploadedFile);
                sessionStorage.setItem('recordingBlob', base64);
            } else {
                sessionStorage.removeItem('recordingBlob');
            }
        } catch (e) {
            console.warn('No se pudo crear respaldo base64 del audio subido:', e);
        }

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

// Inicializa elementos del grabador de reunión
function setupMeetingRecorder() {
    // Reset de analizadores/gains (se crearán al iniciar la grabación)
    systemGainNode = null;
    microphoneGainNode = null;

    // Reiniciar estado visual de las barras
    ['system-audio-visualizer', 'microphone-audio-visualizer'].forEach((id) => {
        const visualizer = document.getElementById(id);
        if (!visualizer) return;

        visualizer.classList.remove('active');
        visualizer.querySelectorAll('.meeting-audio-bar').forEach((bar) => {
            bar.style.height = '8%';
            bar.classList.remove('active', 'high');
        });
    });

    syncMeetingSourceButtons();
}

function syncMeetingSourceButtons() {
    const systemBtn = document.getElementById('system-audio-btn');
    const systemText = systemBtn ? systemBtn.querySelector('.source-text') : null;
    if (systemBtn && systemText) {
        if (systemAudioEnabled) {
            systemBtn.classList.add('active');
            systemText.textContent = 'Sistema activado';
        } else {
            systemBtn.classList.remove('active');
            systemText.textContent = 'Sistema desactivado';
        }
    }

    const microphoneBtn = document.getElementById('microphone-audio-btn');
    const microphoneText = microphoneBtn ? microphoneBtn.querySelector('.source-text') : null;
    if (microphoneBtn && microphoneText) {
        if (microphoneAudioEnabled) {
            microphoneBtn.classList.add('active');
            microphoneText.textContent = 'Micrófono activado';
        } else {
            microphoneBtn.classList.remove('active');
            microphoneText.textContent = 'Micrófono desactivado';
        }
    }
}

// Aplica estados de mute/enable a las fuentes durante la reunión
function applyMuteStates() {
    if (systemGainNode) {
        systemGainNode.gain.value = (systemAudioEnabled && !systemAudioMuted) ? 1 : 0;
    }
    if (microphoneGainNode) {
        microphoneGainNode.gain.value = (microphoneAudioEnabled && !microphoneAudioMuted) ? 1 : 0;
    }
}

// Alternar audio del sistema
function toggleSystemAudio() {
    systemAudioEnabled = !systemAudioEnabled;
    syncMeetingSourceButtons();
    // Aplicar inmediatamente si estamos grabando reunión
    if (meetingRecording) applyMuteStates();
}

// Alternar audio del micrófono
function toggleMicrophoneAudio() {
    microphoneAudioEnabled = !microphoneAudioEnabled;
    syncMeetingSourceButtons();
    // Aplicar inmediatamente si estamos grabando reunión
    if (meetingRecording) applyMuteStates();
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
    applyMuteStates();
}

// Mutear audio del micrófono
function muteMicrophoneAudio() {
    setMicrophoneMuteState(!microphoneAudioMuted);
}

function setMicrophoneMuteState(muted) {
    microphoneAudioMuted = muted;
    const btn = document.getElementById('microphone-mute-btn');
    const icon = btn ? btn.querySelector('.mute-icon') : null;

    if (btn) {
        btn.classList.toggle('muted', muted);
    }
    if (icon) {
        icon.textContent = muted ? '🔇' : '🔊';
    }
    applyMuteStates();
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
        microphoneMutedBeforePause = null;

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
            // Captura de pantalla + audio del sistema (usar constraints simples para evitar NotSupportedError)
            systemAudioStream = await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: true
            });
        }

        // Crear contexto y destino mezclado
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        meetingDestination = audioContext.createMediaStreamDestination();

        // Configurar análisis de audio y mezcla con gains individuales
        if (systemAudioStream) {
            systemAnalyser = audioContext.createAnalyser();
            const systemSource = audioContext.createMediaStreamSource(systemAudioStream);
            systemGainNode = audioContext.createGain();
            systemSource.connect(systemAnalyser);
            systemSource.connect(systemGainNode);
            systemGainNode.connect(meetingDestination);
            systemAnalyser.fftSize = 256;
            systemAnalyser.smoothingTimeConstant = 0.8;
            systemDataArray = new Uint8Array(systemAnalyser.frequencyBinCount);
        }

        if (microphoneAudioStream) {
            microphoneAnalyser = audioContext.createAnalyser();
            const microphoneSource = audioContext.createMediaStreamSource(microphoneAudioStream);
            microphoneGainNode = audioContext.createGain();
            microphoneSource.connect(microphoneAnalyser);
            microphoneSource.connect(microphoneGainNode);
            microphoneGainNode.connect(meetingDestination);
            microphoneAnalyser.fftSize = 256;
            microphoneAnalyser.smoothingTimeConstant = 0.8;
            microphoneDataArray = new Uint8Array(microphoneAnalyser.frequencyBinCount);
        }

        // Aplicar estados iniciales de mute/enable
        applyMuteStates();

        meetingRecording = true;
        meetingStartTime = Date.now();
        limitWarningShown = false;

        // Actualizar UI
        updateMeetingRecordingUI(true);

        // Iniciar timer y análisis
        meetingTimer = setInterval(updateMeetingTimer, 100);
        startMeetingAudioAnalysis();

        // Preparar MediaRecorder para el audio mezclado
        recordedChunks = [];
        currentRecordingId = crypto.randomUUID();
        chunkIndex = 0;
        lastRecordingContext = 'meeting';

        const optimalFormat = getOptimalAudioFormat();
        currentRecordingFormat = optimalFormat;

        recordingStream = meetingDestination.stream;
        mediaRecorder = new MediaRecorder(recordingStream, {
            mimeType: optimalFormat,
            audioBitsPerSecond: 128000
        });

        mediaRecorder.ondataavailable = event => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
                sendChunkToServer(event.data, chunkIndex++);
            }
        };

        mediaRecorder.onstop = () => {
            // Si se descartó, no finalizar ni descargar
            try {
                const discardedFlag = sessionStorage.getItem('audioDiscarded') === 'true';
                if (discardRequested || discardedFlag) {
                    console.log('🗑️ [Meeting] Grabación descartada, se omite finalizeRecording');
                    discardRequested = false;
                    return;
                }
            } catch (_) {
                if (discardRequested) {
                    console.log('🗑️ [Meeting] Grabación descartada (no sessionStorage)');
                    discardRequested = false;
                    return;
                }
            }
            // Dar tiempo a que llegue el último dataavailable antes de finalizar
            setTimeout(() => finalizeRecording(), 50);
        };

        mediaRecorder.start(SEGMENT_MS);

        // Mostrar controles de pausa/descartar para modo reunión
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

        showSuccess('¡Grabación de reunión iniciada!');

    } catch (error) {
        console.error('Error al iniciar grabación de reunión:', error);
        showError('No se pudo acceder a las fuentes de audio. Verifica los permisos.');
    }
}

// Detener grabación de reunión
async function stopMeetingRecording() {
    meetingRecording = false;

    if (microphoneMutedBeforePause === false) {
        setMicrophoneMuteState(false);
    }
    microphoneMutedBeforePause = null;

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

    let combinedVolume = 0;
    let hasData = false;

    // Analizar audio del sistema
    if (systemAnalyser && systemDataArray && !systemAudioMuted) {
        systemAnalyser.getByteFrequencyData(systemDataArray);
        updateMeetingAudioBars('system-audio-visualizer', systemDataArray);
        if (systemSpectrogramCtx) drawSpectrogram(systemSpectrogramCtx, systemDataArray);
        combinedVolume = Math.max(combinedVolume, getAverageVolumeLevel(systemDataArray));
        hasData = true;
    }

    // Analizar audio del micrófono
    if (microphoneAnalyser && microphoneDataArray && !microphoneAudioMuted) {
        microphoneAnalyser.getByteFrequencyData(microphoneDataArray);
        updateMeetingAudioBars('microphone-audio-visualizer', microphoneDataArray);
        if (microphoneSpectrogramCtx) drawSpectrogram(microphoneSpectrogramCtx, microphoneDataArray);
        combinedVolume = Math.max(combinedVolume, getAverageVolumeLevel(microphoneDataArray));
        hasData = true;
    }

    updateMeetingVolumeRings(hasData ? combinedVolume : 0);

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
    const micCircle = document.getElementById('meeting-mic-circle');
    const micIcon = document.getElementById('meeting-record-icon');
    const timerCounter = document.getElementById('meeting-timer-counter');
    const timerLabel = document.getElementById('meeting-timer-label');
    const actions = document.getElementById('meeting-recorder-actions');

    if (recording) {
        if (micCircle) micCircle.classList.add('recording');
        setIcon(micIcon, 'stop');
        if (timerCounter) timerCounter.classList.add('recording');
        if (timerLabel) {
            timerLabel.textContent = 'Grabando reunión...';
            timerLabel.classList.add('recording');
        }
        if (actions) actions.classList.add('show');
    } else {
        if (micCircle) micCircle.classList.remove('recording');
        setIcon(micIcon, 'video');
        if (timerCounter) {
            timerCounter.classList.remove('recording');
            timerCounter.textContent = '00:00:00';
        }
        if (timerLabel) {
            timerLabel.textContent = 'Listo para grabar';
            timerLabel.classList.remove('recording');
        }
        if (actions) actions.classList.remove('show');
        updateMeetingVolumeRings(0);
    }
}

// Actualizar timer de reunión
function updateMeetingTimer() {
    if (!meetingStartTime || !meetingRecording) return;

    const elapsed = Date.now() - meetingStartTime;
    const elapsedMinutes = Math.floor(elapsed / 60000);
    const maxMinutes = Math.floor(MAX_DURATION_MS / 60000);
    const warningThreshold = MAX_DURATION_MS - WARN_BEFORE_MINUTES * 60 * 1000;

    // Debug logging cada 30 segundos
    if (elapsedMinutes > 0 && elapsed % 30000 < 100) {
        console.log(`⏱️ Timer Reunión: ${elapsedMinutes}/${maxMinutes} min - Límite advertencia: ${Math.floor(warningThreshold/60000)} min`);
    }

    if (elapsed >= MAX_DURATION_MS) {
        console.log('🛑 LÍMITE DE TIEMPO REUNIÓN ALCANZADO - Deteniendo grabación automáticamente');
        // Solo usar beep de fallback para límite alcanzado
        playFallbackBeep();
        stopMeetingRecording();
        return;
    }

    if (!limitWarningShown && elapsed >= warningThreshold) {
        console.log(`🚨 ACTIVANDO ADVERTENCIA REUNIÓN: ${elapsedMinutes} min transcurridos de ${maxMinutes} max`);
        showWarning(`Quedan ${WARN_BEFORE_MINUTES} minutos para el límite de grabación`);
        limitWarningShown = true;
    }

    const hours = Math.floor(elapsed / 3600000);
    const minutes = Math.floor((elapsed % 3600000) / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);

    const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    const timerEl = document.getElementById('meeting-timer-counter');
    if (timerEl) {
        timerEl.textContent = timeString;

        // Cambiar color cuando se acerque al límite
        if (elapsed >= warningThreshold) {
            timerEl.style.color = '#ff4444';
            timerEl.style.fontWeight = 'bold';
        }
    }
}

// Función simple para mostrar modal
// Función genérica para mostrar modal de upgrade con mensaje personalizable
function showUpgradeModal(options = {}) {
    console.log('🚀 INICIANDO showUpgradeModal...');

    const modal = document.getElementById('postpone-locked-modal');
    console.log('🔍 Modal encontrado:', !!modal);

    if (!modal) {
        console.error('❌ Modal no encontrado!');
        alert('Esta opción requiere un plan superior.');
        return;
    }

    // Configurar contenido del modal
    const title = options.title || 'Opción disponible en planes superiores';
    const message = options.message || 'Esta opción está disponible para los planes: Negocios, Enterprise, Founder, Developer y Superadmin.';
    const icon = options.icon || 'lock';

    // Actualizar contenido del modal
    const modalTitle = modal.querySelector('.modal-title');
    const modalDescription = modal.querySelector('.modal-description');

    if (modalTitle) {
        modalTitle.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="modal-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                ${icon === 'file' ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />' :
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />'}
            </svg>
            ${title}
        `;
    }

    if (modalDescription) {
        modalDescription.innerHTML = message;
    }

    // Manejar el botón de cerrar si se especifica ocultarlo
    const closeButton = modal.querySelector('.btn:not(.btn-primary)'); // Botón "Cerrar"
    if (options.hideCloseButton && closeButton) {
        closeButton.style.display = 'none';
        console.log('🔒 Botón cerrar ocultado');
    } else if (closeButton) {
        closeButton.style.display = ''; // Mostrar botón cerrar normalmente
    }

    // Resetear cualquier estilo previo
    modal.removeAttribute('style');

    // Aplicar estilos de forma directa y forzada para centrado perfecto
    modal.style.setProperty('display', 'flex', 'important');
    modal.style.setProperty('align-items', 'center', 'important');
    modal.style.setProperty('justify-content', 'center', 'important');
    modal.style.setProperty('visibility', 'visible', 'important');
    modal.style.setProperty('opacity', '1', 'important');

    // Bloquear scroll del body
    document.body.style.setProperty('overflow', 'hidden', 'important');

    console.log('✅ Modal configurado. Display:', modal.style.display);
}

// Función específica para opción posponer (retrocompatibilidad)
function showPostponeLockedModal() {
    showUpgradeModal({
        title: 'Opción disponible en planes superiores',
        message: 'La opción "Posponer" está disponible para los planes: <strong>Negocios</strong> y <strong>Enterprise</strong>.',
        icon: 'lock'
    });
}

// Función específica para límite de tamaño de archivo
function showFileSizeLimitModal(fileSize, maxSize = 50, planLabel = 'Plan Free') {
    const fileSizeMB = (fileSize / (1024 * 1024)).toFixed(1);
    const planText = planLabel || 'tu plan actual';
    showUpgradeModal({
        title: 'Límite de tamaño excedido',
        message: `El archivo pesa <strong>${fileSizeMB}MB</strong>. Los usuarios del <strong>${planText}</strong> tienen un límite de <strong>${maxSize}MB</strong>. Actualiza tu plan para subir archivos más grandes.`,
        icon: 'file'
    });
}

// Hacer funciones globales
window.showUpgradeModal = showUpgradeModal;
window.showFileSizeLimitModal = showFileSizeLimitModal;

// Función simple para cerrar modal
window.closePostponeLockedModal = function() {
    console.log('🔄 INICIANDO closePostponeLockedModal...');

    const modal = document.getElementById('postpone-locked-modal');
    console.log('🔍 Modal para cerrar encontrado:', !!modal);

    if (modal) {
        // Resetear y aplicar estilos de cierre de forma forzada
        modal.removeAttribute('style');
        modal.style.setProperty('display', 'none', 'important');
        modal.style.setProperty('visibility', 'hidden', 'important');
        modal.style.setProperty('opacity', '0', 'important');

        // Restaurar scroll del body
        document.body.removeAttribute('style');
        document.body.style.setProperty('overflow', '', 'important');

        console.log('✅ Modal cerrado. Display:', modal.style.display);
        console.log('✅ Body overflow restaurado:', document.body.style.overflow);
    } else {
        console.error('❌ No se encontró el modal para cerrar');
    }
}

// Función simple para ir a planes
window.goToProfilePlans = function() {
    console.log('🚀 Navegando a planes...');

    // Cerrar modal primero
    const modal = document.getElementById('postpone-locked-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Ir a perfil
    sessionStorage.setItem('navigateToPlans', 'true');
    window.location.href = '/profile';
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
window.discardRecording = requestDiscardRecording;
window.confirmDiscardRecording = confirmDiscardRecording;
window.cancelDiscardRecording = cancelDiscardRecording;
window.confirmRecordingNavigationChange = confirmRecordingNavigationChange;
window.cancelRecordingNavigationChange = cancelRecordingNavigationChange;
window.togglePostponeMode = togglePostponeMode;
// Funciones del grabador de reuniones que faltaban
window.toggleSystemAudio = toggleSystemAudio;
window.toggleMicrophoneAudio = toggleMicrophoneAudio;
window.muteSystemAudio = muteSystemAudio;
window.muteMicrophoneAudio = muteMicrophoneAudio;
window.toggleMeetingRecording = toggleMeetingRecording;
window.setupMeetingRecorder = setupMeetingRecorder;

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

// Funciones globales para manejar reintentos de subida
window.retryUpload = async function() {
    if (!failedAudioBlob || !failedAudioName) {
        console.error('🔄 [Retry] No hay datos de audio para reintentar');
        showError('No hay datos de audio para reintentar');
        return;
    }

    if (retryAttempts >= MAX_RETRY_ATTEMPTS) {
        showError(`Has excedido el máximo de intentos (${MAX_RETRY_ATTEMPTS}). Intenta descargar el archivo.`);
        return;
    }

    retryAttempts++;
    console.log(`🔄 [Retry] Intento ${retryAttempts}/${MAX_RETRY_ATTEMPTS}`);

    // Deshabilitar botón y mostrar progreso
    const retryBtn = document.getElementById('retry-upload-btn');
    const progressDiv = document.getElementById('retry-progress');
    const progressBar = document.getElementById('retry-progress-bar');
    const progressText = document.getElementById('retry-progress-text');

    if (retryBtn) retryBtn.disabled = true;
    if (progressDiv) progressDiv.style.display = 'block';
    if (progressText) progressText.textContent = `Reintentando subida (${retryAttempts}/${MAX_RETRY_ATTEMPTS})...`;

    // Create progress notification
    const notificationId = await createUploadProgressNotification(
        failedAudioName,
        `Reintentando subida (${retryAttempts}/${MAX_RETRY_ATTEMPTS})...`
    );

    try {
        // Función de progreso
        const onProgress = (loaded, total) => {
            const percent = (loaded / total) * 100;
            if (progressBar) {
                // Actualizar la barra de progreso usando CSS custom property
                progressBar.style.setProperty('--progress-width', `${percent}%`);
                // También actualizar directamente el after pseudo-element via style
                const afterElement = progressBar.querySelector('::after');
                if (afterElement) {
                    afterElement.style.width = `${percent}%`;
                }
            }
            if (progressText) {
                progressText.textContent = `Subiendo... ${percent.toFixed(0)}%`;
            }

            // Update notification with progress
            if (notificationId && percent > 0) {
                updateUploadProgressNotification(notificationId, `Subiendo... ${percent.toFixed(0)}%`);
            }
        };

        // Intentar subida - usar función que respeta el plan del usuario
        const result = await uploadInBackground(failedAudioBlob, failedAudioName, onProgress);

        // Éxito
        console.log('✅ [Retry] Subida exitosa en intento', retryAttempts);

        // Create success notification with folder info
        const folderInfo = result?.folder_info || { root_folder: 'Grabaciones', subfolder: 'Sin clasificar' };
        await createUploadSuccessNotification(failedAudioName, folderInfo);

        // Clean up progress notification
        await dismissNotification(notificationId);

        showSuccess(`¡Archivo subido exitosamente en el intento ${retryAttempts}!`);
        clearFailedUploadData();

    } catch (error) {
        console.error(`❌ [Retry] Fallo en intento ${retryAttempts}:`, error);

        // Clean up progress notification on error
        await dismissNotification(notificationId);

        if (retryAttempts >= MAX_RETRY_ATTEMPTS) {
            showError(`Subida falló después de ${MAX_RETRY_ATTEMPTS} intentos. Puedes descargar el archivo manualmente.`);
            if (progressText) progressText.textContent = 'Máximo de intentos alcanzado';
        } else {
            showError(`Intento ${retryAttempts} falló. Puedes intentar nuevamente.`);
            if (progressText) progressText.textContent = `Fallo en intento ${retryAttempts}`;
        }
    } finally {
        // Rehabilitar botón y ocultar progreso
        if (retryBtn) retryBtn.disabled = false;
        if (progressDiv) {
            setTimeout(() => {
                progressDiv.style.display = 'none';
            }, 2000);
        }
    }
};

window.downloadFailedAudio = async function() {
    if (!failedAudioBlob || !failedAudioName) {
        console.error('🔄 [Download] No hay datos de audio para descargar');
        showError('No hay datos de audio para descargar');
        return;
    }

    try {
        const fileName = await downloadAudioAsOgg(failedAudioBlob, failedAudioName);
        console.log('💾 [Download] Archivo descargado:', fileName);
        showSuccess(`Archivo ${fileName} descargado correctamente como OGG`);
    } catch (error) {
        console.error('❌ [Download] Error al descargar como OGG:', error);
        // Fallback to normal download
        const fileName = downloadAudioWithCorrectFormat(failedAudioBlob, failedAudioName);
        showSuccess(`Archivo ${fileName} descargado (formato original)`);
    }
};

window.discardFailedAudio = function() {
    if (confirm('¿Estás seguro de que quieres descartar el audio? Esta acción no se puede deshacer.')) {
        clearFailedUploadData();
        console.log('🗑️ [Discard] Audio descartado por el usuario');
        showSuccess('Audio descartado correctamente');
    }
};

// ===== NOTIFICATION MANAGEMENT FUNCTIONS =====

/**
 * Creates a progress notification for upload operations
 * @param {string} filename - Name of the file being uploaded
 * @param {string} message - Progress message to show
 * @returns {Promise<string>} Notification ID for later updates
 */
async function createUploadProgressNotification(filename, message) {
    try {
        const response = await fetch('/api/notifications', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                type: 'audio_upload_progress',
                message: message,
                data: {
                    meeting_name: filename,
                    status: 'progress'
                }
            })
        });

        if (!response.ok) {
            throw new Error(`Failed to create notification: ${response.status}`);
        }

        const notification = await response.json();
        console.log('📧 [notifications] Created progress notification:', notification.id);

        // Refresh notifications display
        if (window.notifications) {
            window.notifications.refresh();
        }

        return notification.id;
    } catch (error) {
        console.warn('📧 [notifications] Failed to create progress notification:', error);
        return null; // Return null if notification creation fails (non-critical)
    }
}

/**
 * Updates an existing progress notification
 * @param {string} notificationId - ID of the notification to update
 * @param {string} message - New progress message
 */
async function updateUploadProgressNotification(notificationId, message) {
    if (!notificationId) return;

    try {
        const response = await fetch(`/api/notifications/${notificationId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                message: message,
                data: {
                    status: 'progress'
                }
            })
        });

        if (!response.ok) {
            throw new Error(`Failed to update notification: ${response.status}`);
        }

        console.log('📧 [notifications] Updated progress notification:', notificationId);

        // Refresh notifications display
        if (window.notifications) {
            window.notifications.refresh();
        }
    } catch (error) {
        console.warn('📧 [notifications] Failed to update progress notification:', error);
    }
}

/**
 * Creates a success notification for completed uploads
 * @param {string} filename - Name of the uploaded file
 * @param {Object} folderInfo - Information about the upload destination
 */
async function createUploadSuccessNotification(filename, folderInfo) {
    try {
        const rootFolder = folderInfo.root_folder || 'Grabaciones';
        const subfolder = folderInfo.subfolder || 'Sin clasificar';
        const message = `Se ha subido audio pospuesto a la carpeta: ${rootFolder}/${subfolder}`;

        const response = await fetch('/api/notifications', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                type: 'audio_upload_success',
                message: message,
                data: {
                    meeting_name: filename,
                    root_folder: rootFolder,
                    subfolder: subfolder,
                    status: 'success'
                }
            })
        });

        if (!response.ok) {
            throw new Error(`Failed to create success notification: ${response.status}`);
        }

        const notification = await response.json();
        console.log('📧 [notifications] Created success notification:', notification.id);

        // Refresh notifications display
        if (window.notifications) {
            window.notifications.refresh();
        }

        return notification.id;
    } catch (error) {
        console.warn('📧 [notifications] Failed to create success notification:', error);
        return null;
    }
}

/**
 * Dismisses a notification by ID
 * @param {string} notificationId - ID of the notification to dismiss
 */
async function dismissNotification(notificationId) {
    if (!notificationId) return;

    try {
        const response = await fetch(`/api/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (!response.ok) {
            throw new Error(`Failed to dismiss notification: ${response.status}`);
        }

        console.log('📧 [notifications] Dismissed notification:', notificationId);

        // Refresh notifications display
        if (window.notifications) {
            window.notifications.refresh();
        }
    } catch (error) {
        console.warn('📧 [notifications] Failed to dismiss notification:', error);
    }
}

/**
 * Verifica el estado del procesamiento y descarga automáticamente para usuarios BNI
 * @param {string} pendingRecordingId - ID del pending recording
 * @param {boolean} isTemporary - Si es una transcripción temporal
 */
async function checkAndDownloadForBNI(pendingRecordingId, isTemporary = false) {
    const userRole = (window.userRole || '').toString().toLowerCase();
    if (userRole !== 'bni') return;

    const maxAttempts = 20; // Máximo 20 intentos (10 minutos)
    let attempts = 0;

    const checkStatus = async () => {
        try {
            attempts++;
            console.log(`Verificando estado del procesamiento BNI (intento ${attempts}/${maxAttempts})`);

            const response = await fetch(`/api/pending-recordings/${pendingRecordingId}/status`);
            const data = await response.json();

            if (data.status === 'completed') {
                console.log('Procesamiento completado para usuario BNI - iniciando descarga automática');
                
                // Construir URL de descarga
                let downloadUrl = `/api/meetings/${data.meeting_id}/download-ju`;
                
                if (isTemporary) {
                    downloadUrl = `/api/transcriptions-temp/${data.meeting_id}/download-ju`;
                }

                // Iniciar descarga automática
                setTimeout(() => {
                    console.log('Descargando .ju automáticamente para usuario BNI:', downloadUrl);
                    window.location.href = downloadUrl;
                }, 1000);

                return;
            } else if (data.status === 'failed') {
                console.warn('Procesamiento falló - no se puede descargar automáticamente');
                return;
            } else if (attempts >= maxAttempts) {
                console.warn('Tiempo agotado esperando procesamiento - descarga automática cancelada');
                return;
            } else {
                // Continuar verificando cada 30 segundos
                setTimeout(checkStatus, 30000);
            }

        } catch (error) {
            console.error('Error verificando estado para descarga BNI:', error);
            if (attempts < maxAttempts) {
                setTimeout(checkStatus, 30000);
            }
        }
    };

    // Iniciar verificación
    checkStatus();
}
