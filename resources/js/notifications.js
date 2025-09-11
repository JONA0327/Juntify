import { showError, showSuccess } from './utils/alerts.js';

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
                } else if (n.type === 'meeting_share_request') {
                    li.className = 'invitation-item p-3 bg-slate-700/50 rounded-lg mb-2';
                    li.innerHTML = `
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-yellow-400/20 rounded-lg flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                                </svg>
                            </div>
                            <div class="flex-grow">
                                <div class="message text-sm text-slate-200 font-medium">${n.title}</div>
                                <div class="message text-sm text-slate-300 mt-1">${n.message}</div>
                                <div class="meta text-xs text-slate-400 mt-2">De: ${n.from_user ? `${n.from_user.name} (${n.from_user.email})` : 'Usuario'}</div>
                                <div class="actions mt-3 flex gap-2">
                                    <button class="accept-share-btn bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded-md transition-colors" data-id="${n.id}" data-shared-meeting-id="${n.data.shared_meeting_id}">Aceptar</button>
                                    <button class="reject-share-btn bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded-md transition-colors" data-id="${n.id}" data-shared-meeting-id="${n.data.shared_meeting_id}">Rechazar</button>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (n.type === 'audio_upload') {
                    const name = n.data?.meeting_name || 'Audio';
                    li.className = 'upload-item p-3 bg-slate-700/50 rounded-lg mb-2 flex justify-between items-center';
                    li.innerHTML = `
                        <span class="text-sm text-slate-200">${name} - ${n.message}</span>
                        <button class="dismiss-btn text-slate-400 hover:text-white" data-id="${n.id}">&times;</button>
                    `;
                } else if (n.type === 'audio_upload_progress') {
                    const name = n.data?.meeting_name || 'Audio';
                    li.className = 'progress-item p-3 bg-blue-700/50 rounded-lg mb-2 flex items-center gap-3';
                    li.innerHTML = `
                        <div class="flex-shrink-0">
                            <div class="w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
                        </div>
                        <span class="text-sm text-blue-200 flex-grow">${name} - ${n.message}</span>
                    `;
                } else if (n.type === 'audio_upload_success') {
                    const name = n.data?.meeting_name || 'Audio';
                    li.className = 'success-item p-3 bg-green-700/50 rounded-lg mb-2 flex justify-between items-center';
                    li.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-sm text-green-200">${name} - ${n.message}</span>
                        </div>
                        <button class="dismiss-btn text-green-400 hover:text-white" data-id="${n.id}">&times;</button>
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
        document.querySelectorAll('.accept-share-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const sharedMeetingId = e.target.dataset.sharedMeetingId;
                const notificationId = e.target.dataset.id;
                respondToMeetingShareInvitation(sharedMeetingId, 'accept', notificationId);
            });
        });
        document.querySelectorAll('.reject-share-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const sharedMeetingId = e.target.dataset.sharedMeetingId;
                const notificationId = e.target.dataset.id;
                respondToMeetingShareInvitation(sharedMeetingId, 'reject', notificationId);
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

    async function respondToMeetingShareInvitation(sharedMeetingId, action, notificationId) {
        try {
            const response = await fetch('/api/shared-meetings/respond', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    shared_meeting_id: sharedMeetingId,
                    action: action,
                    notification_id: notificationId || null
                })
            });

            if (response.status === 401) {
                showError('Tu sesión ha expirado. Inicia sesión nuevamente.');
                return;
            }

            if (!response.ok) {
                let message = 'Error al responder la invitación.';
                try {
                    const data = await response.json();
                    if (data.errors?.shared_meeting_id) message = data.errors.shared_meeting_id[0];
                    else if (data.errors?.action) message = data.errors.action[0];
                    else if (data.message) message = data.message;
                } catch (_) { /* ignore */ }
                showError(message);
                return;
            }

            const data = await response.json();
            if (data.success) {
                // Remover notificación de la lista
                if (notificationId) {
                    notifications = notifications.filter(n => n.id != notificationId);
                } else {
                    notifications = notifications.filter(n => n.data?.shared_meeting_id != sharedMeetingId);
                }
                render();

                // Mostrar mensaje de éxito
                const actionText = action === 'accept' ? 'aceptado' : 'rechazado';
                showSuccess(`Has ${actionText} la invitación exitosamente.`);

                // Si se aceptó, refrescar las reuniones compartidas si estamos en esa pestaña
                if (action === 'accept' && window.location.pathname.includes('reuniones')) {
                    const activeTab = document.querySelector('.tab-transition.bg-slate-700\\/50');
                    if (activeTab && activeTab.dataset.target === 'shared-meetings') {
                        if (typeof loadSharedMeetings === 'function') {
                            loadSharedMeetings();
                        }
                    }
                }
            } else {
                showError(data.message || 'Error al responder la invitación.');
            }
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('Error responding to meeting share invitation:', error);
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

    let notifLastIso = null;
    let notifInterval = 30000;
    let notifFailures = 0;
    let notifTimer = null;

    function scheduleNotifFetch() {
        if (notifTimer) clearTimeout(notifTimer);
        notifTimer = setTimeout(fetchNotifications, notifInterval);
    }

    async function fetchNotifications() {
        try {
            const url = notifLastIso ? `/api/notifications?since=${encodeURIComponent(notifLastIso)}` : '/api/notifications';
            const response = await fetch(url, { headers: { 'Accept':'application/json' }});
            if (response.status === 401) {
                console.log('📧 [notifications] User not authenticated, skipping notifications');
                scheduleNotifFetch();
                return;
            }
            // Leer Retry-After si viene del backend
            const retryAfterHeader = response.headers.get('Retry-After');
            let retryAfterMs = retryAfterHeader ? parseInt(retryAfterHeader,10)*1000 : null;
            let payload = null;
            let isJson = false;
            try { payload = await response.json(); isJson = true; } catch(_) { /* ignore */ }

            if (!response.ok) {
                console.warn('📧 [notifications] HTTP '+response.status+' error');
                notifFailures++;
                if (payload?.rate_limited) {
                    notifInterval = retryAfterMs || Math.min(60000, 15000 * notifFailures);
                } else {
                    notifInterval = Math.min(60000, 15000 * notifFailures);
                }
                scheduleNotifFetch();
                return;
            }

            if (payload?.rate_limited) {
                // Servicio degradado: no forzar render si ya tenemos datos y la respuesta viene vacía
                console.warn('📧 [notifications] Servicio degradado (cache/backoff)');
                if (payload.notifications && payload.notifications.length) {
                    notifications = payload.notifications;
                    render();
                }
                notifFailures++;
                notifInterval = retryAfterMs || Math.min(60000, 15000 * notifFailures);
                notifLastIso = payload.last_updated || notifLastIso;
                scheduleNotifFetch();
                return;
            }

            if (payload.no_changes) {
                notifFailures = 0;
                notifInterval = 30000;
            } else {
                const list = Array.isArray(payload) ? payload : (payload.notifications || []);
                notifications = list;
                render();
                notifFailures = 0;
                notifInterval = 30000; // reset
            }
            notifLastIso = payload.last_updated || notifLastIso;
            scheduleNotifFetch();
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('Error loading notifications:', error);
            }
            notifFailures++;
            notifInterval = Math.min(60000, 15000 * notifFailures);
            scheduleNotifFetch();
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
