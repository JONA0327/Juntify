function showResult(data) {
    document.getElementById('result').classList.remove('hidden');
    document.getElementById('result-content').textContent = JSON.stringify(data, null, 2);
}

function updateProgress(loaded, total) {
    const percent = (loaded / total) * 100;
    document.getElementById('progress-bar').style.width = percent + '%';
    document.getElementById('progress-text').textContent = `Subiendo... ${percent.toFixed(0)}%`;
}

async function testAudioUpload() {
    const button = document.getElementById('test-upload');
    const progressDiv = document.getElementById('upload-progress');

    button.disabled = true;
    progressDiv.classList.remove('hidden');

    try {
        // Create a simple test audio blob (1 second of silence)
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const sampleRate = audioContext.sampleRate;
        const duration = 1; // 1 second
        const numSamples = sampleRate * duration;

        const buffer = audioContext.createBuffer(1, numSamples, sampleRate);
        const channelData = buffer.getChannelData(0);

        // Generate a simple sine wave for testing
        for (let i = 0; i < numSamples; i++) {
            channelData[i] = Math.sin(2 * Math.PI * 440 * i / sampleRate) * 0.1;
        }

        // Convert to WAV format (simplified)
        const wavBlob = audioBufferToWav(buffer);

        const formData = new FormData();
        const fileName = 'test-audio-' + new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
        formData.append('audioFile', wavBlob, fileName + '.wav');
        formData.append('meetingName', fileName);

        const response = await fetch('/api/drive/upload-pending-audio', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });

        const result = await response.json();

        if (response.ok) {
            showResult({
                success: true,
                status: response.status,
                data: result
            });
        } else {
            showResult({
                success: false,
                status: response.status,
                error: result
            });
        }

    } catch (error) {
        showResult({
            success: false,
            error: error.message
        });
    } finally {
        button.disabled = false;
        progressDiv.classList.add('hidden');
    }
}

// Simple WAV file creation function
function audioBufferToWav(buffer) {
    const length = buffer.length;
    const arrayBuffer = new ArrayBuffer(44 + length * 2);
    const view = new DataView(arrayBuffer);

    // WAV header
    const writeString = (offset, string) => {
        for (let i = 0; i < string.length; i++) {
            view.setUint8(offset + i, string.charCodeAt(i));
        }
    };

    writeString(0, 'RIFF');
    view.setUint32(4, 36 + length * 2, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, buffer.sampleRate, true);
    view.setUint32(28, buffer.sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeString(36, 'data');
    view.setUint32(40, length * 2, true);

    // Convert samples
    const channelData = buffer.getChannelData(0);
    let offset = 44;
    for (let i = 0; i < length; i++) {
        const sample = Math.max(-1, Math.min(1, channelData[i]));
        view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7FFF, true);
        offset += 2;
    }

    return new Blob([arrayBuffer], { type: 'audio/wav' });
}

// Event listeners
document.getElementById('test-upload').addEventListener('click', testAudioUpload);

