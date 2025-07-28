// ===== VARIABLES GLOBALES =====
let currentStep = 1;
let selectedAnalyzer = 'general';
let audioData = null;
let transcriptionData = [];
let analysisResults = null;

// ===== FUNCIONES PRINCIPALES =====

// Función para crear partículas animadas
function createParticles() {
    const particles = document.getElementById('particles');
    const particleCount = window.innerWidth < 768 ? 30 : 50;

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 20 + 's';
        particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
        particles.appendChild(particle);
    }
}

// Función para mostrar un paso específico
function showStep(stepNumber) {
    // Ocultar todos los pasos
    document.querySelectorAll('.processing-step').forEach(step => {
        step.classList.remove('active');
    });

    // Mostrar el paso actual
    const targetStep = document.getElementById(`step-${getStepId(stepNumber)}`);
    if (targetStep) {
        targetStep.classList.add('active');
        currentStep = stepNumber;
    }
}

// Función para obtener el ID del paso
function getStepId(stepNumber) {
    const stepIds = [
        'audio-processing',
        'transcription',
        'edit-transcription',
        'select-analysis',
        'analysis-processing',
        'save-results',
        'saving',
        'completed'
    ];
    return stepIds[stepNumber - 1];
}

// ===== PASO 1: PROCESAMIENTO DE AUDIO =====

function startAudioProcessing() {
    showStep(1);

    const progressBar = document.getElementById('audio-progress');
    const progressText = document.getElementById('audio-progress-text');
    const progressPercent = document.getElementById('audio-progress-percent');

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 8 + 2;
        if (progress > 100) progress = 100;

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';

        // Actualizar estados de procesamiento
        if (progress > 30) {
            document.getElementById('audio-quality-status').textContent = '✅';
            progressText.textContent = 'Detectando hablantes...';
        }
        if (progress > 60) {
            document.getElementById('speaker-detection-status').textContent = '✅';
            progressText.textContent = 'Reduciendo ruido de fondo...';
        }
        if (progress > 90) {
            document.getElementById('noise-reduction-status').textContent = '✅';
            progressText.textContent = 'Finalizando procesamiento...';
        }

        if (progress >= 100) {
            clearInterval(interval);
            setTimeout(() => {
                startTranscription();
            }, 1000);
        }
    }, 150);
}

// ===== PASO 2: TRANSCRIPCIÓN =====

function startTranscription() {
    showStep(2);

    const progressBar = document.getElementById('transcription-progress');
    const progressText = document.getElementById('transcription-progress-text');
    const progressPercent = document.getElementById('transcription-progress-percent');
    const typingText = document.getElementById('typing-text');

    const sampleTexts = [
        "Buenos días a todos, gracias por acompañarnos...",
        "Perfecto, me parece una excelente propuesta...",
        "Creo que deberíamos enfocarnos en los objetivos...",
        "¿Podríamos revisar el presupuesto para este proyecto?",
        "Excelente punto, María. Tomemos nota de eso...",
        "Para el próximo trimestre necesitamos..."
    ];

    let progress = 0;
    let textIndex = 0;
    let charIndex = 0;

    const interval = setInterval(() => {
        progress += Math.random() * 5 + 1;
        if (progress > 100) progress = 100;

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';

        // Simular escritura de texto
        if (textIndex < sampleTexts.length) {
            const currentText = sampleTexts[textIndex];
            if (charIndex < currentText.length) {
                typingText.textContent = currentText.substring(0, charIndex + 1);
                charIndex += Math.random() > 0.7 ? 2 : 1;
            } else {
                textIndex++;
                charIndex = 0;
                if (textIndex < sampleTexts.length) {
                    typingText.textContent = '';
                }
            }
        }

        if (progress >= 100) {
            clearInterval(interval);
            progressText.textContent = 'Transcripción completada';
            setTimeout(() => {
                showTranscriptionEditor();
            }, 1000);
        }
    }, 200);
}

// ===== PASO 3: EDITOR DE TRANSCRIPCIÓN =====

function showTranscriptionEditor() {
    showStep(3);
    generateTranscriptionSegments();
}

