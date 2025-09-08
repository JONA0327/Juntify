<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Test MP3 Conversion (Simple)</title>
    <script src="https://cdn.tailwindcss.com" crossorigin="anonymous"></script>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/ffmpeg-test-simple.js']); ?>
</head>
<body class="bg-gray-900 text-white p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Test MP3 Conversion (CORS-Friendly Version)</h1>

        <div class="mb-4 p-4 bg-blue-900 rounded border border-blue-700">
            <p class="text-sm text-blue-200">
                <strong>Note:</strong> This version uses Web Audio API and doesn't require FFmpeg,
                avoiding CORS issues. For true MP3 conversion, server-side processing is recommended.
            </p>
        </div>

        <div class="space-y-4">
            <div class="p-4 bg-gray-800 rounded">
                <h3 class="font-bold mb-2">Step 1: Test Recording</h3>
                <button id="start-record" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded mr-2">
                    Start Recording
                </button>
                <button id="stop-record" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded" disabled>
                    Stop Recording
                </button>
                <div id="recording-status" class="mt-2 text-sm text-gray-400"></div>
            </div>

            <div class="p-4 bg-gray-800 rounded">
                <h3 class="font-bold mb-2">Step 2: Convert to MP3</h3>
                <button id="convert-mp3" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded" disabled>
                    Convert to MP3 (Demo)
                </button>
                <div id="conversion-progress" class="mt-2 hidden">
                    <div class="text-sm text-blue-400">Converting...</div>
                    <div class="bg-gray-700 rounded-full h-2 mt-1">
                        <div id="progress-bar" class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-gray-800 rounded">
                <h3 class="font-bold mb-2">Step 3: Download & Test</h3>
                <button id="download-original" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded mr-2" disabled>
                    Download Original
                </button>
                <button id="download-mp3" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded" disabled>
                    Download MP3 (Demo)
                </button>
            </div>

            <div class="p-4 bg-gray-800 rounded">
                <h3 class="font-bold mb-2">Audio Players</h3>
                <div class="space-y-2">
                    <div>
                        <label class="block text-sm text-gray-400">Original Recording:</label>
                        <audio id="original-audio" controls class="w-full mt-1" style="display: none;"></audio>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400">MP3 Converted:</label>
                        <audio id="mp3-audio" controls class="w-full mt-1" style="display: none;"></audio>
                    </div>
                </div>
            </div>
        </div>

        <div id="logs" class="mt-6 p-4 bg-gray-800 rounded">
            <h3 class="font-bold mb-2">Logs:</h3>
            <pre id="log-content" class="text-sm overflow-auto h-40 text-green-400"></pre>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\laragon\www\Juntify\resources\views/test-mp3-simple.blade.php ENDPATH**/ ?>