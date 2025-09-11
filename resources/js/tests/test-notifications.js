let lastNotificationId = null;

function showResult(data) {
    document.getElementById('result').classList.remove('hidden');
    document.getElementById('result-content').textContent = JSON.stringify(data, null, 2);
}

async function createProgressNotification() {
    try {
        const response = await fetch('/api/notifications', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                type: 'audio_upload_progress',
                message: 'Subiendo test-audio.mp4...',
                data: {
                    meeting_name: 'test-audio.mp4',
                    status: 'progress'
                }
            })
        });

        const result = await response.json();
        lastNotificationId = result.id;
        showResult(result);
        updateSimulatedNotifications();
    } catch (error) {
        showResult({ error: error.message });
    }
}

async function createSuccessNotification() {
    try {
        const response = await fetch('/api/notifications', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                type: 'audio_upload_success',
                message: 'Se ha subido audio pospuesto a la carpeta: Grabaciones/Audios Pospuestos',
                data: {
                    meeting_name: 'test-audio.mp4',
                    root_folder: 'Grabaciones',
                    subfolder: 'Audios Pospuestos',
                    status: 'success'
                }
            })
        });

        const result = await response.json();
        lastNotificationId = result.id;
        showResult(result);
        updateSimulatedNotifications();
    } catch (error) {
        showResult({ error: error.message });
    }
}

async function dismissNotification() {
    if (!lastNotificationId) {
        showResult({ error: 'No hay notificación para descartar' });
        return;
    }

    try {
        const response = await fetch(`/api/notifications/${lastNotificationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const result = await response.json();
        showResult(result);
        lastNotificationId = null;
        updateSimulatedNotifications();
    } catch (error) {
        showResult({ error: error.message });
    }
}

async function updateSimulatedNotifications() {
    try {
        const response = await fetch('/api/notifications');
        const notifications = await response.json();

        const list = document.getElementById('notifications-list');
        list.innerHTML = '';

        if (notifications.length === 0) {
            const li = document.createElement('li');
            li.className = 'text-gray-400 text-sm text-center py-4';
            li.textContent = 'No hay notificaciones';
            list.appendChild(li);
            return;
        }

        notifications.forEach(n => {
            const li = document.createElement('li');
            if (n.type === 'audio_upload_progress') {
                li.className = 'progress-item p-3 bg-blue-700/50 rounded-lg mb-2 flex items-center gap-3';
                li.innerHTML = `
                    <div class="flex-shrink-0">
                        <div class="w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <span class="text-sm text-blue-200 flex-grow">${n.data?.meeting_name || 'Audio'} - ${n.message}</span>
                `;
            } else if (n.type === 'audio_upload_success') {
                li.className = 'success-item p-3 bg-green-700/50 rounded-lg mb-2 flex justify-between items-center';
                li.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <span class="text-sm text-green-200">${n.data?.meeting_name || 'Audio'} - ${n.message}</span>
                    </div>
                    <button class="dismiss-btn text-green-400 hover:text-white" onclick="dismissSpecific(${n.id})">&times;</button>
                `;
            } else {
                li.className = 'p-3 bg-gray-700/50 rounded-lg mb-2 flex justify-between items-center';
                li.innerHTML = `
                    <span class="text-sm text-gray-200">${n.message}</span>
                    <button class="dismiss-btn text-gray-400 hover:text-white" onclick="dismissSpecific(${n.id})">&times;</button>
                `;
            }
            list.appendChild(li);
        });
    } catch (error) {
        console.error('Error updating notifications:', error);
    }
}

async function dismissSpecific(id) {
    try {
        const response = await fetch(`/api/notifications/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        await response.json();
        updateSimulatedNotifications();
    } catch (error) {
        console.error('Error dismissing notification:', error);
    }
}

// Event listeners
document.getElementById('test-progress').addEventListener('click', createProgressNotification);
document.getElementById('test-success').addEventListener('click', createSuccessNotification);
document.getElementById('test-dismiss').addEventListener('click', dismissNotification);

// Load initial notifications
updateSimulatedNotifications();

