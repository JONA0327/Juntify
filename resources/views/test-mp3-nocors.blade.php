<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test MP3 Conversion (No CORS)</title>
    @vite('resources/css/tests/test-mp3-nocors.css')
</head>
<body>
    <div class="container">
        <h1>Test MP3 Conversion (No External Dependencies)</h1>

        <div class="note">
            <p class="note-text">
                <strong>Note:</strong> This version has no external dependencies and doesn't require FFmpeg,
                avoiding all CORS issues. Uses built-in Web APIs only.
            </p>
        </div>

        <div class="section">
            <h3>Step 1: Test Recording</h3>
            <button id="start-record" class="button red">
                Start Recording
            </button>
            <button id="stop-record" class="button" disabled>
                Stop Recording
            </button>
            <div id="recording-status" class="status"></div>
        </div>

        <div class="section">
            <h3>Step 2: Convert to MP3</h3>
            <button id="convert-mp3" class="button blue" disabled>
                Convert to MP3 (Demo)
            </button>
            <div id="conversion-progress" class="hidden">
                <div style="color: #3b82f6; font-size: 0.875rem; margin-top: 0.5rem;">Converting...</div>
                <div class="progress-container">
                    <div id="progress-bar" class="progress-bar"></div>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>Step 3: Download & Test</h3>
            <button id="download-original" class="button green" disabled>
                Download Original
            </button>
            <button id="download-mp3" class="button green" disabled>
                Download MP3 (Demo)
            </button>
        </div>

        <div class="section">
            <h3>Audio Players</h3>
            <div>
                <label>Original Recording:</label>
                <audio id="original-audio" controls style="display: none;"></audio>
            </div>
            <div style="margin-top: 1rem;">
                <label>MP3 Converted:</label>
                <audio id="mp3-audio" controls style="display: none;"></audio>
            </div>
        </div>

        <div class="section">
            <h3>Logs:</h3>
            <pre id="log-content" class="logs"></pre>
        </div>
    </div>
    @vite('resources/js/tests/test-mp3-nocors.js')
</body>
</html>
