const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const usersTableBody = document.getElementById('users-table-body');
const alertBox = document.getElementById('admin-users-alert');
const blockModal = document.getElementById('block-user-modal');
const blockForm = document.getElementById('block-user-form');
const blockReasonInput = document.getElementById('block-reason');
const blockDurationSelect = document.getElementById('block-duration');

let usersMap = new Map();
let blockTargetUserId = null;

const showAlert = (type, message) => {
    if (!alertBox) return;

    alertBox.className = '';
    alertBox.classList.add('info-card', 'mb-4');
    alertBox.style.display = 'block';
    alertBox.textContent = message;

    if (type === 'success') {
        alertBox.classList.add('bg-green-500/10', 'text-green-200', 'border', 'border-green-400/40');
    } else {
        alertBox.classList.add('bg-red-500/10', 'text-red-200', 'border', 'border-red-400/40');
    }

    setTimeout(() => {
        alertBox.classList.add('hidden');
        alertBox.style.display = 'none';
    }, 4000);
};

const hideAlert = () => {
    if (!alertBox) return;
    alertBox.className = 'hidden';
    alertBox.textContent = '';
    alertBox.style.display = 'none';
};

const formatDate = (isoString) => {
    if (!isoString) return '—';
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const statusDescriptor = (user) => {
    if (user.blocked_permanent) {
        return {
            text: 'Bloqueado permanentemente',
            className: 'status-badge status-danger',
        };
    }

    if (user.blocked) {
        return {
            text: user.blocked_until_human
                ? `Bloqueado (${user.blocked_until_human})`
                : 'Bloqueado temporalmente',
            className: 'status-badge status-warning',
        };
    }

    return {
        text: 'Activo',
        className: 'status-badge status-success',
    };
};

const closeBlockModal = () => {
    blockModal?.classList.remove('show');
    blockModal?.classList.add('hidden');
    blockTargetUserId = null;
    blockReasonInput.value = '';
    blockDurationSelect.value = '1_day';
};

const openBlockModal = (userId) => {
    blockTargetUserId = userId;
    blockReasonInput.value = '';
    blockDurationSelect.value = '1_day';
    blockModal?.classList.remove('hidden');
    blockModal?.classList.add('show');
    blockReasonInput.focus();
};

const renderUsers = () => {
    hideAlert();

    if (!usersTableBody) return;

    if (!usersMap.size) {
        usersTableBody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-6 text-slate-400">No se encontraron usuarios</td>
            </tr>
        `;
        return;
    }

    const roles = Array.from(new Set(Array.from(usersMap.values()).map((user) => user.roles).filter(Boolean))).sort();
    const fragment = document.createDocumentFragment();

    usersMap.forEach((user) => {
        const row = document.createElement('tr');
        row.dataset.userId = user.id;

        const idCell = document.createElement('td');
        idCell.className = 'px-4 py-3 text-slate-500 text-xs uppercase tracking-widest';
        idCell.textContent = user.id;

        const usernameCell = document.createElement('td');
        usernameCell.className = 'px-4 py-3 text-slate-200 font-semibold';
        usernameCell.textContent = user.username;

        const fullNameCell = document.createElement('td');
        fullNameCell.className = 'px-4 py-3 text-slate-300';
        fullNameCell.textContent = user.full_name || '—';

        const emailCell = document.createElement('td');
        emailCell.className = 'px-4 py-3 text-slate-300';
        emailCell.textContent = user.email;

        const roleCell = document.createElement('td');
        roleCell.className = 'px-4 py-3';
        const roleSelect = document.createElement('select');
        roleSelect.className = 'modal-input text-sm bg-slate-800/60 border border-slate-600/60';

        const addOption = (value, label) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            roleSelect.appendChild(option);
        };

        roles.forEach((role) => addOption(role, role));

        if (!roles.includes(user.roles)) {
            addOption(user.roles, user.roles);
        }

        addOption('__custom', 'Otro rol…');

        roleSelect.value = user.roles;
        roleSelect.addEventListener('change', (event) => {
            const selectedValue = event.target.value;
            if (selectedValue === '__custom') {
                const customValue = window.prompt('Ingresa el nuevo rol para el usuario:', user.roles || '');
                if (!customValue) {
                    roleSelect.value = user.roles;
                    return;
                }
                updateUserRole(user.id, customValue.trim());
                return;
            }
            updateUserRole(user.id, selectedValue);
        });

        roleCell.appendChild(roleSelect);

        const organizationCell = document.createElement('td');
        organizationCell.className = 'px-4 py-3 text-slate-300';
        organizationCell.textContent = user.organization || '—';

        const createdCell = document.createElement('td');
        createdCell.className = 'px-4 py-3 text-slate-300';
        createdCell.textContent = formatDate(user.created_at);

        const updatedCell = document.createElement('td');
        updatedCell.className = 'px-4 py-3 text-slate-300';
        updatedCell.textContent = formatDate(user.updated_at);

        const statusCell = document.createElement('td');
        statusCell.className = 'px-4 py-3';
        const badge = document.createElement('span');
        const descriptor = statusDescriptor(user);
        badge.className = descriptor.className;
        badge.textContent = descriptor.text;
        const tooltipParts = [];
        if (user.blocked_reason) {
            tooltipParts.push(`Motivo: ${user.blocked_reason}`);
        }
        if (user.blocked_until_human && !user.blocked_permanent) {
            tooltipParts.push(`Hasta: ${formatDate(user.blocked_until)}`);
        }
        if (user.blocked_by_name) {
            tooltipParts.push(`Acción registrada por: ${user.blocked_by_name}`);
        }
        if (tooltipParts.length) {
            badge.title = tooltipParts.join('\n');
        }
        statusCell.appendChild(badge);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-4 py-3';
        const actionsWrapper = document.createElement('div');
        actionsWrapper.className = 'flex flex-wrap gap-2 justify-end';

        const blockButton = document.createElement('button');
        blockButton.className = user.blocked ? 'btn btn-secondary btn-sm' : 'btn btn-danger btn-sm';
        blockButton.textContent = user.blocked ? 'Desbloquear' : 'Bloquear';
        blockButton.addEventListener('click', () => {
            if (user.blocked) {
                unblockUser(user.id);
            } else {
                openBlockModal(user.id);
            }
        });

        const deleteButton = document.createElement('button');
        deleteButton.className = 'btn btn-danger btn-sm';
        deleteButton.textContent = 'Eliminar';
        deleteButton.disabled = !user.blocked_permanent;
        deleteButton.title = user.blocked_permanent
            ? 'Eliminar cuenta bloqueada permanentemente'
            : 'Solo disponible para cuentas bloqueadas permanentemente';
        deleteButton.addEventListener('click', () => {
            if (deleteButton.disabled) return;
            const confirmDelete = window.confirm('¿Eliminar definitivamente la cuenta de este usuario? Esta acción no se puede deshacer.');
            if (!confirmDelete) return;
            deleteUser(user.id);
        });

        actionsWrapper.appendChild(blockButton);
        actionsWrapper.appendChild(deleteButton);
        actionsCell.appendChild(actionsWrapper);

        row.appendChild(idCell);
        row.appendChild(usernameCell);
        row.appendChild(fullNameCell);
        row.appendChild(emailCell);
        row.appendChild(roleCell);
        row.appendChild(organizationCell);
        row.appendChild(createdCell);
        row.appendChild(updatedCell);
        row.appendChild(statusCell);
        row.appendChild(actionsCell);

        fragment.appendChild(row);
    });

    usersTableBody.innerHTML = '';
    usersTableBody.appendChild(fragment);
};

const fetchUsers = async () => {
    try {
        const response = await fetch('/admin/users/list', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudieron obtener los usuarios');
        }

        const users = await response.json();
        usersMap = new Map(users.map((user) => [user.id, user]));
        renderUsers();
    } catch (error) {
        showAlert('error', error.message || 'Ocurrió un error al cargar los usuarios');
    }
};

const updateUserRole = async (userId, role) => {
    try {
        const response = await fetch(`/admin/users/${userId}/role`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ role }),
        });

        if (!response.ok) {
            const errorBody = await response.json().catch(() => ({}));
            throw new Error(errorBody.message || 'No se pudo actualizar el rol');
        }

        const updatedUser = await response.json();
        usersMap.set(updatedUser.id, updatedUser);
        renderUsers();
        showAlert('success', 'Rol actualizado correctamente y notificación enviada.');
    } catch (error) {
        showAlert('error', error.message || 'Error al actualizar el rol');
        fetchUsers();
    }
};

const blockUser = async (userId, payload) => {
    try {
        const response = await fetch(`/admin/users/${userId}/block`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const errorBody = await response.json().catch(() => ({}));
            throw new Error(errorBody.message || 'No se pudo bloquear al usuario');
        }

        const updatedUser = await response.json();
        usersMap.set(updatedUser.id, updatedUser);
        renderUsers();
        showAlert('success', 'Usuario bloqueado y notificado correctamente.');
        return true;
    } catch (error) {
        showAlert('error', error.message || 'Error al bloquear al usuario');
        return false;
    }
};

const unblockUser = async (userId) => {
    try {
        const response = await fetch(`/admin/users/${userId}/unblock`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            const errorBody = await response.json().catch(() => ({}));
            throw new Error(errorBody.message || 'No se pudo desbloquear al usuario');
        }

        const updatedUser = await response.json();
        usersMap.set(updatedUser.id, updatedUser);
        renderUsers();
        showAlert('success', 'Usuario desbloqueado correctamente.');
    } catch (error) {
        showAlert('error', error.message || 'Error al desbloquear al usuario');
    }
};

const deleteUser = async (userId) => {
    try {
        const response = await fetch(`/admin/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            const errorBody = await response.json().catch(() => ({}));
            throw new Error(errorBody.message || 'No se pudo eliminar la cuenta');
        }

        usersMap.delete(userId);
        renderUsers();
        showAlert('success', 'Cuenta eliminada definitivamente.');
    } catch (error) {
        showAlert('error', error.message || 'Error al eliminar la cuenta');
    }
};

blockForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!blockTargetUserId) return;

    const reason = blockReasonInput.value.trim();
    const duration = blockDurationSelect.value;

    if (!reason) {
        blockReasonInput.focus();
        return;
    }

    const submitButton = document.getElementById('confirm-block-btn');
    submitButton?.setAttribute('disabled', 'disabled');

    try {
        const success = await blockUser(blockTargetUserId, { reason, duration });
        if (success) {
            closeBlockModal();
        }
    } finally {
        submitButton?.removeAttribute('disabled');
    }
});

blockModal?.addEventListener('click', (event) => {
    if (event.target === blockModal || event.target.dataset.closeBlockModal !== undefined) {
        closeBlockModal();
    }
});

window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && blockModal?.classList.contains('show')) {
        closeBlockModal();
    }
});

if (usersTableBody) {
    fetchUsers();
}
