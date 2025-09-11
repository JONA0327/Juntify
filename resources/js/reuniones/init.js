import './globals';

document.addEventListener('DOMContentLoaded', () => {
    const closeBtn = document.getElementById('closeFullPreview');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            const modal = document.getElementById('fullPreviewModal');
            const frame = document.getElementById('fullPreviewFrame');
            if (frame) frame.src = 'about:blank';
            if (modal) modal.classList.add('hidden');
        });
    }
});
