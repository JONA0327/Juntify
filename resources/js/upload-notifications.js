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

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.classList.add('upload-dismiss');
                btn.textContent = '×';
                btn.addEventListener('click', () => dismiss(task.id));
                li.appendChild(btn);

                list.appendChild(li);
            });
        });
        updateIndicator();
    }

    function updateIndicator() {
        const active = activeUploads.some(t =>
            ['Subiendo...', 'Procesando...'].includes(t.status)
        );
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

    function processing(id) {
        const task = activeUploads.find(t => t.id === id);
        if (task) {
            task.status = 'Procesando...';
            task.progress = 1;
            render();
        }
    }

    function success(id, statusText = 'Completado') {
        const task = activeUploads.find(t => t.id === id);
        if (task) {
            task.status = statusText;
            task.progress = 1;
            render();
        }
    }

    function error(id, statusText = 'Error') {
        const task = activeUploads.find(t => t.id === id);
        if (task) {
            task.status = statusText;
            render();
        }
    }

    function remove(id) {
        const index = activeUploads.findIndex(t => t.id === id);
        if (index !== -1) {
            activeUploads.splice(index, 1);
            render();
        }
    }

    function dismiss(id) {
        remove(id);
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

    return { add, progress, processing, success, error, dismiss };
})();

window.uploadNotifications = UploadNotifications;
