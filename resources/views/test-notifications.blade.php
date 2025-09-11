<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Notificaciones</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-6">Test Notificaciones de Subida</h1>

        <div class="space-y-4">
            <button id="test-progress" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                Crear Notificación de Progreso
            </button>

            <button id="test-success" class="w-full bg-green-600 hover:bg-green-700 px-4 py-2 rounded">
                Crear Notificación de Éxito
            </button>

            <button id="test-dismiss" class="w-full bg-red-600 hover:bg-red-700 px-4 py-2 rounded">
                Descartar Última Notificación
            </button>
        </div>

        <div id="result" class="mt-6 p-4 bg-gray-800 rounded hidden">
            <h3 class="font-bold mb-2">Resultado:</h3>
            <pre id="result-content" class="text-sm"></pre>
        </div>

        <!-- Simulated Notifications Panel -->
        <div class="mt-8 border border-gray-700 rounded-lg p-4">
            <h3 class="font-bold mb-4">Panel de Notificaciones (Simulado)</h3>
            <ul id="notifications-list" class="space-y-2">
                <li class="text-gray-400 text-sm text-center py-4">No hay notificaciones</li>
            </ul>
        </div>
    </div>

    @vite('resources/js/tests/test-notifications.js')
</body>
</html>
