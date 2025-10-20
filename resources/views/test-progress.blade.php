<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba Barra de Progreso</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/ai-assistant.css') }}?v={{ time() }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-button {
            background: #3b82f6;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
        }
        .test-button:hover {
            background: #2563eb;
        }
        .input-area {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Prueba de Barra de Progreso</h1>
        <p>Esta página es para probar el sistema de barras de progreso de subida de archivos.</p>

        <div>
            <button class="test-button" onclick="testProgressBar()">🚀 Probar Barra de Progreso</button>
            <button class="test-button" onclick="console.clear()">🧹 Limpiar Console</button>
        </div>

        <!-- Contenedor donde aparecerán las barras de progreso -->
        <div id="attachments-container" class="attachments-container" style="display: none;">
            <!-- Las barras de progreso aparecerán aquí -->
        </div>

        <!-- Simular área de entrada para que el JavaScript encuentre la referencia -->
        <div class="input-area">
            <p>📝 Área de entrada simulada (para referencia del JavaScript)</p>
            <input type="file" id="file-input" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx,.pptx" style="margin-top: 10px;">
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #e0f2fe; border-radius: 6px;">
            <h3>📋 Instrucciones:</h3>
            <ol>
                <li>Haz clic en "🚀 Probar Barra de Progreso" para ver la animación</li>
                <li>Abre la consola del navegador (F12) para ver los logs detallados</li>
                <li>La barra debe aparecer, mostrar progreso y desaparecer automáticamente</li>
            </ol>
        </div>
    </div>

    <script src="{{ asset('js/ai-assistant.js') }}?v={{ time() }}"></script>
    <script>
        // Configurar variables globales necesarias
        window.currentSessionId = 1;
        window.attachedFiles = [];

        // Simular funciones necesarias
        window.updateAttachmentIndicator = function() {
            console.log('📊 updateAttachmentIndicator llamada');
        };

        console.log('🎯 Página de prueba cargada. Usa testProgressBar() para probar.');
    </script>
</body>
</html>
