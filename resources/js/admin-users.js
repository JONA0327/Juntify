const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const usersTableBody = document.getElementById('users-table-body');
const alertBox = document.getElementById('admin-users-alert');
const blockModal = document.getElementById('block-user-modal');
const blockForm = document.getElementById('block-user-form');
const blockReasonInput = document.getElementById('block-reason');
const blockDurationSelect = document.getElementById('block-duration');
const deleteModal = document.getElementById('delete-user-modal');

let usersMap = new Map();
let blockTargetUserId = null;
let deleteTargetUserId = null;

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

const closeDeleteModal = () => {
    deleteModal?.classList.remove('show');
    deleteModal?.classList.add('hidden');
    deleteTargetUserId = null;
};

const openDeleteModal = (userId) => {
    deleteTargetUserId = userId;
    deleteModal?.classList.remove('hidden');
    deleteModal?.classList.add('show');
};

const renderUsers = () => {
    hideAlert();

    if (!usersTableBody) return;

    if (!usersMap.size) {
        usersTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-6 text-slate-400">No se encontraron usuarios</td>
            </tr>
        `;
        return;
    }

    const roles = Array.from(new Set(Array.from(usersMap.values()).map((user) => user.roles).filter(Boolean))).sort();
    const fragment = document.createDocumentFragment();

    usersMap.forEach((user) => {
        const row = document.createElement('tr');
        row.dataset.userId = user.id;

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
        deleteButton.disabled = false;
        deleteButton.title = 'Eliminar cuenta de usuario';
        deleteButton.addEventListener('click', () => {
            openDeleteModal(user.id);
        });

        actionsWrapper.appendChild(blockButton);
        actionsWrapper.appendChild(deleteButton);
        actionsCell.appendChild(actionsWrapper);

        row.appendChild(usernameCell);
        row.appendChild(fullNameCell);
        row.appendChild(emailCell);
        row.appendChild(roleCell);
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

// Delete modal event listeners
const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
confirmDeleteBtn?.addEventListener('click', () => {
    if (deleteTargetUserId) {
        deleteUser(deleteTargetUserId);
        closeDeleteModal();
    }
});

deleteModal?.addEventListener('click', (event) => {
    if (event.target === deleteModal || event.target.dataset.closeDeleteModal !== undefined) {
        closeDeleteModal();
    }
});

window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && blockModal?.classList.contains('show')) {
        closeBlockModal();
    }
    if (event.key === 'Escape' && deleteModal?.classList.contains('show')) {
        closeDeleteModal();
    }
});

if (usersTableBody) {
    fetchUsers();
}
