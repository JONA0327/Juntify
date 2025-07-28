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
                    <div class="step-icon">🎵</div>
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
                            <span class="detail-icon">🔊</span>
                            <span class="detail-text">Optimizando calidad de audio</span>
                            <span class="detail-status" id="audio-quality-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">🎯</span>
                            <span class="detail-text">Detectando hablantes</span>
                            <span class="detail-status" id="speaker-detection-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">🔇</span>
                            <span class="detail-text">Reduciendo ruido de fondo</span>
                            <span class="detail-status" id="noise-reduction-status">⏳</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 2: Transcripción -->
            <div class="processing-step" id="step-transcription">
                <div class="step-header">
                    <div class="step-icon">📝</div>
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
                    <div class="step-icon">✏️</div>
                    <h1 class="step-title">Revisar Transcripción</h1>
                    <p class="step-subtitle">Verifica y corrige la transcripción generada</p>
                </div>

                <div class="transcription-editor">
                    <div class="editor-controls">
                        <button class="btn btn-secondary" onclick="playFullAudio()">
                            <span class="btn-icon">▶️</span>
                            Reproducir audio completo
                        </button>
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
                    <div class="step-icon">🧠</div>
                    <h1 class="step-title">Comenzar Análisis</h1>
                    <p class="step-subtitle">Selecciona el tipo de análisis que deseas realizar</p>
                </div>

                <div class="analysis-selector">
                    <div class="analyzer-grid">
                        <div class="analyzer-card active" data-analyzer="general" onclick="selectAnalyzer('general')">
                            <div class="analyzer-icon">📊</div>
                            <h3 class="analyzer-title">Análisis General</h3>
                            <p class="analyzer-description">Resumen completo, puntos clave y tareas identificadas automáticamente</p>
                            <div class="analyzer-features">
                                <span class="feature-tag">Resumen</span>
                                <span class="feature-tag">Puntos clave</span>
                                <span class="feature-tag">Tareas</span>
                            </div>
                        </div>

                        <div class="analyzer-card" data-analyzer="meeting" onclick="selectAnalyzer('meeting')">
                            <div class="analyzer-icon">🤝</div>
                            <h3 class="analyzer-title">Análisis de Reunión</h3>
                            <p class="analyzer-description">Enfocado en decisiones, acuerdos y seguimientos de reuniones</p>
                            <div class="analyzer-features">
                                <span class="feature-tag">Decisiones</span>
                                <span class="feature-tag">Acuerdos</span>
                                <span class="feature-tag">Seguimientos</span>
                            </div>
                        </div>

                        <div class="analyzer-card" data-analyzer="project" onclick="selectAnalyzer('project')">
                            <div class="analyzer-icon">🎯</div>
                            <h3 class="analyzer-title">Análisis de Proyecto</h3>
                            <p class="analyzer-description">Identifica objetivos, riesgos y próximos pasos del proyecto</p>
                            <div class="analyzer-features">
                                <span class="feature-tag">Objetivos</span>
                                <span class="feature-tag">Riesgos</span>
                                <span class="feature-tag">Próximos pasos</span>
                            </div>
                        </div>

                        <div class="analyzer-card" data-analyzer="sales" onclick="selectAnalyzer('sales')">
                            <div class="analyzer-icon">💼</div>
                            <h3 class="analyzer-title">Análisis de Ventas</h3>
                            <p class="analyzer-description">Detecta oportunidades, objeciones y próximos pasos comerciales</p>
                            <div class="analyzer-features">
                                <span class="feature-tag">Oportunidades</span>
                                <span class="feature-tag">Objeciones</span>
                                <span class="feature-tag">Follow-ups</span>
                            </div>
                        </div>
                    </div>

                    <div class="analysis-actions">
                        <button class="btn btn-secondary" onclick="goBackToTranscription()">
                            ← Volver a transcripción
                        </button>
                        <button class="btn btn-primary" onclick="startAnalysis()">
                            <span class="btn-icon">🚀</span>
                            Comenzar análisis
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paso 5: Procesando Análisis -->
            <div class="processing-step" id="step-analysis-processing">
                <div class="step-header">
                    <div class="step-icon">🧠</div>
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
                            <span class="detail-icon">📋</span>
                            <span class="detail-text">Generando resumen ejecutivo</span>
                            <span class="detail-status" id="summary-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">🎯</span>
                            <span class="detail-text">Identificando puntos clave</span>
                            <span class="detail-status" id="keypoints-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">✅</span>
                            <span class="detail-text">Extrayendo tareas y acciones</span>
                            <span class="detail-status" id="tasks-status">⏳</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 6: Guardar Resultados -->
            <div class="processing-step" id="step-save-results">
                <div class="step-header">
                    <div class="step-icon">💾</div>
                    <h1 class="step-title">Guardar Reunión</h1>
                    <p class="step-subtitle">Configura dónde y cómo guardar los resultados</p>
                </div>

                <div class="save-container">
                    <!-- Configuración de guardado -->
                    <div class="save-config">
                        <div class="config-section">
                            <h3 class="config-title">
                                <span class="config-icon">📁</span>
                                Ubicación de guardado
                            </h3>
                            
                            <div class="form-group">
                                <label class="form-label">Carpeta principal</label>
                                <select class="form-select" id="root-folder-select">
                                    <option value="main-recordings">📁 Juntify-Reuniones-2025</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Subcarpeta (opcional)</label>
                                <select class="form-select" id="subfolder-select">
                                    <option value="">Sin subcarpeta</option>
                                    <option value="enero">📂 Reuniones-Enero-2025</option>
                                    <option value="febrero">📂 Reuniones-Febrero-2025</option>
                                    <option value="proyectos">📂 Reuniones-Proyectos</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nombre de la reunión</label>
                                <input type="text" class="form-input" id="meeting-name" placeholder="Ej: Reunión de planificación Q1 2025" value="Reunión del {{ date('d/m/Y H:i') }}">
                            </div>
                        </div>
                    </div>

                    <!-- Vista previa de resultados -->
                    <div class="results-preview">
                        <h3 class="preview-title">
                            <span class="preview-icon">👁️</span>
                            Vista previa de resultados
                        </h3>

                        <!-- Información del audio -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <span class="section-icon">🎵</span>
                                Audio original
                            </h4>
                            <div class="audio-info">
                                <div class="audio-player">
                                    <button class="play-btn" onclick="toggleAudioPlayback()">
                                        <span class="play-icon">▶️</span>
                                    </button>
                                    <div class="audio-timeline">
                                        <div class="timeline-progress"></div>
                                    </div>
                                    <span class="audio-duration">05:23</span>
                                </div>
                                <button class="btn btn-secondary download-btn" onclick="downloadAudio()">
                                    <span class="btn-icon">⬇️</span>
                                    Descargar audio
                                </button>
                            </div>
                        </div>

                        <!-- Transcripción -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <span class="section-icon">📝</span>
                                Transcripción
                            </h4>
                            <div class="transcription-preview">
                                <div class="transcript-segment">
                                    <div class="speaker-label">Hablante 1</div>
                                    <div class="transcript-text">Buenos días a todos, gracias por acompañarnos en esta reunión de planificación para el primer trimestre del 2025...</div>
                                </div>
                                <div class="transcript-segment">
                                    <div class="speaker-label">Hablante 2</div>
                                    <div class="transcript-text">Perfecto, me parece una excelente propuesta. Creo que deberíamos enfocarnos en los objetivos principales que discutimos la semana pasada...</div>
                                </div>
                                <div class="transcript-more">
                                    <span>+ 15 segmentos más...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Análisis -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <span class="section-icon">🧠</span>
                                Análisis generado
                            </h4>
                            
                            <!-- Resumen -->
                            <div class="analysis-item">
                                <h5 class="analysis-subtitle">
                                    <span class="analysis-icon">📋</span>
                                    Resumen ejecutivo
                                </h5>
                                <div class="analysis-content">
                                    <p>Esta reunión se centró en la planificación estratégica del primer trimestre de 2025, donde se discutieron los objetivos principales, la asignación de recursos y los hitos clave a alcanzar...</p>
                                </div>
                            </div>

                            <!-- Puntos clave -->
                            <div class="analysis-item">
                                <h5 class="analysis-subtitle">
                                    <span class="analysis-icon">🎯</span>
                                    Puntos clave
                                </h5>
                                <div class="analysis-content">
                                    <ul class="key-points-list">
                                        <li>Incremento del 25% en ventas para Q1 2025</li>
                                        <li>Lanzamiento de nueva línea de productos en marzo</li>
                                        <li>Contratación de 5 nuevos desarrolladores</li>
                                        <li>Implementación de nueva estrategia de marketing digital</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Tareas -->
                            <div class="analysis-item">
                                <h5 class="analysis-subtitle">
                                    <span class="analysis-icon">✅</span>
                                    Tareas identificadas
                                </h5>
                                <div class="analysis-content">
                                    <div class="tasks-list">
                                        <div class="task-item">
                                            <div class="task-info">
                                                <span class="task-title">Preparar propuesta de presupuesto Q1</span>
                                                <span class="task-assignee">Asignado a: María González</span>
                                            </div>
                                            <span class="task-deadline">📅 15/01/2025</span>
                                        </div>
                                        <div class="task-item">
                                            <div class="task-info">
                                                <span class="task-title">Revisar estrategia de marketing digital</span>
                                                <span class="task-assignee">Asignado a: Carlos Ruiz</span>
                                            </div>
                                            <span class="task-deadline">📅 20/01/2025</span>
                                        </div>
                                        <div class="task-item">
                                            <div class="task-info">
                                                <span class="task-title">Contactar proveedores para nueva línea</span>
                                                <span class="task-assignee">Asignado a: Ana López</span>
                                            </div>
                                            <span class="task-deadline">📅 25/01/2025</span>
                                        </div>
                                    </div>
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
                            ❌ Cancelar proceso
                        </button>
                        <button class="btn btn-primary" onclick="saveToDatabase()">
                            <span class="btn-icon">💾</span>
                            Guardar reunión
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paso 7: Guardando en BD -->
            <div class="processing-step" id="step-saving">
                <div class="step-header">
                    <div class="step-icon">💾</div>
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
                            <span class="detail-icon">🎵</span>
                            <span class="detail-text">Subiendo archivo de audio</span>
                            <span class="detail-status" id="audio-upload-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">📝</span>
                            <span class="detail-text">Guardando transcripción</span>
                            <span class="detail-status" id="transcription-save-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">🧠</span>
                            <span class="detail-text">Almacenando análisis</span>
                            <span class="detail-status" id="analysis-save-status">⏳</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">✅</span>
                            <span class="detail-text">Creando tareas</span>
                            <span class="detail-status" id="tasks-save-status">⏳</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 8: Completado -->
            <div class="processing-step" id="step-completed">
                <div class="step-header">
                    <div class="step-icon">🎉</div>
                    <h1 class="step-title">¡Reunión Guardada!</h1>
                    <p class="step-subtitle">Tu reunión ha sido procesada y guardada exitosamente</p>
                </div>

                <div class="completion-container">
                    <div class="success-animation">
                        <div class="checkmark-circle">
                            <div class="checkmark">✓</div>
                        </div>
                    </div>

                    <div class="completion-summary">
                        <div class="summary-item">
                            <span class="summary-icon">📁</span>
                            <span class="summary-text">Guardado en: <strong>Juntify-Reuniones-2025/Reuniones-Enero-2025</strong></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-icon">⏱️</span>
                            <span class="summary-text">Duración: <strong>5:23 minutos</strong></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-icon">👥</span>
                            <span class="summary-text">Hablantes: <strong>3 participantes</strong></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-icon">✅</span>
                            <span class="summary-text">Tareas creadas: <strong>3 tareas</strong></span>
                        </div>
                    </div>

                    <div class="completion-actions">
                        <button class="btn btn-secondary" onclick="viewMeetingDetails()">
                            👁️ Ver detalles completos
                        </button>
                        <button class="btn btn-primary" onclick="goToMeetings()">
                            📋 Ir a mis reuniones
                        </button>
                        <button class="btn btn-primary" onclick="createNewMeeting()">
                            ➕ Nueva reunión
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
                    <span class="modal-icon">👤</span>
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
                <button class="btn btn-primary" id="confirm-speaker-change" onclick="confirmSpeakerChange()">✅ Cambiar Hablante</button>
            </div>
        </div>
    </div>

    <!-- Modal para cambiar hablantes globalmente -->
    <div class="modal" id="change-global-speaker-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <span class="modal-icon">👥</span>
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
                <button class="btn btn-primary" id="confirm-global-speaker-change" onclick="confirmGlobalSpeakerChange()">✅ Cambiar Globalmente</button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    @vite(['resources/js/audio-processing.js'])
</body>
</html>