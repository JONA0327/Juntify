const UploadNotifications = (() => {
    const tasks = new Map();

    function render() {
        document.querySelectorAll('.upload-list').forEach(list => {
            list.innerHTML = '';
            tasks.forEach((task) => {
                const li = document.createElement('li');
                li.textContent = `${task.name} - ${task.status}`;
                list.appendChild(li);
            });
        });
        updateIndicator();
    }

    function updateIndicator() {
        const active = Array.from(tasks.values()).some(t => t.status === 'Subiendo...');
        document.querySelectorAll('.upload-dot').forEach(dot => {
            dot.classList.toggle('hidden', !active);
        });
    }

    function add(name) {
        const id = Date.now().toString(36) + Math.random().toString(36).substring(2);
        tasks.set(id, { name, status: 'Subiendo...' });
        render();
        return id;
    }

    function success(id) {
        if (tasks.has(id)) {
            tasks.get(id).status = 'Completado';
            render();
            setTimeout(() => remove(id), 3000);
        }
    }

    function error(id) {
        if (tasks.has(id)) {
            tasks.get(id).status = 'Error';
            render();
            setTimeout(() => remove(id), 3000);
        }
    }

    function remove(id) {
        tasks.delete(id);
        render();
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

    document.addEventListener('DOMContentLoaded', init);

    return { add, success, error, remove };
})();

window.uploadNotifications = UploadNotifications;