function generateTranscriptionSegments() {
    const container = document.getElementById('transcription-segments');
    const segments = [
        {
            speaker: 'Hablante 1',
            time: '00:00 - 00:45',
            text: 'Buenos días a todos, gracias por acompañarnos en esta reunión de planificación para el primer trimestre del 2025. Hoy vamos a revisar los objetivos principales que queremos alcanzar y cómo vamos a distribuir los recursos disponibles.',
            avatar: 'H1'
        },
        {
            speaker: 'Hablante 2',
            time: '00:45 - 01:30',
            text: 'Perfecto, me parece una excelente propuesta. Creo que deberíamos enfocarnos en los objetivos principales que discutimos la semana pasada, especialmente en el incremento de ventas y el lanzamiento de la nueva línea de productos.',
            avatar: 'H2'
        },
        {
            speaker: 'Hablante 3',
            time: '01:30 - 02:15',
            text: '¿Podríamos revisar el presupuesto para este proyecto? Me gustaría entender mejor cómo vamos a financiar la contratación de los nuevos desarrolladores y la campaña de marketing digital que mencionaste.',
            avatar: 'H3'
        },
        {
            speaker: 'Hablante 1',
            time: '02:15 - 03:00',
            text: 'Excelente punto, María. Tomemos nota de eso para incluirlo en el documento final. Para el próximo trimestre necesitamos tener claridad total sobre el presupuesto y los recursos humanos disponibles.',
            avatar: 'H1'
        }
    ];

    container.innerHTML = segments.map((segment, index) => `
        <div class="transcript-segment" data-segment="${index}">
            <div class="segment-header">
                <div class="speaker-info">
                    <div class="speaker-avatar">${segment.avatar}</div>
                    <div class="speaker-details">
                        <div class="speaker-name">${segment.speaker}</div>
                        <div class="speaker-time">${segment.time}</div>
                    </div>
                </div>
                <div class="segment-controls">
                    <button class="control-btn" onclick="playSegmentAudio(${index})" title="Reproducir fragmento">
                        ▶️
                    </button>
                    <button class="control-btn" onclick="openChangeSpeakerModal(${index})" title="Editar hablante">
                        ✏️
                    </button>
                    <button class="control-btn" onclick="openGlobalSpeakerModal(${index})" title="Cambiar hablante globalmente">
                        👥
                    </button>
                </div>
            </div>

            <div class="segment-audio">
                <div class="audio-player-mini">
                    <button class="play-btn-mini" onclick="playSegmentAudio(${index})">
                        <span class="play-icon">▶️</span>
                    </button>
                    <div class="audio-timeline-mini" onclick="seekAudio(${index}, event)">
                        <div class="timeline-progress-mini" style="width: 0%"></div>
                    </div>
                    <span class="audio-duration-mini">${segment.time.split(' - ')[1]}</span>
                </div>
            </div>

            <div class="segment-content">
                <textarea class="transcript-text" placeholder="Texto de la transcripción...">${segment.text}</textarea>
            </div>
        </div>
    `).join('');

    // Guardar datos de transcripción
    transcriptionData = segments;
}

function playSegmentAudio(segmentIndex) {
    console.log('Reproduciendo audio del segmento:', segmentIndex);
    // Aquí iría la lógica para reproducir el fragmento de audio específico
    showNotification('Reproduciendo fragmento de audio', 'info');
}

let selectedSegmentIndex = null;

function openChangeSpeakerModal(segmentIndex) {
    selectedSegmentIndex = segmentIndex;
    const currentName = transcriptionData[segmentIndex].speaker;
    document.getElementById('speaker-name-input').value = currentName;
    document.getElementById('change-speaker-modal').classList.add('show');
}

function closeChangeSpeakerModal() {
    document.getElementById('change-speaker-modal').classList.remove('show');
}

function confirmSpeakerChange() {
    const input = document.getElementById('speaker-name-input');
    const newName = input.value.trim();
    if (!newName) {
        showNotification('Debes ingresar un nombre válido', 'warning');
        return;
    }

    transcriptionData[selectedSegmentIndex].speaker = newName;
    const element = document.querySelector(`[data-segment="${selectedSegmentIndex}"] .speaker-name`);
    if (element) {
        element.textContent = newName;
    }

    closeChangeSpeakerModal();
    showNotification('Hablante actualizado correctamente', 'success');
}

function openGlobalSpeakerModal(segmentIndex) {
    selectedSegmentIndex = segmentIndex;
    const currentName = transcriptionData[segmentIndex].speaker;
    document.getElementById('current-speaker-name').value = currentName;
    document.getElementById('global-speaker-name-input').value = '';
    document.getElementById('change-global-speaker-modal').classList.add('show');
}

function closeGlobalSpeakerModal() {
    document.getElementById('change-global-speaker-modal').classList.remove('show');
}

function confirmGlobalSpeakerChange() {
    const newName = document.getElementById('global-speaker-name-input').value.trim();
    const currentName = document.getElementById('current-speaker-name').value;
    if (!newName) {
        showNotification('Debes ingresar un nombre válido', 'warning');
        return;
    }

    transcriptionData.forEach((segment, idx) => {
        if (segment.speaker === currentName) {
            segment.speaker = newName;
            const el = document.querySelector(`[data-segment="${idx}"] .speaker-name`);
            if (el) {
                el.textContent = newName;
            }
        }
    });

    closeGlobalSpeakerModal();
    showNotification('Hablantes actualizados correctamente', 'success');
}

