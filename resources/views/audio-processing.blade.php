<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Procesando Audio - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/audio-processing.css','resources/css/index.css'])
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
            </div>
        </div>
    </div>

    <!-- Overlay para cerrar dropdown -->
    <div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

    <div class="app-container">
        <main class="main-content">
            <!-- Paso 1: Procesando Audio -->
            <div class="processing-step active" id="step-audio-processing">
                <div class="step-header">
                    <x-icon name="note" class="step-icon" />
                    <h1 class="step-title">Procesando Audio</h1>
                    <p class="step-subtitle">Analizando y optimizando la calidad del audio grabado</p>
                </div>

                <div class="processing-container">
                    <div class="audio-wave-container">
                        <div class="audio-wave">
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" id="audio-progress"></div>
                        </div>
                        <div class="progress-text">
                            <span id="audio-progress-text">Procesando audio...</span>
                            <span id="audio-progress-percent">0%</span>
                        </div>
                    </div>

                    <div class="processing-details">
                        <div class="detail-item">
                            <x-icon name="speaker" class="detail-icon" />
                            <span class="detail-text">Optimizando calidad de audio</span>
                            <span class="detail-status" id="audio-quality-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="chart" class="detail-icon" />
                            <span class="detail-text">Detectando hablantes</span>
                            <span class="detail-status" id="speaker-detection-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="speaker-x" class="detail-icon" />
                            <span class="detail-text">Reduciendo ruido de fondo</span>
                            <span class="detail-status" id="noise-reduction-status">⏳</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 2: Transcripción -->
            <div class="processing-step" id="step-transcription">
                <div class="step-header">
                    <x-icon name="pencil" class="step-icon" />
                    <h1 class="step-title">Generando Transcripción</h1>
                    <p class="step-subtitle">Convirtiendo el audio a texto con IA avanzada</p>
                </div>

                <div class="processing-container">
                    <div class="transcription-visual">
                        <div class="typing-animation">
                            <div class="typing-text" id="typing-text"></div>
                            <div class="typing-cursor">|</div>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" id="transcription-progress"></div>
                        </div>
                        <div class="progress-text">
                            <span id="transcription-progress-text">Transcribiendo audio...</span>
                            <span id="transcription-progress-percent">0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 3: Editar Transcripción -->
            <div class="processing-step" id="step-edit-transcription">
                <div class="step-header">
                    <x-icon name="pencil" class="step-icon" />
                    <h1 class="step-title">Revisar Transcripción</h1>
                    <p class="step-subtitle">Verifica y corrige la transcripción generada</p>
                </div>

                <div class="transcription-editor">
                    <audio id="recorded-audio" style="display: none;"></audio>
                    <div class="editor-controls">
                        <div class="audio-player">
                            <button class="play-btn" onclick="toggleAudioPlayback()">
                                <svg class="play-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path d="M5.25 5.25l13.5 6.75-13.5 6.75V5.25z" />
                                </svg>
                            </button>
                            <div class="audio-timeline" onclick="seekFullAudio(event)">
                                <div class="timeline-progress"></div>
                            </div>
                            <span class="audio-duration" id="full-audio-time">00:00 / 00:00</span>
                        </div>
                        <div class="speaker-count">
                            <span>Hablantes detectados: <strong id="speaker-count">3</strong></span>
                        </div>
                    </div>

                    <div class="transcription-segments" id="transcription-segments">
                        <!-- Los segmentos se generarán dinámicamente -->
                    </div>

                    <div class="editor-actions">
                        <button class="btn btn-secondary" onclick="goBackToRecording()">
                            ← Volver a grabar
                        </button>
                        <button class="btn btn-primary" onclick="saveTranscriptionAndContinue()">
                            Guardar cambios y continuar →
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paso 4: Seleccionar Análisis -->
            <div class="processing-step" id="step-select-analysis">
                <div class="step-header">
                    <x-icon name="shield" class="step-icon" />
                    <h1 class="step-title">Comenzar Análisis</h1>
                    <p class="step-subtitle">Selecciona el tipo de análisis que deseas realizar</p>
                </div>

                <div class="analysis-selector">
                    <div class="analysis-actions">
                        <button class="btn btn-secondary" onclick="goBackToTranscription()">
                            ← Volver a transcripción
                        </button>
                        <button id="start-analysis-button" class="btn btn-primary" onclick="startAnalysis()" disabled>
                            <x-icon name="rocket" class="btn-icon" />
                            <span class="sr-only">Comenzar análisis</span>
                            Comenzar análisis
                        </button>
                    </div>
                    <div class="analyzer-grid" id="analyzer-grid"></div>
                    <p id="no-analyzers-msg" style="display:none">No se encontraron analizadores</p>

                    <div id="analysis-error-message" class="error-message" style="display:none; color: #ef4444; margin-top: 1rem;"></div>
                </div>
            </div>

            <!-- Paso 5: Procesando Análisis -->
            <div class="processing-step" id="step-analysis-processing">
                <div class="step-header">
                    <x-icon name="shield" class="step-icon" />
                    <h1 class="step-title">Analizando Contenido</h1>
                    <p class="step-subtitle">La IA está procesando la transcripción para generar insights</p>
                </div>

                <div class="processing-container">
                    <div class="ai-brain-animation">
                        <div class="brain-container">
                            <div class="brain-core"></div>
                            <div class="brain-pulse pulse-1"></div>
                            <div class="brain-pulse pulse-2"></div>
                            <div class="brain-pulse pulse-3"></div>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" id="analysis-progress"></div>
                        </div>
                        <div class="progress-text">
                            <span id="analysis-progress-text">Analizando contenido...</span>
                            <span id="analysis-progress-percent">0%</span>
                        </div>
                    </div>

                    <div class="processing-details">
                        <div class="detail-item">
                            <x-icon name="clipboard" class="detail-icon" />
                            <span class="detail-text">Generando resumen ejecutivo</span>
                            <span class="detail-status" id="summary-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="chart" class="detail-icon" />
                            <span class="detail-text">Identificando puntos clave</span>
                            <span class="detail-status" id="keypoints-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="check" class="detail-icon" />
                            <span class="detail-text">Extrayendo tareas y acciones</span>
                            <span class="detail-status" id="tasks-status">⏳</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 6: Guardar Resultados -->
            <div class="processing-step" id="step-save-results">
                <div class="step-header">
                    <x-icon name="briefcase" class="step-icon" />
                    <h1 class="step-title">Guardar Reunión</h1>
                    <p class="step-subtitle">Configura dónde y cómo guardar los resultados</p>
                </div>

                <div class="save-container">
                    <!-- Configuración de guardado -->
                    <div class="save-config">
                        <div class="config-section">
                            <h3 class="config-title">
                                <x-icon name="folder" class="config-icon" />
                                Ubicación de guardado
                            </h3>

                            <div class="form-group">
                                <label class="form-label">Drive</label>
                                <select class="form-select" id="drive-select">
                                    <option value="personal">Personal</option>
                                    <option value="organization">Organization</option>
                                </select>
                            </div>

                            <!-- Selector de carpeta principal eliminado: ahora se determina automáticamente según el Drive seleccionado -->

                            <!-- Subcarpetas manuales eliminadas: ahora se usan carpetas fijas (Audios, Transcripciones, Audios Pospuestos, Documentos) -->

                            <div class="form-group">
                                <label class="form-label">Nombre de la reunión</label>
                                <input type="text" class="form-input" id="meeting-name" placeholder="Ej: Reunión de planificación Q1 2025" value="Reunión del {{ date('d/m/Y H:i') }}">
                            </div>

                            <div class="form-group" style="font-size:0.85rem; line-height:1.4; color:#94a3b8;">
                                <strong>Estructura automática:</strong> Los resultados se guardarán en carpetas fijas dentro de tu carpeta principal:
                                <em>Audios</em>, <em>Transcripciones</em>, <em>Audios Pospuestos</em> y <em>Documentos</em>. Ya no es necesario elegir subcarpetas manualmente.
                            </div>
                        </div>
                    </div>

                    <!-- Vista previa de resultados -->
                    <div class="results-preview">
                        <h3 class="preview-title">
                            <x-icon name="eye" class="preview-icon" />
                            Vista previa de resultados
                        </h3>

                        <!-- Información del audio -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <x-icon name="note" class="section-icon" />
                                Audio original
                            </h4>
                            <div class="audio-info">
                        <div id="analysis-audio" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;"></div>
                            </div>
                        </div>

                        <!-- Transcripción -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <x-icon name="pencil" class="section-icon" />
                                Transcripción
                                <button
                                    type="button"
                                    id="toggle-transcript-btn"
                                    class="transcript-toggle-btn"
                                    aria-expanded="false"
                                    title="Ver completa"
                                    aria-label="Ver completa"
                                >
                                    <!-- Chevron down icon (rota al expandir) -->
                                    <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                    <span class="sr-only">Ver completa</span>
                                </button>
                            </h4>
                        <div id="analysis-transcript" class="transcription-preview"></div>

                        <!-- Carpetas y subcarpetas -->
                        <div id="folders-section" class="folders-section" style="margin-top:2rem;"></div>
                        </div>

                        <!-- Análisis -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <x-icon name="shield" class="section-icon" />
                                Análisis generado
                            </h4>

                            <!-- Resumen -->
                            <div class="analysis-item">
                                <h5 class="analysis-subtitle">
                                    <x-icon name="clipboard" class="analysis-icon" />
                                    Resumen ejecutivo
                                </h5>
                                <div class="analysis-content">
                                    <p id="analysis-summary">...</p>
                                </div>
                            </div>

                            <!-- Puntos clave -->
                            <div class="analysis-item">
                                <h5 class="analysis-subtitle">
                                    <x-icon name="chart" class="analysis-icon" />
                                    Puntos clave
                                </h5>
                                <div class="analysis-content">
                                    <ul class="key-points-list" id="analysis-keypoints"></ul>
                                </div>
                            </div>

                            <!-- Tareas -->
                            <div class="analysis-item">
                                <h5 class="analysis-subtitle">
                                    <x-icon name="check" class="analysis-icon" />
                                    Tareas identificadas
                                </h5>
                                <div class="analysis-content">
                                    <div class="tasks-list" id="analysis-tasks"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones finales -->
                    <div class="final-actions">
                        <button class="btn btn-secondary" onclick="goBackToAnalysis()">
                            ← Volver al análisis
                        </button>
                        <button class="btn btn-danger" onclick="cancelProcess()">
                            <x-icon name="x" class="btn-icon" />
                            <span class="sr-only">Cancelar proceso</span>
                            Cancelar proceso
                        </button>
                        <button class="btn btn-primary" onclick="saveToDatabase()">
                            <x-icon name="briefcase" class="btn-icon" />
                            <span class="sr-only">Guardar reunión</span>
                            Guardar reunión
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paso 7: Guardando en BD -->
            <div class="processing-step" id="step-saving">
                <div class="step-header">
                    <x-icon name="briefcase" class="step-icon" />
                    <h1 class="step-title">Guardando Reunión</h1>
                    <p class="step-subtitle">Almacenando todos los datos en la base de datos</p>
                </div>

                <div class="processing-container">
                    <div class="save-animation">
                        <div class="database-icon">
                            <div class="db-cylinder"></div>
                            <div class="db-cylinder"></div>
                            <div class="db-cylinder"></div>
                            <div class="data-flow"></div>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" id="save-progress"></div>
                        </div>
                        <div class="progress-text">
                            <span id="save-progress-text">Guardando datos...</span>
                            <span id="save-progress-percent">0%</span>
                        </div>
                    </div>

                    <div class="processing-details">
                        <div class="detail-item">
                            <x-icon name="note" class="detail-icon" />
                            <span class="detail-text">Subiendo archivo de audio</span>
                            <span class="detail-status" id="audio-upload-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="pencil" class="detail-icon" />
                            <span class="detail-text">Guardando transcripción</span>
                            <span class="detail-status" id="transcription-save-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="shield" class="detail-icon" />
                            <span class="detail-text">Almacenando análisis</span>
                            <span class="detail-status" id="analysis-save-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <x-icon name="check" class="detail-icon" />
                            <span class="detail-text">Creando tareas</span>
                            <span class="detail-status" id="tasks-save-status">⏳</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 8: Completado -->
            <div class="processing-step" id="step-completed">
                <div class="step-header">
                    <x-icon name="check" class="step-icon" />
                    <h1 class="step-title">¡Reunión Guardada!</h1>
                    <p class="step-subtitle">Tu reunión ha sido procesada y guardada exitosamente</p>
                </div>

                <div class="completion-container">
                    <div class="success-animation">
                        <div class="checkmark-circle">
                            <div class="checkmark">✓</div>
                        </div>
                    </div>

                    <div id="completion-temp-warning" class="completion-temp-warning hidden">
                        <div class="warning-icon">⚠️</div>
                        <div class="warning-content">
                            <p class="warning-title">Guardado temporal</p>
                            <p class="warning-text">
                                Tu reunión se almacenó temporalmente por
                                <strong id="completion-temp-retention"></strong>.
                                El audio se eliminará en
                                <strong id="completion-temp-countdown"></strong>.
                                <span id="completion-temp-action"></span>
                            </p>
                            <!-- Botón de exportar a Drive -->
                            <div class="flex gap-2 mt-3">
                                <button id="completion-export-drive-btn" class="export-drive-btn hidden px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6.94 14.036c-.233.624-.43 1.2-.606 1.783.96-.697 2.101-1.139 3.418-1.304 2.513-.314 4.746-1.973 5.876-4.058l-1.456-1.455 1.413-1.415 1-1.001c.43-.43.915-1.224 1.428-2.368-5.593.867-9.018 4.292-10.073 9.818zM17 9v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h10a2 2 0 012 2z"/>
                                    </svg>
                                    Exportar a Drive
                                </button>
                                <button id="completion-upgrade-btn" class="upgrade-plan-btn hidden px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors duration-200 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    Actualizar Plan
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="completion-summary">
                        <div class="summary-item">
                            <x-icon name="folder" class="summary-icon" />
                            <span class="summary-text">Guardado en: <strong id="completion-drive-path"></strong></span>
                        </div>
                        <div class="summary-item">
                            <x-icon name="calendar" class="summary-icon" />
                            <span class="summary-text">Duración: <strong id="completion-audio-duration"></strong></span>
                        </div>
                        <div class="summary-item">
                            <x-icon name="users" class="summary-icon" />
                            <span class="summary-text">Hablantes: <strong id="completion-speaker-count"></strong></span>
                        </div>
                        <div class="summary-item">
                            <x-icon name="check" class="summary-icon" />
                            <span class="summary-text">Tareas creadas: <strong id="completion-task-count"></strong></span>
                        </div>
                    </div>

                    <div class="completion-actions">
                        <button class="btn btn-primary" onclick="goToMeetings()">
                            <x-icon name="clipboard" class="btn-icon" />
                            <span class="sr-only">Ir a mis reuniones</span>
                            Ir a mis reuniones
                        </button>
                        <button class="btn btn-primary" onclick="createNewMeeting()">
                            <x-icon name="plus" class="btn-icon" />
                            <span class="sr-only">Nueva reunión</span>
                            Nueva reunión
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modales -->

    <!-- Modal para cambiar hablante individual -->
    <div class="modal" id="change-speaker-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <x-icon name="user" class="modal-icon" />
                    Cambiar Hablante
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Ingresa el nuevo nombre para este hablante específico.
                </p>
                <div class="form-group">
                    <label class="form-label">Nombre del hablante</label>
                    <input type="text" class="modal-input" id="speaker-name-input" placeholder="Ej: María González">
                    <div class="input-hint">Este cambio solo afectará a este segmento de la transcripción.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeChangeSpeakerModal()">Cancelar</button>
                <button class="btn btn-primary" id="confirm-speaker-change" onclick="confirmSpeakerChange()">
                    <x-icon name="check" class="btn-icon" />
                    <span class="sr-only">Cambiar Hablante</span>
                    Cambiar Hablante
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para cambiar hablantes globalmente -->
    <div class="modal" id="change-global-speaker-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <x-icon name="users" class="modal-icon" />
                    Cambiar Hablante Globalmente
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Cambia el nombre de este hablante en toda la transcripción.
                </p>
                <div class="form-group">
                    <label class="form-label">Hablante actual</label>
                    <input type="text" class="modal-input" id="current-speaker-name" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Nuevo nombre</label>
                    <input type="text" class="modal-input" id="global-speaker-name-input" placeholder="Ej: María González">
                    <div class="input-hint">Este cambio afectará a todos los segmentos de este hablante.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeGlobalSpeakerModal()">Cancelar</button>
                <button class="btn btn-primary" id="confirm-global-speaker-change" onclick="confirmGlobalSpeakerChange()">
                    <x-icon name="check" class="btn-icon" />
                    <span class="sr-only">Cambiar Globalmente</span>
                    Cambiar Globalmente
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        window.userRole = @json($userRole);
        window.currentOrganizationId = @json($organizationId);
    </script>
    @vite(['resources/js/audio-processing.js'])

    <!-- Global vars and functions -->
    @include('partials.global-vars')

</body>
</html>

<?php
Route::post('/drive/save-results', [DriveController::class, 'saveResults']);
?>
