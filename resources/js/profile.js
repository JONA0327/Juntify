// resources/js/profile.js

import { showError } from './utils/alerts.js';
import { initMercadoPagoStatusPolling } from './payments/status-modal';

// Configura Axios para incluir el token CSRF en cada petición
axios.defaults.headers.common['X-CSRF-TOKEN'] = document
  .querySelector('meta[name="csrf-token"]')
  .getAttribute('content');

const INTEGRATIONS_STORAGE_KEY = 'juntifyApiToken';
const INTEGRATIONS_BASE_URL = '/api/integrations';

/**
 * Crea partículas animadas de fondo
 */
function createParticles() {
  const container = document.getElementById('particles');
  const count     = 60;
  for (let i = 0; i < count; i++) {
    const p = document.createElement('div');
    p.className             = 'particle';
    p.style.left            = Math.random() * 100 + '%';
    p.style.top             = Math.random() * 100 + '%';
    p.style.animationDelay  = Math.random() * 8 + 's';
    p.style.animationDuration = (Math.random() * 4 + 4) + 's';
    container.appendChild(p);
  }
}

/**
 * Alterna el sidebar abierto/cerrado
 */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('show');
}

/**
 * Cierra el sidebar
 */
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('show');
}

/**
 * Alterna la navegación móvil
 */
function toggleMobileNavbar() {
  const navLinks  = document.getElementById('nav-links');
  const hamburger = document.querySelector('.hamburger-navbar');
  if (navLinks)  navLinks.classList.toggle('show');
  if (hamburger) hamburger.classList.toggle('active');
}

/**
 * Configura los tabs de planes en el perfil
 */
function setupProfilePricingToggle() {
  const pricingWrappers = document.querySelectorAll('#section-plans .pricing-wrapper');

  pricingWrappers.forEach(wrapper => {
    const buttons = wrapper.querySelectorAll('.toggle-btn');
    const planGroups = wrapper.querySelectorAll('[data-plan-group]');

    if (!buttons.length || !planGroups.length) {
      return;
    }

    const showGroup = (target) => {
      planGroups.forEach(group => {
        const isTarget = group.dataset.planGroup === target;
        group.classList.toggle('hidden', !isTarget);
        // Force display to avoid style collisions overriding .hidden
        group.style.display = isTarget ? '' : 'none';
      });
    };

    buttons.forEach(button => {
      const target = button.dataset.target;
      if (!target) return;

      button.addEventListener('click', () => {
        buttons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        showGroup(target);
      });
    });

    const activeBtn = wrapper.querySelector('.toggle-btn.active') || buttons[0];
    if (activeBtn && activeBtn.dataset.target) {
      showGroup(activeBtn.dataset.target);
    }
  });
}

// Cerrar sidebar si se hace click fuera en móvil
document.addEventListener('click', e => {
  const sidebar = document.getElementById('sidebar');
  const btn     = document.querySelector('.mobile-menu-btn');
  if (
    window.innerWidth <= 768 &&
    sidebar.classList.contains('open') &&
    !sidebar.contains(e.target) &&
    btn && !btn.contains(e.target)
  ) {
    closeSidebar();
  }
});

/**
 * Llama al endpoint para autorizar Google Drive
 */
function connectDrive() {
  const modal = document.getElementById('drive-loading-modal');
  if (modal) modal.classList.add('show');

  axios.get('/drive/status')
    .then(res => {
      if (!res.data.connected) {
        alert('Conecta tu cuenta de drive para crear una carpeta');
        window.location.href = '/auth/google/redirect';
      } else {
        window.location.reload();
      }
    })
    .catch(() => {
      alert('Error verificando Drive');
      window.location.href = '/auth/google/redirect';
    })
    .finally(() => {
      if (modal) modal.classList.remove('show');
    });
}

// Creación manual de carpeta principal eliminada: ahora es automática en el backend

/**
 * Establece la carpeta principal existente por su ID
 */
function setMainFolder() {
  const input         = document.getElementById('main-folder-input');
  const mainFolderId  = input.value.trim();
  const currentId     = input.dataset.id;
  const subfolderCard = document.getElementById('subfolder-card');
  const mainFolderName= document.getElementById('main-folder-name');
  const btn           = document.getElementById('set-main-folder-btn');

  if (!mainFolderId) {
    return alert('Por favor ingresa el ID de la carpeta o créala primero.');
  }

  if (currentId && currentId !== mainFolderId) {
    const confirmMsg =
      'Al cambiar la carpeta principal, deberás mover manualmente los archivos de audio existentes a la nueva carpeta. ¿Deseas continuar?';
    if (!confirm(confirmMsg)) {
      return; // Abort if user cancels
    }
  }

  btn.disabled = true;
  axios.post('/drive/set-main-folder', { id: mainFolderId })
    .then((res) => {
      const msg = res?.data?.message || 'Carpeta principal establecida.';
      // Actualiza nombre/ID visibles
      if (mainFolderName) {
        const nameEl = document.querySelector('#main-folder-name div:first-child');
        const idEl = document.querySelector('#main-folder-name div:last-child');
        if (nameEl && idEl) {
          nameEl.textContent = nameEl.textContent || 'Carpeta personalizada';
          idEl.textContent = `ID: ${mainFolderId}`;
        } else {
          mainFolderName.textContent = mainFolderId;
        }
      }
      if (subfolderCard) subfolderCard.style.display = 'block';
      showSuccessMessage(msg);
      input.dataset.id = mainFolderId;
    })
    .catch(err => {
      console.error('Error estableciendo carpeta principal:', err.response?.data || err.message);
      const serverMessage = err.response?.data?.message;
      showErrorMessage(serverMessage ?? 'No se pudo establecer la carpeta principal.');
    })
    .finally(() => {
      btn.disabled = false;
    });
}

