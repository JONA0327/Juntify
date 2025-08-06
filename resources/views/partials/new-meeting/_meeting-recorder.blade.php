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
                <span class="meeting-time-counter" id="meeting-timer-counter">00:00</span>
                <span class="meeting-timer-label" id="meeting-timer-label">Listo para grabar</span>
            </div>

            <div class="meeting-controls">
                <button class="btn btn-primary meeting-record-btn" id="meeting-record-btn" onclick="toggleMeetingRecording()">
                    <x-icon name="video" class="btn-icon" />
                    <span class="btn-text">Seleccionar fuente de audio</span>
                </button>
                <button class="btn pause-btn recorder-btn" id="meeting-pause" onclick="pauseRecording()" style="display: none;">
                    <x-icon name="pause" class="btn-icon" />
                    <span class="btn-text">Pausar</span>
                </button>
                <button class="btn resume-btn recorder-btn" id="meeting-resume" onclick="resumeRecording()" style="display: none;">
                    <x-icon name="play" class="btn-icon" />
                    <span class="btn-text">Reanudar</span>
                </button>
                <button class="btn discard-btn recorder-btn" id="meeting-discard" onclick="discardRecording()" style="display: none;">
                    <x-icon name="x" class="btn-icon" />
                    <span class="btn-text">Descartar</span>
                </button>
            </div>
        </div>
    </div>
</div>
