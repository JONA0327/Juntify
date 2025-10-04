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
let systemGainNode = null;
let microphoneGainNode = null;
let meetingDestination = null;
let systemSpectrogramCanvas = null;
let microphoneSpectrogramCanvas = null;
let systemSpectrogramCtx = null;
let microphoneSpectrogramCtx = null;
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

// Helper para descargar un blob como archivo
function downloadBlob(blob, fileName) {
    try {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.warn('⚠️ [Download] No se pudo descargar el archivo automáticamente:', e);
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
    allow_postpone: true,
    warn_before_minutes: 5,
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
        const resp = await fetch('/api/plan/limits', { credentials: 'include' });
        if (resp.ok) {
            const limits = await resp.json();
            PLAN_LIMITS = limits;
            // Duración máxima por reunión
            const minutes = Number(limits.max_duration_minutes || 120);
            MAX_DURATION_MS = minutes * 60 * 1000;
            WARN_BEFORE_MINUTES = Number(limits.warn_before_minutes || 5);
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
                        const meetBtn = document.getElementById('meeting-record-btn');
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

    // Poblar lista de micrófonos disponibles
    try {
        await populateMicrophoneDevices();
    } catch (e) {
        console.warn('⚠️ No se pudieron cargar los dispositivos de micrófono:', e);
    }
    // React a cambios de hardware (conexión/desconexión de dispositivos)
    try {
        if (navigator.mediaDevices && 'ondevicechange' in navigator.mediaDevices) {
            navigator.mediaDevices.addEventListener('devicechange', async () => {
                console.log('🔌 [new-meeting] Cambio en dispositivos detectado, recargando lista de micrófonos...');
                await populateMicrophoneDevices();
            });
        }
    } catch (_) {}
});

// ===== Handlers globales para compatibilidad con atributos inline =====
// Notas:
// - Vite usa módulos ES, por lo que las funciones no son globales por defecto.
// - Estos enlaces aseguran que los onClick/onChange de las vistas Blade funcionen.
window.toggleRecording = toggleRecording;
window.pauseRecording = pauseRecording;
window.resumeRecording = resumeRecording;
window.discardRecording = discardRecording;
window.togglePostponeMode = togglePostponeMode;
// Modo de grabación (para onClick inline en Blade)
window.selectRecordingMode = selectRecordingMode;
// Exponer también la función de UI por si se usa desde consola o pruebas
window.showRecordingInterface = showRecordingInterface;

// Stubs seguros para los controles del grabador de reuniones (UI aún no implementada aquí)
function toggleMeetingRecording() {
    console.warn('[new-meeting] toggleMeetingRecording no implementado todavía');
    showWarning('El grabador de reuniones aún no está disponible en esta versión.');
}
function toggleSystemAudio() {
    console.warn('[new-meeting] toggleSystemAudio no implementado todavía');
    showWarning('Control de audio del sistema no disponible por ahora.');
}
function toggleMicrophoneAudio() {
    console.warn('[new-meeting] toggleMicrophoneAudio no implementado todavía');
    showWarning('Control avanzado del micrófono no disponible por ahora.');
}
function muteSystemAudio() {
    console.warn('[new-meeting] muteSystemAudio no implementado todavía');
}
function muteMicrophoneAudio() {
    console.warn('[new-meeting] muteMicrophoneAudio no implementado todavía');
}
function setupMeetingRecorder() {
    console.warn('[new-meeting] setupMeetingRecorder no implementado todavía');
}
window.toggleMeetingRecording = toggleMeetingRecording;
window.toggleSystemAudio = toggleSystemAudio;
window.toggleMicrophoneAudio = toggleMicrophoneAudio;
window.muteSystemAudio = muteSystemAudio;
window.muteMicrophoneAudio = muteMicrophoneAudio;
window.setupMeetingRecorder = setupMeetingRecorder;

// ===== Handlers para reintento/descarga/descartar subida fallida =====
function stripFileExtension(name) {
    if (!name) return '';
    const i = name.lastIndexOf('.');
    return i > 0 ? name.substring(0, i) : name;
}

async function retryUpload() {
    try {
        if (!failedAudioBlob) {
            showWarning('No hay audio pendiente para reintentar.');
            return;
        }
        const base = stripFileExtension(failedAudioName) || 'grabacion';
        showSuccess('Reintentando subida del audio...');
        await uploadInBackground(failedAudioBlob, base, (loaded, total) => {
            const pct = Math.min(100, Math.round((loaded / total) * 100));
            const bar = document.getElementById('retry-progress-bar');
            const txt = document.getElementById('retry-progress-text');
            const wrap = document.getElementById('retry-progress');
            if (wrap) wrap.style.display = 'block';
            if (bar) bar.style.setProperty('--pct', pct + '%');
            if (txt) txt.textContent = `Subiendo... ${pct}%`;
        });
        clearFailedUploadData();
        showSuccess('Audio subido correctamente');
    } catch (e) {
        console.error('❌ [Retry] Error al reintentar subida:', e);
        showError('No se pudo completar la subida. Intenta nuevamente.');
    }
}

function downloadFailedAudio() {
    if (!failedAudioBlob) {
        showWarning('No hay audio para descargar.');
        return;
    }
    const base = stripFileExtension(failedAudioName) || 'grabacion_fallida';
    try {
        downloadAudioWithCorrectFormat(failedAudioBlob, base);
    } catch (_) {
        downloadBlob(failedAudioBlob, base);
    }
}

function discardFailedAudio() {
    clearFailedUploadData();
    showSuccess('Audio fallido descartado.');
}

window.retryUpload = retryUpload;
window.downloadFailedAudio = downloadFailedAudio;
window.discardFailedAudio = discardFailedAudio;

// ===== DISPOSITIVOS DE AUDIO (MICRÓFONOS) =====
async function populateMicrophoneDevices() {
    const select = document.getElementById('microphone-device');
    if (!select) return;

    // Limpia opciones evitando duplicar placeholder
    select.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.disabled = true;
    placeholder.selected = true;
    placeholder.textContent = '🔍 Selecciona un micrófono...';
    select.appendChild(placeholder);

    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        console.warn('⚠️ enumerateDevices no soportado en este navegador.');
        const opt = document.createElement('option');
        opt.value = '';
        opt.disabled = true;
        opt.textContent = 'Navegador no soporta dispositivos';
        select.appendChild(opt);
        return;
    }

    // Intentar obtener permisos para leer labels; si ya están concedidos, esto será rápido
    let tempStream = null;
    try {
        // Solo solicitar si aún no tenemos permiso (heurística: labels vacías en un primer intento)
        let devices = await navigator.mediaDevices.enumerateDevices();
        const labelsMissing = devices.filter(d => d.kind === 'audioinput').every(d => !d.label);
        if (labelsMissing) {
            try {
                tempStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (permErr) {
                console.warn('⚠️ No se concedió permiso de micrófono aún. Se mostrarán nombres genéricos.');
            }
            // Re-enumerar luego de permiso
            devices = await navigator.mediaDevices.enumerateDevices();
        }

        const audioInputs = devices.filter(d => d.kind === 'audioinput');
        if (audioInputs.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.disabled = true;
            opt.textContent = 'No se detectaron micrófonos';
            select.appendChild(opt);
            return;
        }

        // Persistencia de selección
        let savedDeviceId = null;
        try {
            savedDeviceId = localStorage.getItem('selectedMicrophoneId') || sessionStorage.getItem('selectedMicrophoneId');
        } catch (_) {}

        audioInputs.forEach((d, idx) => {
            const opt = document.createElement('option');
            opt.value = d.deviceId;
            const label = d.label && d.label.trim().length > 0 ? d.label : `Micrófono ${idx + 1}`;
            opt.textContent = `🎙️ ${label}`;
            if (savedDeviceId && d.deviceId === savedDeviceId) {
                opt.selected = true;
                placeholder.selected = false;
            }
            select.appendChild(opt);
        });

        // Guardar cambios cuando el usuario seleccione
        select.addEventListener('change', () => {
            try {
                localStorage.setItem('selectedMicrophoneId', select.value || '');
                sessionStorage.setItem('selectedMicrophoneId', select.value || '');
            } catch (_) {}
        }, { once: true });

    } catch (e) {
        console.error('❌ Error al enumerar dispositivos:', e);
        const opt = document.createElement('option');
        opt.value = '';
        opt.disabled = true;
        opt.textContent = 'Error al cargar dispositivos';
        select.appendChild(opt);
    } finally {
        if (tempStream) {
            try { tempStream.getTracks().forEach(t => t.stop()); } catch (_) {}
        }
    }
}

// ===== FUNCIONES DE GRABACIÓN =====

// Obtener las restricciones de audio basadas en las opciones avanzadas
async function getAudioConstraints() {
    const deviceSelect = document.getElementById('microphone-device');

    const constraints = {
        echoCancellation: true,
        noiseSuppression: true,
        sampleRate: 44100
    };

    // Prefer UI selection; fallback to persisted selection
    let chosenId = deviceSelect && deviceSelect.value ? deviceSelect.value : null;
    if (!chosenId) {
        try {
            chosenId = localStorage.getItem('selectedMicrophoneId') || sessionStorage.getItem('selectedMicrophoneId');
        } catch (_) {}
    }
    if (chosenId) {
        constraints.deviceId = { exact: chosenId };
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
                // Enviar chunk a servidor para almacenamiento incremental (opcional)
                try {
                    sendChunkToServer(event.data, chunkIndex++);
                } catch (_) {
                    // Si no está implementado, continuar sin bloquear
                }
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
    recordedChunks = [];

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch(_) {}
        try { mediaRecorder.stream.getTracks().forEach(track => track.stop()); } catch(_) {}
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

// ===== HELPERS FALTANTES =====
// Envío incremental de chunks (no bloqueante). Se puede ampliar en el futuro.
async function sendChunkToServer(blob, index) {
    // Desactivado por defecto: retornar sin hacer nada para evitar errores en dev
    // Si se desea, implementar POST a '/api/recordings/chunk' con session id.
    return false;
}

function handlePostActionCleanup(keepUI = false) {
    // Limpia timers/animaciones y restablece controles; si keepUI=true, evita reset completo (p.ej. uploads bg)
    try {
        if (recordingTimer) {
            clearInterval(recordingTimer);
            recordingTimer = null;
        }
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }
        if (!keepUI) {
            updateRecordingUI(false);
            resetAudioVisualizer();
            resetRecordingControls();
        }
    } catch (_) {}
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        try {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        } catch (e) {
            reject(e);
        }
    });
}

