// FFmpeg test functionality
import { FFmpeg } from '@ffmpeg/ffmpeg';
import { fetchFile } from '@ffmpeg/util';

let mediaRecorder;
let recordedChunks = [];
let originalBlob;
let mp3Blob;
let ffmpeg;

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
        log('Starting MP3 conversion...');

        if (!ffmpeg) {
            ffmpeg = new FFmpeg();

            ffmpeg.on('log', ({ message }) => {
                log(`FFmpeg: ${message}`);
            });

            ffmpeg.on('progress', ({ progress }) => {
                const percent = Math.round(progress * 100);
                document.getElementById('progress-bar').style.width = `${percent}%`;
                log(`FFmpeg progress: ${percent}%`);
            });

            log('Loading FFmpeg...');

            // Try to load FFmpeg with minimal configuration to avoid CORS issues
            try {
                await ffmpeg.load();
                log('FFmpeg loaded successfully');
            } catch (error) {
                log(`FFmpeg load failed: ${error.message}`);
                log('This may be due to CORS restrictions with SharedArrayBuffer.');
                log('Try using a production server or different browser configuration.');
                throw error;
            }
        }

        // Convert blob to Uint8Array
        const inputData = new Uint8Array(await originalBlob.arrayBuffer());
        const inputName = 'input.mp4';
        const outputName = 'output.mp3';

        log('Writing input file...');
        await ffmpeg.writeFile(inputName, inputData);

        log('Converting to MP3...');
        await ffmpeg.exec([
            '-i', inputName,
            '-codec:a', 'libmp3lame',
            '-b:a', '128k',
            '-ar', '44100',
            outputName
        ]);

        log('Reading output file...');
        const mp3Data = await ffmpeg.readFile(outputName);

        // Clean up files
        await ffmpeg.deleteFile(inputName);
        await ffmpeg.deleteFile(outputName);

        mp3Blob = new Blob([mp3Data], { type: 'audio/mpeg' });
        log(`MP3 conversion successful! Size: ${(mp3Blob.size / 1024).toFixed(1)} KB`);

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
        log('MP3 file downloaded');
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

    log('Test page loaded. FFmpeg modules ready.');
});
