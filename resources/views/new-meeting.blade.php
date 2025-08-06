<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva Reunión - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/new-meeting.css','resources/css/index.css'])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal arriba de todo -->
    @include('partials.navbar')

    <!-- Barra de navegación móvil -->
    @include('partials.mobile-nav')

    <div class="app-container">
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">Crear una nueva reunión</h1>
                </div>
            </div>

            <!-- Status Alert - Drive conectado -->
            <div class="status-alert success">
                <x-icon name="check" class="alert-icon" />
                <div class="alert-content">
                    <span>Tu carpeta de Drive está conectada correctamente.</span>
                </div>
            </div>

            <!-- Análisis mensual -->
            <div class="analysis-banner">
                <div class="analysis-content">
                    <x-icon name="chart" class="analysis-icon" />
                    <div class="analysis-text">
                        <span class="analysis-title">Análisis mensuales</span>
                        <span class="analysis-subtitle">Has alcanzado el límite de análisis para este mes.</span>
                    </div>
                </div>
                <div class="analysis-info">
                    <span class="analysis-count">0/</span>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Modo de Grabación -->
                <div class="info-card">
                    <h2 class="card-title">Seleccionar modo de grabación</h2>

                    <div class="recording-modes">
                        <div class="mode-option active" data-mode="audio" onclick="selectRecordingMode('audio')">
                            <x-icon name="microphone" class="mode-icon" />
                            <div class="mode-content">
                                <h3 class="mode-title">Grabar audio</h3>
                                <p class="mode-description">Graba audio directamente desde tu dispositivo</p>
                            </div>
                        </div>

                        <div class="mode-option" data-mode="upload" onclick="selectRecordingMode('upload')">
                            <x-icon name="folder" class="mode-icon" />
                            <div class="mode-content">
                                <h3 class="mode-title">Subir audio</h3>
                                <p class="mode-description">Sube un archivo de audio existente</p>
                            </div>
                        </div>

                        <div class="mode-option" data-mode="meeting" onclick="selectRecordingMode('meeting')">
                            <x-icon name="computer" class="mode-icon" />
                            <div class="mode-content">
                                <h3 class="mode-title">Grabar reunión</h3>
                                <p class="mode-description">Graba reuniones desde plataformas externas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración de grabación -->
                <div class="info-card">
                    <h2 class="card-title">
                        <x-icon name="shield" class="card-icon" />
                        Configuración
                    </h2>

                    <div class="form-group">
                        <label class="form-label">Idioma de transcripción</label>
                        <select class="form-select" id="advanced-language">
                            <option value="es">Español</option>
                            <option value="en">English</option>
                            <option value="fr">Français</option>
                            <option value="de">Deutsch</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dispositivo de micrófono</label>
                        <select class="form-select" id="microphone-device">
                            <option value="" disabled selected>🔍 Selecciona un micrófono...</option>
                        </select>
                    </div>
                </div>

                <!-- Contenedor para las interfaces de grabación -->
                <div class="info-card recorder-card">
                    {{-- El título cambiará dinámicamente con JS --}}
                    <h2 class="card-title" id="recorder-title">
                        <x-icon name="microphone" class="card-icon" />
                        Grabador de audio
                    </h2>

                    <!-- Incluir parcial de Grabador de Audio -->
                    @include('partials.new-meeting._audio-recorder')

                    <!-- Incluir parcial de Subir Audio -->
                    @include('partials.new-meeting._audio-uploader')

                    <!-- Incluir parcial de Grabador de Reunión -->
                    @include('partials.new-meeting._meeting-recorder')
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    @vite(['resources/js/new-meeting.js'])
</body>
</html>