function seekAudio(segmentIndex, event) {
    const timeline = event.currentTarget;
    const rect = timeline.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const percentage = (clickX / rect.width) * 100;

    const progress = timeline.querySelector('.timeline-progress-mini');
    progress.style.width = percentage + '%';

    console.log(`Buscando en el audio del segmento ${segmentIndex} al ${percentage.toFixed(1)}%`);
}

function playFullAudio() {
    console.log('Reproduciendo audio completo');
    showNotification('Reproduciendo audio completo de la reunión', 'info');
}

function saveTranscriptionAndContinue() {
    const segments = document.querySelectorAll('.transcript-segment');

    segments.forEach((segment, index) => {
        const nameEl = segment.querySelector('.speaker-name');
        const textEl = segment.querySelector('.transcript-text');

        if (!transcriptionData[index]) {
            console.warn(`Segmento faltante en transcriptionData[${index}]`);
            return;
        }

        const speakerName = nameEl ? nameEl.textContent : transcriptionData[index].speaker;
        const transcriptText = textEl ? textEl.value : transcriptionData[index].text;

        transcriptionData[index].speaker = speakerName;
        transcriptionData[index].text = transcriptText;
    });

    showNotification('Transcripción guardada correctamente', 'success');
    setTimeout(() => {
        showAnalysisSelector();
    }, 1000);
}


// ===== PASO 4: SELECTOR DE ANÁLISIS =====

function showAnalysisSelector() {
    showStep(4);
}

function selectAnalyzer(analyzerType) {
    // Remover selección anterior
    document.querySelectorAll('.analyzer-card').forEach(card => {
        card.classList.remove('active');
    });

    // Seleccionar nuevo analizador
    document.querySelector(`[data-analyzer="${analyzerType}"]`).classList.add('active');
    selectedAnalyzer = analyzerType;

    console.log('Analizador seleccionado:', analyzerType);
}

function startAnalysis() {
    if (!selectedAnalyzer) {
        showNotification('Por favor selecciona un tipo de análisis', 'error');
        return;
    }

    showNotification(`Iniciando análisis: ${selectedAnalyzer}`, 'info');
    setTimeout(() => {
        processAnalysis();
    }, 1000);
}

// ===== PASO 5: PROCESAMIENTO DE ANÁLISIS =====

function processAnalysis() {
    showStep(5);

    const progressBar = document.getElementById('analysis-progress');
    const progressText = document.getElementById('analysis-progress-text');
    const progressPercent = document.getElementById('analysis-progress-percent');

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 6 + 2;
        if (progress > 100) progress = 100;

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';

        // Actualizar estados de análisis
        if (progress > 25) {
            document.getElementById('summary-status').textContent = '✅';
            progressText.textContent = 'Identificando puntos clave...';
        }
        if (progress > 60) {
            document.getElementById('keypoints-status').textContent = '✅';
            progressText.textContent = 'Extrayendo tareas y acciones...';
        }
        if (progress > 90) {
            document.getElementById('tasks-status').textContent = '✅';
            progressText.textContent = 'Finalizando análisis...';
        }

        if (progress >= 100) {
            clearInterval(interval);
            progressText.textContent = 'Análisis completado';
            setTimeout(() => {
                showSaveResults();
            }, 1000);
        }
    }, 180);
}

// ===== PASO 6: GUARDAR RESULTADOS =====

function showSaveResults() {
    showStep(6);
    loadDriveFolders();
}

function loadDriveFolders() {
    // Simular carga de carpetas desde Drive
    console.log('Cargando carpetas de Drive...');
    // Aquí iría la llamada AJAX para obtener las carpetas reales
}

function toggleAudioPlayback() {
    const playBtn = document.querySelector('.play-btn');
    const playIcon = playBtn.querySelector('.play-icon');

    if (playIcon.textContent === '▶️') {
        playIcon.textContent = '⏸️';
        console.log('Reproduciendo audio');
        // Simular progreso de reproducción
        simulateAudioProgress();
    } else {
        playIcon.textContent = '▶️';
        console.log('Pausando audio');
    }
}

function simulateAudioProgress() {
    const timeline = document.querySelector('.timeline-progress');
    let progress = 0;

    const interval = setInterval(() => {
        progress += 1;
        timeline.style.width = progress + '%';

        if (progress >= 100) {
            clearInterval(interval);
            document.querySelector('.play-icon').textContent = '▶️';
        }
    }, 100);
}

function downloadAudio() {
    showNotification('Descargando archivo de audio...', 'info');
    // Aquí iría la lógica para descargar el audio
}

function saveToDatabase() {
    const meetingName = document.getElementById('meeting-name').value.trim();
    const rootFolder = document.getElementById('root-folder-select').value;
    const subfolder = document.getElementById('subfolder-select').value;

    if (!meetingName) {
        showNotification('Por favor ingresa un nombre para la reunión', 'error');
        return;
    }

    showNotification('Iniciando guardado en base de datos...', 'info');
    setTimeout(() => {
        processDatabaseSave();
    }, 1000);
}

