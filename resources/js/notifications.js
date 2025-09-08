import { showError } from './utils/alerts.js';

const Notifications = (() => {
    let notifications = [];

    function render() {
        document.querySelectorAll('.notifications-list').forEach(list => {
            list.innerHTML = '';
            if (notifications.length === 0) {
                const li = document.createElement('li');
                li.className = 'text-slate-400 text-sm text-center py-4';
                li.textContent = 'No hay notificaciones';
                list.appendChild(li);
                return;
            }

            notifications.forEach(n => {
                const li = document.createElement('li');
                if (n.type === 'group_invitation') {
                    li.className = 'invitation-item p-3 bg-slate-700/50 rounded-lg mb-2';
                    li.innerHTML = `
                        <div class="message text-sm text-slate-200">${n.message}</div>
                        <div class="meta text-xs text-slate-400">De: ${n.remitente ? `${n.remitente.full_name} (@${n.remitente.username})` : 'Usuario'}</div>
                        <div class="actions">
                            <button class="accept-btn" data-id="${n.id}">Aceptar</button>
                            <button class="reject-btn" data-id="${n.id}">Rechazar</button>
                        </div>
                    `;
                } else if (n.type === 'audio_upload') {
                    const name = n.data?.meeting_name || 'Audio';
                    li.className = 'upload-item p-3 bg-slate-700/50 rounded-lg mb-2 flex justify-between items-center';
                    li.innerHTML = `
                        <span class="text-sm text-slate-200">${name} - ${n.message}</span>
                        <button class="dismiss-btn text-slate-400 hover:text-white" data-id="${n.id}">&times;</button>
                    `;
                } else {
                    li.className = 'p-3 bg-slate-700/50 rounded-lg mb-2 flex justify-between items-center';
                    li.innerHTML = `
                        <span class="text-sm text-slate-200">${n.message}</span>
                        <button class="dismiss-btn text-slate-400 hover:text-white" data-id="${n.id}">&times;</button>
                    `;
                }
                list.appendChild(li);
            });
        });
        updateIndicator();
        attachListeners();
    }

    function updateIndicator() {
        const has = notifications.length > 0;
        document.querySelectorAll('.notifications-dot').forEach(dot => {
            dot.classList.toggle('hidden', !has);
        });
    }

    function attachListeners() {
        document.querySelectorAll('.accept-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const id = e.target.dataset.id;
                respondToInvitation(id, 'accept');
            });
        });
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const id = e.target.dataset.id;
                respondToInvitation(id, 'reject');
            });
        });
        document.querySelectorAll('.dismiss-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const id = e.target.dataset.id;
                dismissNotification(id);
            });
        });
    }

    async function respondToInvitation(id, action) {
        try {
            const response = await fetch(`/api/users/notifications/${id}/respond`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ action })
            });
            if (response.status === 401) {
                showError('Tu sesión ha expirado. Inicia sesión nuevamente.');
                return;
            }
            if (!response.ok) {
                let message = 'Error al responder la invitación.';
                try {
                    const data = await response.json();
                    if (data.message) message = data.message;
                } catch (_) { /* ignore */ }
                showError(message);
                return;
            }
            notifications = notifications.filter(n => n.id != id);
            render();
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('Error responding to invitation:', error);
            }
            showError('Error de conexión al responder la invitación.');
        }
    }

    async function dismissNotification(id) {
        try {
            const response = await fetch(`/api/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.status === 401) {
                showError('Tu sesión ha expirado. Inicia sesión nuevamente.');
                return;
            }
            if (!response.ok) {
                let message = 'Error al descartar la notificación.';
                try {
                    const data = await response.json();
                    if (data.message) message = data.message;
                } catch (_) { /* ignore */ }
                showError(message);
                return;
            }
            notifications = notifications.filter(n => n.id != id);
            render();
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('Error dismissing notification:', error);
            }
            showError('Error de conexión al descartar la notificación.');
        }
    }

    async function fetchNotifications() {
        try {
            const response = await fetch('/api/notifications');
            if (response.status === 401) {
                console.log('📧 [notifications] User not authenticated, skipping notifications');
                return;
            }
            if (response.status === 500) {
                console.warn('📧 [notifications] Server error loading notifications, skipping');
                return;
            }
            if (!response.ok) {
                let message = 'Error al cargar notificaciones.';
                try {
                    const data = await response.json();
                    if (data.message) message = data.message;
                } catch (_) { /* ignore */ }
                showError(message);
                return;
            }
            notifications = await response.json();
            render();
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('Error loading notifications:', error);
            }
            showError('Error de conexión al cargar notificaciones.');
        }
    }

    function init() {
        document.querySelectorAll('.notifications-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                document.querySelectorAll('.notifications-panel').forEach(panel => {
                    panel.classList.toggle('hidden');
                });
            });
        });

        // Cerrar al hacer click fuera del panel
        document.addEventListener('click', (e) => {
            const inside = e.target.closest('.notifications');
            if (!inside) {
                document.querySelectorAll('.notifications-panel').forEach(panel => panel.classList.add('hidden'));
            }
        });

        fetchNotifications();
        setInterval(fetchNotifications, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        refresh: fetchNotifications
    };
})();

window.notifications = Notifications;
