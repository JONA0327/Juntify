<!-- Sección: Conectar Servicios -->
<div class="content-section" id="section-connect" style="display: none;" data-tutorial="connect-section">
    <div class="content-grid">
        @if(!$driveConnected)
            <!-- No conectado -->
            <div class="info-card" data-tutorial="connect-card">
                <h3 class="card-title">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972-3.332 0-6.033-2.701-6.033-6.032s2.701-6.032 6.033-6.032c1.498 0 2.866.549 3.921 1.453l2.814-2.814C17.503 2.988 15.139 2 12.545 2 7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748L12.545 10.239z" fill="#4285F4"/>
                        </svg>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" fill="#34A853"/>
                        </svg>
                        Drive y Calendar
                    </span>
                </h3>
                <div class="info-item">
                    <span class="info-label">Estado</span>
                    <span class="status-badge google-connection-status" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                        Desconectado
                    </span>
                    <div class="google-connection-indicator" style="display: none; margin-left: 10px;">
                        <svg class="google-refresh-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="23.562"/>
                        </svg>
                    </div>
                </div>
                <div class="info-item">
                    @if($driveLocked)
                        <span class="info-value">Tu plan actual no permite conectar Google Drive. Las reuniones nuevas se guardarán temporalmente durante {{ $tempRetentionDays }} {{ $tempRetentionDays === 1 ? 'día' : 'días' }}.</span>
                    @else
                        <span class="info-value">Debes reconectar tu cuenta de Google.</span>
                    @endif
                </div>
                <div class="action-buttons" data-tutorial="connect-actions">
                    @if($driveLocked)
                        <button type="button" class="btn btn-secondary" id="connect-drive-btn" data-drive-locked="true" data-tutorial="connect-drive-button">
                            🔒 Disponible en planes Business y Enterprise
                        </button>
                    @else
                        <button type="button" class="btn btn-primary" id="connect-drive-btn" data-drive-locked="false" data-tutorial="connect-drive-button">
                            🔗 Conectar Drive y Calendar
                        </button>
                    @endif
                </div>
            </div>
        @else
            <!-- Conectado - Estado de Drive -->
            <div class="info-card" data-tutorial="connect-card">
                <h3 class="card-title">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972-3.332 0-6.033-2.701-6.033-6.032s2.701-6.032 6.033-6.032c1.498 0 2.866.549 3.921 1.453l2.814-2.814C17.503 2.988 15.139 2 12.545 2 7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748L12.545 10.239z" fill="#4285F4"/>
                        </svg>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" fill="#34A853"/>
                        </svg>
                        Drive y Calendar
                    </span>
                </h3>
                <div class="info-item">
                    <span class="info-label">Drive</span>
                    <span class="status-badge status-active google-drive-status">Conectado</span>
                    <div class="google-connection-indicator" style="display: none; margin-left: 10px;">
                        <svg class="google-refresh-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="23.562"/>
                        </svg>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-label">Calendar</span>
                    <span id="calendar-status" class="status-badge google-calendar-status {{ $calendarConnected ? 'status-active' : '' }}" @unless($calendarConnected) style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);" @endunless>
                        {{ $calendarConnected ? 'Conectado' : 'Sin acceso' }}
                    </span>
                    <div class="google-connection-indicator" style="display: none; margin-left: 10px;">
                        <svg class="google-refresh-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="23.562"/>
                        </svg>
                    </div>
                </div>
                <div class="info-item" id="calendar-advice" @if($calendarConnected) style="display:none;" @endif>
                    <span class="info-value">Vuelve a conectar a través de <a href="{{ route('google.reauth') }}" style="text-decoration: underline;">Google OAuth</a>.</span>
                </div>
                @if($lastSync)
                <div class="info-item">
                    <span class="info-label">Última sincronización</span>
                    <span class="info-value">{{ $lastSync->format('d/m/Y H:i:s') }}</span>
                </div>
                @endif
                @if($driveLocked)
                <div class="info-item">
                    <span class="info-value" style="color: #f97316;">
                        Tu plan actual ya no permite nuevas conexiones de Drive. Las reuniones recientes se guardarán temporalmente durante {{ $tempRetentionDays }} {{ $tempRetentionDays === 1 ? 'día' : 'días' }} hasta que actualices tu plan.
                    </span>
                </div>
                @endif
                <div class="action-buttons" data-tutorial="connect-actions">
                    <form method="POST" action="{{ route('drive.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">
                            🔌 Cerrar sesión de Drive y Calendar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Configuración de Carpetas -->
            <div class="info-card" data-tutorial="folder-config-card">
                <h3 class="card-title">
                    <span class="card-icon">📁</span>
                    Configuración de Carpetas
                </h3>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label">Carpeta Principal</label>
                    @if(!empty($folderMessage))
                        <div class="info-item">
                            <span class="info-value" style="color: #ef4444;">{{ $folderMessage }}</span>
                        </div>
                    @endif
                    <div class="info-item">
                        <span class="info-value" style="color:#cbd5e1;">
                            Juntify ya establece una carpeta automática en tu Google Drive. Si prefieres usar otra como principal, pega aquí su ID y haz clic en "Establecer Carpeta".
                        </span>
                    </div>
                    @if($folder)
                        <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.2rem;">📁</span>
                                <div id="main-folder-name" data-name="{{ $folder->name ?? '' }}" data-id="{{ $folder->google_id ?? '' }}" style="flex: 1; min-width: 0;">
                                    <div style="color: #ffffff; font-weight: 600; word-break: break-all;">
                                        {{ $folder->name }}
                                    </div>
                                    <div style="color: #94a3b8; font-size: 0.8rem; font-family: monospace; word-break: break-all;">
                                        ID: {{ $folder->google_id }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <input
                        type="text"
                        class="form-input"
                        id="main-folder-input"
                        placeholder="ID de la carpeta principal"
                        data-id="{{ $folder->google_id ?? '' }}"
                        style="margin-bottom: 1rem;"
                    >

                    <div class="action-buttons" data-tutorial="folder-actions">
                        <button class="btn btn-secondary" id="set-main-folder-btn">
                            ✅ Establecer Carpeta
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subcarpetas -->
            @if($folder)
            <div class="info-card" id="subfolder-card" data-tutorial="subfolder-card">
                <h3 class="card-title">
                    <span class="card-icon">📂</span>
                    Estructura de Carpetas
                </h3>
                <div style="margin-bottom: 1.5rem; line-height:1.5; color:#cbd5e1; font-size:0.9rem;">
                    Ahora la organización es automática. Al trabajar con tus reuniones se crearán (si no existen) estas carpetas dentro de tu carpeta principal:
                    <ul style="margin:0.75rem 0 0 1.2rem; list-style:disc;">
                        <li><strong>Audios</strong>: Archivos de audio finales</li>
                        <li><strong>Transcripciones</strong>: Archivos .ju encriptados con la transcripción y resumen</li>
                        <li><strong>Audios Pospuestos</strong>: Audios subidos pendientes de completar</li>
                        <li><strong>Documentos</strong>: Exportaciones y archivos adicionales relacionados a la reunión</li>
                    </ul>
                    No necesitas crear subcarpetas manualmente. Esta sección reemplaza la antigua gestión de subcarpetas.
                </div>
            </div>
            @endif
        @endif

        <!-- Sincronización de Voz -->
        <div class="info-card" data-tutorial="voice-sync-card" id="voice-sync-card">
            <h3 class="card-title">
                <span style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                    </svg>
                    Sincronización de Voz
                </span>
            </h3>
            <div class="info-item">
                <span class="info-label">Estado</span>
                <span class="status-badge" id="voice-sync-status" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                    No configurado
                </span>
            </div>
            <div class="info-item">
                <span class="info-value" id="voice-sync-description">
                    Configura tu perfil de voz para mejorar la identificación de participantes en reuniones y obtener transcripciones más precisas.
                </span>
            </div>

            <!-- Texto para leer durante la grabación -->
            <div class="info-item" id="voice-text-container" style="display: none; margin-top: 1rem;">
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 1.5rem; line-height: 1.6;">
                    <p style="font-style: italic; color: #e2e8f0; margin: 0;">
                        "La voz humana es el instrumento más bello de todos, pero es el más difícil de tocar. A través de este texto, mi voz
                        está siendo analizada para crear un mapa digital único. Al hablar con claridad, entonación y pausa, permito que el sistema
                        registre los matices graves y agudos, el timbre y la cadencia que me identifican. Esto servirá para reconocerme automáticamente
                        en futuras reuniones sin importar el entorno."
                    </p>
                </div>
                <p style="margin-top: 0.75rem; color: #94a3b8; font-size: 0.9rem;">
                    Lee el texto anterior en voz alta durante la grabación. Procura grabar al menos 10 segundos con claridad.
                </p>
            </div>

            <!-- Controles de grabación -->
            <div class="info-item" id="voice-recording-controls" style="display: none; margin-top: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div id="voice-recording-indicator" style="display: none; align-items: center; gap: 0.5rem;">
                        <span style="width: 12px; height: 12px; background: #ef4444; border-radius: 50%; animation: pulse 1.5s ease-in-out infinite;"></span>
                        <span style="color: #ef4444; font-weight: 600;">Grabando</span>
                    </div>
                    <span id="voice-recording-timer" style="font-family: monospace; font-size: 1.1rem; color: #3b82f6; font-weight: 600;">00:00</span>
                </div>
            </div>

            <div class="action-buttons" data-tutorial="voice-sync-actions">
                <button type="button" class="btn btn-primary" id="configure-voice-btn" onclick="startVoiceConfiguration()">
                    🎙️ Configurar Perfil de Voz
                </button>
                <button type="button" class="btn btn-primary" id="start-recording-btn" style="display: none;" onclick="startVoiceRecording()">
                    <svg style="width: 20px; height: 20px; margin-right: 0.5rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                    </svg>
                    Iniciar Grabación
                </button>
                <button type="button" class="btn btn-danger" id="stop-recording-btn" style="display: none;" onclick="stopVoiceRecording()">
                    <svg style="width: 20px; height: 20px; margin-right: 0.5rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z" />
                    </svg>
                    Detener
                </button>
                <button type="button" class="btn btn-secondary" id="cancel-voice-btn" style="display: none;" onclick="cancelVoiceConfiguration()">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" id="remove-voice-btn" style="display: none;" onclick="removeVoiceProfile()">
                    🗑️ Eliminar Perfil
                </button>
            </div>

            <p id="voice-status-message" style="margin-top: 1rem; color: #94a3b8; font-size: 0.9rem;"></p>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}
