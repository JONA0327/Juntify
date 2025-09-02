<?php
  // URL base de tu home
  $homeUrl = url('/');
  // ¿estamos en la home sin ancla?
  $isHome = request()->is('/');
  // Mostrar botón central de "Nueva reunión" según rol en grupos
  $user = auth()->user();
  $belongsToAnyGroup = $user && $user->groups()->exists();
  $hasNonGuestRole   = $user && $user->groups()->wherePivot('rol','!=','invitado')->exists();
  $canCreateMeeting  = (!$belongsToAnyGroup) || $hasNonGuestRole;
?>

<!-- Barra de navegación móvil exclusiva -->
<div class="mobile-bottom-nav">
    
    <a href="<?php echo e(route('reuniones.index')); ?>" class="nav-item <?php echo e(request()->routeIs('reuniones.index') ? 'active' : ''); ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
        </svg>
        <span class="nav-label">Reuniones</span>
    </a>

    
    <a href="<?php echo e($isHome ? '#asistente' : $homeUrl . '#asistente'); ?>" class="nav-item">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9" cy="12" r="1" fill="currentColor"/>
            <circle cx="15" cy="12" r="1" fill="currentColor"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
        </svg>
        <span class="nav-label">Asistente IA</span>
    </a>

    
    <?php if(isset($canCreateMeeting) && $canCreateMeeting): ?>
    <a href="<?php echo e(route('new-meeting')); ?>" class="nav-item nav-center <?php echo e(request()->routeIs('new-meeting') ? 'active' : ''); ?>">
        <svg class="nav-icon-center" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    </a>
    <?php endif; ?>

    
    <a href="<?php echo e($isHome ? '#tareas' : $homeUrl . '#tareas'); ?>" class="nav-item">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="nav-label">Tareas</span>
    </a>

    
    <div class="nav-item dropdown-trigger" onclick="toggleMobileDropdown()">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
        </svg>
        <span class="nav-label">Más</span>
        <div class="mobile-dropdown" id="mobile-dropdown">
            <a href="<?php echo e(route('profile.show')); ?>" class="dropdown-item">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'user','class' => 'dropdown-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'user','class' => 'dropdown-icon']); ?>
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
                <span class="dropdown-text">Perfil</span>
            </a>
            
            <a href="<?php echo e($isHome ? '#exportar' : $homeUrl . '#exportar'); ?>" class="dropdown-item">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'share','class' => 'dropdown-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'share','class' => 'dropdown-icon']); ?>
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
                <span class="dropdown-text">Exportar</span>
            </a>
            <div class="dropdown-item">
                <?php if (isset($component)) { $__componentOriginale5bc9b34dd139a393f71cdc403b71855 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale5bc9b34dd139a393f71cdc403b71855 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.notifications','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('notifications'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale5bc9b34dd139a393f71cdc403b71855)): ?>
<?php $attributes = $__attributesOriginale5bc9b34dd139a393f71cdc403b71855; ?>
<?php unset($__attributesOriginale5bc9b34dd139a393f71cdc403b71855); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale5bc9b34dd139a393f71cdc403b71855)): ?>
<?php $component = $__componentOriginale5bc9b34dd139a393f71cdc403b71855; ?>
<?php unset($__componentOriginale5bc9b34dd139a393f71cdc403b71855); ?>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Overlay para cerrar dropdown -->
<div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>
<?php /**PATH C:\Users\goku0\Downloads\Proyectos\Juntify Laravel\Juntify\resources\views/partials/mobile-nav.blade.php ENDPATH**/ ?>