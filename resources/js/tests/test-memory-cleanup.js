// Simular el módulo idb.js
const idb = {
    async clearAllAudio() {
        console.log('Clearing IndexedDB audio data...');
        return new Promise(resolve => {
            const deleteReq = indexedDB.deleteDatabase('AudioStorage');
            deleteReq.onsuccess = () => resolve(true);
            deleteReq.onerror = () => resolve(false);
        });
    },

    async deleteAudioBlob(key) {
        console.log(`Deleting audio blob with key: ${key}`);
        return true;
    }
};

// Función de limpieza de memoria
async function clearPreviousAudioData() {
    console.log('Starting audio memory cleanup...');

    try {
        // 1. Limpiar IndexedDB
        await idb.clearAllAudio();
        console.log('✓ IndexedDB cleared');

        // 2. Limpiar sessionStorage
        const sessionKeys = ['uploadedAudioKey', 'recordingBlob', 'recordingSegments', 'recordingMetadata'];
        sessionKeys.forEach(key => {
            sessionStorage.removeItem(key);
            console.log(`✓ Removed ${key} from sessionStorage`);
        });

        // 3. Limpiar localStorage
        localStorage.removeItem('pendingAudioData');
        console.log('✓ Removed pendingAudioData from localStorage');

        // 4. Limpiar variables locales (simulated)
        console.log('✓ Local variables cleared');

        console.log('Audio memory cleanup completed successfully');
        return true;
    } catch (error) {
        console.error('Error during audio cleanup:', error);
        return false;
    }
}

// Funciones de test
function simulateAudioStorage() {
    sessionStorage.setItem('uploadedAudioKey', 'test_audio_123');
    sessionStorage.setItem('recordingBlob', 'blob_data');
    sessionStorage.setItem('recordingSegments', JSON.stringify(['segment1', 'segment2']));
    sessionStorage.setItem('recordingMetadata', JSON.stringify({duration: 120}));
    localStorage.setItem('pendingAudioData', JSON.stringify({id: 1, name: 'test.mp3'}));

    showStatus('<div class="alert alert-success"><i class="fas fa-check-circle"></i> Audio data simulado correctamente en memoria</div>');
}

async function testCleanup() {
    showStatus('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Ejecutando limpieza de memoria...</div>');

    const result = await clearPreviousAudioData();
    const alertClass = result ? 'alert-success' : 'alert-danger';
    const icon = result ? 'fa-check-circle' : 'fa-times-circle';
    const message = result ? '¡Limpieza completada exitosamente!' : 'Error en la limpieza de memoria';

    showStatus(`<div class="alert ${alertClass}"><i class="fas ${icon}"></i> ${message}</div>`);
}

function checkMemory() {
    const sessionKeys = ['uploadedAudioKey', 'recordingBlob', 'recordingSegments', 'recordingMetadata'];
    const sessionData = sessionKeys.map(key => {
        const value = sessionStorage.getItem(key);
        const status = value ? '<span class="badge bg-danger">Presente</span>' : '<span class="badge bg-success">Limpio</span>';
        const displayValue = value ? (value.length > 50 ? value.substring(0, 47) + '...' : value) : '<em>null</em>';
        return `<tr><td><code>${key}</code></td><td>${status}</td><td class="text-muted small">${displayValue}</td></tr>`;
    });

    const localValue = localStorage.getItem('pendingAudioData');
    const localStatus = localValue ? '<span class="badge bg-danger">Presente</span>' : '<span class="badge bg-success">Limpio</span>';
    const localDisplay = localValue ? (localValue.length > 50 ? localValue.substring(0, 47) + '...' : localValue) : '<em>null</em>';

    const report = `
        <div class="alert alert-info">
            <h5><i class="fas fa-chart-bar"></i> Reporte de Estado de Memoria</h5>

            <h6 class="mt-3 mb-2"><i class="fas fa-database"></i> SessionStorage:</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Clave</th>
                            <th>Estado</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${sessionData.join('')}
                    </tbody>
                </table>
            </div>

            <h6 class="mt-3 mb-2"><i class="fas fa-hdd"></i> LocalStorage:</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Clave</th>
                            <th>Estado</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>pendingAudioData</code></td>
                            <td>${localStatus}</td>
                            <td class="text-muted small">${localDisplay}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;

    showStatus(report);
}

function showStatus(content) {
    const statusDiv = document.getElementById('status');
    statusDiv.innerHTML = content;
    statusDiv.style.display = 'block';
    statusDiv.scrollIntoView({ behavior: 'smooth' });
}

// Mostrar mensaje inicial
document.addEventListener('DOMContentLoaded', function() {
    showStatus(`
        <div class="alert alert-primary">
            <h6><i class="fas fa-info-circle"></i> Instrucciones de Uso:</h6>
            <ol class="mb-0">
                <li><strong>Simular Almacenamiento:</strong> Crea datos de audio de prueba en memoria</li>
                <li><strong>Probar Limpieza:</strong> Ejecuta la función de limpieza de memoria</li>
                <li><strong>Verificar Memoria:</strong> Muestra el estado actual de los datos almacenados</li>
            </ol>
        </div>
    `);
});

