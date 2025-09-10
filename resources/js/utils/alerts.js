function ensureToastContainer() {
    let container = document.getElementById('global-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'global-toast-container';
        container.style.position = 'fixed';
        container.style.top = '72px'; // slightly below navbar
        container.style.left = '50%';
        container.style.transform = 'translateX(-50%)';
        container.style.width = 'min(420px, calc(100% - 2rem))';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        container.style.zIndex = '9999';
        container.style.pointerEvents = 'none';
        document.body.appendChild(container);
    }
    return container;
}

function createToast(message, bgClasses = []) {
    const container = ensureToastContainer();
    const el = document.createElement('div');
    el.className = 'p-4 rounded-lg shadow-lg w-full transform transition-all duration-300 opacity-0 translate-y-2';
    if (Array.isArray(bgClasses)) {
        el.classList.add(...bgClasses);
    }
    el.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 18a9 9 0 110-18 9 9 0 010 18z"/></svg>
            </div>
            <div class="text-sm leading-5">${message}</div>
        </div>`;
    el.style.pointerEvents = 'auto';
    container.appendChild(el);
    setTimeout(() => el.classList.remove('opacity-0', 'translate-y-2'), 10);
    setTimeout(() => {
        el.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => el.remove(), 200);
    }, 4000);
}

export function showError(message) {
    createToast(message, ['bg-red-500', 'text-white']);
}

export function showSuccess(message) {
    createToast(message, ['bg-green-600', 'text-white']);
}
