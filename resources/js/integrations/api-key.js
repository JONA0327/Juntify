const INTEGRATIONS_STORAGE_KEY = 'juntifyApiToken';
const INTEGRATIONS_BASE_URL = '/api/integrations';
let notificationsStyleInjected = false;

function ensureNotificationStyles() {
  if (notificationsStyleInjected) {
    return;
  }

  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideOutRight {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(100%); opacity: 0; }
    }
  `;

  document.head.appendChild(style);
  notificationsStyleInjected = true;
}

export function showSuccessMessage(message) {
  ensureNotificationStyles();

  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: rgba(16, 185, 129, 0.9);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    z-index: 1001;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(16, 185, 129, 0.3);
    animation: slideInRight 0.3s ease;
  `;
  notification.textContent = `✅ ${message}`;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
      notification.remove();
    }, 300);
  }, 3000);
}

export function showErrorMessage(message) {
  ensureNotificationStyles();

  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
    z-index: 1001;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(239, 68, 68, 0.3);
    animation: slideInRight 0.3s ease;
  `;
  notification.textContent = `❌ ${message}`;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
      notification.remove();
    }, 300);
  }, 4000);
}

function getStoredIntegrationToken() {
  try {
    return localStorage.getItem(INTEGRATIONS_STORAGE_KEY);
  } catch (error) {
    console.warn('No se pudo leer el token de API almacenado', error);
    return null;
  }
}

function storeIntegrationToken(token) {
  try {
    localStorage.setItem(INTEGRATIONS_STORAGE_KEY, token);
  } catch (error) {
    console.warn('No se pudo guardar el token de API', error);
  }
}

function clearIntegrationToken() {
  try {
    localStorage.removeItem(INTEGRATIONS_STORAGE_KEY);
  } catch (error) {
    console.warn('No se pudo limpiar el token de API', error);
  }
}

function renderList(element, items, emptyMessage) {
  if (!element) {
    return;
  }

  if (!items.length) {
    element.innerHTML = `<li class="api-list-empty">${emptyMessage}</li>`;
    return;
  }

  element.innerHTML = '';
  items.forEach(item => {
    const li = document.createElement('li');
    li.className = 'api-list-item';
    li.innerHTML = item.trim();
    element.appendChild(li);
  });
}

export function initApiIntegrationSection(rootId = 'section-apikey') {
  const section = document.getElementById(rootId);
  if (!section) {
    return;
  }

  const statusBadge = section.querySelector('#api-connection-status');
  const tokenValueEl = section.querySelector('#api-token-value');
  const copyBtn = section.querySelector('#api-copy-token');
  const logoutBtn = section.querySelector('#api-logout-btn');
  const dataPanels = section.querySelector('#api-data-panels');
  const meetingsList = section.querySelector('#api-meetings-list');
  const tasksList = section.querySelector('#api-tasks-list');
  const generateBtn = section.querySelector('#api-generate-token');

  if (generateBtn && !generateBtn.dataset.defaultText) {
    generateBtn.dataset.defaultText = generateBtn.textContent ?? 'Generar token';
  }
  const userSearchForm = section.querySelector('#api-user-search-form');
  const userSearchInput = section.querySelector('#api-user-search-input');
  const userSearchResults = section.querySelector('#api-user-search-results');

  const setDisconnectedState = (message = 'Aún no has generado un token desde este dispositivo.') => {
    if (statusBadge) {
      statusBadge.textContent = 'Sin token activo';
      statusBadge.classList.add('api-status--disconnected');
      statusBadge.classList.remove('api-status--connected');
    }

    if (tokenValueEl) {
      tokenValueEl.textContent = message;
    }

    if (copyBtn) copyBtn.disabled = true;
    if (logoutBtn) logoutBtn.disabled = true;
    if (generateBtn) {
      generateBtn.disabled = false;
      generateBtn.textContent = generateBtn.dataset.defaultText ?? 'Generar token';
    }

    if (dataPanels) dataPanels.style.display = 'none';
    if (meetingsList) {
      meetingsList.innerHTML = '<li class="api-list-empty">Genera tu token para ver tus reuniones.</li>';
    }
    if (tasksList) {
      tasksList.innerHTML = '<li class="api-list-empty">Genera tu token para listar tus tareas.</li>';
    }
    if (userSearchResults) {
      userSearchResults.innerHTML = '';
    }
  };

  const setConnectedState = token => {
    if (statusBadge) {
      statusBadge.textContent = 'Conectado';
      statusBadge.classList.remove('api-status--disconnected');
      statusBadge.classList.add('api-status--connected');
    }

    if (tokenValueEl) {
      tokenValueEl.textContent = token;
    }

    if (copyBtn) copyBtn.disabled = false;
    if (logoutBtn) logoutBtn.disabled = false;
    if (generateBtn) {
      generateBtn.disabled = false;
      generateBtn.textContent = generateBtn.dataset.defaultText ?? 'Generar token';
    }
    if (dataPanels) dataPanels.style.display = 'grid';
  };

  const getAuthHeaders = () => {
    const token = getStoredIntegrationToken();
    return token ? { Authorization: `Bearer ${token}` } : {};
  };

  const handleUnauthorized = () => {
    clearIntegrationToken();
    setDisconnectedState('Tu token expiró o fue revocado. Genera uno nuevo desde tu perfil.');
    showErrorMessage('Tu token API ya no es válido. Genera uno nuevo desde el panel.');
  };

  const fetchMeetings = async () => {
    if (!meetingsList) {
      return;
    }

    try {
      const response = await axios.get(`${INTEGRATIONS_BASE_URL}/meetings`, { headers: getAuthHeaders() });
      const meetings = response?.data?.data ?? [];
      const items = meetings.map(meeting => `
        <div class="api-item-title">${meeting.title}</div>
        <div class="api-item-meta">${meeting.created_at_readable ?? ''}</div>
      `);
      renderList(meetingsList, items, 'No se encontraron reuniones.');
    } catch (error) {
      if (error.response?.status === 401) {
        handleUnauthorized();
      } else {
        renderList(meetingsList, [], 'No se pudieron obtener las reuniones.');
        showErrorMessage('No se pudieron cargar las reuniones desde la API.');
      }
    }
  };

  const fetchTasks = async () => {
    if (!tasksList) {
      return;
    }

    try {
      const response = await axios.get(`${INTEGRATIONS_BASE_URL}/tasks`, { headers: getAuthHeaders() });
      const tasks = response?.data?.data ?? [];
      const items = tasks.map(task => `
        <div class="api-item-title">${task.title}</div>
        <div class="api-item-meta">${task.meeting ? task.meeting.title : 'Sin reunión vinculada'} · ${task.due_date ?? 'Sin fecha'}</div>
      `);
      renderList(tasksList, items, 'No se encontraron tareas asignadas.');
    } catch (error) {
      if (error.response?.status === 401) {
        handleUnauthorized();
      } else {
        renderList(tasksList, [], 'No se pudieron obtener las tareas.');
        showErrorMessage('No se pudieron cargar las tareas desde la API.');
      }
    }
  };

  const refreshData = () => {
    const stored = getStoredIntegrationToken();
    if (!stored) {
      setDisconnectedState();
      return;
    }

    setConnectedState(stored);
    fetchMeetings();
    fetchTasks();
  };

  generateBtn?.addEventListener('click', async () => {
    if (generateBtn.disabled) {
      return;
    }

    generateBtn.disabled = true;
    generateBtn.textContent = 'Generando...';

    try {
      const response = await axios.post(`${INTEGRATIONS_BASE_URL}/token`, {
        device_name: 'Panel de perfil',
      });

      const token = response?.data?.token;
      if (token) {
        storeIntegrationToken(token);
        showSuccessMessage('Token generado correctamente.');
        refreshData();
      } else {
        showErrorMessage('No se recibió un token válido.');
        setDisconnectedState();
      }
    } catch (error) {
      const message = error.response?.data?.message
        ?? error.response?.data?.errors?.device_name?.[0]
        ?? 'No se pudo generar el token.';
      showErrorMessage(message);
      setDisconnectedState();
    }

    generateBtn.disabled = false;
    generateBtn.textContent = generateBtn.dataset.defaultText ?? 'Generar token';
  });

  copyBtn?.addEventListener('click', async () => {
    const token = getStoredIntegrationToken();
    if (!token) {
      showErrorMessage('No hay un token disponible para copiar.');
      return;
    }

    try {
      await navigator.clipboard.writeText(token);
      showSuccessMessage('Token copiado en el portapapeles.');
    } catch (error) {
      showErrorMessage('No se pudo copiar el token automáticamente.');
    }
  });

  logoutBtn?.addEventListener('click', async () => {
    const token = getStoredIntegrationToken();
    if (!token) {
      return;
    }

    if (logoutBtn) {
      logoutBtn.disabled = true;
      logoutBtn.textContent = 'Revocando...';
    }

    try {
      await axios.post(`${INTEGRATIONS_BASE_URL}/logout`, {}, { headers: getAuthHeaders() });
      showSuccessMessage('Token revocado con éxito.');
    } catch (error) {
      if (error.response?.status !== 401) {
        showErrorMessage('No se pudo revocar el token en el servidor, pero se eliminará localmente.');
      }
    } finally {
      clearIntegrationToken();
      setDisconnectedState('Has cerrado la sesión de la API.');
      if (logoutBtn) {
        logoutBtn.disabled = false;
        logoutBtn.textContent = 'Revocar token';
      }
    }
  });

  userSearchForm?.addEventListener('submit', async event => {
    event.preventDefault();

    const term = userSearchInput?.value?.trim();
    if (!term || term.length < 2) {
      showErrorMessage('Ingresa al menos 2 caracteres para buscar.');
      return;
    }

    if (!getStoredIntegrationToken()) {
      showErrorMessage('Primero debes generar un token de API desde este panel.');
      return;
    }

    const submitBtn = userSearchForm.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    try {
      const response = await axios.get(`${INTEGRATIONS_BASE_URL}/users/search`, {
        params: { query: term },
        headers: getAuthHeaders(),
      });

      const results = response?.data?.data ?? [];
      const items = results.map(user => `
        <div class="api-item-title">${user.full_name} <span class="api-user-role">${user.role}</span></div>
        <div class="api-item-meta">${user.email} · ${user.username}</div>
      `);
      renderList(userSearchResults, items, 'No se encontraron usuarios con ese término.');
    } catch (error) {
      if (error.response?.status === 401) {
        handleUnauthorized();
      } else {
        renderList(userSearchResults, [], 'Ocurrió un error buscando usuarios.');
        showErrorMessage('No se pudo completar la búsqueda de usuarios.');
      }
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });

  refreshData();
}

export function getIntegrationToken() {
  return getStoredIntegrationToken();
}

export function clearIntegrationSession() {
  clearIntegrationToken();
}
