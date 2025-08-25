<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'id',
    'meetingName',
    'createdAt',
    'audioFolder' => null,
    'transcriptFolder' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'id',
    'meetingName',
    'createdAt',
    'audioFolder' => null,
    'transcriptFolder' => null,
]); ?>
<?php foreach (array_filter(([
    'id',
    'meetingName',
    'createdAt',
    'audioFolder' => null,
    'transcriptFolder' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<div class="meeting-card" data-meeting-id="<?php echo e($id); ?>" draggable="true">
    <div class="meeting-card-header">
        <div class="meeting-content">
            <div class="meeting-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="meeting-title"><?php echo e($meetingName); ?></h3>
            <p class="meeting-date">
                <svg xmlns="http://www.w3.org/2000/svg" class="inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <?php echo e($createdAt); ?>

            </p>

            <div class="meeting-folders">
                <div class="folder-info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Transcripción:</span>
                    <span class="folder-name"><?php echo e($transcriptFolder); ?></span>
                </div>
                <div class="folder-info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728" />
                    </svg>
                    <span>Audio:</span>
                    <span class="folder-name"><?php echo e($audioFolder); ?></span>
                </div>
            </div>
        </div>

        <div class="meeting-actions">
            <button class="icon-btn container-btn" onclick="openContainerSelectModal(<?php echo e($id); ?>)" aria-label="Añadir a contenedor" title="Añadir a contenedor">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 0a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2H9l-2-2H4a2 2 0 00-2 2v12z" />
                </svg>
            </button>
            <button class="icon-btn edit-btn" onclick="editMeetingName(<?php echo e($id); ?>)" aria-label="Editar reunión" title="Editar nombre de reunión">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
            </button>
            <button class="icon-btn delete-btn" onclick="deleteMeeting(<?php echo e($id); ?>)" aria-label="Eliminar reunión" title="Eliminar reunión">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
    <button class="download-btn icon-btn absolute bottom-4 right-4" aria-label="Descargar reunión" title="Descargar reunión">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
        </svg>
    </button>
</div>
<?php /**PATH C:\laragon\www\Juntify\resources\views/components/meeting-card.blade.php ENDPATH**/ ?>