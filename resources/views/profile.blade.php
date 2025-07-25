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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/profile.css', 'resources/js/profile.js','resources/css/index.css'])


</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Header con navbar -->
    @include('partials.navbar')

    <!-- Botón hamburguesa para navbar (móvil) - esquina superior derecha -->
    <button class="mobile-navbar-btn" onclick="toggleMobileNavbar()" id="mobile-navbar-btn">
        <div class="hamburger-navbar">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>

    <div class="app-container">
        <!-- Botón flecha para sidebar (móvil) - esquina superior izquierda -->
        <button class="mobile-sidebar-btn" onclick="toggleSidebar()" id="mobile-sidebar-btn">
            <span class="arrow-right">›</span>
        </button>

        <!-- Overlay para cerrar sidebar en móvil -->
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <!-- Botón X para cerrar sidebar (solo móvil) -->
                <button class="sidebar-close-btn" onclick="closeSidebar()" id="sidebar-close-btn">
                    <span>×</span>
                </button>

                <div class="logo">Panel de Usuario</div>

            </div>

            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="#" class="nav-link active" data-section="info">👤 Información</a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link" data-section="connect">🔗 Conectar</a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link" data-section="plans">💎 Planes</a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link" data-section="purchases">🛒 Mis Compras</a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link" data-section="news">📰 Noticias</a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link" data-section="about">ℹ️ Acerca de</a>
                </div>
                <div class="action-buttons">
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                    <a href="#"
                    class="btn logout-btn"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
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
              <div id="section-info" class="content-section">
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
                                    <img src="{{ asset('badges/creative-badge.png') }}" alt="Avatar Creativos" class="avatar">
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
              </div>

                {{-- SECCIÓN CONECTAR (oculta) --}}
                <div id="section-connect" class="content-section" style="display:none">
                    <!-- Header -->
                    <div class="content-header">
                        <div>
                            <h1 class="page-title">Conectar con Google Drive</h1>
                            <p class="page-subtitle">Conecta tu cuenta de Google Drive para sincronizar tus reuniones</p>
                        </div>
                        <div class="user-avatar">
                            <span style="font-size: 3rem;">🔗</span>
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- Conexión Google Drive -->
                        <div class="info-card" id="drive-connection-card">
                            <h2 class="card-title">
                                <span class="card-icon">📁</span>
                                Google Drive
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Estado</span>
                                <span class="status-badge {{ $driveConnected ? 'status-active' : 'status-warning' }}" id="drive-status">
                                    {{ $driveConnected ? 'Conectado' : 'Desconectado' }}
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Última sincronización</span>
                                <span class="info-value" id="last-sync">
                                    @if($lastSync)
                                        {{ $lastSync->format('d/m/Y H:i:s') }}
                                    @else
                                        Nunca
                                    @endif
                                </span>
                            </div>
                            @if(!$driveConnected)
                                <div class="action-buttons">
                                    <button
                                        type="button"
                                        id="connect-drive-btn"
                                        class="btn btn-primary"
                                    >
                                        🔗 Conectar con Google Drive
                                    </button>
                                </div>
                            @else
                                <div class="action-buttons">
                                    <form method="POST" action="{{ route('drive.disconnect') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary">
                                            🔌 Cerrar sesión de Drive
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        <!-- Configuración de Carpetas (oculta inicialmente) -->
                        <div class="info-card" id="folder-config-card" @unless($driveConnected) style="display:none;" @endunless>
                            <h2 class="card-title">
                                <span class="card-icon">📂</span>
                                Configuración de Carpetas
                            </h2>
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label class="info-label" style="display: block; margin-bottom: 0.5rem;">Carpeta Principal</label>
                                <input type="text" id="main-folder-input" class="form-input" placeholder="Pega aquí el ID de la carpeta o crea una nueva" style="width: 100%; padding: 0.75rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(59,130,246,0.3); border-radius: 8px; color: #fff;" value="{{ $folder->google_id ?? '' }}" data-id="{{ $folder->google_id ?? '' }}">
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-secondary" onclick="showCreateFolderModal()">
                                    ➕ Crear Carpeta Principal
                                </button>
                                <button class="btn btn-primary" id="set-main-folder-btn" onclick="setMainFolder()">
                                    ✅ Establecer Carpeta
                                </button>
                            </div>
                        </div>

                        <!-- Gestión de Subcarpetas (oculta inicialmente) -->
                        <div class="info-card" id="subfolder-card" @unless($driveConnected) style="display:none;" @endunless>
                            <h2 class="card-title">
                                <span class="card-icon">📁</span>
                                Subcarpetas
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Carpeta Principal</span>
                                <span class="info-value" id="main-folder-name" data-name="{{ $folder->name ?? '' }}" data-id="{{ $folder->google_id ?? '' }}">{{ isset($folder) ? ($folder->name . ' (' . $folder->google_id . ')') : '' }}</span>
                            </div>
                            <div class="form-group" style="margin: 1rem 0;">
                                <label class="info-label" style="display: block; margin-bottom: 0.5rem;">Nueva Subcarpeta</label>
                                <input type="text" id="subfolder-input" class="form-input" placeholder="Nombre de la subcarpeta" style="width: 100%; padding: 0.75rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(59,130,246,0.3); border-radius: 8px; color: #fff;">
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="showCreateSubfolderModal()">
                                    ➕ Crear Subcarpeta
                                </button>
                            </div>
                            <div id="subfolders-list" style="margin-top: 1rem;">
                                @foreach ($subfolders as $sf)
                                      <div data-id="{{ $sf->google_id }}" style="margin: 0.5rem 0; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(59, 130, 246, 0.2);">
                                        <div>
                                            <div style="color: #ffffff; font-weight: 600;">{{ $sf->name }}</div>
                                            <div style="color: #94a3b8; font-size: 0.8rem;">{{ $sf->google_id }}</div>
                                        </div>
                                        <button type="button" class="btn-remove-subfolder" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 0.5rem; border-radius: 8px; cursor: pointer;">🗑️</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN PLANES (oculta) --}}
                <div id="section-plans" class="content-section" style="display:none">
                    <!-- Header -->
                    <div class="content-header">
                        <div>
                            <h1 class="page-title">Planes de Suscripción</h1>
                            <p class="page-subtitle">Elige el plan que mejor se adapte a tus necesidades</p>
                        </div>
                        <div class="user-avatar">
                            <span style="font-size: 3rem;">💎</span>
                        </div>
                    </div>

                    <!-- Pricing Toggle -->
                    <div class="pricing-toggle" style="display: flex; justify-content: center; margin-bottom: 3rem;">
                        <button class="toggle-btn active">Anual</button>
                        <button class="toggle-btn">Mensual</button>
                        <button class="toggle-btn">Reuniones Individuales</button>
                    </div>

                    <!-- Pricing Grid -->
                    <div class="pricing-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; max-width: 1200px; margin: 0 auto;">
                        <div class="pricing-card">
                            <h3 class="pricing-title">Freemium</h3>
                            <div class="pricing-price">$0</div>
                            <div class="pricing-period">mes</div>
                            <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                                Ideal para uso personal y equipos pequeños que buscan optimizar sus reuniones básicas.
                            </p>
                            <ul class="pricing-features">
                                <li>Hasta 3 reuniones por mes</li>
                                <li>Transcripción básica</li>
                                <li>Resúmenes automáticos (30 minutos)</li>
                                <li>Exportar como texto</li>
                                <li>Soporte por email</li>
                            </ul>
                            <a href="#" class="pricing-btn secondary">Empezar gratis</a>
                        </div>

                        <div class="pricing-card popular">
                            <h3 class="pricing-title">Básico</h3>
                            <div class="pricing-price">$499</div>
                            <div class="pricing-period">mes</div>
                            <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                                Ideal para equipos medianos que buscan optimizar sus reuniones y aumentar la productividad.
                            </p>
                            <ul class="pricing-features">
                                <li>Reuniones ilimitadas</li>
                                <li>Transcripción avanzada</li>
                                <li>Resúmenes inteligentes</li>
                                <li>Identificación de hablantes</li>
                                <li>Exportar múltiples formatos</li>
                                <li>Integraciones básicas</li>
                                <li>Soporte prioritario</li>
                            </ul>
                            <a href="#" class="pricing-btn">Seleccionar Plan</a>
                        </div>

                        <div class="pricing-card">
                            <h3 class="pricing-title">Negocios</h3>
                            <div class="pricing-price">$999</div>
                            <div class="pricing-period">mes</div>
                            <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                                Ideal para empresas que buscan una solución completa para optimizar todas sus reuniones.
                            </p>
                            <ul class="pricing-features">
                                <li>Todo lo del plan Básico</li>
                                <li>IA avanzada para análisis</li>
                                <li>Análisis de sentimientos</li>
                                <li>Dashboards ejecutivos</li>
                                <li>Integraciones avanzadas</li>
                                <li>API personalizada</li>
                                <li>Soporte 24/7</li>
                                <li>Capacitación incluida</li>
                            </ul>
                            <a href="#" class="pricing-btn">Seleccionar Plan</a>
                        </div>

                        <div class="pricing-card">
                            <h3 class="pricing-title">Empresas</h3>
                            <div class="pricing-price">$2999</div>
                            <div class="pricing-period">mes</div>
                            <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                                Ideal para grandes empresas y corporaciones que necesitan máxima personalización y control.
                            </p>
                            <ul class="pricing-features">
                                <li>Todo lo del plan Negocios</li>
                                <li>Implementación personalizada</li>
                                <li>Seguridad empresarial</li>
                                <li>Cumplimiento normativo</li>
                                <li>Análisis predictivo avanzado</li>
                                <li>Integraciones ilimitadas</li>
                                <li>Gerente de cuenta dedicado</li>
                                <li>SLA garantizado</li>
                            </ul>
                            <a href="#" class="pricing-btn">Contactar Ventas</a>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN MIS COMPRAS --}}
                <div id="section-purchases" class="content-section" style="display:none">
                    <!-- Header -->
                    <div class="content-header">
                        <div>
                            <h1 class="page-title">Historial de Compras</h1>
                            <p class="page-subtitle">Revisa tu historial de suscripciones y pagos</p>
                        </div>
                        <div class="user-avatar">
                            <span style="font-size: 3rem;">🛒</span>
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- Compra Reciente -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">💳</span>
                                Plan Básico - Anual
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Precio</span>
                                <span class="info-value">$4,990.00 MXN</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Estado</span>
                                <span class="status-badge status-active">Activo</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha de Compra</span>
                                <span class="info-value">15 Enero 2025</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Vence</span>
                                <span class="info-value">15 Enero 2026</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Método de Pago</span>
                                <span class="info-value">•••• •••• •••• 4532</span>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-secondary">
                                    📄 Descargar Factura
                                </button>
                                <button class="btn btn-primary">
                                    🔄 Renovar Plan
                                </button>
                            </div>
                        </div>

                        <!-- Compra Anterior -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">💳</span>
                                Plan Free - Upgrade
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Precio</span>
                                <span class="info-value">$0.00 MXN</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Estado</span>
                                <span class="status-badge status-expired">Expirado</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha de Compra</span>
                                <span class="info-value">10 Diciembre 2024</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Expiró</span>
                                <span class="info-value">14 Enero 2025</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Método de Pago</span>
                                <span class="info-value">Gratuito</span>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-secondary">
                                    📄 Ver Detalles
                                </button>
                            </div>
                        </div>

                        <!-- Resumen de Gastos -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">📊</span>
                                Resumen de Gastos
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Total Gastado</span>
                                <span class="info-value">$4,990.00 MXN</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Próximo Pago</span>
                                <span class="info-value">15 Enero 2026</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Ahorro Anual</span>
                                <span class="info-value">$1,998.00 MXN</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Reuniones Procesadas</span>
                                <span class="info-value">47 reuniones</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN NOTICIAS --}}
                <div id="section-news" class="content-section" style="display:none">
                    <!-- Header -->
                    <div class="content-header">
                        <div>
                            <h1 class="page-title">Actualizaciones de Juntify</h1>
                            <p class="page-subtitle">Mantente al día con las últimas mejoras y novedades</p>
                        </div>
                        <div class="user-avatar">
                            <span style="font-size: 3rem;">📰</span>
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- Actualización Reciente -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">🚀</span>
                                Juntify 2.1 - IA Mejorada
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Versión</span>
                                <span class="info-value">2.1.0</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha</span>
                                <span class="info-value">20 Enero 2025</span>
                            </div>
                            <div style="margin: 1rem 0; color: #cbd5e1; line-height: 1.6;">
                                <p><strong>Nuevas características:</strong></p>
                                <ul style="margin-left: 1rem; margin-top: 0.5rem;">
                                    <li>• Reconocimiento de emociones en tiempo real</li>
                                    <li>• Mejoras en la precisión de transcripción (95%)</li>
                                    <li>• Nueva integración con Microsoft Teams</li>
                                    <li>• Dashboard de analytics renovado</li>
                                </ul>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-primary">
                                    📖 Ver Detalles Completos
                                </button>
                            </div>
                        </div>

                        <!-- Actualización Anterior -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">⚡</span>
                                Juntify 2.0 - Rediseño Completo
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Versión</span>
                                <span class="info-value">2.0.0</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha</span>
                                <span class="info-value">15 Enero 2025</span>
                            </div>
                            <div style="margin: 1rem 0; color: #cbd5e1; line-height: 1.6;">
                                <p><strong>Cambios principales:</strong></p>
                                <ul style="margin-left: 1rem; margin-top: 0.5rem;">
                                    <li>• Interfaz completamente rediseñada</li>
                                    <li>• Nuevo sistema de autenticación</li>
                                    <li>• Integración con Google Drive</li>
                                    <li>• Mejoras de rendimiento del 40%</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Próximas Actualizaciones -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">🔮</span>
                                Próximamente - Juntify 2.2
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Versión</span>
                                <span class="info-value">2.2.0 (Beta)</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha Estimada</span>
                                <span class="info-value">Febrero 2025</span>
                            </div>
                            <div style="margin: 1rem 0; color: #cbd5e1; line-height: 1.6;">
                                <p><strong>Características en desarrollo:</strong></p>
                                <ul style="margin-left: 1rem; margin-top: 0.5rem;">
                                    <li>• Traducción automática en tiempo real</li>
                                    <li>• Integración con Slack y Discord</li>
                                    <li>• API pública para desarrolladores</li>
                                    <li>• Modo offline para transcripciones</li>
                                </ul>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-secondary">
                                    🧪 Unirse a Beta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN ACERCA DE --}}
                <div id="section-about" class="content-section" style="display:none">
                    <!-- Header -->
                    <div class="content-header">
                        <div>
                            <h1 class="page-title">Acerca de Juntify</h1>
                            <p class="page-subtitle">Conoce al equipo y la visión detrás de Juntify</p>
                        </div>
                        <div class="user-avatar">
                            <span style="font-size: 3rem;">ℹ️</span>
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- Información de la Empresa -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">🏢</span>
                                Juntify Technologies
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Fundada</span>
                                <span class="info-value">2024</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Ubicación</span>
                                <span class="info-value">Ciudad de México, México</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Misión</span>
                                <span class="info-value">Revolucionar la forma en que las empresas gestionan sus reuniones</span>
                            </div>
                            <div style="margin: 1rem 0; color: #cbd5e1; line-height: 1.6;">
                                <p>Juntify nació de la necesidad de hacer más eficientes las reuniones empresariales. Utilizamos inteligencia artificial de vanguardia para transformar conversaciones en acciones concretas, ahorrando tiempo valioso a equipos de todo el mundo.</p>
                            </div>
                        </div>

                        <!-- Desarrollador Principal -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">👨‍💻</span>
                                Desarrollador Principal
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Nombre</span>
                                <span class="info-value">Alex Rodriguez</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Cargo</span>
                                <span class="info-value">CTO & Lead Developer</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Experiencia</span>
                                <span class="info-value">8+ años en IA y Machine Learning</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Especialidades</span>
                                <span class="info-value">NLP, Speech Recognition, Full-Stack</span>
                            </div>
                            <div style="margin: 1rem 0; color: #cbd5e1; line-height: 1.6;">
                                <p>Experto en procesamiento de lenguaje natural y reconocimiento de voz. Anteriormente trabajó en Google AI y Microsoft Research, especializándose en sistemas de transcripción en tiempo real.</p>
                            </div>
                        </div>

                        <!-- Equipo de Desarrollo -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">👥</span>
                                Equipo de Desarrollo
                            </h2>
                            <div style="margin: 1rem 0;">
                                <div style="margin-bottom: 1rem; padding: 1rem; background: rgba(59,130,246,0.1); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong style="color: #3b82f6;">María González</strong>
                                            <p style="color: #cbd5e1; font-size: 0.9rem;">Frontend Developer & UX Designer</p>
                                        </div>
                                        <span style="font-size: 1.5rem;">🎨</span>
                                    </div>
                                </div>
                                <div style="margin-bottom: 1rem; padding: 1rem; background: rgba(59,130,246,0.1); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong style="color: #3b82f6;">Carlos Mendoza</strong>
                                            <p style="color: #cbd5e1; font-size: 0.9rem;">Backend Developer & DevOps</p>
                                        </div>
                                        <span style="font-size: 1.5rem;">⚙️</span>
                                    </div>
                                </div>
                                <div style="margin-bottom: 1rem; padding: 1rem; background: rgba(59,130,246,0.1); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong style="color: #3b82f6;">Ana Jiménez</strong>
                                            <p style="color: #cbd5e1; font-size: 0.9rem;">AI Engineer & Data Scientist</p>
                                        </div>
                                        <span style="font-size: 1.5rem;">🤖</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información Técnica -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">🔧</span>
                                Información Técnica
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Versión Actual</span>
                                <span class="info-value">Juntify 2.1.0</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tecnologías</span>
                                <span class="info-value">Laravel, Vue.js, Python, TensorFlow</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Base de Datos</span>
                                <span class="info-value">MySQL, Redis</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Infraestructura</span>
                                <span class="info-value">AWS, Docker, Kubernetes</span>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-secondary">
                                    📧 Contactar Soporte
                                </button>
                                <button class="btn btn-primary">
                                    🌐 Visitar GitHub
                                </button>
                            </div>
                        </div>

                        <!-- Contacto y Legal -->
                        <div class="info-card">
                            <h2 class="card-title">
                                <span class="card-icon">📞</span>
                                Contacto y Legal
                            </h2>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value">contacto@juntify.com</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Soporte</span>
                                <span class="info-value">soporte@juntify.com</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Teléfono</span>
                                <span class="info-value">+52 55 1234 5678</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Licencia</span>
                                <span class="info-value">Propietaria - Todos los derechos reservados</span>
                            </div>
                            <div style="margin: 1rem 0; color: #cbd5e1; font-size: 0.8rem; line-height: 1.4;">
                                <p>© 2024-2025 Juntify Technologies. Todos los derechos reservados. Juntify es una marca registrada de Juntify Technologies S.A. de C.V.</p>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </div>

    <!-- Modal para crear carpeta principal -->
    <div class="modal" id="create-folder-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <span class="modal-icon">📁</span>
                    Crear Carpeta Principal
                </h2>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Ingresa el nombre para tu carpeta principal de reuniones en Google Drive.
                </p>
                <div class="form-group">
                    <label for="folder-name-input" class="form-label">Nombre de la carpeta</label>
                    <input
                        type="text"
                        id="folder-name-input"
                        class="form-input modal-input"
                        placeholder="Ej: Juntify-Reuniones-2025"
                        maxlength="100"
                    >
                    <div class="input-hint">
                        Se creará en tu Google Drive raíz
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateFolderModal()">
                    ❌ Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmCreateFolder()" id="confirm-create-btn">
                    ✅ Crear Carpeta
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para crear subcarpeta -->
    <div class="modal" id="create-subfolder-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <span class="modal-icon">📂</span>
                    Crear Subcarpeta
                </h2>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Ingresa el nombre para la nueva subcarpeta dentro de tu carpeta principal.
                </p>
                <div class="form-group">
                    <label for="subfolder-name-input" class="form-label">Nombre de la subcarpeta</label>
                    <input
                        type="text"
                        id="subfolder-name-input"
                        class="form-input modal-input"
                        placeholder="Ej: Reuniones-Enero-2025"
                        maxlength="100"
                    >
                    <div class="input-hint">
                        Se creará dentro de tu carpeta principal
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateSubfolderModal()">
                    ❌ Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmCreateSubfolder()" id="confirm-create-sub-btn">
                    ✅ Crear Subcarpeta
                </button>
            </div>
        </div>
    </div>

    <div class="modal" id="drive-loading-modal">
        <div class="modal-content">
            <div class="modal-icon">⏳</div>
            <h2 class="modal-title">Verificando conexión...</h2>
        </div>
    </div>

</body>
</html>
