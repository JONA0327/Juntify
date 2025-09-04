// resources/js/profile.js

import { showError } from './utils/alerts.js';

// Configura Axios para incluir el token CSRF en cada petición
axios.defaults.headers.common['X-CSRF-TOKEN'] = document
  .querySelector('meta[name="csrf-token"]')
  .getAttribute('content');

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

/**
 * Muestra el modal para crear carpeta principal
 */
function showCreateFolderModal() {
  const modal = document.getElementById('create-folder-modal');
  const input = document.getElementById('folder-name-input');

  // Generar nombre sugerido
  const suggestedName = `Juntify-Reuniones-${new Date().getFullYear()}`;
  input.value = suggestedName;

  modal.classList.add('show');

  // Focus en el input después de la animación
  setTimeout(() => {
    input.focus();
    input.select();
  }, 300);
}

/**
 * Cierra el modal de crear carpeta principal
 */
function closeCreateFolderModal() {
  const modal = document.getElementById('create-folder-modal');
  const input = document.getElementById('folder-name-input');

  modal.classList.remove('show');
  input.value = '';
}

/**
 * Confirma la creación de la carpeta principal
 */
function confirmCreateFolder() {
  const input = document.getElementById('folder-name-input');
  const name = input.value.trim();
  const btn = document.getElementById('confirm-create-btn');
  const mainInput = document.getElementById('main-folder-input');

  if (!name) {
    alert('Por favor ingresa un nombre para la carpeta');
    input.focus();
    return;
  }

  btn.disabled = true;
  btn.textContent = '⏳ Creando...';

  axios.post('/drive/main-folder', { name })
    .then(res => {
      mainInput.value = res.data.id;
      closeCreateFolderModal();

      // Mostrar mensaje de éxito
      showSuccessMessage(`Carpeta "${name}" creada exitosamente`);

      // Mostrar la sección de subcarpetas sin auto-completar
      const subfolderCard = document.getElementById('subfolder-card');
      if (subfolderCard) {
        subfolderCard.style.display = 'block';
      }
    })
    .catch(err => {
      console.error('Error creando carpeta principal:', err.response?.data || err.message);
      const serverMessage = err.response?.data?.message;
      showErrorMessage(serverMessage ?? 'No se pudo crear la carpeta principal. Inténtalo de nuevo.');
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = '✅ Crear Carpeta';
    });
}

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
    .then(() => {
      mainFolderName.textContent  = mainFolderId;
      subfolderCard.style.display = 'block';
      alert('Carpeta principal establecida: ' + mainFolderId);
      input.dataset.id = mainFolderId;
    })
    .catch(err => {
      console.error('Error estableciendo carpeta principal:', err.response?.data || err.message);
      alert('No se pudo establecer la carpeta principal.');
    })
    .finally(() => {
      btn.disabled = false;
    });
}

/**
 * Muestra el modal para crear subcarpeta
 */
function showCreateSubfolderModal() {
  const modal = document.getElementById('create-subfolder-modal');
  const input = document.getElementById('subfolder-name-input');
  const mainFolderId = document.getElementById('main-folder-input').value.trim();

  if (!mainFolderId) {
    showErrorMessage('Primero debes establecer la carpeta principal');
    return;
  }

  // Generar nombre sugerido
  const currentDate = new Date();
  const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const suggestedName = `Reuniones-${monthNames[currentDate.getMonth()]}-${currentDate.getFullYear()}`;
  input.value = suggestedName;

  modal.classList.add('show');

  // Focus en el input después de la animación
  setTimeout(() => {
    input.focus();
    input.select();
  }, 300);
}

/**
 * Cierra el modal de crear subcarpeta
 */
function closeCreateSubfolderModal() {
  const modal = document.getElementById('create-subfolder-modal');
  const input = document.getElementById('subfolder-name-input');

  modal.classList.remove('show');
  input.value = '';
}

/**
 * Confirma la creación de la subcarpeta
 */
function confirmCreateSubfolder() {
  const input = document.getElementById('subfolder-name-input');
  const name = input.value.trim();
  const mainFolderId = document.getElementById('main-folder-input').value.trim();
  const btn = document.getElementById('confirm-create-sub-btn');

  if (!name) {
    alert('Por favor ingresa un nombre para la subcarpeta');
    input.focus();
    return;
  }

  if (!mainFolderId) {
    showErrorMessage('Primero debes establecer la carpeta principal');
    closeCreateSubfolderModal();
    return;
  }

  btn.disabled = true;
  btn.textContent = '⏳ Creando...';

  axios.post('/drive/subfolder', { name, parentId: mainFolderId })
    .then(res => {
      addSubfolderToList(name, res.data.id);

      closeCreateSubfolderModal();
      showSuccessMessage(`Subcarpeta "${name}" creada exitosamente`);
    })
    .catch(err => {
      console.error('Error creando subcarpeta:', err.response?.data || err.message);
      showErrorMessage('No se pudo crear la subcarpeta. Inténtalo de nuevo.');
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = '✅ Crear Subcarpeta';
    });
}

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
    closeCreateFolderModal();
    closeCreateSubfolderModal();
  }
});

// Cerrar modales al hacer click fuera
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal')) {
    closeCreateFolderModal();
    closeCreateSubfolderModal();
  }
});

// Inicialización general al cargar la página
document.addEventListener('DOMContentLoaded', () => {
  createParticles();

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

  // Toggle de botones (pricing u otros)
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });

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

// Exponer funciones para handlers inline (si aún usas onclick en HTML)
window.toggleSidebar       = toggleSidebar;
window.closeSidebar        = closeSidebar;
window.toggleMobileNavbar = toggleMobileNavbar;
window.connectDrive        = connectDrive;
window.showCreateFolderModal = showCreateFolderModal;
window.closeCreateFolderModal = closeCreateFolderModal;
window.confirmCreateFolder = confirmCreateFolder;
window.showCreateSubfolderModal = showCreateSubfolderModal;
window.closeCreateSubfolderModal = closeCreateSubfolderModal;
window.confirmCreateSubfolder = confirmCreateSubfolder;
window.setMainFolder       = setMainFolder;
window.addSubfolderToList = addSubfolderToList;
