// Generador de audio simple para navegador
// Pega este código en la consola del navegador en /audio/notifications/generator.html

function createSimpleBeep(frequency = 800, duration = 1, volume = 0.3) {
    const sampleRate = 44100;
    const samples = sampleRate * duration;
    const audioBuffer = new ArrayBuffer(44 + samples * 2);
    const view = new DataView(audioBuffer);

    // WAV header
    const writeString = (offset, string) => {
        for (let i = 0; i < string.length; i++) {
            view.setUint8(offset + i, string.charCodeAt(i));
        }
    };

    writeString(0, 'RIFF');
    view.setUint32(4, 36 + samples * 2, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeString(36, 'data');
    view.setUint32(40, samples * 2, true);

    // Generar datos de audio
    let offset = 44;
    for (let i = 0; i < samples; i++) {
        const t = i / sampleRate;
        let sample = 0;

        // Beep doble para advertencia
        if (frequency === 800) {
            if (t < 0.2 || (t > 0.3 && t < 0.5)) {
                const envelope = Math.sin(Math.PI * (t % 0.2) / 0.2);
                sample = Math.sin(2 * Math.PI * frequency * t) * envelope * volume;
            }
        } else {
            // Beep triple para límite alcanzado
            if (t < 0.15 || (t > 0.2 && t < 0.35) || (t > 0.4 && t < 0.55)) {
                const envelope = Math.sin(Math.PI * ((t % 0.15) % 0.15) / 0.15);
                sample = Math.sin(2 * Math.PI * frequency * t) * envelope * volume;
            }
        }

        view.setInt16(offset, Math.max(-32767, Math.min(32767, sample * 32767)), true);
        offset += 2;
    }

    return audioBuffer;
}

function downloadBeep(filename, frequency, duration) {
    const audioBuffer = createSimpleBeep(frequency, duration);
    const blob = new Blob([audioBuffer], { type: 'audio/wav' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// Usar estas funciones en la consola:
// downloadBeep('time-warning.wav', 800, 1.5);
// downloadBeep('time-limit-reached.wav', 600, 2);
// downloadBeep('meeting-start.wav', 900, 0.8);
// downloadBeep('meeting-end.wav', 500, 1.2);
