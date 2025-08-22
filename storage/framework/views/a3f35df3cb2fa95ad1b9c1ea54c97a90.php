<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['name', 'class' => '']) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['name', 'class' => '']); ?>
<?php foreach (array_filter((['name', 'class' => '']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $paths = [
        'check' => 'M4.5 12.75l6 6 9-13.5',
        'x' => 'M6 18L18 6M6 6l12 12',
        'play' => 'M5.25 5.25l13.5 6.75-13.5 6.75V5.25z',
        'pause' => 'M6.75 5.25h3v13.5h-3zM14.25 5.25h3v13.5h-3z',
        'microphone' => 'M12 3a3 3 0 013 3v6a3 3 0 11-6 0V6a3 3 0 013-3zM5 10v1a7 7 0 0014 0v-1M12 17v4',
        'folder' => 'M2.25 6.75A2.25 2.25 0 014.5 4.5h5.25l1.5 1.5h8.25A2.25 2.25 0 0121.75 8.25v9A2.25 2.25 0 0119.5 19.5H4.5A2.25 2.25 0 012.25 17.25z',
        'computer' => 'M3.75 6A2.25 2.25 0 016 3.75h12A2.25 2.25 0 0120.25 6v7.5A2.25 2.25 0 0118 15.75H6A2.25 2.25 0 013.75 13.5V6zM6 18h12',
        'chart' => 'M3 3v18h18M7.5 15V9m4.5 6V5.25m4.5 9V12',
        'speaker' => 'M5.75 8.75v6.5H9l5 5V3.75l-5 5H5.75z',
        'speaker-x' => 'M5.75 8.75v6.5H9l5 5V3.75l-5 5H5.75zM15 9l4.5 4.5m0-4.5l-4.5 4.5',
        'rocket' => 'M12 2l5 5-5 15-5-15 5-5z',
        'search' => 'M21 21l-4.35-4.35m0 0A7.5 7.5 0 1010.5 3a7.5 7.5 0 006.15 13.65z',
        'shield' => 'M12 2.25l7.5 3v5.25c0 4.5-3 8.4-7.5 10.5-4.5-2.1-7.5-6-7.5-10.5V5.25l7.5-3z',
        'note' => 'M9 19V5l12-2v13',
        'video' => 'M15 10.5l6-4.5v11l-6-4.5M3 6.75A2.25 2.25 0 015.25 4.5h6A2.25 2.25 0 0113.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-6A2.25 2.25 0 013 17.25V6.75z',
        'pencil' => 'M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5',
        'clipboard' => 'M9 2.25h6v2.25H9V2.25zM5.25 6h13.5v13.5a2.25 2.25 0 01-2.25 2.25H7.5a2.25 2.25 0 01-2.25-2.25V6z',
        'calendar' => 'M6 2.25v2.25M18 2.25v2.25M3.75 6h16.5v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6z',
        'eye' => 'M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
        'user' => 'M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z',
        'users' => 'M7.5 15a3 3 0 100-6 3 3 0 000 6zm9 0a3 3 0 100-6 3 3 0 000 6zm-9 1.5a4.5 4.5 0 00-4.5 4.5v1.5h9v-1.5a4.5 4.5 0 00-4.5-4.5zm9 0a4.5 4.5 0 014.5 4.5v1.5h-9v-1.5a4.5 4.5 0 014.5-4.5z',
        'briefcase' => 'M4.5 7.5h15v10.5H4.5V7.5zM9 7.5V6a3 3 0 013-3h0a3 3 0 013 3v1.5',
    ];
    $path = $paths[$name] ?? '';
?>
<svg <?php echo e($attributes->merge(['class' => $class])); ?> xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
  <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($path); ?>" />
</svg>

<?php /**PATH C:\laragon\www\Juntify\resources\views/components/icon.blade.php ENDPATH**/ ?>