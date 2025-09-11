<!-- Interfaz de Grabación de Audio -->
<div class="recorder-interface" id="audio-recorder">
    <div class="recorder-visual">
        <!-- Visualizador de audio -->
        <div class="audio-visualizer" id="audio-visualizer">
            
            <?php for($i = 0; $i < 15; $i++): ?>
                <div class="audio-bar"></div>
            <?php endfor; ?>
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
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'microphone','class' => 'mic-symbol']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'microphone','class' => 'mic-symbol']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
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
        <p class="text-xs text-gray-500 mt-4 text-center">Puedes grabar hasta 2 horas continuas. Se notificará cuando queden 5 min para el límite.</p>
    </div>
</div>

<!-- Modal Guardar Grabación -->
<div class="modal" id="save-recording-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'note','class' => 'modal-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'note','class' => 'modal-icon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
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
<?php /**PATH C:\laragon\www\Juntify\resources\views/partials/new-meeting/_audio-recorder.blade.php ENDPATH**/ ?>