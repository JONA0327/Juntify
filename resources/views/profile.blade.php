<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Perfil - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/index.css',    // <— añade esto
    'resources/js/index.js',      // <— añade esto
    'resources/css/profile.css',
    'resources/js/profile.js'
    ])


</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal arriba de todo -->
    @include('partials.navbar')

    <!-- Barra de navegación móvil exclusiva -->
    <div class="mobile-bottom-nav">
        <div class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            <span class="nav-label">Reuniones</span>
        </div>
        <div class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="12" r="1" fill="currentColor"/>
                <circle cx="15" cy="12" r="1" fill="currentColor"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
            </svg>
            <span class="nav-label">Asistente IA</span>
        </div>
        <div class="nav-item nav-center">
            <svg class="nav-icon-center" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </div>
        <div class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="nav-label">Tareas</span>
        </div>
        <div class="nav-item dropdown-trigger" onclick="toggleMobileDropdown()">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            <span class="nav-label">Más</span>
            <div class="mobile-dropdown" id="mobile-dropdown">
                <a href="{{ route('profile.show') }}" class="dropdown-item">
                    <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
                    </svg>
                    <span class="dropdown-text">Perfil</span>
                </a>
                <a href="#" class="dropdown-item">
                    <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 10.5L12 6m0 0l4.5 4.5M12 6v12" />
                    </svg>
                    <span class="dropdown-text">Compartir</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay para cerrar dropdown -->
    <div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

    <button class="mobile-sidebar-btn mobile-menu-btn" onclick="toggleSidebar()" aria-label="Abrir menú">
        <svg class="icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 01-1.414-1.414L10.586 10 5.879 5.707a1 1 0 011.414-1.414l4.001 4a1 1 0 010 1.414l-4.001 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
    </button>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Cerrar menú">
                <svg class="icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
            <div class="sidebar-header">
                <h1 class="logo">Juntify</h1>
                <p class="logo-subtitle">Panel de usuario</p>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-section="info">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
                            </svg>
                            Información
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="connect">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 015.657 5.657l-3.536 3.536a4 4 0 01-5.657 0m-3.536-3.536a4 4 0 015.657-5.657l3.536 3.536" />
                            </svg>
                            Conectar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="plans">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.639 5.033a1 1 0 00.95.69h5.287c.969 0 1.371 1.24.588 1.81l-4.278 3.11a1 1 0 00-.364 1.118l1.64 5.034c.3.921-.755 1.688-1.54 1.118l-4.279-3.11a1 1 0 00-1.175 0l-4.279 3.11c-.784.57-1.838-.197-1.539-1.118l1.639-5.034a1 1 0 00-.364-1.118l-4.278-3.11c-.783-.57-.38-1.81.588-1.81h5.287a1 1 0 00.951-.69l1.639-5.034z" />
                            </svg>
                            Planes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="purchases">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 2.25h1.386c.51 0 .96.36 1.07.86l.507 2.454a.563.563 0 00.55.436h11.614a.563.563 0 00.551-.436L19.294 3.11a1.125 1.125 0 011.07-.86H21.75M5.25 12.75h14.25m-14.25 0l-.75 3.75m0 0h15.75m-15.75 0L5.25 21m0 0a1.5 1.5 0 103 0m10.5 0a1.5 1.5 0 103 0" />
                            </svg>
                            Mis Compras
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="notifications">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 10v-.25A2.75 2.75 0 0011.75 7h-.5A2.75 2.75 0 008.5 9.75v.25c0 2.827-1.842 5.222-4.5 6v1.25h15v-1.25c-2.658-.778-4.5-3.173-4.5-6z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.25a2.25 2.25 0 01-4.5 0" />
                            </svg>
                            Notificaciones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="about">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h1.5m-1.5 0v5.25m.75-9.75h.008v.008H12" />
                                <circle cx="12" cy="12" r="9" />
                            </svg>
                            Acerca de
                        </a>
                    </li>
                </ul>

                <div class="action-buttons">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn logout-btn">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="version-info">
                    Versión <span class="version-number">Juntify 2.0</span>
                </div>
            </div>
        </aside>
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">Bienvenido, {{ $user->full_name }}</h1>
                    <p class="page-subtitle">Gestiona tu cuenta y configuraciones</p>
                </div>
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

            <!-- Content Sections -->

            <!-- Información del Usuario -->
            <div class="content-section" id="section-info">
                <div class="content-grid">
                    <div class="info-card">
                        <h2 class="card-title">
                            <span class="card-icon">👤</span>
                            Información Personal
                        </h2>
                        <div class="info-item">
                            <span class="info-label">Nombre de usuario</span>
                            <span class="info-value">{{ $user->username }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nombre completo</span>
                            <span class="info-value">{{ $user->full_name }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Correo electrónico</span>
                            <span class="info-value">{{ $user->email }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Organización</span>
                            <span class="info-value">{{ $user->organization ?? 'No especificada' }}</span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h2 class="card-title">
                            <span class="card-icon">💎</span>
                            Plan Actual
                        </h2>
                        <div class="info-item">
                            <span class="info-label">Tipo de plan</span>
                            <span class="status-badge status-{{ strtolower($user->roles ?? 'free') }}">
                                {{ ucfirst($user->roles ?? 'free') }}
                            </span>
                        </div>
                        @if($user->plan_expires_at)
                        <div class="info-item">
                            <span class="info-label">Expira el</span>
                            <span class="info-value">{{ $user->plan_expires_at->format('d/m/Y') }}</span>
                        </div>
                        @endif
                        <div class="info-item">
                            <span class="info-label">Miembro desde</span>
                            <span class="info-value">{{ $user->created_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conectar Servicios -->
            <div class="content-section" id="section-connect" style="display: none;">
                <div class="content-grid">
                    @if(!$driveConnected)
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
                            <div class="action-buttons">
                                <button class="btn btn-primary" id="connect-drive-btn">
                                    🔗 Conectar Drive y Calendar
                                </button>
                            </div>
                        </div>
                    @else
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
                                <span class="info-label">Estado</span>
                                <span class="status-badge status-active">Conectado</span>
                            </div>
                            @if($lastSync)
                            <div class="info-item">
                                <span class="info-label">Última sincronización</span>
                                <span class="info-value">{{ $lastSync->format('d/m/Y H:i:s') }}</span>
                            </div>
                            @endif
                            <div class="action-buttons">
                                <form method="POST" action="{{ route('drive.disconnect') }}">
                                    @csrf
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
                                @if($folder)
                                    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                            <span style="font-size: 1.2rem;">📁</span>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="color: #ffffff; font-weight: 600; word-break: break-all;">
                                                    {{ $folder->name }}
                                                </div>
                                                <div style="color: #94a3b8; font-size: 0.8rem; font-family: monospace; word-break: break-all;">
                                                    ID: {{ $folder->google_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <input
                                    type="text"
                                    class="form-input"
                                    id="main-folder-input"
                                    placeholder="ID de la carpeta principal"
                                    data-id="{{ $folder->google_id ?? '' }}"
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
                        @if($folder)
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
                                    @foreach($subfolders as $subfolder)
                                    <div data-id="{{ $subfolder->google_id }}" style="margin: 0.5rem 0; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(59, 130, 246, 0.2);">
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="color: #ffffff; font-weight: 600; word-break: break-all;">{{ $subfolder->name }}</div>
                                            <div style="color: #94a3b8; font-size: 0.8rem; font-family: monospace; word-break: break-all;">{{ $subfolder->google_id }}</div>
                                        </div>
                                        <button type="button" class="btn-remove-subfolder" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 0.5rem; border-radius: 8px; cursor: pointer; margin-left: 1rem; flex-shrink: 0;">🗑️</button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Planes -->
            <div class="content-section" id="section-plans" style="display: none;">
                <div class="pricing-toggle">
                    <button class="toggle-btn active">Anual</button>
                    <button class="toggle-btn">Mensual</button>
                </div>

                <div class="pricing-grid">
                    <div class="pricing-card">
                        <h3 class="pricing-title">Freemium</h3>
                        <div class="pricing-price">$0</div>
                        <div class="pricing-period">mes</div>
                        <ul class="pricing-features">
                            <li>Hasta 3 reuniones por mes</li>
                            <li>Transcripción básica</li>
                            <li>Resúmenes automáticos</li>
                            <li>Exportar como texto</li>
                        </ul>
                        <button class="pricing-btn secondary">Plan Actual</button>
                    </div>

                    <div class="pricing-card popular">
                        <h3 class="pricing-title">Básico</h3>
                        <div class="pricing-price">$499</div>
                        <div class="pricing-period">mes</div>
                        <ul class="pricing-features">
                            <li>Reuniones ilimitadas</li>
                            <li>Transcripción avanzada</li>
                            <li>Identificación de hablantes</li>
                            <li>Integraciones básicas</li>
                        </ul>
                        <button class="pricing-btn">Actualizar Plan</button>
                    </div>

                    <div class="pricing-card">
                        <h3 class="pricing-title">Negocios</h3>
                        <div class="pricing-price">$999</div>
                        <div class="pricing-period">mes</div>
                        <ul class="pricing-features">
                            <li>Todo lo del plan Básico</li>
                            <li>IA avanzada para análisis</li>
                            <li>Dashboards ejecutivos</li>
                            <li>API personalizada</li>
                        </ul>
                        <button class="pricing-btn">Actualizar Plan</button>
                    </div>
                </div>
            </div>

            <!-- Otras secciones ocultas por defecto -->
            <div class="content-section" id="section-purchases" style="display: none;">
                <div class="info-card">
                    <h2 class="card-title">
                        <span class="card-icon">🛒</span>
                        Historial de Compras
                    </h2>
                    <p style="color: #cbd5e1; text-align: center; padding: 2rem;">
                        No tienes compras registradas aún.
                    </p>
                </div>
            </div>

            <div class="content-section" id="section-notifications" style="display: none;">
                <div class="info-card">
                    <h2 class="card-title">
                        <span class="card-icon">🔔</span>
                        Notificaciones
                    </h2>
                    <p style="color: #cbd5e1; text-align: center; padding: 2rem;">
                        No tienes notificaciones nuevas.
                    </p>
                </div>
            </div>

            <div class="content-section" id="section-about" style="display: none;">
                <div class="info-card">
                    <h2 class="card-title">
                        <span class="card-icon">ℹ️</span>
                        Acerca de Juntify
                    </h2>
                    <p style="color: #cbd5e1; line-height: 1.6; margin-bottom: 1rem;">
                        Juntify es la plataforma líder en transcripción y análisis de reuniones,
                        diseñada para maximizar la productividad de tu equipo.
                    </p>
                    <div class="info-item">
                        <span class="info-label">Versión</span>
                        <span class="info-value">2.0.1</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última actualización</span>
                        <span class="info-value">{{ now()->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modales -->

    <!-- Modal para crear carpeta principal -->
    <div class="modal" id="create-folder-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <span class="modal-icon">📁</span>
                    Crear Carpeta Principal
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Esta carpeta será el directorio principal donde se almacenarán todas tus grabaciones y transcripciones.
                </p>
                <div class="form-group">
                    <label class="form-label">Nombre de la carpeta</label>
                    <input type="text" class="modal-input" id="folder-name-input" placeholder="Ej: Juntify-Reuniones-2025">
                    <div class="input-hint">Se creará en tu Google Drive y se compartirá automáticamente con el sistema.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCreateFolderModal()">Cancelar</button>
                <button class="btn btn-primary" id="confirm-create-btn" onclick="confirmCreateFolder()">✅ Crear Carpeta</button>
            </div>
        </div>
    </div>

    <!-- Modal para crear subcarpeta -->
    <div class="modal" id="create-subfolder-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <span class="modal-icon">📂</span>
                    Crear Subcarpeta
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Las subcarpetas te ayudan a organizar tus reuniones por proyecto, fecha o tema.
                </p>
                <div class="form-group">
                    <label class="form-label">Nombre de la subcarpeta</label>
                    <input type="text" class="modal-input" id="subfolder-name-input" placeholder="Ej: Reuniones-Enero-2025">
                    <div class="input-hint">Se creará dentro de tu carpeta principal de grabaciones.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCreateSubfolderModal()">Cancelar</button>
                <button class="btn btn-primary" id="confirm-create-sub-btn" onclick="confirmCreateSubfolder()">✅ Crear Subcarpeta</button>
            </div>
        </div>
    </div>

    <!-- Modal de carga -->
    <div class="modal" id="drive-loading-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <span class="modal-icon">⏳</span>
                    Conectando con Google Drive
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Por favor espera mientras verificamos tu conexión...
                </p>
            </div>
        </div>
    </div>
</body>
</html>
