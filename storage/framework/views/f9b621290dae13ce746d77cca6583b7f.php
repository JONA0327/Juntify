<!-- Sección: Conectar Servicios -->
<div class="content-section" id="section-connect" style="display: none;">
    <div class="content-grid">
        <?php if(!$driveConnected): ?>
            <!-- No conectado -->
            <div class="info-card">
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
                    <span class="status-badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                        Desconectado
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-value">Debes reconectar tu cuenta de Google.</span>
                </div>
                <div class="action-buttons">
                    <a href="<?php echo e(route('google.reauth')); ?>" class="btn btn-primary">
                        🔗 Conectar Drive y Calendar
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Conectado - Estado de Drive -->
            <div class="info-card">
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
                    <span class="status-badge status-active">Conectado</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Calendar</span>
                    <span id="calendar-status" class="status-badge <?php echo e($calendarConnected ? 'status-active' : ''); ?>" <?php if (! ($calendarConnected)): ?> style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);" <?php endif; ?>>
                        <?php echo e($calendarConnected ? 'Conectado' : 'Sin acceso'); ?>

                    </span>
                </div>
                <div class="info-item" id="calendar-advice" <?php if($calendarConnected): ?> style="display:none;" <?php endif; ?>>
                    <span class="info-value">Vuelve a conectar a través de <a href="<?php echo e(route('google.reauth')); ?>" style="text-decoration: underline;">Google OAuth</a>.</span>
                </div>
                <?php if($lastSync): ?>
                <div class="info-item">
                    <span class="info-label">Última sincronización</span>
                    <span class="info-value"><?php echo e($lastSync->format('d/m/Y H:i:s')); ?></span>
                </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <form method="POST" action="<?php echo e(route('drive.disconnect')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-secondary">
                            🔌 Cerrar sesión de Drive y Calendar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Configuración de Carpetas -->
            <div class="info-card">
                <h3 class="card-title">
                    <span class="card-icon">📁</span>
                    Configuración de Carpetas
                </h3>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label">Carpeta Principal</label>
                    <?php if($folder): ?>
                        <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.2rem;">📁</span>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="color: #ffffff; font-weight: 600; word-break: break-all;">
                                        <?php echo e($folder->name); ?>

                                    </div>
                                    <div style="color: #94a3b8; font-size: 0.8rem; font-family: monospace; word-break: break-all;">
                                        ID: <?php echo e($folder->google_id); ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input
                        type="text"
                        class="form-input"
                        id="main-folder-input"
                        placeholder="ID de la carpeta principal"
                        data-id="<?php echo e($folder->google_id ?? ''); ?>"
                        style="margin-bottom: 1rem;"
                    >

                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="showCreateFolderModal()">
                            ➕ Crear Carpeta Principal
                        </button>
                        <button class="btn btn-secondary" id="set-main-folder-btn">
                            ✅ Establecer Carpeta
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subcarpetas -->
            <?php if($folder): ?>
            <div class="info-card" id="subfolder-card">
                <h3 class="card-title">
                    <span class="card-icon">📂</span>
                    Subcarpetas
                </h3>

                <div style="margin-bottom: 1.5rem;">
                    <div class="action-buttons" style="margin-bottom: 1rem;">
                        <button class="btn btn-primary" onclick="showCreateSubfolderModal()">
                            ➕ Crear Subcarpeta
                        </button>
                    </div>

                    <div id="subfolders-list">
                        <?php $__currentLoopData = $subfolders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subfolder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div data-id="<?php echo e($subfolder->google_id); ?>" style="margin: 0.5rem 0; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(59, 130, 246, 0.2);">
                            <div style="flex: 1; min-width: 0;">
                                <div style="color: #ffffff; font-weight: 600; word-break: break-all;"><?php echo e($subfolder->name); ?></div>
                                <div style="color: #94a3b8; font-size: 0.8rem; font-family: monospace; word-break: break-all;"><?php echo e($subfolder->google_id); ?></div>
                            </div>
                            <button type="button" class="btn-remove-subfolder" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 0.5rem; border-radius: 8px; cursor: pointer; margin-left: 1rem; flex-shrink: 0;">🗑️</button>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\goku0\Downloads\Proyectos\Juntify Laravel\Juntify\resources\views/partials/profile/_section-connect.blade.php ENDPATH**/ ?>