</style>

<script>
// Variables globales para la grabación de voz
let voiceMediaRecorder = null;
let voiceAudioChunks = [];
let voiceStream = null;
let voiceStartTime = null;
let voiceTimerInterval = null;

// Verificar estado al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    checkVoiceProfileStatus();
});

function checkVoiceProfileStatus() {
    fetch('/api/voice-profile/status')
        .then(response => response.json())
        .then(data => {
            updateVoiceUIStatus(data.configured, data.embedding_size);
        })
        .catch(error => {
            console.error('Error checking voice profile:', error);
            setVoiceStatusMessage('No se pudo verificar el estado del perfil de voz', 'error');
        });
}

function updateVoiceUIStatus(configured, embeddingSize) {
    const statusBadge = document.getElementById('voice-sync-status');
    const configureBtn = document.getElementById('configure-voice-btn');
    const removeBtn = document.getElementById('remove-voice-btn');
    const description = document.getElementById('voice-sync-description');
    
    if (configured) {
        statusBadge.style.background = 'rgba(34, 197, 94, 0.2)';
        statusBadge.style.color = '#22c55e';
        statusBadge.style.border = '1px solid rgba(34, 197, 94, 0.3)';
        statusBadge.textContent = 'Configurado';
        
        configureBtn.innerHTML = '🔄 Reconfigurar Perfil de Voz';
        removeBtn.style.display = 'inline-block';
        
        description.textContent = `Tu perfil de voz está configurado con ${embeddingSize} características biométricas. El sistema podrá identificarte en futuras reuniones.`;
    } else {
        statusBadge.style.background = 'rgba(239, 68, 68, 0.2)';
        statusBadge.style.color = '#ef4444';
        statusBadge.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        statusBadge.textContent = 'No configurado';
        
        configureBtn.innerHTML = '🎙️ Configurar Perfil de Voz';
        removeBtn.style.display = 'none';
        
        description.textContent = 'Configura tu perfil de voz para mejorar la identificación de participantes en reuniones y obtener transcripciones más precisas.';
    }
}

