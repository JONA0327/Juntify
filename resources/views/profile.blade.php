<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="navigate-to-plans" content="{{ session('navigateToPlans') ? 'true' : 'false' }}">
    <title>Perfil - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/js/index.js',
        'resources/css/profile.css',
        'resources/js/profile.js',
    ])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    @include('partials.navbar')

    <!-- Botón para abrir sidebar en móvil -->
    <button class="mobile-sidebar-btn mobile-menu-btn" onclick="toggleSidebar()" aria-label="Abrir menú">
        <svg class="icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 01-1.414-1.414L10.586 10 5.879 5.707a1 1 0 011.414-1.414l4.001 4a1 1 0 010 1.414l-4.001 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
    </button>

    <div class="app-container">
        <!-- Sidebar -->
        @include('partials.profile._sidebar')

        <!-- Main Content -->
        <main class="main-content" data-tutorial="main-content">
            <!-- Header - Solo visible en la sección de Información -->
            <div class="content-header" id="welcome-card" data-tutorial="welcome-header">
                <div>
                    <h1 class="page-title">Bienvenido, {{ $user->full_name }}</h1>
                    <p class="page-subtitle">Gestiona tu cuenta y configuraciones</p>
                </div>

                <!-- CÓDIGO DEL AVATAR/BADGE RESTAURADO -->
                <div class="user-avatar">
                    @php
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
                    @endphp
                    <img src="/badges/{{ $userRole }}-badge.png"
                         alt="{{ ucfirst($userRole) }} Badge"
                         class="avatar"
                         style="filter: drop-shadow(0 0 10px {{ $badgeColor }}40);">
                </div>
            </div>

            <!-- Notificaciones de sistema -->
            @if(session('success'))
                <div class="notification success" id="success-notification">
                    <div class="notification-content">
                        <svg class="notification-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="notification-message">{{ session('success') }}</span>
                        <button class="notification-close" onclick="closeNotification('success-notification')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="notification error" id="error-notification">
                    <div class="notification-content">
                        <svg class="notification-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6m0 0l6-6m-6 6l-6-6m6 6v12" />
                        </svg>
                        <span class="notification-message">{{ session('error') }}</span>
                        <button class="notification-close" onclick="closeNotification('error-notification')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Content Sections -->
            @include('partials.profile._section-info')
            @include('partials.profile._section-connect', ['folder' => $folder, 'subfolders' => $subfolders, 'folderMessage' => $folderMessage])
            @include('partials.profile._section-plans')
            @include('partials.profile._section-purchases')
            @include('partials.profile._section-other')

        </main>
    </div>

    <!-- Modals -->
    @include('partials.profile._modals')

    <!-- Global vars and functions -->
    @include('partials.global-vars')

    <!-- Google Connection Monitor Script -->
    <script src="{{ asset('js/google-connection-monitor.js') }}"></script>

</body>
</html>
