<!-- Interfaz de Grabación de Audio -->
<div class="recorder-interface" id="audio-recorder">
    <div class="recorder-visual">
        <!-- Micrófono central -->
        <div class="microphone-container">
            <div class="volume-rings" id="volume-rings">
                <div class="volume-ring ring-1"></div>
                <div class="volume-ring ring-2"></div>
                <div class="volume-ring ring-3"></div>
            </div>
            <div class="mic-circle" id="mic-circle">
                <x-icon name="microphone" class="mic-symbol" />
            </div>
        </div>

        <!-- Visualizador de audio -->
        <div class="audio-visualizer" id="audio-visualizer">
            {{-- Barras de audio generadas dinámicamente o estáticas --}}
            @for ($i = 0; $i < 15; $i++)
                <div class="audio-bar"></div>
            @endfor
        </div>

        <!-- Timer -->
        <div class="timer-display">
            <span class="time-counter" id="timer-counter">00:00</span>
            <span class="timer-label" id="timer-label">Listo para grabar</span>
        </div>
    </div>

    <div class="recorder-controls">
        <button class="btn btn-primary recorder-btn" id="start-recording" onclick="toggleRecording()">
            <x-icon name="play" class="btn-icon" />
            <span class="btn-text">Iniciar grabación</span>
        </button>
        <div class="postpone-switch flex flex-col items-center mt-3">
            <span id="postpone-mode-label" class="text-sm mb-1">Activar modo posponer</span>
            <label for="postpone-toggle" class="cursor-pointer select-none">
                <input id="postpone-toggle" type="checkbox" class="sr-only peer" onchange="togglePostponeMode()">
                <span id="postpone-track" class="w-12 h-6 rounded-full bg-gray-400 peer-checked:bg-blue-500 transition-colors relative overflow-hidden">
                    <span class="absolute top-1 left-1 h-4 w-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-6"></span>
                </span>
            </label>
        </div>
        <button class="btn pause-btn recorder-btn" id="pause-recording" onclick="pauseRecording()" style="display: none;">
            <x-icon name="pause" class="btn-icon" />
            <span class="btn-text">Pausar</span>
        </button>
        <button class="btn resume-btn" id="resume-recording" onclick="resumeRecording()" style="display: none;">
            <x-icon name="play" class="btn-icon" />
            <span class="btn-text">Reanudar</span>
        </button>
        <button class="btn discard-btn recorder-btn" id="discard-recording" onclick="discardRecording()" style="display: none;">
            <x-icon name="x" class="btn-icon" />
            <span class="btn-text">Descartar</span>
        </button>
    </div>
</div>

<!-- Modal Guardar Grabación -->
<div class="modal" id="save-recording-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">
                <x-icon name="note" class="modal-icon" />
                Guardar grabación
            </h2>
        </div>
        <div class="modal-body" id="save-modal-body">
            <p class="modal-description">¿Qué deseas hacer con la grabación?</p>
        </div>
        <div class="modal-footer" id="save-modal-footer">
            <button class="btn btn-primary" id="analyze-now-btn">Analizar ahora</button>
            <button class="btn" id="postpone-btn">Posponer</button>
        </div>
    </div>
</div>
