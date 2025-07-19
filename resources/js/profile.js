// resources/js/profile.js

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
 * Crea la carpeta principal en Drive y muestra su ID
 */
function createMainFolder() {
  const input = document.getElementById('main-folder-input');
  const name  = 'Juntify-Reuniones-' + Date.now();
  const btn   = document.getElementById('create-main-folder-btn');

  btn.disabled = true;
  axios.post('/drive/main-folder', { name })
    .then(res => {
      input.value = res.data.id;
      alert('Carpeta principal creada: ' + res.data.id);
    })
    .catch(err => {
      console.error('Error creando carpeta principal:', err.response?.data || err.message);
      alert('No se pudo crear la carpeta principal.');
    })
    .finally(() => {
      btn.disabled = false;
    });
}

/**
 * Establece la carpeta principal existente por su ID
 */
function setMainFolder() {
  const input         = document.getElementById('main-folder-input');
  const mainFolderId  = input.value.trim();
  const subfolderCard = document.getElementById('subfolder-card');
  const mainFolderName= document.getElementById('main-folder-name');
  const btn           = document.getElementById('set-main-folder-btn');

  if (!mainFolderId) {
    return alert('Por favor ingresa el ID de la carpeta o créala primero.');
  }

  btn.disabled = true;
  axios.post('/drive/set-main-folder', { id: mainFolderId })
    .then(() => {
      mainFolderName.textContent  = mainFolderId;
      subfolderCard.style.display = 'block';
      alert('Carpeta principal establecida: ' + mainFolderId);
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
 * Crea una subcarpeta dentro de la carpeta principal
 */
function createSubfolder() {
  const input        = document.getElementById('subfolder-input');
  const name         = input.value.trim();
  const mainFolderId = document.getElementById('main-folder-input').value.trim();
  const list         = document.getElementById('subfolders-list');
  const btn          = document.getElementById('create-subfolder-btn');

  if (!name) {
    return alert('Por favor ingresa el nombre de la subcarpeta.');
  }
  if (!mainFolderId) {
    return alert('Primero debes establecer la carpeta principal.');
  }

  btn.disabled = true;
  axios.post('/drive/subfolder', { name, parentId: mainFolderId })
    .then(res => {
      const div = document.createElement('div');
      div.style.cssText = `
        margin: 0.5rem 0;
        padding: 0.75rem;
        background: rgba(59,130,246,0.1);
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      `;
      div.innerHTML = `
        <span style="color: #1e293b;">${res.data.id}</span>
        <button type="button" class="btn-remove-subfolder">🗑️</button>
      `;
      list.appendChild(div);
      input.value = '';
    })
    .catch(err => {
      console.error('Error creando subcarpeta:', err.response?.data || err.message);
      alert('No se pudo crear la subcarpeta.');
    })
    .finally(() => {
      btn.disabled = false;
    });
}

// Delegación para eliminar subcarpeta
document.addEventListener('click', e => {
  if (e.target.matches('.btn-remove-subfolder')) {
    e.target.closest('div').remove();
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
  const createMainBtn = document.getElementById('create-main-folder-btn');
  const setMainBtn    = document.getElementById('set-main-folder-btn');
  const createSubBtn  = document.getElementById('create-subfolder-btn');
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
  if (createMainBtn) createMainBtn.addEventListener('click', createMainFolder);
  if (setMainBtn)    setMainBtn.addEventListener('click', setMainFolder);
  if (createSubBtn)  createSubBtn.addEventListener('click', createSubfolder);
});

// Exponer funciones para handlers inline (si aún usas onclick en HTML)
window.toggleSidebar       = toggleSidebar;
window.closeSidebar        = closeSidebar;
window.toggleMobileNavbar = toggleMobileNavbar;
window.connectDrive        = connectDrive;
window.createMainFolder    = createMainFolder;
window.setMainFolder       = setMainFolder;
window.createSubfolder     = createSubfolder;
