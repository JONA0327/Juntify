<!-- Interfaz de Grabación de Reunión -->
<div class="meeting-interface" id="meeting-recorder" style="display: none;">
    <div class="meeting-recorder-container">
        <div class="meeting-header">
            <h3 class="meeting-title">Grabador de audio de reuniones</h3>
            <p class="meeting-subtitle">Captura el audio de una reunión o llamada, combinando el audio del sistema y tu micrófono</p>
        </div>

        <!-- Controles de fuentes de audio -->
        <div class="audio-sources-controls">
            <button class="audio-source-btn system-audio" id="system-audio-btn" onclick="toggleSystemAudio()">
                <x-icon name="computer" class="source-icon" />
                <span class="source-text">Sistema activado</span>
            </button>
            <button class="audio-source-btn microphone-audio active" id="microphone-audio-btn" onclick="toggleMicrophoneAudio()">
                <x-icon name="microphone" class="source-icon" />
                <span class="source-text">Micrófono activado</span>
            </button>
        </div>

        <!-- Visualizadores de audio -->
        <div class="meeting-audio-visualizers">
            <!-- Audio del sistema -->
            <div class="audio-source-container">
                <div class="source-header">
                    <x-icon name="computer" class="source-icon" />
                    <span class="source-label">Audio del sistema</span>
                    <button class="mute-btn" id="system-mute-btn" onclick="muteSystemAudio()">
                        <x-icon name="speaker" class="mute-icon" />
                    </button>
                </div>
                <div class="audio-visualizer-container">
                    <div class="meeting-audio-visualizer" id="system-audio-visualizer">
                        @for ($i = 0; $i < 15; $i++)
                            <div class="meeting-audio-bar"></div>
                        @endfor
                    </div>
                </div>
            </div>

            <!-- Audio del micrófono -->
            <div class="audio-source-container">
                <div class="source-header">
                    <x-icon name="microphone" class="source-icon" />
                    <span class="source-label">Audio del micrófono</span>
                    <button class="mute-btn" id="microphone-mute-btn" onclick="muteMicrophoneAudio()">
                        <x-icon name="speaker" class="mute-icon" />
                    </button>
                </div>
                <div class="audio-visualizer-container">
                    <div class="meeting-audio-visualizer" id="microphone-audio-visualizer">
                        @for ($i = 0; $i < 15; $i++)
                            <div class="meeting-audio-bar"></div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <!-- Timer y controles -->
        <div class="meeting-timer-section">
            <div class="meeting-timer-display">
                <span class="meeting-time-counter" id="meeting-timer-counter">00:00:00</span>
                <span class="meeting-timer-label" id="meeting-timer-label">Listo para grabar</span>
            </div>

        <div class="meeting-controls">
            <button class="icon-btn" id="meeting-record-btn" onclick="toggleMeetingRecording()">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5l6-4.5v11l-6-4.5M3 6.75A2.25 2.25 0 015.25 4.5h6A2.25 2.25 0 0113.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-6A2.25 2.25 0 013 17.25V6.75z" />
                </svg>
            </button>
            <button class="icon-btn" id="meeting-pause" onclick="pauseRecording()" style="display: none;">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                </svg>
            </button>
            <button class="icon-btn" id="meeting-resume" onclick="resumeRecording()" style="display: none;">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" />
                </svg>
            </button>
            <button class="icon-btn" id="meeting-discard" onclick="discardRecording()" style="display: none;">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
    <p id="max-duration-hint-meeting" class="text-xs text-gray-500 mt-4 text-center">Puedes grabar hasta 2 horas continuas. Se notificará cuando queden 5 min para el límite.</p>
</div>
</div>
