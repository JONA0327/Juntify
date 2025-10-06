<!-- Interfaz de Grabación de Audio -->
<div class="recorder-interface" id="audio-recorder">
    <div class="recorder-visual">
        <!-- Visualizador de audio -->
        <div class="audio-visualizer" id="audio-visualizer">
            {{-- Barras de audio generadas dinámicamente o estáticas --}}
            @for ($i = 0; $i < 15; $i++)
                <div class="audio-bar"></div>
            @endfor
        </div>

        <!-- Timer -->
        <div class="timer-display">
            <span class="time-counter" id="timer-counter">00:00:00</span>
            <span class="timer-label" id="timer-label">Listo para grabar</span>
        </div>
    </div>

    <div class="recorder-controls" style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <div class="microphone-container">
            <div class="volume-rings" id="volume-rings">
                <div class="volume-ring ring-1"></div>
                <div class="volume-ring ring-2"></div>
                <div class="volume-ring ring-3"></div>
            </div>
            <button type="button" class="mic-circle" id="mic-circle" onclick="toggleRecording()" aria-label="Iniciar o detener grabación">
                <x-icon name="microphone" class="mic-symbol" />
            </button>
        </div>
        <div class="recorder-actions" id="recorder-actions">
            <button class="icon-btn" id="pause-recording" onclick="pauseRecording()" style="display: none;">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
            </svg>
            </button>
            <button class="icon-btn" id="resume-recording" onclick="resumeRecording()" style="display: none;">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" />
            </svg>
            </button>
            <button class="icon-btn" id="discard-recording" onclick="discardRecording()" style="display: none;">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            </button>
        </div>
        <div class="recorder-action-hints text-xs text-gray-500 mt-3 text-center">
            <div class="flex justify-center gap-6">
                <span>Pausa</span>
                <span>Reanudar</span>
                <span>Descartar</span>
            </div>
            <p class="mt-2">Cuando se detenga la reunión, selecciona nuevamente el ícono de micrófono para detener y procesar el audio.</p>
        </div>
        <div class="postpone-switch flex flex-col items-center mt-6" id="postpone-switch">
            <span id="postpone-mode-label" class="text-sm mb-1">Modo posponer: Apagado</span>
            <label for="postpone-toggle" class="cursor-pointer select-none">
                <input id="postpone-toggle" type="checkbox" class="sr-only" onchange="togglePostponeMode()">
                <span id="postpone-track" class="switch-track">
                    <span class="switch-label off">OFF</span>
                    <span class="switch-label on">ON</span>
                    <span class="switch-thumb"></span>
                </span>
            </label>
        </div>
    <p id="max-duration-hint-audio" class="text-xs text-gray-500 mt-4 text-center">Puedes grabar hasta 2 horas continuas. Se notificará cuando queden 5 min para el límite.</p>
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

<!-- Modal Confirmar Descarte -->
<div class="modal" id="discard-recording-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">
                <x-icon name="x" class="modal-icon" />
                Descartar grabación
            </h2>
        </div>
        <div class="modal-body">
            <p>¿Deseas descartar la reunión actual? Si confirmas, la grabación se eliminará y no podrás recuperarla.</p>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="cancelDiscardRecording()">Continuar grabando</button>
            <button class="btn btn-danger" onclick="confirmDiscardRecording()">Descartar reunión</button>
        </div>
    </div>
</div>
