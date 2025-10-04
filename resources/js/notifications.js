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
                const header = document.createElement('div');
                header.className = 'notifications-header flex items-center justify-between mb-2 px-1';
                header.innerHTML = '<span class="text-xs uppercase tracking-wide text-slate-400">Notificaciones</span>' +
                    '<button type="button" class="notif-clear-all text-[10px] font-medium text-slate-500 hover:text-red-400 transition ml-auto px-2 py-1 rounded-md hover:bg-red-500/10" title="Eliminar todas">Limpiar</button>';
                list.appendChild(header);


            notifications.forEach(n => {
                const li = document.createElement('li');
        if (n.type === 'group_invitation') {
                    li.className = 'invitation-item p-3 bg-slate-700/50 rounded-lg mb-2';
                    li.innerHTML = `
                        <div class="message text-sm text-slate-200">${n.message}</div>
                        <div class="meta text-xs text-slate-400">De: ${n.sender_name || (n.from_user ? `${n.from_user.name} (${n.from_user.email})` : (n.remitente ? (n.remitente.full_name || n.remitente.username || 'Usuario') : 'Usuario'))}</div>
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
                                } else if (n.type === 'contact_request') {
                                        const senderName = n.from_user?.name || n.sender_name || n.remitente_name || 'Usuario';
                                        const senderEmail = n.from_user?.email || n.sender_email || '';
                                        li.className = 'contact-request-item p-3 bg-slate-700/60 rounded-lg mb-2 flex flex-col gap-2';
                                        li.innerHTML = `
                                                <div class="flex items-start gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center text-slate-900 font-semibold">${senderName.charAt(0).toUpperCase()}</div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm text-slate-200 font-medium truncate">${senderName}</p>
                                                        <p class="text-[11px] text-slate-500 truncate">${senderEmail}</p>
                                                        <p class="text-[11px] text-slate-400 mt-1">Solicitud de contacto</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 justify-end">
                                                    <button class="contact-req-accept bg-green-600 hover:bg-green-500 text-white text-xs px-3 py-1 rounded-md transition" data-id="${n.id}">Aceptar</button>
                                                    <button class="contact-req-reject bg-red-600 hover:bg-red-500 text-white text-xs px-3 py-1 rounded-md transition" data-id="${n.id}">Rechazar</button>
                                                </div>`;
                                } else {
                    li.className = 'relative p-3 bg-slate-700/60 backdrop-blur rounded-lg mb-2 pr-8 overflow-hidden';
                    li.innerHTML = `
                        <span class="block text-sm text-slate-200 leading-snug">${n.message}</span>
                        <button class="dismiss-btn absolute top-1.5 right-1.5 h-6 w-6 flex items-center justify-center rounded-md text-slate-400 hover:text-white hover:bg-slate-600/60 transition-colors" data-id="${n.id}" aria-label="Cerrar notificación">&times;</button>
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
        document.querySelectorAll('.contact-req-accept').forEach(btn => {
            btn.addEventListener('click', e => {
                const id = e.target.dataset.id; respondContactRequest(id, 'accept');
            });
        });
        document.querySelectorAll('.contact-req-reject').forEach(btn => {
            btn.addEventListener('click', e => {
                const id = e.target.dataset.id; respondContactRequest(id, 'reject');
            });
        });
            document.querySelectorAll('.notifications-list .notif-clear-all').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        const res = await fetch('/api/notifications/clear-all', { method: 'DELETE', headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Error limpiando');
                        notifications = [];
                        render();
                    } catch (err) {
                        console.error('Error al limpiar todas las notificaciones', err);
                    }
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
                            const header = document.createElement('div');
                            header.className = 'notifications-header flex items-center justify-between';
                            header.innerHTML = '<span>Notificaciones</span><button type="button" class="notif-clear-all text-[11px] font-medium text-slate-400 hover:text-red-400 transition" title="Eliminar todas">Limpiar</button>';
                            list.appendChild(header);
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

                // Si se aceptó, refrescar las reuniones compartidas
                if (action === 'accept' && typeof loadSharedMeetings === 'function') {
                    loadSharedMeetings();
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

    async function respondContactRequest(notificationId, action){
        try {
            const res = await fetch(`/api/contacts/requests/${notificationId}/respond`, {
                method:'POST',
                headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                body: JSON.stringify({ action })
            });
            if(!res.ok){ showError('Error al procesar solicitud'); return; }
            // remover notificación local
            notifications = notifications.filter(n => n.id != notificationId);
            render();
            if(action==='accept'){
                showSuccess('Contacto agregado');
                // refrescar contactos si el módulo está cargado
                if(window.contactsModule) {
                    try { window.contactsModule.reload(); } catch(_) {}
                }
            } else {
                showSuccess('Solicitud rechazada');
            }
        } catch(err){
            console.error('contact request respond error', err);
            showError('Error de conexión');
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
            const response = await fetch(url);
            if (response.status === 401) {
                console.log('📧 [notifications] User not authenticated, skipping notifications');
                scheduleNotifFetch();
                return;
            }
            if (response.status === 500) {
                console.warn('📧 [notifications] Server error loading notifications, skipping');
                notifFailures++;
                notifInterval = Math.min(30000, 30000 * notifFailures);
                scheduleNotifFetch();
                return;
            }
            if (!response.ok) {
                let message = 'Error al cargar notificaciones.';
                try {
                    const data = await response.json();
                    if (data.message) message = data.message;
                } catch (_) { /* ignore */ }
                showError(message);
                notifFailures++;
                notifInterval = Math.min(30000, 30000 * notifFailures);
                scheduleNotifFetch();
                return;
            }
            const payload = await response.json();
            if (payload.no_changes) {
                notifFailures = 0;
                notifInterval = 30000; // mantener
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
            showError('Error de conexión al cargar notificaciones.');
            notifFailures++;
            notifInterval = Math.min(60000, 30000 * notifFailures);
            scheduleNotifFetch();
        }
    }

    function init() {
        document.querySelectorAll('.notifications-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                document.querySelectorAll('.notifications-panel').forEach(panel => {
                    const willShow = panel.classList.contains('hidden');
                    panel.classList.toggle('hidden');
                    if (willShow) {
                        positionNotificationsPanel(panel, toggle);
                    }
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

        // Reposicionar en resize si está visible
        window.addEventListener('resize', () => {
            const toggle = document.querySelector('.notifications-toggle');
            if (!toggle) return;
            document.querySelectorAll('.notifications-panel').forEach(panel => {
                if (!panel.classList.contains('hidden')) {
                    positionNotificationsPanel(panel, toggle);
                }
            });
        });


    // Toggle panel al hacer clic en el botón
    document.querySelectorAll('.notifications-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const panel = btn.closest('.notifications').querySelector('.notifications-panel');
            if (panel.classList.contains('hidden')) {
                document.querySelectorAll('.notifications-panel').forEach(p => p.classList.add('hidden'));
                panel.classList.remove('hidden');
                positionNotificationsPanel(panel, btn);
            } else {
                panel.classList.add('hidden');
            }
        });
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

// Posicionamiento dinámico del panel para alinearlo con el botón y evitar que quede demasiado lejos en páginas (ej. perfil)
function positionNotificationsPanel(panel, toggle) {
    try {
        // Asegurar estilos base
        panel.style.position = 'fixed';
        panel.style.zIndex = panel.style.zIndex || '5000';
    panel.style.maxWidth = '360px';
    panel.classList.add('notifications-panel--styled');

        // Calcular ancho disponible
        const viewportWidth = window.innerWidth;
        const desiredWidth = Math.min(360, viewportWidth - 16); // 8px margen lateral
        panel.style.width = desiredWidth + 'px';

        // Para medir la altura real si estaba hidden antes
        const prevVisibility = panel.style.visibility;
        panel.style.visibility = 'hidden';
        panel.style.display = 'block';

        const rect = toggle.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect(); // altura tras forzar display

        // Preferimos debajo del botón
        let top = rect.bottom + 8;
        if (top + panelRect.height > window.innerHeight - 8) {
            // Colocarlo arriba si no cabe
            top = Math.max(8, rect.top - panelRect.height - 8);
        }

        // Alinear borde derecho del panel con el borde derecho del botón
        let left = rect.right - desiredWidth;
        if (left < 8) left = 8; // no salir por la izquierda
        if (left + desiredWidth > viewportWidth - 8) left = viewportWidth - desiredWidth - 8; // no salir por la derecha

        panel.style.top = `${Math.round(top)}px`;
        panel.style.left = `${Math.round(left)}px`;
        panel.style.right = 'auto'; // neutralizar right del CSS
        panel.style.visibility = prevVisibility || '';
    } catch (err) {
        if (import.meta.env?.DEV) console.debug('positionNotificationsPanel error', err);
    }
}

window.notifications = Notifications;
