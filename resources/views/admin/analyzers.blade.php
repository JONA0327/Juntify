<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gestión de Analizadores - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/js/profile.js'
    ])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    @include('partials.navbar')

    <!-- Barra de navegación móvil -->
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
                <a href="/admin" class="dropdown-item">
                    <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="dropdown-text">Admin</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay para cerrar dropdown -->
    <div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

    <div class="app-container">
        <main class="main-admin">
            <!-- Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">Gestión de Analizadores</h1>
                    <p class="page-subtitle">Crea y administra los analizadores de IA para el procesamiento de reuniones</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='/admin'">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver al panel
                    </button>
                    <button class="btn btn-primary" onclick="showCreateAnalyzerModal()">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Crear Analizador
                    </button>
                </div>
            </div>

            <!-- Analizadores existentes -->
            <div class="content-grid">
                <!-- Analizador General -->
                <div class="info-card analyzer-card">
                    <div class="analyzer-header">
                        <h3 class="card-title">
                            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                            Análisis General
                        </h3>
                        <div class="analyzer-actions">
                            <button class="control-btn" onclick="editAnalyzer('general')" title="Editar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                </svg>
                            </button>
                            <button class="control-btn delete-btn" onclick="deleteAnalyzer('general')" title="Eliminar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="analyzer-description">
                        <p>Realiza un análisis completo de la reunión identificando resumen, puntos clave y tareas automáticamente.</p>
                    </div>
                    <div class="analyzer-details">
                        <div class="info-item">
                            <span class="info-label">Tipo</span>
                            <span class="status-badge status-active">Sistema</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Creado</span>
                            <span class="info-value">15/01/2025</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Usos</span>
                            <span class="info-value">1,247</span>
                        </div>
                    </div>
                </div>

                <!-- Analizador de Reuniones -->
                <div class="info-card analyzer-card">
                    <div class="analyzer-header">
                        <h3 class="card-title">
                            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            Análisis de Reunión
                        </h3>
                        <div class="analyzer-actions">
                            <button class="control-btn" onclick="editAnalyzer('meeting')" title="Editar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                </svg>
                            </button>
                            <button class="control-btn delete-btn" onclick="deleteAnalyzer('meeting')" title="Eliminar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="analyzer-description">
                        <p>Enfocado en decisiones, acuerdos y seguimientos específicos de reuniones corporativas.</p>
                    </div>
                    <div class="analyzer-details">
                        <div class="info-item">
                            <span class="info-label">Tipo</span>
                            <span class="status-badge status-active">Sistema</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Creado</span>
                            <span class="info-value">15/01/2025</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Usos</span>
                            <span class="info-value">892</span>
                        </div>
                    </div>
                </div>

                <!-- Analizador de Proyectos -->
                <div class="info-card analyzer-card">
                    <div class="analyzer-header">
                        <h3 class="card-title">
                            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                            </svg>
                            Análisis de Proyecto
                        </h3>
                        <div class="analyzer-actions">
                            <button class="control-btn" onclick="editAnalyzer('project')" title="Editar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                </svg>
                            </button>
                            <button class="control-btn delete-btn" onclick="deleteAnalyzer('project')" title="Eliminar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="analyzer-description">
                        <p>Identifica objetivos, riesgos y próximos pasos específicos para la gestión de proyectos.</p>
                    </div>
                    <div class="analyzer-details">
                        <div class="info-item">
                            <span class="info-label">Tipo</span>
                            <span class="status-badge status-active">Sistema</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Creado</span>
                            <span class="info-value">15/01/2025</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Usos</span>
                            <span class="info-value">634</span>
                        </div>
                    </div>
                </div>

                <!-- Analizador de Ventas -->
                <div class="info-card analyzer-card">
                    <div class="analyzer-header">
                        <h3 class="card-title">
                            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                            </svg>
                            Análisis de Ventas
                        </h3>
                        <div class="analyzer-actions">
                            <button class="control-btn" onclick="editAnalyzer('sales')" title="Editar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                </svg>
                            </button>
                            <button class="control-btn delete-btn" onclick="deleteAnalyzer('sales')" title="Eliminar analizador">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="analyzer-description">
                        <p>Detecta oportunidades, objeciones y próximos pasos comerciales en reuniones de ventas.</p>
                    </div>
                    <div class="analyzer-details">
                        <div class="info-item">
                            <span class="info-label">Tipo</span>
                            <span class="status-badge status-active">Sistema</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Creado</span>
                            <span class="info-value">15/01/2025</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Usos</span>
                            <span class="info-value">423</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para crear/editar analizador -->
    <div class="modal" id="analyzer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c.232.232.348.694.348 1.154v1.697a2.25 2.25 0 01-2.25 2.25H5.25a2.25 2.25 0 01-2.25-2.25v-1.697c0-.46.116-.922.348-1.154L5 14.5" />
                    </svg>
                    Crear Nuevo Analizador
                </h3>
            </div>
            <div class="modal-body">
                <form id="analyzer-form">
                    <div class="form-group">
                        <label class="form-label">Nombre del Analizador</label>
                        <input type="text" class="modal-input" id="analyzer-name" placeholder="Ej: Análisis de Estrategia" required>
                        <div class="input-hint">Nombre descriptivo que aparecerá en la lista de analizadores</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea class="modal-input" id="analyzer-description" rows="3" placeholder="Describe qué tipo de análisis realizará este analizador..." required></textarea>
                        <div class="input-hint">Descripción breve de la funcionalidad del analizador</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prompt del Sistema</label>
                        <textarea class="modal-input" id="analyzer-prompt" rows="8" placeholder="Eres un asistente especializado en... Tu función es analizar transcripciones de reuniones y..." required></textarea>
                        <div class="input-hint">Instrucciones detalladas para ChatGPT sobre cómo debe comportarse este analizador</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icono (Emoji)</label>
                        <input type="text" class="modal-input" id="analyzer-icon" placeholder="🧠" maxlength="2">
                        <div class="input-hint">Emoji que representará este analizador (opcional)</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAnalyzerModal()">Cancelar</button>
                <button class="btn btn-primary" id="save-analyzer-btn" onclick="saveAnalyzer()">
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Guardar Analizador
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal" id="delete-analyzer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Confirmar Eliminación
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    ¿Estás seguro de que quieres eliminar este analizador? Esta acción no se puede deshacer.
                </p>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                    <p style="color: #ef4444; font-size: 0.9rem; margin: 0;">
                        <strong>Advertencia:</strong> Eliminar este analizador afectará todas las futuras transcripciones que dependan de él.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                <button class="btn btn-danger" id="confirm-delete-btn" onclick="confirmDeleteAnalyzer()">
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Eliminar Analizador
                </button>
            </div>
        </div>
    </div>

    <script>
        let editingAnalyzerId = null;
        let deletingAnalyzerId = null;

        function showCreateAnalyzerModal() {
            editingAnalyzerId = null;
            document.getElementById('modal-title').innerHTML = `
                <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c.232.232.348.694.348 1.154v1.697a2.25 2.25 0 01-2.25 2.25H5.25a2.25 2.25 0 01-2.25-2.25v-1.697c0-.46.116-.922.348-1.154L5 14.5" />
                </svg>
                Crear Nuevo Analizador
            `;
            document.getElementById('save-analyzer-btn').innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Guardar Analizador
            `;
            
            // Limpiar formulario
            document.getElementById('analyzer-form').reset();
            
            document.getElementById('analyzer-modal').classList.add('show');
        }

        function editAnalyzer(analyzerId) {
            editingAnalyzerId = analyzerId;
            document.getElementById('modal-title').innerHTML = `
                <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                </svg>
                Editar Analizador
            `;
            document.getElementById('save-analyzer-btn').innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Actualizar Analizador
            `;

            // Cargar datos del analizador (simulado)
            const analyzerData = {
                'general': {
                    name: 'Análisis General',
                    description: 'Realiza un análisis completo de la reunión identificando resumen, puntos clave y tareas automáticamente.',
                    prompt: 'Eres un asistente especializado en análisis de reuniones. Tu función es analizar transcripciones y generar un resumen ejecutivo, identificar puntos clave y extraer tareas específicas con asignaciones y fechas límite.',
                    icon: '📊'
                },
                'meeting': {
                    name: 'Análisis de Reunión',
                    description: 'Enfocado en decisiones, acuerdos y seguimientos específicos de reuniones corporativas.',
                    prompt: 'Eres un especialista en análisis de reuniones corporativas. Identifica decisiones tomadas, acuerdos establecidos y seguimientos necesarios.',
                    icon: '👥'
                },
                'project': {
                    name: 'Análisis de Proyecto',
                    description: 'Identifica objetivos, riesgos y próximos pasos específicos para la gestión de proyectos.',
                    prompt: 'Eres un experto en gestión de proyectos. Analiza la transcripción para identificar objetivos, riesgos potenciales y próximos pasos.',
                    icon: '📋'
                },
                'sales': {
                    name: 'Análisis de Ventas',
                    description: 'Detecta oportunidades, objeciones y próximos pasos comerciales en reuniones de ventas.',
                    prompt: 'Eres un especialista en análisis de ventas. Identifica oportunidades comerciales, objeciones del cliente y próximos pasos para cerrar la venta.',
                    icon: '💼'
                }
            };

            const data = analyzerData[analyzerId];
            if (data) {
                document.getElementById('analyzer-name').value = data.name;
                document.getElementById('analyzer-description').value = data.description;
                document.getElementById('analyzer-prompt').value = data.prompt;
                document.getElementById('analyzer-icon').value = data.icon;
            }

            document.getElementById('analyzer-modal').classList.add('show');
        }

        function deleteAnalyzer(analyzerId) {
            deletingAnalyzerId = analyzerId;
            document.getElementById('delete-analyzer-modal').classList.add('show');
        }

        function closeAnalyzerModal() {
            document.getElementById('analyzer-modal').classList.remove('show');
            editingAnalyzerId = null;
        }

        function closeDeleteModal() {
            document.getElementById('delete-analyzer-modal').classList.remove('show');
            deletingAnalyzerId = null;
        }

        function saveAnalyzer() {
            const name = document.getElementById('analyzer-name').value.trim();
            const description = document.getElementById('analyzer-description').value.trim();
            const prompt = document.getElementById('analyzer-prompt').value.trim();
            const icon = document.getElementById('analyzer-icon').value.trim();

            if (!name || !description || !prompt) {
                showNotification('Por favor completa todos los campos requeridos', 'error');
                return;
            }

            const btn = document.getElementById('save-analyzer-btn');
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Guardando...
            `;

            // Simular guardado
            setTimeout(() => {
                const action = editingAnalyzerId ? 'actualizado' : 'creado';
                showNotification(`Analizador ${action} exitosamente`, 'success');
                closeAnalyzerModal();
                
                btn.disabled = false;
                btn.innerHTML = `
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    ${editingAnalyzerId ? 'Actualizar' : 'Guardar'} Analizador
                `;
            }, 1500);
        }

        function confirmDeleteAnalyzer() {
            const btn = document.getElementById('confirm-delete-btn');
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Eliminando...
            `;

            // Simular eliminación
            setTimeout(() => {
                showNotification('Analizador eliminado exitosamente', 'success');
                closeDeleteModal();
                
                btn.disabled = false;
                btn.innerHTML = `
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Eliminar Analizador
                `;
            }, 1500);
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;

            const icons = {
                success: '✅',
                error: '❌',
                info: 'ℹ️',
                warning: '⚠️'
            };

            notification.innerHTML = `
                <div class="notification-content">
                    <span class="notification-icon">${icons[type]}</span>
                    <span class="notification-message">${message}</span>
                </div>
            `;

            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                background: rgba(15, 23, 42, 0.95);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-radius: 12px;
                padding: 1rem 1.5rem;
                z-index: 3000;
                animation: slideIn 0.3s ease;
                color: white;
                font-weight: 500;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeAnalyzerModal();
                closeDeleteModal();
            }
        });

        // Cerrar modales al hacer click fuera
        document.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) {
                closeAnalyzerModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>