/**
 * Muestra el modal para crear subcarpeta
 */
// Subcarpetas manuales deshabilitadas: funciones convertidas en no-op
function showCreateSubfolderModal() { console.warn('Creación manual de subcarpetas deshabilitada'); }

/**
 * Cierra el modal de crear subcarpeta
 */
function closeCreateSubfolderModal() { /* noop */ }

/**
 * Confirma la creación de la subcarpeta
 */
function confirmCreateSubfolder() { console.warn('Creación manual de subcarpetas deshabilitada'); }

function addSubfolderToList(name, id) {
  const list = document.getElementById('subfolders-list');
  if (!list) return;
  const div = document.createElement('div');
  div.dataset.id = id;
  div.style.cssText = `
    margin: 0.5rem 0;
    padding: 0.75rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid rgba(59, 130, 246, 0.2);
  `;
  div.innerHTML = `
    <div>
      <div style="color: #ffffff; font-weight: 600;">${name}</div>
      <div style="color: #94a3b8; font-size: 0.8rem;">${id}</div>
    </div>
    <button type="button" class="btn-remove-subfolder" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 0.5rem; border-radius: 8px; cursor: pointer;">🗑️</button>
  `;
  list.appendChild(div);
}

/**
 * Muestra mensaje de éxito
 */
function showSuccessMessage(message) {
  // Crear elemento de notificación
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

  // Remover después de 3 segundos
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 3000);
}

/**
 * Muestra mensaje de error
 */
function showErrorMessage(message) {
  // Crear elemento de notificación
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

  // Remover después de 4 segundos
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 4000);
}

// Agregar estilos de animación para las notificaciones
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

function initApiIntegrationSection() {
  const section = document.getElementById('section-apikey');
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
  const loginForm = section.querySelector('#api-login-form');
  const loginEmail = section.querySelector('#api-login-email');
  const loginPassword = section.querySelector('#api-login-password');
  const loginSubmit = section.querySelector('#api-login-submit');
  const userSearchForm = section.querySelector('#api-user-search-form');
  const userSearchInput = section.querySelector('#api-user-search-input');
  const userSearchResults = section.querySelector('#api-user-search-results');

  const setDisconnectedState = (message = 'No has iniciado sesión aún.') => {
    if (statusBadge) {
      statusBadge.textContent = 'Sin conectar';
      statusBadge.classList.add('api-status--disconnected');
      statusBadge.classList.remove('api-status--connected');
    }

    if (tokenValueEl) {
      tokenValueEl.textContent = message;
    }

    if (copyBtn) copyBtn.disabled = true;
    if (logoutBtn) logoutBtn.disabled = true;

    if (dataPanels) dataPanels.style.display = 'none';
    if (meetingsList) {
      meetingsList.innerHTML = '<li class="api-list-empty">Inicia sesión para ver tus reuniones.</li>';
    }
    if (tasksList) {
      tasksList.innerHTML = '<li class="api-list-empty">Inicia sesión para listar tus tareas.</li>';
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
    if (dataPanels) dataPanels.style.display = 'grid';
  };

  const getAuthHeaders = () => {
    const token = getStoredIntegrationToken();
    return token ? { Authorization: `Bearer ${token}` } : {};
  };

  const handleUnauthorized = () => {
    clearIntegrationToken();
    setDisconnectedState('Tu token expiró o fue revocado. Genera uno nuevo.');
    showErrorMessage('Tu token API ya no es válido. Inicia sesión nuevamente.');
  };

  const renderList = (element, items, emptyMessage) => {
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

  loginForm?.addEventListener('submit', async event => {
    event.preventDefault();

    if (!loginEmail?.value || !loginPassword?.value) {
      showErrorMessage('Debes ingresar tu correo y contraseña.');
      return;
    }

    if (loginSubmit) {
      loginSubmit.disabled = true;
      loginSubmit.textContent = 'Generando...';
    }

    try {
      const response = await axios.post(`${INTEGRATIONS_BASE_URL}/login`, {
        email: loginEmail.value,
        password: loginPassword.value,
        device_name: 'Panel de perfil',
      });

      const token = response?.data?.token;
      if (token) {
        storeIntegrationToken(token);
        showSuccessMessage('Token generado correctamente.');
        loginForm.reset();
        refreshData();
      } else {
        showErrorMessage('No se recibió un token válido.');
      }
    } catch (error) {
      const message = error.response?.data?.message
        ?? error.response?.data?.errors?.email?.[0]
        ?? 'No se pudo generar el token. Verifica tus credenciales.';
      showErrorMessage(message);
    } finally {
      if (loginSubmit) {
        loginSubmit.disabled = false;
        loginSubmit.textContent = 'Generar token';
      }
    }
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
      showErrorMessage('Primero debes iniciar sesión en la API.');
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

// Delegación para eliminar subcarpeta
document.addEventListener('click', e => {
  if (e.target.matches('.btn-remove-subfolder')) {
    const folderDiv = e.target.closest('div');
    const folderName = folderDiv.querySelector('div > div').textContent;
    const id = folderDiv.dataset.id;


    if (confirm(`¿Estás seguro de que quieres eliminar la subcarpeta "${folderName}"?`)) {
            axios.delete('/drive/subfolder/' + id)
        .then(() => {
          folderDiv.remove();
          showSuccessMessage(`Subcarpeta "${folderName}" eliminada`);
        })
        .catch(() => {
          showErrorMessage('No se pudo eliminar la subcarpeta');
        });
    }
  }
});

// Cerrar modales con ESC
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeCreateSubfolderModal();
  }
});

// Cerrar modales al hacer click fuera
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal')) {
    closeCreateSubfolderModal();
  }
});

