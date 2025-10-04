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

// Función para obtener el mejor formato de audio disponible priorizando OGG (Vorbis)
function getOptimalAudioFormat() {
    const formats = [
        'audio/ogg;codecs=vorbis',  // OGG/Vorbis preferido (sin Opus)
        'audio/ogg',                // OGG genérico como respaldo (puede variar por navegador)
        'audio/mpeg',               // MP3 como alternativa universal
        'audio/mp4',                // MP4 audio si es lo único disponible
        'audio/webm'                // WebM genérico como último recurso
    ];

    for (const format of formats) {
        if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(format)) {
            console.log(`🎵 [Recording] Formato seleccionado: ${format}`);
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

// Función específica para descargar en un formato ampliamente reproducible cuando hay errores
async function downloadAudioAsOgg(blob, baseName) {
    try {
        let outBlob = blob;
        let ext = 'ogg';
        // Si no es OGG, intentar convertir a OGG Vorbis; si no es posible, a MP3
        if (!blob.type.includes('ogg')) {
            console.log('🎵 [Download] Convirtiendo para descarga (preferir OGG/Vorbis)...');
            try {
                outBlob = await convertToOgg(blob);
                ext = 'ogg';
            } catch (convErr) {
                console.warn('⚠️ [Download] Conversión a OGG falló, intentando MP3:', convErr);
                outBlob = await convertToMp3(blob);
                ext = 'mp3';
            }
        }

        const fileName = `${baseName}.${ext}`;
        downloadBlob(outBlob, fileName);
        console.log(`💾 [Download] Audio descargado como ${ext.toUpperCase()}: ${fileName}`);
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

// Helper para convertir blobs a OGG (preferir Vorbis) usando MediaRecorder
async function convertToOgg(blob) {
    try {
        console.log(`🎵 [Convert] Iniciando conversión a OGG...`);
        console.log(`🎵 [Convert] Blob original: ${blob.type}, Tamaño: ${(blob.size / 1024).toFixed(1)} KB`);

        // Si ya es OGG, devolver tal como está
        if (blob.type.includes('ogg')) {
            console.log(`✅ [Convert] Ya es OGG, no se requiere conversión`);
            return blob;
        }

        // Intentar específicamente Vorbis primero
        const vorbisSupported = window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/ogg;codecs=vorbis');
        const oggSupported = window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/ogg');
        if (!vorbisSupported && !oggSupported) {
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
            const mime = vorbisSupported ? 'audio/ogg;codecs=vorbis' : 'audio/ogg';
            const recorder = new MediaRecorder(destination.stream, { mimeType: mime });

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

// Conversión simple a MP3 vía Web Audio + MediaRecorder si es posible; si no, devuelve el blob original con MIME mp3
async function convertToMp3(blob) {
    try {
        const mp3Supported = window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/mpeg');
        if (!mp3Supported) {
            const buf = await blob.arrayBuffer();
            return new Blob([buf], { type: 'audio/mpeg' });
        }
        const AC = window.AudioContext || window.webkitAudioContext;
        const ac = AC ? new AC() : null;
        if (!ac) {
            const buf = await blob.arrayBuffer();
            return new Blob([buf], { type: 'audio/mpeg' });
        }
        const arr = await blob.arrayBuffer();
        const audioBuffer = await ac.decodeAudioData(arr.slice(0));
        const dest = ac.createMediaStreamDestination();
        const src = ac.createBufferSource();
        src.buffer = audioBuffer;
        src.connect(dest);
        const chunks = [];
        const recorder = new MediaRecorder(dest.stream, { mimeType: 'audio/mpeg' });
        return await new Promise((resolve, reject) => {
            recorder.ondataavailable = e => { if (e.data?.size) chunks.push(e.data); };
            recorder.onerror = e => reject(e.error || new Error('Error en MediaRecorder MP3'));
            recorder.onstop = () => {
                const out = new Blob(chunks, { type: 'audio/mpeg' });
                resolve(out);
                ac.close().catch(() => {});
            };
            src.onended = () => { if (recorder.state !== 'inactive') recorder.stop(); };
            recorder.start();
            ac.resume().then(() => src.start(0)).catch(reject);
        });
    } catch (e) {
        console.warn('⚠️ [Convert] MP3 conversion failed, falling back to mime-only:', e);
        const buf = await blob.arrayBuffer();
        return new Blob([buf], { type: 'audio/mpeg' });
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
let MAX_DURATION_MS = PLAN_LIMITS.max_duration_minutes * 60 * 1000;


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
            setupUploadInterface();
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
                if (limits.allow_postpone) {
                    postponeToggle.disabled = false;
                    setPostponeMode(postponeToggle.checked);
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
    // Alterna la grabación de reunión (captura de pantalla + audio)
    if (!isRecording) {
        startMeetingRecording();
    } else {
        // Si estamos grabando una reunión, detén
        stopRecording();
    }
}
function toggleSystemAudio() {
    // Alterna estado y refleja en UI (sin captura avanzada todavía)
    systemAudioEnabled = !systemAudioEnabled;
    const btn = document.getElementById('system-audio-btn');
    const text = btn?.querySelector('.source-text');
    if (btn) btn.classList.toggle('active', systemAudioEnabled);
    if (text) text.textContent = systemAudioEnabled ? 'Sistema activado' : 'Sistema desactivado';
    // Aplicar al mezclador si existe
    try {
        if (systemGainNode) {
            systemGainNode.gain.value = (systemAudioEnabled && !systemAudioMuted) ? 1 : 0;
        }
    } catch(_) {}
}
function toggleMicrophoneAudio() {
    microphoneAudioEnabled = !microphoneAudioEnabled;
    const btn = document.getElementById('microphone-audio-btn');
    const text = btn?.querySelector('.source-text');
    if (btn) btn.classList.toggle('active', microphoneAudioEnabled);
    if (text) text.textContent = microphoneAudioEnabled ? 'Micrófono activado' : 'Micrófono desactivado';
    // Aplicar al mezclador si existe
    try {
        if (microphoneGainNode) {
            microphoneGainNode.gain.value = (microphoneAudioEnabled && !microphoneAudioMuted) ? 1 : 0;
        }
    } catch(_) {}
}
function muteSystemAudio() {
    systemAudioMuted = !systemAudioMuted;
    const btn = document.getElementById('system-mute-btn');
    const icon = btn?.querySelector('.mute-icon');
    btn?.classList.toggle('muted', systemAudioMuted);
    if (icon) icon.setAttribute('data-muted', systemAudioMuted ? 'true' : 'false');
    try { if (systemGainNode) systemGainNode.gain.value = systemAudioMuted ? 0 : 1; } catch (_) {}
}
function muteMicrophoneAudio() {
    microphoneAudioMuted = !microphoneAudioMuted;
    const btn = document.getElementById('microphone-mute-btn');
    const icon = btn?.querySelector('.mute-icon');
    btn?.classList.toggle('muted', microphoneAudioMuted);
    if (icon) icon.setAttribute('data-muted', microphoneAudioMuted ? 'true' : 'false');
    try { if (microphoneGainNode) microphoneGainNode.gain.value = microphoneAudioMuted ? 0 : 1; } catch (_) {}
}
function setupMeetingRecorder() {
    // Inicializa UI del modo reunión
    try {
        lastRecordingContext = 'meeting';
        // Activar sistema por defecto
        systemAudioEnabled = true;
        const recordBtn = document.getElementById('meeting-record-btn');
        const pauseBtn = document.getElementById('meeting-pause');
        const resumeBtn = document.getElementById('meeting-resume');
        const discardBtn = document.getElementById('meeting-discard');
        if (recordBtn) recordBtn.classList.toggle('recording', isRecording);
        if (pauseBtn) pauseBtn.style.display = isRecording && !isPaused ? 'inline-block' : 'none';
        if (resumeBtn) resumeBtn.style.display = isRecording && isPaused ? 'inline-block' : 'none';
        if (discardBtn) discardBtn.style.display = isRecording ? 'inline-block' : 'none';
        const label = document.getElementById('meeting-timer-label');
        if (label) label.textContent = isRecording ? 'Grabando...' : 'Listo para grabar';
        const sysBtn = document.getElementById('system-audio-btn');
        const sysText = sysBtn?.querySelector('.source-text');
        if (sysBtn) sysBtn.classList.toggle('active', systemAudioEnabled);
        if (sysText) sysText.textContent = systemAudioEnabled ? 'Sistema activado' : 'Sistema desactivado';
        const micBtn = document.getElementById('microphone-audio-btn');
        const micText = micBtn?.querySelector('.source-text');
        if (micBtn) micBtn.classList.toggle('active', microphoneAudioEnabled);
        if (micText) micText.textContent = microphoneAudioEnabled ? 'Micrófono activado' : 'Micrófono desactivado';
    } catch (_) {}
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
    downloadAudioAsOgg(failedAudioBlob, base);
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

// Selección de MIME para grabación de reunión (video)
function getOptimalMeetingFormat() {
    const candidates = [
        'video/webm;codecs=vp9',
        'video/webm;codecs=vp8',
        'video/webm',
        'video/mp4' // poco fiable en navegadores, fallback tardío
    ];
    for (const mt of candidates) {
        if (MediaRecorder.isTypeSupported?.(mt)) {
            return mt;
        }
    }
    return 'video/webm';
}

async function startMeetingRecording() {
    try {
        discardRequested = false;
        await clearPreviousAudioData();

        // 1) Captura de pantalla con audio del sistema si disponible
        const displayStream = await navigator.mediaDevices.getDisplayMedia({
            video: { frameRate: 30 },
            audio: true
        });

        // 2) Micrófono opcional (según toggle)
        let micStream = null;
        if (microphoneAudioEnabled) {
            const audioConstraints = await getAudioConstraints();
            micStream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints });
        }

        // 3) Mezclar audios (sistema + mic) en un solo track
        const AC = window.AudioContext || window.webkitAudioContext;
        const ac = AC ? new AC() : null;
        meetingDestination = ac ? ac.createMediaStreamDestination() : null;
        systemGainNode = ac ? ac.createGain() : null;
        microphoneGainNode = ac ? ac.createGain() : null;

        const displayAudioTrack = displayStream.getAudioTracks()[0] || null;
        const micAudioTrack = micStream ? micStream.getAudioTracks()[0] : null;

        if (ac && meetingDestination) {
            // Inicializar analizadores para visualizadores y espectrogramas
            systemAnalyser = ac.createAnalyser();
            microphoneAnalyser = ac.createAnalyser();
            // Configuración de FFT y suavizado
            systemAnalyser.fftSize = 256;
            microphoneAnalyser.fftSize = 256;
            systemAnalyser.smoothingTimeConstant = 0.8;
            microphoneAnalyser.smoothingTimeConstant = 0.8;

            // Buffers para datos de frecuencia
            systemDataArray = new Uint8Array(systemAnalyser.frequencyBinCount);
            microphoneDataArray = new Uint8Array(microphoneAnalyser.frequencyBinCount);

            if (displayAudioTrack) {
                const sysSource = ac.createMediaStreamSource(new MediaStream([displayAudioTrack]));
                // Conectar a analizador y a la cadena de salida (ganancia -> destino)
                sysSource.connect(systemAnalyser);
                if (systemGainNode) {
                    systemGainNode.gain.value = systemAudioMuted ? 0 : 1;
                    sysSource.connect(systemGainNode).connect(meetingDestination);
                } else {
                    sysSource.connect(meetingDestination);
                }
            }
            if (micAudioTrack) {
                const micSource = ac.createMediaStreamSource(new MediaStream([micAudioTrack]));
                // Conectar a analizador y a la cadena de salida (ganancia -> destino)
                micSource.connect(microphoneAnalyser);
                if (microphoneGainNode) {
                    microphoneGainNode.gain.value = microphoneAudioMuted ? 0 : 1;
                    micSource.connect(microphoneGainNode).connect(meetingDestination);
                } else {
                    micSource.connect(meetingDestination);
                }
            }
        }

        // 4) Construir stream final con video + audio mezclado (o alguno disponible)
        const finalStream = new MediaStream();
        const videoTrack = displayStream.getVideoTracks()[0];
        if (videoTrack) finalStream.addTrack(videoTrack);
        if (meetingDestination && meetingDestination.stream.getAudioTracks()[0]) {
            finalStream.addTrack(meetingDestination.stream.getAudioTracks()[0]);
        } else if (displayAudioTrack) {
            finalStream.addTrack(displayAudioTrack);
        } else if (micAudioTrack) {
            finalStream.addTrack(micAudioTrack);
        }

        // Guardar refs para detener luego
        systemAudioStream = displayStream;
        microphoneAudioStream = micStream;
        recordingStream = finalStream;

        // Si el usuario deja de compartir, detener la grabación
        try {
            videoTrack?.addEventListener('ended', () => {
                if (isRecording && mediaRecorder && mediaRecorder.state !== 'inactive') {
                    stopRecording();
                }
            });
        } catch (_) {}

        // 5) Preparar estados y MediaRecorder
        audioContext = ac; // reutilizar ciclo de vida para cierre al finalizar
        analyser = null; // no usamos el visualizador del modo audio aquí
        recordedChunks = [];
        currentRecordingId = crypto.randomUUID();
        chunkIndex = 0;
        startTime = Date.now();
        limitWarningShown = false;
        isRecording = true;
        lastRecordingContext = 'meeting';

        updateRecordingUI(true);
        recordingTimer = setInterval(updateTimer, 100);

        // Preparar espectrogramas
        try {
            systemSpectrogramCanvas = document.getElementById('system-spectrogram') || null;
            microphoneSpectrogramCanvas = document.getElementById('microphone-spectrogram') || null;
            systemSpectrogramCtx = systemSpectrogramCanvas ? systemSpectrogramCanvas.getContext('2d') : null;
            microphoneSpectrogramCtx = microphoneSpectrogramCanvas ? microphoneSpectrogramCanvas.getContext('2d') : null;
            initSpectrogramCanvas(systemSpectrogramCanvas, systemSpectrogramCtx);
            initSpectrogramCanvas(microphoneSpectrogramCanvas, microphoneSpectrogramCtx);
        } catch(_) {}

        // Iniciar bucle de análisis de reunión
        startMeetingAnalysis();

        const mimeType = getOptimalMeetingFormat();
        currentRecordingFormat = mimeType;
        mediaRecorder = new MediaRecorder(finalStream, { mimeType });
        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };
        mediaRecorder.onstop = () => {
            if (discardRequested) {
                discardRequested = false;
                return;
            }
            finalizeRecording();
        };
        mediaRecorder.start(SEGMENT_MS);
    } catch (err) {
        console.error('[meeting] Error al iniciar grabación de reunión:', err);
        showError('No se pudo iniciar la grabación de reunión. Verifica permisos de pantalla y micrófono.');
        try { if (systemAudioStream) systemAudioStream.getTracks().forEach(t => t.stop()); } catch(_) {}
        try { if (microphoneAudioStream) microphoneAudioStream.getTracks().forEach(t => t.stop()); } catch(_) {}
    }
}

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

        // Mute inputs and stop visualizers while paused
        try {
            // Audio-only: disable mic tracks
            if (recordingStream) {
                recordingStream.getAudioTracks().forEach(t => { t.enabled = false; });
            }
            // Stop timers/visualizers
            if (animationId) { cancelAnimationFrame(animationId); animationId = null; }
            if (meetingAnimationId) { cancelAnimationFrame(meetingAnimationId); meetingAnimationId = null; }
            resetAudioVisualizer();
            resetMeetingVisualizer();
            // Meeting gains to zero
            if (systemGainNode) systemGainNode.gain.value = 0;
            if (microphoneGainNode) microphoneGainNode.gain.value = 0;
        } catch (_) {}
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

        // Re-enable inputs and restart visualizers
        try {
            if (recordingStream) {
                recordingStream.getAudioTracks().forEach(t => { t.enabled = true; });
            }
            // Restart loops
            if (analyser) startAudioAnalysis();
            // Restore meeting gains based on toggles
            if (systemGainNode) systemGainNode.gain.value = (systemAudioEnabled && !systemAudioMuted) ? 1 : 0;
            if (microphoneGainNode) microphoneGainNode.gain.value = (microphoneAudioEnabled && !microphoneAudioMuted) ? 1 : 0;
            startMeetingAnalysis();
        } catch (_) {}
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
    if (animationId) { cancelAnimationFrame(animationId); animationId = null; }
    if (meetingAnimationId) { cancelAnimationFrame(meetingAnimationId); meetingAnimationId = null; }

    updateRecordingUI(false);
    resetAudioVisualizer();
    resetMeetingVisualizer();
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
        if (animationId) { cancelAnimationFrame(animationId); animationId = null; }
        if (meetingAnimationId) { cancelAnimationFrame(meetingAnimationId); meetingAnimationId = null; }
        if (!keepUI) {
            updateRecordingUI(false);
            resetAudioVisualizer();
            resetMeetingVisualizer();
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
    resetMeetingVisualizer();
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

    // Determinar contexto
    const context = lastRecordingContext || (selectedMode === 'meeting' ? 'meeting' : 'recording');
    // Ya no descargamos automáticamente las grabaciones de reunión.
    // Se seguirá el mismo flujo que audio: subir en segundo plano si está "posponer",
    // o analizar ahora.
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
                    downloadAudioAsOgg(finalBlob, name);
                });

            showSuccess('La subida continuará en segundo plano. Revisa el panel de notificaciones para el estado final.');
            handlePostActionCleanup(true);
        } else {
            downloadAudioAsOgg(finalBlob, name);
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
            downloadAudioAsOgg(finalBlob, name);
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
    const meetingActions = document.getElementById('meeting-actions');
    if (meetingActions) meetingActions.classList.add('show');
        // UI de reunión
        const meetBtn = document.getElementById('meeting-record-btn');
        const meetCounter = document.getElementById('meeting-timer-counter');
        const meetLabel = document.getElementById('meeting-timer-label');
        const mp = document.getElementById('meeting-pause');
        const mr = document.getElementById('meeting-resume');
        const md = document.getElementById('meeting-discard');
        if (meetBtn) meetBtn.classList.add('recording');
        if (meetLabel) meetLabel.textContent = 'Grabando...';
        if (meetCounter) meetCounter.classList.add('recording');
        if (mp) mp.style.display = isPaused ? 'none' : 'inline-block';
        if (mr) mr.style.display = isPaused ? 'inline-block' : 'none';
        if (md) md.style.display = 'inline-block';
        const sysViz = document.getElementById('system-audio-visualizer');
        const micViz = document.getElementById('microphone-audio-visualizer');
        if (sysViz) sysViz.classList.add('active');
        if (micViz) micViz.classList.add('active');
    } else {
        micCircle.classList.remove('recording');
        timerCounter.classList.remove('recording');
        timerLabel.textContent = 'Listo para grabar';
        timerLabel.classList.remove('recording');
        timerCounter.textContent = '00:00:00';
        visualizer.classList.remove('active');
    if (actions) actions.classList.remove('show');
    const meetingActions = document.getElementById('meeting-actions');
    if (meetingActions) meetingActions.classList.remove('show');
        // UI de reunión
        const meetBtn = document.getElementById('meeting-record-btn');
        const meetCounter = document.getElementById('meeting-timer-counter');
        const meetLabel = document.getElementById('meeting-timer-label');
        const mp = document.getElementById('meeting-pause');
        const mr = document.getElementById('meeting-resume');
        const md = document.getElementById('meeting-discard');
        if (meetBtn) meetBtn.classList.remove('recording');
        if (meetLabel) meetLabel.textContent = 'Listo para grabar';
        if (meetCounter) meetCounter.textContent = '00:00:00';
        if (mp) mp.style.display = 'none';
        if (mr) mr.style.display = 'none';
        if (md) md.style.display = 'none';
        const sysViz = document.getElementById('system-audio-visualizer');
        const micViz = document.getElementById('microphone-audio-visualizer');
        if (sysViz) sysViz.classList.remove('active');
        if (micViz) micViz.classList.remove('active');
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

// Reset de visualizadores de reunión (barras + espectrogramas)
function resetMeetingVisualizer() {
    try {
        // Reset barras
        const bars = document.querySelectorAll('#system-audio-visualizer .meeting-audio-bar, #microphone-audio-visualizer .meeting-audio-bar');
        bars.forEach(bar => {
            bar.style.height = '4px';
            bar.classList.remove('high', 'active');
        });
        // Limpiar espectrogramas
        if (systemSpectrogramCtx && systemSpectrogramCanvas) {
            systemSpectrogramCtx.clearRect(0, 0, systemSpectrogramCanvas.width, systemSpectrogramCanvas.height);
        }
        if (microphoneSpectrogramCtx && microphoneSpectrogramCanvas) {
            microphoneSpectrogramCtx.clearRect(0, 0, microphoneSpectrogramCanvas.width, microphoneSpectrogramCanvas.height);
        }
    } catch (_) {}
}

// Inicializa tamaño y fondo de espectrograma
function initSpectrogramCanvas(canvas, ctx) {
    if (!canvas || !ctx) return;
    try {
        // Ajustar tamaño al contenedor visible
        const rect = canvas.getBoundingClientRect();
        // Ajustar tamaño interno del canvas para mejor nitidez
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.max(300, Math.floor(rect.width * dpr));
        canvas.height = Math.max(60, Math.floor(rect.height * dpr));
        ctx.scale(dpr, dpr);
        // Fondo inicial
        ctx.fillStyle = '#0b1020';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    } catch(_) {}
}

// Dibuja una nueva columna en el espectrograma desplazando el contenido a la izquierda
function drawSpectrogramColumn(canvas, ctx, freqData) {
    if (!canvas || !ctx || !freqData) return;
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    // Desplazar 1px a la izquierda
    try {
        ctx.drawImage(canvas, -1, 0);
    } catch(_) {}
    // Dibujar nueva columna en el borde derecho
    const x = w - 1;
    const bins = freqData.length;
    for (let i = 0; i < h; i++) {
        // Mapear fila y -> bin de frecuencia (invertido para graves abajo)
        const bin = Math.floor((1 - i / h) * (bins - 1));
        const v = freqData[bin] / 255; // 0..1
        const hue = Math.max(0, 260 - Math.floor(v * 260)); // 260: azul -> 0: rojo
        const light = 20 + Math.floor(v * 60); // 20%..80%
        ctx.fillStyle = `hsl(${hue} 100% ${light}%)`;
        ctx.fillRect(x, i, 1, 1);
    }
}

// Bucle de análisis para reunión (sistema + mic)
function startMeetingAnalysis() {
    // Si no hay analizadores aún, salir silenciosamente
    if (!isRecording) return;
    try {
        if (systemAnalyser && systemDataArray) {
            systemAnalyser.getByteFrequencyData(systemDataArray);
            updateMeetingAudioBars('#system-audio-visualizer', systemDataArray);
            drawSpectrogramColumn(systemSpectrogramCanvas, systemSpectrogramCtx, systemDataArray);
        }
        if (microphoneAnalyser && microphoneDataArray) {
            microphoneAnalyser.getByteFrequencyData(microphoneDataArray);
            updateMeetingAudioBars('#microphone-audio-visualizer', microphoneDataArray);
            drawSpectrogramColumn(microphoneSpectrogramCanvas, microphoneSpectrogramCtx, microphoneDataArray);
        }
    } catch (_) {}
    meetingAnimationId = requestAnimationFrame(startMeetingAnalysis);
}

// Actualiza barras de un visualizador de reunión
function updateMeetingAudioBars(selector, frequencyData) {
    const container = document.querySelector(selector);
    if (!container) return;
    const bars = container.querySelectorAll('.meeting-audio-bar');
    if (!bars || bars.length === 0) return;
    const step = Math.max(1, Math.floor(frequencyData.length / bars.length));
    bars.forEach((bar, idx) => {
        const v = frequencyData[idx * step] || 0;
        const h = Math.max(4, Math.floor((v / 255) * 50)); // hasta 50px
        bar.style.height = h + 'px';
        bar.classList.toggle('high', h > 35);
        bar.classList.toggle('active', h > 12);
    });
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
    const meetCounter = document.getElementById('meeting-timer-counter');
    if (meetCounter) meetCounter.textContent = timeString;
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

// ====== SUBIR AUDIO: UI y Transcripción ======
let selectedUploadFile = null;

function setupUploadInterface() {
    try {
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('audio-file-input');
        const uploadBtn = document.querySelector('#audio-uploader .upload-btn');
        const selectedWrap = document.getElementById('selected-file');

        if (!uploadArea || !fileInput || !uploadBtn || !selectedWrap) return;

        // Evitar múltiples bindings
        if (uploadArea.__uploadBound) return;
        uploadArea.__uploadBound = true;

        // Click en botón abre selector
        uploadBtn.addEventListener('click', () => fileInput.click());

        // Cambio de input
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (file) handleFileSelected(file);
        });

        // Drag & drop
        ['dragenter','dragover'].forEach(evt => uploadArea.addEventListener(evt, (e) => {
            e.preventDefault(); e.stopPropagation();
            uploadArea.classList.add('dragover');
        }));
        ['dragleave','drop'].forEach(evt => uploadArea.addEventListener(evt, (e) => {
            e.preventDefault(); e.stopPropagation();
            uploadArea.classList.remove('dragover');
        }));
        uploadArea.addEventListener('drop', (e) => {
            const file = e.dataTransfer?.files?.[0];
            if (file) handleFileSelected(file);
        });
    } catch (err) {
        console.error('Error setting up upload interface', err);
    }
}

function handleFileSelected(file) {
    selectedUploadFile = file;
    const uploadArea = document.getElementById('upload-area');
    const selectedWrap = document.getElementById('selected-file');
    const nameEl = document.getElementById('file-name');
    const sizeEl = document.getElementById('file-size');
    const progressWrap = document.getElementById('upload-progress');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');

    if (uploadArea) uploadArea.style.display = 'none';
    if (selectedWrap) selectedWrap.style.display = 'block';
    if (nameEl) nameEl.textContent = file.name;
    if (sizeEl) sizeEl.textContent = formatBytes(file.size);
    if (progressWrap) progressWrap.style.display = 'none';
    if (progressFill) progressFill.style.width = '0%';
    if (progressText) progressText.textContent = '0%';
}

function removeSelectedFile() {
    selectedUploadFile = null;
    const fileInput = document.getElementById('audio-file-input');
    const uploadArea = document.getElementById('upload-area');
    const selectedWrap = document.getElementById('selected-file');
    const progressWrap = document.getElementById('upload-progress');
    if (fileInput) fileInput.value = '';
    if (selectedWrap) selectedWrap.style.display = 'none';
    if (uploadArea) uploadArea.style.display = 'block';
    if (progressWrap) progressWrap.style.display = 'none';
}
window.removeSelectedFile = removeSelectedFile;

async function processAudioFile() {
    // Importante: la transcripción SIEMPRE se hace en /audio-processing
    try {
        if (!selectedUploadFile) {
            showWarning('Selecciona un archivo de audio primero.');
            return;
        }

        const progressWrap = document.getElementById('upload-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const processBtn = document.querySelector('#audio-uploader .process-btn');
        if (progressWrap) progressWrap.style.display = 'flex';
        if (progressFill) progressFill.style.width = '5%';
        if (progressText) progressText.textContent = 'Guardando...';
        if (processBtn) { processBtn.disabled = true; processBtn.classList.add('opacity-50'); }

        const key = await saveAudioBlob(selectedUploadFile);
    sessionStorage.setItem('uploadedAudioKey', key);

        window.location.href = '/audio-processing';
    } catch (err) {
        console.error('Error preparando el audio para procesar', err);
        showError('No se pudo preparar el audio para procesarlo. Intenta de nuevo.');
        const processBtn = document.querySelector('#audio-uploader .process-btn');
        if (processBtn) { processBtn.disabled = false; processBtn.classList.remove('opacity-50'); }
    }
}
window.processAudioFile = processAudioFile;

// Transcripción desde "new-meeting" ya no se hace aquí; se delega a /audio-processing

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024, sizes = ['B','KB','MB','GB','TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
        downloadAudioAsOgg(pendingAudioBlob, 'grabacion_error');
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
