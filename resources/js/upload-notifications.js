const UploadNotifications = (() => {
    const activeUploads = [];
    window.activeUploads = activeUploads;

    function render() {
        document.querySelectorAll('.upload-list').forEach(list => {
            list.innerHTML = '';
            activeUploads.forEach((task) => {
                const li = document.createElement('li');
                const percent = Math.round(task.progress * 100);
                li.textContent = `${task.name} - ${task.status} (${percent}%)`;
                list.appendChild(li);
            });
        });
        updateIndicator();
    }

    function updateIndicator() {
        const active = activeUploads.some(t => t.status === 'Subiendo...');
        document.querySelectorAll('.upload-dot').forEach(dot => {
            dot.classList.toggle('hidden', !active);
        });
    }

    function add(name) {
        const id = Date.now().toString(36) + Math.random().toString(36).substring(2);
        activeUploads.push({ id, name, status: 'Subiendo...', progress: 0 });
        render();
        return id;
    }

    function progress(id, loaded, total) {
        const task = activeUploads.find(t => t.id === id);
        if (task && total > 0) {
            task.progress = loaded / total;
            render();
        }
    }

    function success(id) {
        const task = activeUploads.find(t => t.id === id);
        if (task) {
            task.status = 'Completado';
            task.progress = 1;
            render();
            setTimeout(() => remove(id), 3000);
        }
    }

    function error(id) {
        const task = activeUploads.find(t => t.id === id);
        if (task) {
            task.status = 'Error';
            render();
            setTimeout(() => remove(id), 3000);
        }
    }

    function remove(id) {
        const index = activeUploads.findIndex(t => t.id === id);
        if (index !== -1) {
            activeUploads.splice(index, 1);
            render();
        }
    }

    function init() {
        document.querySelectorAll('.upload-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const panel = btn.parentElement.querySelector('.upload-panel');
                if (panel) {
                    panel.classList.toggle('hidden');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        init();
        render();
    });

    return { add, progress, success, error, remove };
})();

window.uploadNotifications = UploadNotifications;