function showPostponeLockedModal() {
    // Mensaje simple de upgrade; puede integrarse con tu sistema de modales si existe
    alert('La opción de posponer está disponible para los planes Negocios y Enterprise.');
}
window.showPostponeLockedModal = showPostponeLockedModal;


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

    // Determinar contexto y descargar copia local en OGG cuando es reunión
    const context = lastRecordingContext || (selectedMode === 'meeting' ? 'meeting' : 'recording');
    if (context === 'meeting') {
        try {
            await downloadAudioAsOgg(finalBlob, name);
        } catch (_) {
            downloadAudioWithCorrectFormat(finalBlob, name);
        }
    }
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

    if (elapsed >= MAX_DURATION_MS) {
        stopRecording();
        return;
    }

    if (!limitWarningShown && elapsed >= MAX_DURATION_MS - WARN_BEFORE_MINUTES * 60 * 1000) {
        showWarning(`Quedan ${WARN_BEFORE_MINUTES} minutos para el límite de grabación`);
        limitWarningShown = true;
    }

    const hours = Math.floor(elapsed / 3600000);
    const minutes = Math.floor((elapsed % 3600000) / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);

    const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    document.getElementById('timer-counter').textContent = timeString;
}

// Función para mostrar advertencias
function showWarning(message) {
    const notification = document.createElement('div');
    notification.className = 'notification warning';
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">⚠️</span>
            <span class="notification-message">${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 5000);

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

    // Forzar Drive personal para audios pospuestos
    const driveType = 'personal';

    formData.append('audioFile', blob, fileName);
    formData.append('meetingName', name);
    formData.append('driveType', driveType); // Send drive type to backend
    // No enviamos subcarpeta explícita; backend resolverá "Audios Pospuestos"
    console.log(`🗂️ [Upload] Subiendo a Drive tipo: ${driveType}`);

    // Remove the default rootFolder - let backend handle folder creation
    // formData.append('rootFolder', 'default');

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    return new Promise((resolve, reject) => {
        // Crear (o reutilizar) notificación flotante de progreso local
        let progressNotification = document.querySelector('[data-upload-notification="active"]');
        if (!progressNotification) {
            progressNotification = document.createElement('div');
            progressNotification.setAttribute('data-upload-notification', 'active');
            progressNotification.style.position = 'fixed';
            progressNotification.style.bottom = '1rem';
            progressNotification.style.right = '1rem';
            progressNotification.style.zIndex = '9999';
            progressNotification.style.minWidth = '280px';
            progressNotification.innerHTML = `
                <div class="bg-slate-900/90 border border-slate-700/70 rounded-xl p-4 shadow-xl backdrop-blur-sm text-slate-200 text-sm font-medium flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-slate-400">Subiendo audio</span>
                        <button type="button" class="text-slate-500 hover:text-slate-300 text-xs" data-close-upload>&times;</button>
                    </div>
                    <div class="text-xs line-clamp-1" title="${name}">${name}</div>
                    <div class="h-2 w-full bg-slate-700/60 rounded overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-yellow-400 to-amber-500 rounded upload-progress-bar" style="width:0%"></div>
                    </div>
                    <div class="flex justify-between text-[10px] tracking-wide text-slate-400">
                        <span class="upload-progress-label">Iniciando...</span>
                        <span class="upload-progress-percent">0%</span>
                    </div>
                </div>`;
            document.body.appendChild(progressNotification);
            const closeBtn = progressNotification.querySelector('[data-close-upload]');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    progressNotification.remove();
                });
            }
        } else {
            const label = progressNotification.querySelector('.upload-progress-label');
            if (label) label.textContent = 'Reiniciando...';
        }

        // Refrescar notificaciones globales para que aparezca "Subida iniciada" del backend cuanto antes
        try { if (window.notifications) { window.notifications.refresh(); } } catch (_) {}

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/drive/upload-pending-audio');
        xhr.setRequestHeader('X-CSRF-TOKEN', token);
        // No enviamos subcarpeta explícita; el backend resolverá "Audios Pospuestos"

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable && onProgress) {
                onProgress(e.loaded, e.total);
            }
            if (e.lengthComputable) {
                const pct = Math.min(100, Math.round((e.loaded / e.total) * 100));
                const bar = progressNotification.querySelector('.upload-progress-bar');
                const pctSpan = progressNotification.querySelector('.upload-progress-percent');
                const label = progressNotification.querySelector('.upload-progress-label');
                if (bar) bar.style.width = pct + '%';
                if (pctSpan) pctSpan.textContent = pct + '%';
                if (label) label.textContent = pct < 100 ? 'Subiendo...' : 'Procesando respuesta...';
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

                if (response?.pending_recording) {
                    pollPendingRecordingStatus(response.pending_recording);
                }
                if (window.notifications) {
                    window.notifications.refresh();
                }
                // Actualizar notificación local a éxito
                try {
                    const label = progressNotification.querySelector('.upload-progress-label');
                    const pctSpan = progressNotification.querySelector('.upload-progress-percent');
                    const bar = progressNotification.querySelector('.upload-progress-bar');
                    if (label) label.textContent = 'Completado';
                    if (pctSpan) pctSpan.textContent = '100%';
                    if (bar) bar.style.width = '100%';
                    setTimeout(() => { progressNotification.remove(); }, 4000);
                } catch (_) {}
                // Limpiar datos de fallo si la subida fue exitosa
                clearFailedUploadData();
                resolve(response);
            } else {
                console.error('Upload failed with status:', xhr.status, xhr.responseText);
                try {
                    const label = progressNotification.querySelector('.upload-progress-label');
                    if (label) label.textContent = 'Error';
                    progressNotification.classList.add('shake');
                } catch(_) {}
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
            try {
                const label = progressNotification.querySelector('.upload-progress-label');
                if (label) label.textContent = 'Error de red';
            } catch(_) {}
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

// Función existente para compatibilidad
function uploadInBackground(blob, name, onProgress) {
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
        // Respaldo: guardar base64 si el blob no es muy grande (<= 50MB)
        try {
            if (pendingAudioBlob.size <= 50 * 1024 * 1024) {
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

// Subcarpeta: dejamos que el backend resuelva automáticamente "Audios Pospuestos" en Drive personal
let pendingAudioSubfolderId = '';

// Helper: fetch con timeout para evitar UI bloqueada si una extensión del navegador intercepta la petición
async function fetchWithTimeout(url, options = {}, timeoutMs = 10000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const resp = await fetch(url, { ...options, signal: controller.signal });
        return resp;
    } finally {
        clearTimeout(id);
    }
}

// Ya no se carga selector de subcarpetas ni se consulta al backend aquí

// Dentro de la función que construye formData para upload pending añadir:
// ...existing code...
function buildPendingUploadFormData(blob, fileName, name, driveType, onProgress) {
    const formData = new FormData();
    formData.append('audioFile', blob, fileName);
    formData.append('meetingName', name);
    formData.append('driveType', driveType);
    // No enviamos subcarpeta: el backend usará/creará "Audios Pospuestos" en Drive personal
    return { formData };
}
// Reemplazar uso original donde se creaba formData manual.
