<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Perfil - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/js/index.js',
        'resources/css/profile.css',
        'resources/js/profile.js'
    ]); ?>
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    <?php echo $__env->make('partials.navbar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Barra de navegación móvil -->
    <?php echo $__env->make('partials.mobile-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Botón para abrir sidebar en móvil -->
    <button class="mobile-sidebar-btn mobile-menu-btn" onclick="toggleSidebar()" aria-label="Abrir menú">
        <svg class="icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 01-1.414-1.414L10.586 10 5.879 5.707a1 1 0 011.414-1.414l4.001 4a1 1 0 010 1.414l-4.001 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
    </button>

    <div class="app-container">
        <!-- Sidebar -->
        <?php echo $__env->make('partials.profile._sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">Bienvenido, <?php echo e($user->full_name); ?></h1>
                    <p class="page-subtitle">Gestiona tu cuenta y configuraciones</p>
                </div>

                <!-- CÓDIGO DEL AVATAR/BADGE RESTAURADO -->
                <div class="user-avatar">
                    <?php
                        $roleColors = [
                            'free' => '#F472B6',
                            'basic' => '#64748B',
                            'business' => '#06B6D4',
                            'developer' => '#A855F7',
                            'enterprise' => '#A855F7',
                            'founder' => '#9CA3AF',
                            'superadmin' => '#DC2626',
                            'creative' => '#FF6B6B'
                        ];
                        $userRole = $user->roles ?? 'free';
                        $badgeColor = $roleColors[$userRole] ?? '#64748B';
                    ?>
                    <img src="/badges/<?php echo e($userRole); ?>-badge.png"
                         alt="<?php echo e(ucfirst($userRole)); ?> Badge"
                         class="avatar"
                         style="filter: drop-shadow(0 0 10px <?php echo e($badgeColor); ?>40);">
                </div>
            </div>

            <!-- Content Sections -->
            <?php echo $__env->make('partials.profile._section-info', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('partials.profile._section-connect', ['folder' => $folder, 'subfolders' => $subfolders, 'folderMessage' => $folderMessage], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('partials.profile._section-plans', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('partials.profile._section-other', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </main>
    </div>

    <!-- Modals -->
    <?php echo $__env->make('partials.profile._modals', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Google Connection Monitor Styles -->
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .google-connection-indicator {
            display: inline-flex;
            align-items: center;
            color: #3b82f6;
        }

        .google-refresh-spinner {
            color: #3b82f6;
        }

        .google-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .google-notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .google-notification.success {
            background-color: #10b981;
            border: 1px solid #059669;
        }

        .google-notification.error {
            background-color: #ef4444;
            border: 1px solid #dc2626;
        }

        .google-notification.info {
            background-color: #3b82f6;
            border: 1px solid #2563eb;
        }
    </style>

    <!-- Google Connection Monitor Script -->
    <script src="<?php echo e(asset('js/google-connection-monitor.js')); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof GoogleConnectionMonitor !== 'undefined') {
                const monitor = new GoogleConnectionMonitor();
                monitor.init();
            }
        });
    </script>

</body>
</html>
<?php /**PATH C:\laragon\www\Juntify\resources\views/profile.blade.php ENDPATH**/ ?>