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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/index.css'])

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .test-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 1000px;
        }
        .btn-test {
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            margin: 0.5rem;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .status-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1rem;
            border-left: 4px solid #667eea;
        }
    </style>
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

    <!-- Scripts -->
    <script>
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
    </script>

    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
