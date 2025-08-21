const InvitationNotifications = (() => {
    let invitations = [];

    function render() {
        document.querySelectorAll('.invitation-list').forEach(list => {
            list.innerHTML = '';
            invitations.forEach((invitation) => {
                const li = document.createElement('li');
                li.className = 'invitation-item p-3 bg-slate-700/50 rounded-lg mb-2';

                li.innerHTML = `
                    <div class="text-sm text-slate-200 mb-2">${invitation.message}</div>
                    <div class="text-xs text-slate-400 mb-2">De: ${invitation.remitente?.username || 'Usuario'}</div>
                    <div class="flex space-x-2">
                        <button class="accept-btn px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700" data-id="${invitation.id}">Aceptar</button>
                        <button class="reject-btn px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700" data-id="${invitation.id}">Rechazar</button>
                    </div>
                `;

                list.appendChild(li);
            });

            if (invitations.length === 0) {
                const li = document.createElement('li');
                li.className = 'text-slate-400 text-sm text-center py-4';
                li.textContent = 'No hay invitaciones pendientes';
                list.appendChild(li);
            }
        });
        updateIndicator();
        attachEventListeners();
    }

    function updateIndicator() {
        const hasInvitations = invitations.length > 0;
        document.querySelectorAll('.invitation-dot').forEach(dot => {
            dot.classList.toggle('hidden', !hasInvitations);
        });
    }

    function attachEventListeners() {
        document.querySelectorAll('.accept-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                respondToInvitation(id, 'accept');
            });
        });

        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                respondToInvitation(id, 'reject');
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

            if (response.ok) {
                // Remover la invitación de la lista
                invitations = invitations.filter(inv => inv.id != id);
                render();

                const actionText = action === 'accept' ? 'aceptada' : 'rechazada';
                alert(`Invitación ${actionText} correctamente`);
            }
        } catch (error) {
            console.error('Error responding to invitation:', error);
        }
    }

    async function loadInvitations() {
        try {
            const response = await fetch('/api/users/notifications');
            if (response.ok) {
                invitations = await response.json();
                render();
            }
        } catch (error) {
            console.error('Error loading invitations:', error);
        }
    }

    function init() {
        // Panel toggle functionality
        document.querySelectorAll('.invitation-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                document.querySelectorAll('.invitation-panel').forEach(panel => {
                    panel.classList.toggle('hidden');
                });
            });
        });

        // Load invitations on page load
        loadInvitations();

        // Refresh invitations every 30 seconds
        setInterval(loadInvitations, 30000);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        refresh: loadInvitations
    };
})();

window.invitationNotifications = InvitationNotifications;
