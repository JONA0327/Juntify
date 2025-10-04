let ffmpegLoaderPromise = null;
let ffmpegInstance = null;
let fetchFileFn = null;
let ffmpegQueue = Promise.resolve();

async function loadFFmpeg() {
    if (!ffmpegLoaderPromise) {
        ffmpegLoaderPromise = (async () => {
            const [ffmpegModule, coreUrlModule, wasmUrlModule, utilModule] = await Promise.all([
                import('@ffmpeg/ffmpeg'),
                import('@ffmpeg/core?url'),
                import('@ffmpeg/core/wasm?url'),
                import('@ffmpeg/util')
            ]);
            const { FFmpeg } = ffmpegModule;
            const fetchFile = utilModule?.fetchFile
                || utilModule?.default?.fetchFile
                || utilModule?.default;
            if (typeof fetchFile !== 'function') {
                throw new Error('No se pudo cargar fetchFile de FFmpeg');
            }

            const ffmpeg = new FFmpeg();
            if (import.meta.env?.DEV) {
                ffmpeg.on('log', ({ message }) => console.log(message));
            }
            const coreURL = coreUrlModule?.default || coreUrlModule;
            const wasmURL = wasmUrlModule?.default || wasmUrlModule;
            await ffmpeg.load({
                coreURL,
                wasmURL
            });
            ffmpegInstance = ffmpeg;
            fetchFileFn = fetchFile;
            return ffmpegInstance;
        })();
    }
    await ffmpegLoaderPromise;
    return ffmpegInstance;
}

async function runSerial(fn) {
    const job = ffmpegQueue.then(() => fn());
    ffmpegQueue = job.then(() => undefined, () => undefined);
    return job;
}

function getInputFileName(blob) {
    const type = blob?.type || '';
    if (type.includes('ogg')) return 'input.ogg';
    if (type.includes('mpeg')) return 'input.mp3';
    if (type.includes('webm')) return 'input.webm';
    if (type.includes('mp4')) return 'input.mp4';
    if (type.includes('wav')) return 'input.wav';
    if (type.includes('m4a')) return 'input.m4a';
    return 'input.bin';
}

export async function convertBlobToOgg(blob) {
    if (!blob) {
        throw new Error('Se requiere un blob válido para convertir con FFmpeg');
    }

    const ffmpeg = await loadFFmpeg();

    return runSerial(async () => {
        const inputName = getInputFileName(blob);
        const outputName = 'output.ogg';

        try {
            if (!fetchFileFn) {
                throw new Error('FFmpeg no se inicializó correctamente');
            }

            const fileData = await fetchFileFn(blob);
            ffmpeg.FS('writeFile', inputName, fileData);

            await ffmpeg.run(
                '-i', inputName,
                '-vn',
                '-acodec', 'libvorbis',
                '-f', 'ogg',
                outputName
            );

            const outputData = ffmpeg.FS('readFile', outputName);
            const buffer = outputData.buffer.slice(
                outputData.byteOffset,
                outputData.byteOffset + outputData.byteLength
            );
            return new Blob([buffer], { type: 'audio/ogg' });
        } finally {
            try { ffmpeg.FS('unlink', inputName); } catch (_) {}
            try { ffmpeg.FS('unlink', outputName); } catch (_) {}
        }
    });
}