function startVoiceConfiguration() {
    // Mostrar el texto a leer y los controles
    document.getElementById('voice-text-container').style.display = 'block';
    document.getElementById('voice-recording-controls').style.display = 'block';
    document.getElementById('configure-voice-btn').style.display = 'none';
    document.getElementById('start-recording-btn').style.display = 'inline-block';
    document.getElementById('cancel-voice-btn').style.display = 'inline-block';
    document.getElementById('remove-voice-btn').style.display = 'none';
    
    setVoiceStatusMessage('Lee el texto en voz alta cuando inicies la grabación. Mínimo 10 segundos.', 'info');
}

async function startVoiceRecording() {
    if (!navigator.mediaDevices?.getUserMedia) {
        setVoiceStatusMessage('Tu navegador no soporta la grabación de audio.', 'error');
        return;
    }

    try {
        voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (error) {
        setVoiceStatusMessage('No se pudo acceder al micrófono. Verifica los permisos.', 'error');
        return;
    }

    voiceMediaRecorder = new MediaRecorder(voiceStream);
    voiceAudioChunks = [];
    voiceStartTime = Date.now();
    
    // Actualizar timer
    updateVoiceTimer();
    voiceTimerInterval = setInterval(updateVoiceTimer, 1000);
    
    // Mostrar indicador de grabación
    document.getElementById('voice-recording-indicator').style.display = 'flex';
    document.getElementById('start-recording-btn').style.display = 'none';
    document.getElementById('stop-recording-btn').style.display = 'inline-block';
    
    voiceMediaRecorder.addEventListener('dataavailable', event => {
        if (event.data && event.data.size > 0) {
            voiceAudioChunks.push(event.data);
        }
    });

    voiceMediaRecorder.addEventListener('stop', () => {
        clearInterval(voiceTimerInterval);
        const durationSeconds = Math.floor((Date.now() - voiceStartTime) / 1000);
        const blob = new Blob(voiceAudioChunks, { type: voiceMediaRecorder.mimeType || 'audio/webm' });
        stopVoiceStream();
        uploadVoiceRecording(blob, durationSeconds);
    });

    voiceMediaRecorder.start();
    setVoiceStatusMessage('Grabando... Lee el texto en voz alta con claridad.', 'recording');
}

function stopVoiceRecording() {
    if (voiceMediaRecorder && voiceMediaRecorder.state !== 'inactive') {
        setVoiceStatusMessage('Procesando tu grabación...', 'info');
        voiceMediaRecorder.stop();
        document.getElementById('voice-recording-indicator').style.display = 'none';
        document.getElementById('stop-recording-btn').style.display = 'none';
    }
}

function updateVoiceTimer() {
    const timer = document.getElementById('voice-recording-timer');
    if (!timer || !voiceStartTime) return;
    
    const elapsed = Math.floor((Date.now() - voiceStartTime) / 1000);
    const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const seconds = String(elapsed % 60).padStart(2, '0');
    timer.textContent = `${minutes}:${seconds}`;
}

function stopVoiceStream() {
    if (voiceStream) {
        voiceStream.getTracks().forEach(track => track.stop());
        voiceStream = null;
    }
}

async function uploadVoiceRecording(blob, durationSeconds) {
    if (durationSeconds < 10) {
        setVoiceStatusMessage('La grabación es demasiado corta. Graba al menos 10 segundos.', 'error');
        resetVoiceUI();
        return;
    }

    const formData = new FormData();
    formData.append('audio', blob, 'voice-enrollment.webm');

    setVoiceStatusMessage('Procesando tu huella de voz... Esto puede tardar unos segundos.', 'processing');
    
    // Deshabilitar botones durante el procesamiento
    document.getElementById('cancel-voice-btn').disabled = true;

    try {
        const response = await fetch('{{ route("profile.voice.enroll") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });

        const data = await response.json();

        if (response.ok) {
            setVoiceStatusMessage('✅ ' + (data.message || 'Huella de voz registrada correctamente.'), 'success');
            setTimeout(() => {
                checkVoiceProfileStatus();
                resetVoiceUI();
            }, 2000);
        } else {
            setVoiceStatusMessage('❌ ' + (data.message || 'No se pudo procesar el audio.'), 'error');
            resetVoiceUI();
        }
    } catch (error) {
        console.error('Error uploading voice:', error);
        setVoiceStatusMessage('❌ Error al enviar el audio. Intenta nuevamente.', 'error');
        resetVoiceUI();
    } finally {
        document.getElementById('cancel-voice-btn').disabled = false;
    }
}

function cancelVoiceConfiguration() {
    stopVoiceStream();
    if (voiceMediaRecorder && voiceMediaRecorder.state !== 'inactive') {
        voiceMediaRecorder.stop();
    }
    clearInterval(voiceTimerInterval);
    resetVoiceUI();
    setVoiceStatusMessage('', '');
}

function resetVoiceUI() {
    document.getElementById('voice-text-container').style.display = 'none';
    document.getElementById('voice-recording-controls').style.display = 'none';
    document.getElementById('voice-recording-indicator').style.display = 'none';
    document.getElementById('voice-recording-timer').textContent = '00:00';
    document.getElementById('configure-voice-btn').style.display = 'inline-block';
    document.getElementById('start-recording-btn').style.display = 'none';
    document.getElementById('stop-recording-btn').style.display = 'none';
    document.getElementById('cancel-voice-btn').style.display = 'none';
    
    // Restaurar el botón de eliminar si existe perfil
    checkVoiceProfileStatus();
}

function removeVoiceProfile() {
    if (!confirm('¿Estás seguro de que deseas eliminar tu perfil de voz? Tendrás que volver a configurarlo para que el sistema te reconozca en futuras reuniones.')) {
        return;
    }

    setVoiceStatusMessage('Eliminando perfil de voz...', 'processing');

    fetch('/api/voice-profile/remove', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setVoiceStatusMessage('✅ Perfil de voz eliminado correctamente.', 'success');
            setTimeout(() => {
                checkVoiceProfileStatus();
                setVoiceStatusMessage('', '');
            }, 2000);
        } else {
            setVoiceStatusMessage('❌ No se pudo eliminar el perfil.', 'error');
        }
    })
    .catch(error => {
        console.error('Error removing voice profile:', error);
        setVoiceStatusMessage('❌ Error al eliminar el perfil.', 'error');
    });
}

function setVoiceStatusMessage(message, type) {
    const messageEl = document.getElementById('voice-status-message');
    if (!messageEl) return;
    
    messageEl.textContent = message;
    
    // Colores según el tipo
    const colors = {
        'info': '#3b82f6',
        'success': '#22c55e',
        'error': '#ef4444',
        'recording': '#f97316',
        'processing': '#eab308'
    };
    
    messageEl.style.color = colors[type] || '#94a3b8';
}
</script>
