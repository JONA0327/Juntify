<!-- Sección: Información del Usuario -->
<div class="content-section" id="section-info">
    <div class="content-grid">
        <div class="info-card">
            <h2 class="card-title">
                <span class="card-icon">👤</span>
                Información Personal
            </h2>
            <div class="info-item">
                <span class="info-label">Nombre de usuario</span>
                <span class="info-value"><?php echo e($user->username); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nombre completo</span>
                <span class="info-value"><?php echo e($user->full_name); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Correo electrónico</span>
                <span class="info-value"><?php echo e($user->email); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Organización</span>
                <span class="info-value"><?php echo e($user->organization?->nombre_organizacion ?? 'No especificada'); ?></span>
            </div>
        </div>

        <div class="info-card">
            <h2 class="card-title">
                <span class="card-icon">💎</span>
                Plan Actual
            </h2>
            <div class="info-item">
                <span class="info-label">Tipo de plan</span>
                <span class="status-badge status-<?php echo e(strtolower($user->roles ?? 'free')); ?>">
                    <?php echo e(ucfirst($user->roles ?? 'free')); ?>

                </span>
            </div>
            <?php if($user->plan_expires_at): ?>
            <div class="info-item">
                <span class="info-label">Expira el</span>
                <span class="info-value"><?php echo e($user->plan_expires_at->format('d/m/Y')); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Miembro desde</span>
                <span class="info-value"><?php echo e($user->created_at->format('d/m/Y')); ?></span>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\Juntify\resources\views/partials/profile/_section-info.blade.php ENDPATH**/ ?>