<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Memory Cleanup - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/index.css', 'resources/css/tests/test-memory-cleanup.css'])
</head>
<body>
    <div class="container-fluid">
        <!-- Navbar similar a las otras páginas -->
        <nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px);">
            <div class="container">
                <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                    <span style="color: #667eea;">Juntify</span>
                </a>
                <div class="navbar-nav ms-auto">
                    @auth
                        <a class="nav-link" href="{{ route('reuniones.index') }}">Reuniones</a>
                        <a class="nav-link" href="{{ route('profile.show') }}">Perfil</a>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="nav-link btn btn-link">Cerrar Sesión</button>
                        </form>
                    @else
                        <a class="nav-link" href="{{ route('login') }}">Iniciar Sesión</a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- Contenido principal -->
        <div class="test-container">
            <div class="text-center mb-4">
                <h1 class="display-4 fw-bold mb-3" style="color: #667eea;">
                    Test Audio Memory Cleanup
                </h1>
                <p class="lead text-muted">
                    Herramienta para probar la limpieza de memoria de audio
                </p>
            </div>

            <div class="row justify-content-center mb-4">
                <div class="col-md-4 text-center">
                    <button class="btn btn-primary btn-test w-100" onclick="simulateAudioStorage()">
                        🎵 Simular Almacenamiento
                    </button>
                </div>
                <div class="col-md-4 text-center">
                    <button class="btn btn-warning btn-test w-100" onclick="testCleanup()">
                        🧹 Probar Limpieza
                    </button>
                </div>
                <div class="col-md-4 text-center">
                    <button class="btn btn-info btn-test w-100" onclick="checkMemory()">
                        📊 Verificar Memoria
                    </button>
                </div>
            </div>

            <div id="status" class="status-card" style="display: none;"></div>
        </div>
    </div>

    @vite('resources/js/tests/test-memory-cleanup.js')

    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
