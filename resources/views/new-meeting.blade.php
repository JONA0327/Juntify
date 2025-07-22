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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/new-meeting.css', 'resources/js/new-meeting.js','resources/css/index.css'])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Header con navbar -->
    @include('partials.navbar')

    <!-- Botón hamburguesa para navbar (móvil) -->
    <button class="mobile-navbar-btn" onclick="toggleMobileNavbar()" id="mobile-navbar-btn">
        <div class="hamburger-navbar">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>

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
                                <span class="checkbox-title">🔵 Detección automática de hablantes</span>
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
                            <div class="mode-icon">⬆️</div>
                            <div class="mode-content">
                                <h3 class="mode-title">Subir audio</h3>
                                <p class="mode-description">Sube un archivo de audio existente</p>
                            </div>
                        </div>
                        
                        <div class="mode-option" data-mode="meeting" onclick="selectRecordingMode('meeting')">
                            <div class="mode-icon">📹</div>
                            <div class="mode-content">
                                <h3 class="mode-title">Grabar reunión</h3>
                                <p class="mode-description">Graba reuniones desde plataformas externas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grabador de Audio -->
                <div class="info-card recorder-card">
                    <h2 class="card-title">Grabador de audio extendido</h2>
                    
                    <div class="recorder-interface">
                        <div class="recorder-visual">
                            <div class="microphone-icon" id="microphone-visual">
                                <div class="mic-circle">
                                    <span class="mic-symbol">🎤</span>
                                </div>
                                <!-- Espectro de audio en tiempo real -->
                                <div class="audio-spectrum" id="audio-spectrum">
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                    <div class="spectrum-bar"></div>
                                </div>
                            </div>
                            
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
                                <option value="default">Predeterminado - Micrófono (USB Audio Device) (4c4a:4155)</option>
                                <option value="builtin">Micrófono integrado</option>
                                <option value="external">Micrófono externo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sensibilidad del micrófono</label>
                            <div class="slider-container">
                                <input type="range" class="form-slider" id="mic-sensitivity" min="0" max="100" value="75">
                                <div class="slider-labels">
                                    <span>Baja</span>
                                    <span class="slider-value">75%</span>
                                    <span>Alta</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Calidad de grabación</label>
                            <select class="form-select" id="recording-quality">
                                <option value="medium">Media (128 kbps)</option>
                                <option value="high">Alta (256 kbps)</option>
                                <option value="low">Baja (64 kbps)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reducción de ruido</label>
                            <select class="form-select" id="noise-reduction">
                                <option value="auto">Automático</option>
                                <option value="high">Alto</option>
                                <option value="medium">Medio</option>
                                <option value="off">Desactivado</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Variables globales
        let isRecording = false;
        let isPaused = false;
        let recordingTimer = null;
        let startTime = null;
        let selectedMode = 'audio';
        let mediaRecorder = null;
        let audioContext = null;
        let analyser = null;
        let dataArray = null;

        // Función para seleccionar modo de grabación
        function selectRecordingMode(mode) {
            document.querySelectorAll('.mode-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
            selectedMode = mode;
        }

        // Función para alternar opciones avanzadas
        function toggleAdvancedOptions() {
            const content = document.getElementById('advanced-content');
            const icon = document.getElementById('expand-icon');
            
            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                icon.textContent = '▲';
            } else {
                content.classList.add('collapsed');
                icon.textContent = '▼';
            }
        }

        // Función para iniciar/detener grabación
        function toggleRecording() {
            if (!isRecording) {
                startRecording();
            } else {
                stopRecording();
            }
        }

        // Función para iniciar grabación
        async function startRecording() {
            try {
                // Solicitar acceso al micrófono
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                
                // Configurar Web Audio API para análisis de frecuencias
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                const source = audioContext.createMediaStreamSource(stream);
                source.connect(analyser);
                
                analyser.fftSize = 64;
                const bufferLength = analyser.frequencyBinCount;
                dataArray = new Uint8Array(bufferLength);
                
                // Configurar MediaRecorder
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.start();
                
                isRecording = true;
                startTime = Date.now();
                
                // Actualizar UI
                document.getElementById('start-recording').innerHTML = '⏹️ Detener grabación';
                document.getElementById('start-recording').classList.add('recording');
                document.getElementById('timer-label').textContent = 'Grabando...';
                document.getElementById('microphone-visual').classList.add('recording');
                
                // Iniciar timer y análisis de audio
                recordingTimer = setInterval(updateTimer, 1000);
                startAudioAnalysis();
                
            } catch (error) {
                console.error('Error al acceder al micrófono:', error);
                alert('No se pudo acceder al micrófono. Por favor, permite el acceso.');
            }
        }
        
        // Función para analizar audio en tiempo real
        function startAudioAnalysis() {
            if (!isRecording) return;
            
            analyser.getByteFrequencyData(dataArray);
            
            // Actualizar barras del espectro
            const spectrumBars = document.querySelectorAll('.spectrum-bar');
            const step = Math.floor(dataArray.length / spectrumBars.length);
            
            spectrumBars.forEach((bar, index) => {
                const value = dataArray[index * step];
                const height = (value / 255) * 100;
                bar.style.height = Math.max(height, 2) + '%';
                
                // Cambiar color según intensidad
                if (height > 70) {
                    bar.style.background = '#ef4444'; // Rojo para volumen alto
                } else if (height > 40) {
                    bar.style.background = '#f59e0b'; // Amarillo para volumen medio
                } else {
                    bar.style.background = '#3b82f6'; // Azul para volumen bajo
                }
            });
            
            requestAnimationFrame(startAudioAnalysis);
        }
        
        // Función original actualizada
        function startRecordingOriginal() {
            isRecording = true;
            startTime = Date.now();
            
            // Actualizar UI
            document.getElementById('start-recording').innerHTML = '⏹️ Detener grabación';
            document.getElementById('start-recording').classList.add('recording');
            document.getElementById('timer-label').textContent = 'Grabando...';
            document.getElementById('microphone-visual').classList.add('recording');
            document.getElementById('audio-spectrum').classList.add('active');
            
            // Iniciar timer
            recordingTimer = setInterval(updateTimer, 1000);
        }

        // Función para detener grabación
        function stopRecording() {
            isRecording = false;
            isPaused = false;
            
            // Detener MediaRecorder y AudioContext
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            if (audioContext) {
                audioContext.close();
            }
            
            // Limpiar timer
            if (recordingTimer) {
                clearInterval(recordingTimer);
                recordingTimer = null;
            }
            
            // Resetear UI
            document.getElementById('start-recording').innerHTML = '▶️ Iniciar grabación';
            document.getElementById('start-recording').classList.remove('recording');
            document.getElementById('timer-label').textContent = 'Listo para grabar';
            document.getElementById('timer-counter').textContent = '00:00';
            document.getElementById('microphone-visual').classList.remove('recording');
            document.getElementById('audio-spectrum').classList.remove('active');
            
            // Resetear barras del espectro
            const spectrumBars = document.querySelectorAll('.spectrum-bar');
            spectrumBars.forEach(bar => {
                bar.style.height = '2%';
                bar.style.background = '#3b82f6';
            });
            
            // Aquí iría la lógica para procesar la grabación
            alert('Grabación finalizada. Procesando transcripción...');
        }

        // Función para actualizar el timer
        function updateTimer() {
            if (isPaused) return;
            
            const elapsed = Date.now() - startTime;
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            document.getElementById('timer-counter').textContent = timeString;
        }

        // Actualizar valor del slider
        document.getElementById('mic-sensitivity').addEventListener('input', function() {
            document.querySelector('.slider-value').textContent = this.value + '%';
        });

        // Función para alternar navbar móvil
        function toggleMobileNavbar() {
            // Implementar lógica del navbar móvil
        }

        // Crear partículas animadas
        function createParticles() {
            const particles = document.getElementById('particles');
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                particles.appendChild(particle);
            }
        }

        // Inicializar partículas al cargar la página
        document.addEventListener('DOMContentLoaded', createParticles);
    </script>
</body>
</html>