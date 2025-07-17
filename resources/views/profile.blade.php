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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/profile.css', 'resources/js/profile.js'])


</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Mobile menu button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        ☰
    </button>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">Juntify</div>
                <div class="logo-subtitle">Panel de Usuario</div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <span class="nav-icon">👤</span>
                        Información
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon">🔗</span>
                        Conectar
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon">💎</span>
                        Planes
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        Mis Compras
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon">📰</span>
                        Noticias
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon">ℹ️</span>
                        Acerca de
                    </a>
                </div>
                <div class="action-buttons ">
                        <a href="{{ route('login') }}" class="btn logout-btn">
                            🚪 Cerrar Sesión
                        </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="version-info">
                    Versión <span class="version-number">Juntify 2.0</span>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">Mi Perfil</h1>
                    <p class="page-subtitle">Gestiona tu información personal y configuración de cuenta</p>
                </div>
                <div class="user-avatar">
                    @switch(Auth::user()->roles)
                        @case('free')
                            <img src="{{ asset('badges/free-badge.png') }}" alt="Avatar Free" class="avatar">
                            @break
                        @case('basic')
                            <img src="{{ asset('badges/basic-badge.png') }}" alt="Avatar Basic" class="avatar">
                            @break
                        @case('business')
                            <img src="{{ asset('badges/business-badge.png') }}" alt="Avatar Business" class="avatar">
                            @break
                        @case('enterprise')
                            <img src="{{ asset('badges/enterprise-badge.png') }}" alt="Avatar Enterprise" class="avatar">
                            @break
                        @case('founder')
                            <img src="{{ asset('badges/founder-badge.png') }}" alt="Avatar Founder" class="avatar">
                            @break
                        @case('developer')
                            <img src="{{ asset('badges/developer-badge.png') }}" alt="Avatar Developer" class="avatar">
                            @break
                        @case('superadmin')
                            <img src="{{ asset('badges/superadmin-badge.png') }}" alt="Avatar Superadmin" class="avatar">
                            @break
                        @case('creative')
                            <img src="{{ asset('badges/creativ-badge.png') }}" alt="Avatar Creativos" class="avatar">
                            @break

                        @break
                    @endswitch
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Información Personal -->
                <div class="info-card">
                    <h2 class="card-title">
                        <span class="card-icon">👤</span>
                        Información Personal
                    </h2>
                    <div class="info-item">
                        <span class="info-label">Nombre de Usuario</span>
                        <span class="info-value">{{ Auth::user()->username }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nombre Completo</span>
                        <span class="info-value">{{ Auth::user()->full_name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Correo Electrónico</span>
                        <span class="info-value">{{ Auth::user()->email }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Organización</span>
                        <span class="info-value">
                            @if (Auth::user()->organization === null)
                                <span class="status-badge status-warning">No asignada</span>

                            @else
                                {{ Auth::user()->organization }}

                            @endif

                        </span>
                    </div>
                    <div class="action-buttons">
                        <a href="#" class="btn btn-primary">
                            ✏️ Editar Perfil
                        </a>
                        <a href="#" class="btn btn-secondary">
                            🔒 Cambiar Contraseña
                        </a>
                    </div>
                </div>

                <!-- Estado de la Cuenta -->
                <div class="info-card">
                    <h2 class="card-title">
                        <span class="card-icon">💎</span>
                        Estado de la Cuenta
                    </h2>
                    <div class="info-item">
                        <span class="info-label">Plan Actual</span>
                        <span class="status-badge status-{{ Auth::user()->roles }}">
                        {{ ucfirst(Auth::user()->roles) }}
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado</span>
                        <span class="status-badge ">
                           @php
                              $role = Auth::user()->roles;
                            @endphp

                            @if(in_array($role, ['free','developer','founder','superadmin','creative']))
                                <span class="status-badge status-active">
                                 Activa
                                </span>
                            @elseif(in_array($role, ['basic','business','enterprise']))
                                @if(Auth::user()->plan_expires_at > now())
                                     <span class="status-badge status-active">
                                        Activa
                                    </span>
                                @else
                                    <span class="status-badge status-expired">
                                    Expirada
                                    </span>
                                @endif
                            @else
                                Activa
                            @endif
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Vencimiento</span>
                        @if (Auth::user()->roles === 'free' || Auth::user()->roles === 'developer' || Auth::user()->roles === 'founder' || Auth::user()->roles === 'superadmin'|| Auth::user()->roles === 'creative')
                            <span class="status-badge status-warning">ilimitado</span>

                        @else
                            <span class="info-value">{{ Auth::user()->plan_expires_at->format('d M Y') }}</span>
                        @endif


                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Registro</span>
                        <span class="info-value">{{ Auth::user()->created_at->format('d M Y') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última Actualización</span>
                        <span class="info-value">{{ Auth::user()->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="action-buttons">
                        <a href="#" class="btn btn-primary">
                            🚀 Actualizar Plan
                        </a>
                    </div>
                </div>

                <!-- Estadísticas de Uso -->
                <div class="info-card">
                    <h2 class="card-title">
                        <span class="card-icon">📊</span>
                        Estadísticas de Uso
                    </h2>
                    <div class="info-item">
                        <span class="info-label">Reuniones Totales</span>
                        <span class="info-value">47</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Horas Transcritas</span>
                        <span class="info-value">156.5h</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tareas Creadas</span>
                        <span class="info-value">234</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Integraciones Activas</span>
                        <span class="info-value">5</span>
                    </div>
                    <div class="action-buttons">
                        <a href="#" class="btn btn-secondary">
                            📈 Ver Reportes Detallados
                        </a>
                    </div>
                </div>


            </div>
        </main>
    </div>


</body>
</html>
