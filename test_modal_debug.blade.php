<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="test-token">
    <title>Test Modal - AI Assistant</title>
    <link rel="stylesheet" href="/css/ai-assistant.css">
    <style>
        body {
            background: #020617;
            color: #e2e8f0;
            font-family: Inter, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .test-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .test-btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.5rem;
            color: #3b82f6;
            cursor: pointer;
            text-decoration: none;
            margin: 0.5rem;
        }
        .test-btn:hover {
            background: rgba(59, 130, 246, 0.3);
        }
        .debug-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(100, 116, 139, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            font-size: 0.875rem;
            max-width: 300px;
            z-index: 9999;
        }
        .debug-section {
            margin-bottom: 1rem;
        }
        .debug-section h4 {
            margin: 0 0 0.5rem 0;
            color: #94a3b8;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .debug-item {
            display: flex;
            justify-content: space-between;
            margin: 0.25rem 0;
            font-size: 0.75rem;
        }
        .status-ok { color: #10b981; }
        .status-error { color: #ef4444; }
    </style>
</head>
<body>
    <div class="test-header">
        <h1>🧪 Test del Modal de Contexto</h1>
        <p>Verificación de funcionalidad del sistema de selección de contexto</p>

        <div class="test-actions">
            <button class="test-btn" onclick="testOpenModal()">
                🚀 Abrir Modal de Contexto
            </button>
            <button class="test-btn" onclick="runDiagnostics()">
                🔍 Ejecutar Diagnósticos
            </button>
            <button class="test-btn" onclick="clearConsole()">
                🧹 Limpiar Consola
            </button>
        </div>
    </div>

    <div class="debug-panel" id="debugPanel">
        <div class="debug-section">
            <h4>Estado del Sistema</h4>
            <div id="systemStatus">Iniciando verificación...</div>
        </div>
        <div class="debug-section">
            <h4>Funciones JavaScript</h4>
            <div id="jsStatus">Verificando...</div>
        </div>
        <div class="debug-section">
            <h4>Elementos DOM</h4>
            <div id="domStatus">Verificando...</div>
        </div>
    </div>

    <!-- Incluir el modal -->
    @include('ai-assistant.modals.container-selector')

    <!-- Scripts principales -->
    <script src="/js/ai-assistant.js"></script>

    <script>
        // Variables globales de prueba si no existen
        if (typeof currentSessionId === 'undefined') {
            window.currentSessionId = 'test-session-123';
        }
        if (typeof currentContextType === 'undefined') {
            window.currentContextType = 'containers';
        }
        if (typeof loadedContextItems === 'undefined') {
            window.loadedContextItems = [];
        }

        // Función de prueba para abrir modal
        function testOpenModal() {
            console.log('🧪 Intentando abrir modal de contexto...');
            try {
                if (typeof openContextSelector === 'function') {
                    openContextSelector();
                    updateStatus('systemStatus', '✅ Modal abierto correctamente', 'status-ok');
                } else {
                    updateStatus('systemStatus', '❌ Función openContextSelector no encontrada', 'status-error');
                }
            } catch (error) {
                console.error('Error al abrir modal:', error);
                updateStatus('systemStatus', `❌ Error: ${error.message}`, 'status-error');
            }
        }

        // Función para ejecutar diagnósticos completos
        function runDiagnostics() {
            console.log('🔍 Ejecutando diagnósticos completos...');

            // Verificar funciones JavaScript
            const functions = [
                'openContextSelector',
                'closeContextSelector',
                'switchContextType',
                'filterContextItems',
                'selectAllMeetings',
                'updateSessionInfo'
            ];

            let jsResults = '';
            functions.forEach(funcName => {
                if (typeof window[funcName] === 'function') {
                    jsResults += `<div class="debug-item"><span>${funcName}</span><span class="status-ok">✅</span></div>`;
                } else {
                    jsResults += `<div class="debug-item"><span>${funcName}</span><span class="status-error">❌</span></div>`;
                }
            });
            document.getElementById('jsStatus').innerHTML = jsResults;

            // Verificar elementos DOM
            const elements = [
                'contextSelectorModal',
                'contextSearchInput',
                'containersView',
                'meetingsView',
                'loadedContextItems'
            ];

            let domResults = '';
            elements.forEach(elementId => {
                const element = document.getElementById(elementId);
                if (element) {
                    domResults += `<div class="debug-item"><span>#${elementId}</span><span class="status-ok">✅</span></div>`;
                } else {
                    domResults += `<div class="debug-item"><span>#${elementId}</span><span class="status-error">❌</span></div>`;
                }
            });
            document.getElementById('domStatus').innerHTML = domResults;
        }

        // Función para actualizar estado
        function updateStatus(elementId, message, className = '') {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = `<div class="${className}">${message}</div>`;
            }
        }

        // Función para limpiar consola
        function clearConsole() {
            console.clear();
            console.log('🧹 Consola limpiada');
        }

        // Ejecutar diagnósticos al cargar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                runDiagnostics();
                updateStatus('systemStatus', '✅ Sistema inicializado', 'status-ok');
            }, 500);
        });

        // Capturar errores JavaScript globales
        window.addEventListener('error', function(e) {
            console.error('Error JavaScript capturado:', e.error);
            updateStatus('systemStatus', `❌ Error: ${e.message}`, 'status-error');
        });
    </script>
</body>
</html>
