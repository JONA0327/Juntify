<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Test MP3 Conversion (No CORS)</title>
    <style>
        body {
            background-color: #1a1a2e;
            color: white;
            font-family: Arial, sans-serif;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .section {
            background-color: #16213e;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        .button {
            background-color: #0f3460;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .button:hover {
            background-color: #1e5f8b;
        }
        .button:disabled {
            background-color: #666;
            cursor: not-allowed;
        }
        .button.red {
            background-color: #dc2626;
        }
        .button.red:hover {
            background-color: #b91c1c;
        }
        .button.blue {
            background-color: #2563eb;
        }
        .button.blue:hover {
            background-color: #1d4ed8;
        }
        .button.green {
            background-color: #16a34a;
        }
        .button.green:hover {
            background-color: #15803d;
        }
        .progress-container {
            background-color: #374151;
            border-radius: 9999px;
            height: 8px;
            margin-top: 0.5rem;
        }
        .progress-bar {
            background-color: #2563eb;
            height: 8px;
            border-radius: 9999px;
            width: 0%;
            transition: width 0.3s;
        }
        .logs {
            background-color: #111827;
            padding: 1rem;
            border-radius: 4px;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.875rem;
            color: #10b981;
        }
        .hidden {
            display: none;
        }
        .note {
            background-color: #1e3a8a;
            border: 1px solid #3b82f6;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .note-text {
            color: #bfdbfe;
            font-size: 0.875rem;
        }
        audio {
            width: 100%;
            margin-top: 0.5rem;
        }
        .status {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #9ca3af;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        h3 {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        label {
            display: block;
            font-size: 0.875rem;
            color: #9ca3af;
        }
    </style>
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

    <script>
        // Inline JavaScript to avoid external dependencies
        let mediaRecorder;
        let recordedChunks = [];
        let originalBlob;
        let mp3Blob;

        function log(message) {
            const logContent = document.getElementById('log-content');
            const timestamp = new Date().toLocaleTimeString();
            logContent.textContent += `[${timestamp}] ${message}\n`;
            logContent.scrollTop = logContent.scrollHeight;
            console.log(message);
        }

        async function startRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });

                const options = { mimeType: 'audio/mp4' };
                if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                    options.mimeType = 'audio/webm';
                }

                mediaRecorder = new MediaRecorder(stream, options);
                recordedChunks = [];

                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };

                mediaRecorder.onstop = () => {
                    originalBlob = new Blob(recordedChunks, {
                        type: mediaRecorder.mimeType || 'audio/mp4'
                    });

                    log(`Recording stopped. Size: ${(originalBlob.size / 1024).toFixed(1)} KB, Type: ${originalBlob.type}`);

                    // Create audio player for original
                    const originalAudio = document.getElementById('original-audio');
                    originalAudio.src = URL.createObjectURL(originalBlob);
                    originalAudio.style.display = 'block';

                    // Enable conversion button
                    document.getElementById('convert-mp3').disabled = false;
                    document.getElementById('download-original').disabled = false;

                    stream.getTracks().forEach(track => track.stop());
                };

                mediaRecorder.start(1000);
                log('Recording started...');

                document.getElementById('start-record').disabled = true;
                document.getElementById('stop-record').disabled = false;
                document.getElementById('recording-status').textContent = 'Recording...';

            } catch (error) {
                log(`Error starting recording: ${error.message}`);
            }
        }

        async function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                document.getElementById('start-record').disabled = false;
                document.getElementById('stop-record').disabled = true;
                document.getElementById('recording-status').textContent = 'Recording stopped';
            }
        }

        async function convertToMp3() {
            if (!originalBlob) {
                log('No recording to convert');
                return;
            }

            try {
                document.getElementById('conversion-progress').classList.remove('hidden');
                log('Starting MP3 conversion demo...');

                // Simulate conversion progress
                for (let i = 0; i <= 100; i += 10) {
                    document.getElementById('progress-bar').style.width = `${i}%`;
                    log(`Conversion progress: ${i}%`);
                    await new Promise(resolve => setTimeout(resolve, 100));
                }

                // Create a "converted" file (same content, different MIME type for demo)
                mp3Blob = new Blob([originalBlob], { type: 'audio/mpeg' });
                
                log(`MP3 conversion completed! Size: ${(mp3Blob.size / 1024).toFixed(1)} KB`);
                log('Note: This is a demo conversion. For true MP3 encoding, use server-side processing.');

                // Create audio player for MP3
                const mp3Audio = document.getElementById('mp3-audio');
                mp3Audio.src = URL.createObjectURL(mp3Blob);
                mp3Audio.style.display = 'block';

                // Enable download button
                document.getElementById('download-mp3').disabled = false;

            } catch (error) {
                log(`MP3 conversion failed: ${error.message}`);
            } finally {
                document.getElementById('conversion-progress').classList.add('hidden');
            }
        }

        function downloadBlob(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function downloadOriginal() {
            if (originalBlob) {
                downloadBlob(originalBlob, 'test-original.mp4');
                log('Original file downloaded');
            }
        }

        function downloadMp3() {
            if (mp3Blob) {
                downloadBlob(mp3Blob, 'test-converted.mp3');
                log('MP3 file downloaded (demo file - same content as original)');
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            // Event listeners
            document.getElementById('start-record').addEventListener('click', startRecording);
            document.getElementById('stop-record').addEventListener('click', stopRecording);
            document.getElementById('convert-mp3').addEventListener('click', convertToMp3);
            document.getElementById('download-original').addEventListener('click', downloadOriginal);
            document.getElementById('download-mp3').addEventListener('click', downloadMp3);

            log('Test page loaded. Web Audio API ready (No external dependencies).');
            log('No CORS issues - everything runs locally.');
        });
    </script>
</body>
</html>
<?php /**PATH C:\laragon\www\Juntify\resources\views/test-mp3-nocors.blade.php ENDPATH**/ ?>