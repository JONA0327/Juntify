document.addEventListener('DOMContentLoaded', () => {
    if (typeof GoogleConnectionMonitor !== 'undefined') {
        const monitor = new GoogleConnectionMonitor();
        monitor.init();
    }
});
