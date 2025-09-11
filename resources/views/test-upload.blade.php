<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Audio Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-6">Test Audio Upload to Drive</h1>

        <div class="space-y-4">
            <button id="test-upload" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                Test Audio Upload (Crear archivo de prueba)
            </button>

            <div id="upload-progress" class="hidden">
                <div class="bg-gray-700 rounded-full h-2 mb-2">
                    <div id="progress-bar" class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                </div>
                <div id="progress-text" class="text-sm text-center">Preparando...</div>
            </div>
        </div>

        <div id="result" class="mt-6 p-4 bg-gray-800 rounded hidden">
            <h3 class="font-bold mb-2">Resultado:</h3>
            <pre id="result-content" class="text-sm overflow-auto"></pre>
        </div>
    </div>

    @vite('resources/js/tests/test-upload.js')
</body>
</html>
