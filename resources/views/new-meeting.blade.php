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

    <!-- Barra de navegación móvil exclusiva -->
    <div class="mobile-bottom-nav">
        <div class="nav-item">
            <div class="nav-icon">📅</div>
            <span class="nav-label">Reuniones</span>
        </div>
        <div class="nav-item active">
            <div class="nav-icon">➕</div>
            <span class="nav-label">Nueva</span>
        </div>
        <div class="nav-item nav-center">
            <div class="nav-icon-center">🎬</div>
        </div>
        <div class="nav-item">
            <div class="nav-icon">✅</div>
            <span class="nav-label">Tareas</span>
        </div>
        <div class="nav-item dropdown-trigger" onclick="toggleMobileDropdown()">
            <div class="nav-icon">⋯</div>
            <span class="nav-label">Más</span>
            <div class="mobile-dropdown" id="mobile-dropdown">
                <a href="{{ route('profile.show') }}" class="dropdown-item">
                    <span class="dropdown-icon">👤</span>
                    <span class="dropdown-text">Perfil</span>
                </a>
                <a href="#compartir" class="dropdown-item">
                    <span class="dropdown-icon">📤</span>
                    <span class="dropdown-text">Compartir</span>
                </a>
                <a href="#asistente" class="dropdown-item">
                    <span class="dropdown-icon">🤖</span>
                    <span class="dropdown-text">Asistente IA</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay para cerrar dropdown -->
    <div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

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
                <div class="alert-icon">✅</div>
                <div class="alert-content">
                    <span>Tu carpeta de Drive está conectada correctamente.</span>
                </div>
            </div>

            <!-- Análisis mensual -->
            <div class="analysis-banner">
                <div class="analysis-content">
                    <span class="analysis-icon">📊</span>
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
                <!-- Configuración de Transcripción -->
                <div class="info-card">
                    <h2 class="card-title">Configuración de transcripción</h2>
                    
                    <div class="form-group">
                        <label class="form-label">Idioma de la grabación</label>
                        <select class="form-select" id="transcription-language">
                            <option value="es">Español</option>
                            <option value="en">English</option>
                            <option value="fr">Français</option>
                            <option value="de">Deutsch</option>
                            <option value="it">Italiano</option>
                            <option value="pt">Português</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="speaker-detection" class="form-checkbox" checked>
                            <label for="speaker-detection" class="checkbox-label">
                                <span class="checkbox-title">🎯 Detección automática de hablantes</span>
                                <span class="checkbox-description">El sistema detectará automáticamente los diferentes hablantes en la conversación. Para mejores resultados, asegúrate de que el audio sea claro y que haya diferencias notables entre las voces.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Modo de Grabación -->
                <div class="info-card">
                    <h2 class="card-title">Seleccionar modo de grabación</h2>
                    
                    <div class="recording-modes">
                        <div class="mode-option active" data-mode="audio" onclick="selectRecordingMode('audio')">
                            <div class="mode-icon">🎤</div>
                            <div class="mode-content">
                                <h3 class="mode-title">Grabar audio</h3>
                                <p class="mode-description">Graba audio directamente desde tu dispositivo</p>
                            </div>
                        </div>
                        
                        <div class="mode-option" data-mode="upload" onclick="selectRecordingMode('upload')">
                            <div class="mode-icon">📁</div>
                            <div class="mode-content">
                                <h3 class="mode-title">Subir audio</h3>
                                <p class="mode-description">Sube un archivo de audio existente</p>
                            </div>
                        </div>
                        
                        <div class="mode-option" data-mode="meeting" onclick="selectRecordingMode('meeting')">
                            <div class="mode-icon">💻</div>
                            <div class="mode-content">
                                <h3 class="mode-title">Grabar reunión</h3>
                                <p class="mode-description">Graba reuniones desde plataformas externas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grabador de Audio -->
                <div class="info-card recorder-card">
                    <h2 class="card-title" id="recorder-title">🎙️ Grabador de audio</h2>
                    
                    <!-- Interfaz de Grabación -->
                    <div class="recorder-interface" id="audio-recorder">
                        <div class="recorder-visual">
                            <!-- Micrófono central -->
                            <div class="microphone-container">
                                <div class="volume-rings" id="volume-rings">
                                    <div class="volume-ring ring-1"></div>
                                    <div class="volume-ring ring-2"></div>
                                    <div class="volume-ring ring-3"></div>
                                </div>
                                <div class="mic-circle" id="mic-circle">
                                    <span class="mic-symbol">🎤</span>
                                </div>
                            </div>
                            
                            <!-- Visualizador de audio -->
                            <div class="audio-visualizer" id="audio-visualizer">
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                                <div class="audio-bar"></div>
                            </div>
                            
                            <!-- Timer -->
                            <div class="timer-display">
                                <span class="time-counter" id="timer-counter">00:00</span>
                                <span class="timer-label" id="timer-label">Listo para grabar</span>
                            </div>
                        </div>
                        
                        <div class="recorder-controls">
                            <button class="btn btn-primary recorder-btn" id="start-recording" onclick="toggleRecording()">
                                ▶️ Iniciar grabación
                            </button>
                        </div>
                    </div>
                    
                    <!-- Interfaz de Subir Audio -->
                    <div class="upload-interface" id="audio-uploader" style="display: none;">
                        <div class="upload-area" id="upload-area">
                            <div class="upload-icon">📁</div>
                            <h3 class="upload-title">Arrastra y suelta tu archivo de audio aquí</h3>
                            <p class="upload-subtitle">O haz clic para seleccionar un archivo de audio</p>
                            <p class="upload-formats">Formatos soportados: MP3, WAV, M4A, FLAC, OGG, AAC</p>
                            <input type="file" id="audio-file-input" accept=".mp3,.wav,.m4a,.flac,.ogg,.aac,audio/*" style="display: none;">
                            <button class="btn btn-primary upload-btn" onclick="document.getElementById('audio-file-input').click()">
                                📁 Seleccionar archivo
                            </button>
                        </div>
                        
                        <!-- Archivo seleccionado -->
                        <div class="selected-file" id="selected-file" style="display: none;">
                            <div class="file-info">
                                <div class="file-icon">🎵</div>
                                <div class="file-details">
                                    <div class="file-name" id="file-name"></div>
                                    <div class="file-size" id="file-size"></div>
                                </div>
                                <button class="remove-file-btn" onclick="removeSelectedFile()">❌</button>
                            </div>
                            <div class="upload-progress" id="upload-progress" style="display: none;">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill"></div>
                                </div>
                                <div class="progress-text" id="progress-text">0%</div>
                            </div>
                            <button class="btn btn-primary process-btn" onclick="processAudioFile()">
                                🚀 Procesar archivo
                            </button>
                        </div>
                    </div>
                    
                    <!-- Interfaz de Reunión -->
                    <div class="meeting-interface" id="meeting-recorder" style="display: none;">
                        <div class="meeting-recorder-container">
                            <div class="meeting-header">
                                <h3 class="meeting-title">Grabador de audio de reuniones</h3>
                                <p class="meeting-subtitle">Captura el audio de una reunión o llamada, combinando el audio del sistema y tu micrófono</p>
                            </div>
                            
                            <!-- Controles de fuentes de audio -->
                            <div class="audio-sources-controls">
                                <button class="audio-source-btn system-audio" id="system-audio-btn" onclick="toggleSystemAudio()">
                                    <span class="source-icon">🖥️</span>
                                    <span class="source-text">Sistema activado</span>
                                </button>
                                <button class="audio-source-btn microphone-audio active" id="microphone-audio-btn" onclick="toggleMicrophoneAudio()">
                                    <span class="source-icon">🎤</span>
                                    <span class="source-text">Micrófono activado</span>
                                </button>
                            </div>
                            
                            <!-- Visualizadores de audio -->
                            <div class="meeting-audio-visualizers">
                                <!-- Audio del sistema -->
                                <div class="audio-source-container">
                                    <div class="source-header">
                                        <span class="source-icon">🖥️</span>
                                        <span class="source-label">Audio del sistema</span>
                                        <button class="mute-btn" id="system-mute-btn" onclick="muteSystemAudio()">
                                            <span class="mute-icon">🔊</span>
                                        </button>
                                    </div>
                                    <div class="audio-visualizer-container">
                                        <div class="meeting-audio-visualizer" id="system-audio-visualizer">
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Audio del micrófono -->
                                <div class="audio-source-container">
                                    <div class="source-header">
                                        <span class="source-icon">🎤</span>
                                        <span class="source-label">Audio del micrófono</span>
                                        <button class="mute-btn" id="microphone-mute-btn" onclick="muteMicrophoneAudio()">
                                            <span class="mute-icon">🔊</span>
                                        </button>
                                    </div>
                                    <div class="audio-visualizer-container">
                                        <div class="meeting-audio-visualizer" id="microphone-audio-visualizer">
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                            <div class="meeting-audio-bar"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Timer y controles -->
                            <div class="meeting-timer-section">
                                <div class="meeting-timer-display">
                                    <span class="meeting-time-counter" id="meeting-timer-counter">00:00</span>
                                    <span class="meeting-timer-label" id="meeting-timer-label">Listo para grabar</span>
                                </div>
                                
                                <div class="meeting-controls">
                                    <button class="btn btn-primary meeting-record-btn" id="meeting-record-btn" onclick="toggleMeetingRecording()">
                                        <span class="btn-icon">🎬</span>
                                        <span class="btn-text">Seleccionar fuente de audio</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Opciones Avanzadas -->
                <div class="info-card advanced-options">
                    <h2 class="card-title">
                        <span class="card-icon">⚙️</span>
                        Opciones avanzadas
                        <button class="expand-btn" onclick="toggleAdvancedOptions()">
                            <span class="expand-icon" id="expand-icon">▲</span>
                        </button>
                    </h2>
                    
                    <div class="advanced-content" id="advanced-content">
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
                                <option value="default">🎤 Predeterminado - Micrófono (USB Audio Device)</option>
                                <option value="builtin">🔊 Micrófono integrado</option>
                                <option value="external">🎧 Micrófono externo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sensibilidad del micrófono</label>
                            <div class="slider-container">
                                <input type="range" class="form-slider" id="mic-sensitivity" min="0" max="100" value="75">
                                <div class="slider-labels">
                                    <span>🔇 Baja</span>
                                    <span class="slider-value">75%</span>
                                    <span>🔊 Alta</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Calidad de grabación</label>
                            <select class="form-select" id="recording-quality">
                                <option value="medium">📻 Media (128 kbps)</option>
                                <option value="high">🎵 Alta (256 kbps)</option>
                                <option value="low">📢 Baja (64 kbps)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reducción de ruido</label>
                            <select class="form-select" id="noise-reduction">
                                <option value="auto">🤖 Automático</option>
                                <option value="high">🛡️ Alto</option>
                                <option value="medium">⚖️ Medio</option>
                                <option value="off">❌ Desactivado</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    @vite(['resources/js/new-meeting.js'])
</body>
</html>