// Configurar CSRF token para las requests AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Configurar CSRF token para fetch
    window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
});

function addToLog(message, type = 'info') {
    const log = document.getElementById('test-log');
    const timestamp = new Date().toLocaleTimeString();
    const colors = {
        'info': 'text-blue-400',
        'success': 'text-green-400',
        'error': 'text-red-400',
        'warning': 'text-yellow-400'
    };

    const entry = document.createElement('div');
    entry.className = colors[type] || colors['info'];
    entry.textContent = `[${timestamp}] ${message}`;
    log.appendChild(entry);
    log.scrollTop = log.scrollHeight;
}

function clearLog() {
    document.getElementById('test-log').innerHTML = '';
    addToLog('Log limpiado', 'info');
}

// Test 1: Verificar datos de BD
async function checkDatabaseData() {
    addToLog('Verificando datos de base de datos...', 'info');
    const statusDiv = document.getElementById('db-status');

    try {
        const response = await fetch('/debug-pending', {
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        statusDiv.innerHTML = `
            <div class="space-y-2">
                <p class="text-green-400">✅ Usuario: ${data.current_user}</p>
                <p class="text-blue-400">📁 Carpetas pendientes: ${data.folders.length}</p>
                <p class="text-yellow-400">🎵 Grabaciones pendientes: ${data.recordings.length}</p>
                ${data.recordings.map(r => `<p class="text-slate-300 text-xs">- ${r.meeting_name} (${r.status})</p>`).join('')}
            </div>
        `;

        addToLog(`Datos obtenidos: ${data.folders.length} carpetas, ${data.recordings.length} grabaciones`, 'success');

    } catch (error) {
        statusDiv.innerHTML = `<p class="text-red-400">❌ Error: ${error.message}</p>`;
        addToLog(`Error al verificar BD: ${error.message}`, 'error');
    }
}

// Test 2: API de reuniones pendientes
async function testPendingAPI() {
    addToLog('Probando API de reuniones pendientes...', 'info');
    const statusDiv = document.getElementById('api-status');

    try {
        const response = await fetch('/api/pending-meetings', {
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        statusDiv.innerHTML = `
            <div class="space-y-2">
                <p class="text-green-400">✅ API disponible</p>
                <p class="text-blue-400">📊 Reuniones encontradas: ${data.pending_meetings.length}</p>
                <p class="text-yellow-400">🔄 Hay pendientes: ${data.has_pending ? 'Sí' : 'No'}</p>
                ${data.pending_meetings.map(m => `<p class="text-slate-300 text-xs">- ${m.name} (${m.size})</p>`).join('')}
            </div>
        `;

        addToLog(`API funcionando: ${data.pending_meetings.length} reuniones pendientes`, 'success');

    } catch (error) {
        statusDiv.innerHTML = `<p class="text-red-400">❌ Error API: ${error.message}</p>`;
        addToLog(`Error en API: ${error.message}`, 'error');
    }
}

// Test 3: Botón dinámico
function testDynamicButton() {
    addToLog('Simulando botón dinámico...', 'info');
    const statusDiv = document.getElementById('button-status');

    // Simular diferentes estados del botón
    const states = [
        { text: 'Cargando...', color: 'text-blue-400' },
        { text: 'Reuniones disponibles', color: 'text-green-400' },
        { text: 'Botón habilitado', color: 'text-yellow-400' }
    ];

    let currentState = 0;

    const interval = setInterval(() => {
        if (currentState < states.length) {
            const state = states[currentState];
            statusDiv.innerHTML = `<p class="${state.color}">🔄 ${state.text}</p>`;
            addToLog(`Estado del botón: ${state.text}`, 'info');
            currentState++;
        } else {
            clearInterval(interval);
            statusDiv.innerHTML = '<p class="text-green-400">✅ Test del botón completado</p>';
            addToLog('Test del botón dinámico finalizado', 'success');
        }
    }, 1000);
}

// Test 4: Modal
function testModal() {
    addToLog('Abriendo modal de prueba...', 'info');
    const statusDiv = document.getElementById('modal-status');

    // Crear modal de prueba
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-slate-800 rounded-lg p-8 max-w-md mx-4 border border-slate-700">
            <h3 class="text-xl font-bold text-white mb-4">🎉 Modal de Prueba</h3>
            <p class="text-slate-300 mb-4">El modal se abrió correctamente!</p>
            <div class="text-sm text-slate-400 mb-6">
                <p>En el sistema real aquí aparecerían:</p>
                <div class="card-list mt-2">
                    <div class="info-card">Lista de reuniones pendientes</div>
                    <div class="info-card">Botones de análisis</div>
                    <div class="info-card">Estados de procesamiento</div>
                    <div class="info-card">Información de archivos</div>
                </div>
            </div>
            <button onclick="this.closest('.fixed').remove()"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors">
                Cerrar Modal
            </button>
        </div>
    `;

    document.body.appendChild(modal);
    statusDiv.innerHTML = '<p class="text-green-400">✅ Modal abierto exitosamente</p>';
    addToLog('Modal de prueba desplegado', 'success');
}

