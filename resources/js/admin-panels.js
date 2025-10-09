const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const panelsTableBody = document.getElementById('panels-table-body');
const alertBox = document.getElementById('admin-panels-alert');
const createPanelBtn = document.getElementById('create-panel-btn');
const createPanelModal = document.getElementById('create-panel-modal');
const createPanelForm = document.getElementById('create-panel-form');
const companyNameInput = document.getElementById('company-name');
const panelAdminSelect = document.getElementById('panel-admin');
const panelUrlInput = document.getElementById('panel-url');

let panels = [];
let adminOptions = [];
let adminsLoaded = false;

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

const renderPanels = () => {
    hideAlert();

    if (!panelsTableBody) return;

    if (!panels.length) {
        panelsTableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-6 text-slate-400">Aún no hay paneles creados</td>
            </tr>
        `;
        return;
    }

    const fragment = document.createDocumentFragment();

    panels.forEach((panel) => {
        const row = document.createElement('tr');
        row.dataset.panelId = panel.id;

        const companyCell = document.createElement('td');
        companyCell.className = 'px-4 py-3 text-slate-200 font-semibold';
        companyCell.textContent = panel.company_name;

        const adminCell = document.createElement('td');
        adminCell.className = 'px-4 py-3 text-slate-300';
        adminCell.textContent = panel.administrator?.name || '—';

        const emailCell = document.createElement('td');
        emailCell.className = 'px-4 py-3 text-slate-300';
        emailCell.textContent = panel.administrator?.email || '—';

        const roleCell = document.createElement('td');
        roleCell.className = 'px-4 py-3 text-slate-300';
        roleCell.textContent = panel.administrator?.role || '—';

        const urlCell = document.createElement('td');
        urlCell.className = 'px-4 py-3 text-slate-300';
        if (panel.panel_url) {
            const link = document.createElement('a');
            link.href = panel.panel_url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'text-blue-400 hover:text-blue-300 underline';
            link.textContent = panel.panel_url;
            urlCell.appendChild(link);
        } else {
            urlCell.textContent = '—';
        }

        const createdCell = document.createElement('td');
        createdCell.className = 'px-4 py-3 text-slate-300';
        createdCell.textContent = formatDate(panel.created_at);

        row.appendChild(companyCell);
        row.appendChild(adminCell);
        row.appendChild(emailCell);
        row.appendChild(roleCell);
        row.appendChild(urlCell);
        row.appendChild(createdCell);

        fragment.appendChild(row);
    });

    panelsTableBody.innerHTML = '';
    panelsTableBody.appendChild(fragment);
};

const fetchPanels = async () => {
    try {
        const response = await fetch('/admin/panels/list', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudieron cargar los paneles');
        }

        panels = await response.json();
        renderPanels();
    } catch (error) {
        showAlert('error', error.message || 'Ocurrió un error al cargar los paneles');
    }
};

const fetchEligibleAdmins = async () => {
    if (adminsLoaded) return;

    try {
        const response = await fetch('/admin/panels/eligible-admins', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudieron obtener los administradores disponibles');
        }

        adminOptions = await response.json();
        adminsLoaded = true;
        fillAdminSelect();
    } catch (error) {
        showAlert('error', error.message || 'Error al cargar administradores elegibles');
    }
};

const fillAdminSelect = () => {
    if (!panelAdminSelect) return;

    // Clean existing options except first placeholder
    panelAdminSelect.querySelectorAll('option:not([disabled])').forEach((option) => option.remove());

    if (!adminOptions.length) {
        const option = document.createElement('option');
        option.value = '';
        option.disabled = true;
        option.textContent = 'Sin usuarios elegibles disponibles';
        panelAdminSelect.appendChild(option);
        return;
    }

    adminOptions.forEach((admin) => {
        const option = document.createElement('option');
        option.value = admin.id;
        option.textContent = `${admin.name} — ${admin.email} (${admin.role})`;
        panelAdminSelect.appendChild(option);
    });
};

const openCreatePanelModal = () => {
    createPanelModal?.classList.remove('hidden');
    createPanelModal?.classList.add('show');
    companyNameInput.value = '';
    panelUrlInput.value = '';
    if (panelAdminSelect) {
        panelAdminSelect.selectedIndex = 0;
    }
    companyNameInput.focus();
    fetchEligibleAdmins();
};

const closeCreatePanelModal = () => {
    createPanelModal?.classList.remove('show');
    createPanelModal?.classList.add('hidden');
};

createPanelBtn?.addEventListener('click', openCreatePanelModal);

createPanelModal?.addEventListener('click', (event) => {
    if (event.target === createPanelModal || event.target.dataset.closePanelModal !== undefined) {
        closeCreatePanelModal();
    }
});

window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && createPanelModal?.classList.contains('show')) {
        closeCreatePanelModal();
    }
});

createPanelForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const companyName = companyNameInput.value.trim();
    const administratorId = panelAdminSelect?.value;
    const panelUrl = panelUrlInput.value.trim();

    if (!companyName || !administratorId) {
        showAlert('error', 'Completa los campos requeridos para crear el panel.');
        return;
    }

    const submitButton = document.getElementById('confirm-create-panel');
    submitButton?.setAttribute('disabled', 'disabled');

    try {
        const response = await fetch('/admin/panels', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                company_name: companyName,
                administrator_id: administratorId,
                panel_url: panelUrl,
            }),
        });

        if (!response.ok) {
            const errorBody = await response.json().catch(() => ({}));
            throw new Error(errorBody.message || 'No se pudo crear el panel');
        }

        const newPanel = await response.json();
        panels.unshift(newPanel);
        renderPanels();
        showAlert('success', 'Panel creado correctamente. El administrador seleccionado ya cuenta con acceso.');
        closeCreatePanelModal();
    } catch (error) {
        showAlert('error', error.message || 'Error al crear el panel');
    } finally {
        submitButton?.removeAttribute('disabled');
    }
});

if (panelsTableBody) {
    fetchPanels();
}