// Inicialización general al cargar la página
document.addEventListener('DOMContentLoaded', () => {
  createParticles();
  initApiIntegrationSection();

  // Navegación del sidebar
  document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      document.querySelectorAll('.sidebar-nav .nav-link')
        .forEach(l => l.classList.remove('active'));
      link.classList.add('active');

      const section = link.dataset.section;
      document.querySelectorAll('.content-section').forEach(sec => {
        sec.style.display = (sec.id === `section-${section}`) ? 'block' : 'none';
      });

      if (window.innerWidth <= 768) {
        closeSidebar();
      }
    });
  });

  setupProfilePricingToggle();
  initMercadoPagoStatusPolling();

  // Vincular botones de Drive
  const connectBtn    = document.getElementById('connect-drive-btn');
  const setMainBtn    = document.getElementById('set-main-folder-btn');
  const mainInput     = document.getElementById('main-folder-input');
  const mainName      = document.getElementById('main-folder-name');

  if (mainInput && mainInput.dataset.id) {
    mainInput.value = mainInput.dataset.id;
  }

  if (mainName && mainName.dataset.name) {
    const name = mainName.dataset.name;
    const id   = mainName.dataset.id;
    mainName.textContent = name ? `${name} (${id})` : '';
  }

  if (connectBtn)    connectBtn.addEventListener('click', connectDrive);
  if (setMainBtn)    setMainBtn.addEventListener('click', setMainFolder);

  const notificationsList = document.getElementById('notifications-list');
  if (notificationsList) {
      axios.get('/api/notifications')
        .then(res => {
          const items = res.data;
          notificationsList.innerHTML = '';
          if (items.length === 0) {
            notificationsList.innerHTML = '<p style="color: #cbd5e1; text-align: center; padding: 2rem;">No tienes notificaciones nuevas.</p>';
          } else {
            items.forEach(n => {
              const div = document.createElement('div');
              div.className = 'notification-item';
              div.innerHTML = `<span class="icon">📨</span><span class="text">${n.message}</span><button class="delete" data-id="${n.id}">✖</button>`;
              notificationsList.appendChild(div);
            });
          }
        })
        .catch(err => {
          if (err.response && err.response.status === 401) {
            showError('Tu sesión ha expirado. Inicia sesión nuevamente.');
          }
        });

    notificationsList.addEventListener('click', e => {
      if (e.target.classList.contains('delete')) {
        const id = e.target.dataset.id;
          axios.delete(`/api/notifications/${id}`)
            .then(() => {
              e.target.parentElement.remove();
              if (!notificationsList.children.length) {
                notificationsList.innerHTML = '<p style="color: #cbd5e1; text-align: center; padding: 2rem;">No tienes notificaciones nuevas.</p>';
              }
            })
            .catch(err => {
              if (err.response && err.response.status === 401) {
                showError('Tu sesión ha expirado. Inicia sesión nuevamente.');
              }
            });
      }
    });
  }
});

/**
 * Abre un modal genérico por id (agrega la clase .show)
 */
function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('show');
}

/**
 * Cierra un modal genérico por id (remueve la clase .show)
 */
function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('show');
}

// Exponer funciones para handlers inline (si aún usas onclick en HTML)
window.toggleSidebar       = toggleSidebar;
window.closeSidebar        = closeSidebar;
window.toggleMobileNavbar = toggleMobileNavbar;
window.connectDrive        = connectDrive;
window.showCreateSubfolderModal = showCreateSubfolderModal;
window.closeCreateSubfolderModal = closeCreateSubfolderModal;
window.confirmCreateSubfolder = confirmCreateSubfolder;
window.setMainFolder       = setMainFolder;
window.addSubfolderToList = addSubfolderToList;
window.openModal          = openModal;
window.closeModal         = closeModal;