// ===== PASO 7: GUARDANDO EN BD =====

function processDatabaseSave() {
    showStep(7);

    const progressBar = document.getElementById('save-progress');
    const progressText = document.getElementById('save-progress-text');
    const progressPercent = document.getElementById('save-progress-percent');

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 8 + 3;
        if (progress > 100) progress = 100;

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';

        // Actualizar estados de guardado
        if (progress > 20) {
            document.getElementById('audio-upload-status').textContent = '✅';
            progressText.textContent = 'Guardando transcripción...';
        }
        if (progress > 50) {
            document.getElementById('transcription-save-status').textContent = '✅';
            progressText.textContent = 'Almacenando análisis...';
        }
        if (progress > 80) {
            document.getElementById('analysis-save-status').textContent = '✅';
            progressText.textContent = 'Creando tareas...';
        }
        if (progress > 95) {
            document.getElementById('tasks-save-status').textContent = '✅';
            progressText.textContent = 'Finalizando guardado...';
        }

        if (progress >= 100) {
            clearInterval(interval);
            progressText.textContent = 'Guardado completado';
            setTimeout(() => {
                showCompletion();
            }, 1000);
        }
    }, 200);
}

// ===== PASO 8: COMPLETADO =====

function showCompletion() {
    showStep(8);
}

// ===== FUNCIONES DE NAVEGACIÓN =====

function goBackToRecording() {
    if (confirm('¿Estás seguro de que quieres volver a grabar? Se perderá el progreso actual.')) {
        window.location.href = '/new-meeting';
    }
}

function goBackToTranscription() {
    showStep(3);
}

function goBackToAnalysis() {
    showStep(4);
}

function cancelProcess() {
    if (confirm('¿Estás seguro de que quieres cancelar? Se perderá todo el progreso.')) {
        window.location.href = '/new-meeting';
    }
}

function viewMeetingDetails() {
    showNotification('Redirigiendo a detalles de la reunión...', 'info');
    // Aquí iría la redirección a la vista de detalles
}

function goToMeetings() {
    showNotification('Redirigiendo a lista de reuniones...', 'info');
    // Aquí iría la redirección a la lista de reuniones
}

function createNewMeeting() {
    window.location.href = '/new-meeting';
}

// ===== FUNCIONES AUXILIARES =====

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

// Funciones para navbar móvil
function toggleMobileDropdown() {
    const dropdown = document.getElementById('mobile-dropdown');
    const overlay = document.getElementById('mobile-dropdown-overlay');

    dropdown.classList.toggle('show');
    overlay.classList.toggle('show');
}

function closeMobileDropdown() {
    const dropdown = document.getElementById('mobile-dropdown');
    const overlay = document.getElementById('mobile-dropdown-overlay');

    dropdown.classList.remove('show');
    overlay.classList.remove('show');
}

// ===== INICIALIZACIÓN =====

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeChangeSpeakerModal();
        closeGlobalSpeakerModal();
    }
});

// Cerrar modales al hacer click fuera
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal')) {
        closeChangeSpeakerModal();
        closeGlobalSpeakerModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    createParticles();

    // Iniciar automáticamente el procesamiento de audio
    setTimeout(() => {
        startAudioProcessing();
    }, 1000);
});

// Agregar estilos de animación para las notificaciones
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Exponer funciones globalmente
window.selectAnalyzer = selectAnalyzer;
window.startAnalysis = startAnalysis;
window.playFullAudio = playFullAudio;
window.saveTranscriptionAndContinue = saveTranscriptionAndContinue;
window.goBackToRecording = goBackToRecording;
window.goBackToTranscription = goBackToTranscription;
window.goBackToAnalysis = goBackToAnalysis;
window.cancelProcess = cancelProcess;
window.toggleAudioPlayback = toggleAudioPlayback;
window.downloadAudio = downloadAudio;
window.saveToDatabase = saveToDatabase;
window.viewMeetingDetails = viewMeetingDetails;
window.goToMeetings = goToMeetings;
window.createNewMeeting = createNewMeeting;
window.toggleMobileDropdown = toggleMobileDropdown;
window.closeMobileDropdown = closeMobileDropdown;
window.playSegmentAudio = playSegmentAudio;
window.openChangeSpeakerModal = openChangeSpeakerModal;
window.closeChangeSpeakerModal = closeChangeSpeakerModal;
window.confirmSpeakerChange = confirmSpeakerChange;
window.openGlobalSpeakerModal = openGlobalSpeakerModal;
window.closeGlobalSpeakerModal = closeGlobalSpeakerModal;
window.confirmGlobalSpeakerChange = confirmGlobalSpeakerChange;
window.seekAudio = seekAudio;
