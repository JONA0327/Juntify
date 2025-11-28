function closeOrganizationMeetingModal() {
    const modal = document.getElementById('meeting-modal');
    if (!modal) return;

    modal.classList.add('hidden');
    const audioPlayer = document.getElementById('meeting-audio-player');
    if (audioPlayer) {
        audioPlayer.pause();
    }
    if (typeof stopMeetingTempCountdown === 'function') {
        stopMeetingTempCountdown();
    }
}

function openOrganizationMeetingModal(meetingId) {
    if (!meetingId) {
        console.error('ID de reunión no válido');
        return;
    }

    const modal = document.getElementById('meeting-modal');
    const loadingEl = document.getElementById('meeting-modal-loading');
    const errorEl = document.getElementById('meeting-modal-error');
    const contentEl = document.getElementById('meeting-modal-content');

    if (!modal || !loadingEl || !errorEl || !contentEl) return;

    modal.classList.remove('hidden');
    loadingEl.classList.remove('hidden');
    errorEl.classList.add('hidden');
    contentEl.classList.add('hidden');

    fetch(`/api/meetings/${meetingId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            loadingEl.classList.add('hidden');

            if (!data.success || !data.meeting) {
                throw new Error(data.message || data.error || 'Error al cargar reunión');
            }

            const meeting = data.meeting;

            document.getElementById('meeting-modal-title').textContent = meeting.meeting_name || meeting.title || 'Reunión sin título';
            document.getElementById('meeting-modal-date').textContent = meeting.created_at || '';

            const audioSection = document.getElementById('meeting-audio-section');
            const audioPlayer = document.getElementById('meeting-audio-player');
            const tempWarning = document.getElementById('meeting-modal-temp-warning');
            const tempCountdown = document.getElementById('meeting-modal-temp-countdown');
            const tempRetentionEl = document.getElementById('meeting-modal-temp-retention');
            const tempActionEl = document.getElementById('meeting-modal-temp-action');

            audioPlayer.pause();
            audioPlayer.removeAttribute('src');
            try { audioPlayer.load(); } catch (_) {}

            const audioSrc = meeting.audio_path || '';
            const fallbackUrl = meeting?.id ? `/api/meetings/${meeting.id}/audio?ts=${Date.now()}` : null;
            const origin = window.location.origin;
            const isExternalAudio = !!(audioSrc && origin && !audioSrc.startsWith(origin));

            if (!audioSrc && !fallbackUrl) {
                audioSection.classList.add('hidden');
            } else {
                audioSection.classList.remove('hidden');
                let triedFallback = false;
                audioPlayer.addEventListener('error', () => {
                    if (!triedFallback && fallbackUrl && audioPlayer.src !== fallbackUrl) {
                        triedFallback = true;
                        audioPlayer.src = fallbackUrl;
                        try { audioPlayer.load(); } catch (_) {}
                    }
                }, { once: true });

                if (isExternalAudio && fallbackUrl) {
                    audioPlayer.src = fallbackUrl;
                    try { audioPlayer.load(); } catch (_) {}
                } else if (audioSrc) {
                    audioPlayer.src = audioSrc;
                    try { audioPlayer.load(); } catch (_) {}
                } else if (fallbackUrl) {
                    audioPlayer.src = fallbackUrl;
                    try { audioPlayer.load(); } catch (_) {}
                }
            }

            if (tempWarning) {
                const canShowTemp = meeting.storage_type === 'temp' && (typeof isBasicOrFreeRole === 'function'
                    ? isBasicOrFreeRole()
                    : ((window.userRole || '').toString().toLowerCase() === 'basic' || (window.userRole || '').toString().toLowerCase() === 'free'));
                if (canShowTemp) {
                    tempWarning.classList.remove('hidden');
                    if (tempRetentionEl) {
                        const retentionDays = Number(meeting.retention_days ?? window.tempRetentionDays ?? 7);
                        tempRetentionEl.textContent = `${retentionDays} ${retentionDays === 1 ? 'día' : 'días'}`;
                    }
                    if (tempActionEl) {
                        tempActionEl.textContent = meeting.storage_reason === 'drive_not_connected'
                            ? 'Conecta tu Google Drive desde tu perfil para guardarla permanentemente.'
                            : 'Actualiza tu plan para guardarla permanentemente en Google Drive.';
                    }
                    if (tempCountdown) {
                        if (typeof startMeetingTempCountdown === 'function' && meeting.expires_at) {
                            startMeetingTempCountdown(meeting.expires_at, tempCountdown);
                        } else if (meeting.time_remaining) {
                            tempCountdown.textContent = meeting.time_remaining;
                        }
                    }
                } else {
                    tempWarning.classList.add('hidden');
                    if (typeof stopMeetingTempCountdown === 'function') {
                        stopMeetingTempCountdown();
                    }
                }
            }

            const summaryEl = document.getElementById('meeting-summary');
            summaryEl.textContent = meeting.summary || 'No hay resumen disponible';

            const keypointsEl = document.getElementById('meeting-keypoints');
            keypointsEl.innerHTML = '';
            if (Array.isArray(meeting.key_points) && meeting.key_points.length > 0) {
                meeting.key_points.forEach(point => {
                    const li = document.createElement('li');
                    li.className = 'flex items-start';

                    const bullet = document.createElement('span');
                    bullet.className = 'text-yellow-400 mt-1 mr-2';
                    bullet.textContent = '•';

                    const text = document.createElement('span');
                    text.textContent = point;

                    li.appendChild(bullet);
                    li.appendChild(text);
                    keypointsEl.appendChild(li);
                });
            } else {
                const emptyItem = document.createElement('li');
                emptyItem.className = 'text-slate-500';
                emptyItem.textContent = 'No hay puntos claves disponibles';
                keypointsEl.appendChild(emptyItem);
            }

            const transcriptionEl = document.getElementById('meeting-transcription');
            transcriptionEl.innerHTML = '';
            const segments = Array.isArray(meeting.segments) ? meeting.segments : [];
            if (segments.length > 0) {
                segments.forEach(segment => {
                    const containerDiv = document.createElement('div');
                    containerDiv.className = 'bg-slate-700/30 rounded p-3 border-l-2 border-yellow-400';

                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between mb-2';

                    const speakerSpan = document.createElement('span');
                    speakerSpan.className = 'text-yellow-400 font-medium';
                    speakerSpan.textContent = segment.speaker || 'Desconocido';

                    const timeSpan = document.createElement('span');
                    timeSpan.className = 'text-xs text-slate-500';
                    timeSpan.textContent = segment.time || segment.timestamp || '';

                    header.appendChild(speakerSpan);
                    header.appendChild(timeSpan);

                    const textParagraph = document.createElement('p');
                    textParagraph.className = 'text-slate-300';
                    textParagraph.textContent = segment.text || '';

                    containerDiv.appendChild(header);
                    containerDiv.appendChild(textParagraph);
                    transcriptionEl.appendChild(containerDiv);
                });
            } else {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'text-slate-500 p-3';
                emptyDiv.textContent = 'No hay transcripción disponible';
                transcriptionEl.appendChild(emptyDiv);
            }

            const tasksSection = document.getElementById('meeting-tasks-section');
            const tasksEl = document.getElementById('meeting-tasks');
            tasksEl.innerHTML = '';
            const tasks = Array.isArray(meeting.tasks) ? meeting.tasks : [];
            if (tasks.length > 0) {
                tasks.forEach(task => {
                    const taskDiv = document.createElement('div');
                    taskDiv.className = 'bg-slate-700/30 rounded p-3 flex items-start';

                    const icon = document.createElement('svg');
                    icon.className = 'w-4 h-4 text-yellow-400 mt-1 mr-3 flex-shrink-0';
                    icon.setAttribute('fill', 'none');
                    icon.setAttribute('stroke', 'currentColor');
                    icon.setAttribute('viewBox', '0 0 24 24');
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />';

                    const content = document.createElement('div');
                    content.className = 'flex-1';

                    const description = document.createElement('p');
                    description.className = 'text-slate-300';
                    const taskDescription = task.description || task.descripcion || task.title || task.tarea || (typeof task === 'string' ? task : '');
                    description.textContent = taskDescription;

                    content.appendChild(description);

                    const assignee = task.assignee || task.asignado;
                    if (assignee) {
                        const assigneeText = document.createElement('p');
                        assigneeText.className = 'text-xs text-slate-500 mt-1';
                        assigneeText.textContent = `Asignado a: ${assignee}`;
                        content.appendChild(assigneeText);
                    }

                    taskDiv.appendChild(icon);
                    taskDiv.appendChild(content);
                    tasksEl.appendChild(taskDiv);
                });
                tasksSection.classList.remove('hidden');
            } else {
                tasksSection.classList.add('hidden');
            }

            contentEl.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error al cargar reunión:', error);
            loadingEl.classList.add('hidden');
            errorEl.classList.remove('hidden');
            document.getElementById('meeting-modal-error-text').textContent = error.message || 'Error desconocido';
        });
}

function initOrganizationMeetingModal() {
    const modal = document.getElementById('meeting-modal');
    if (!modal) return;

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeOrganizationMeetingModal();
        }
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeOrganizationMeetingModal();
        }
    });
}

document.addEventListener('DOMContentLoaded', initOrganizationMeetingModal);

window.closeMeetingModal = closeOrganizationMeetingModal;
window.openMeetingModal = openOrganizationMeetingModal;
