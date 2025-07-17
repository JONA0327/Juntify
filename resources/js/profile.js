// Create animated particles
function createParticles() {
  const particlesContainer = document.getElementById('particles');
  const particleCount = 60;
  for (let i = 0; i < particleCount; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.left = Math.random()*100 + '%';
    p.style.top =  Math.random()*100 + '%';
    p.style.animationDelay    = Math.random()*8 + 's';
    p.style.animationDuration = (Math.random()*4 + 4) + 's';
    particlesContainer.appendChild(p);
  }
}

// Toggle sidebar open/close
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('show');
}

// Close sidebar
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('show');
}

// Toggle mobile navbar
function toggleMobileNavbar() {
  const navLinks = document.getElementById('nav-links');
  const hamburger = document.querySelector('.hamburger-navbar');
  
  if (navLinks) {
    navLinks.classList.toggle('show');
  }
  if (hamburger) {
    hamburger.classList.toggle('active');
  }
}
// Click fuera cierra sidebar en móvil
document.addEventListener('click', e => {
  const sidebar = document.getElementById('sidebar');
  const btn     = document.querySelector('.mobile-menu-btn');
  if (
    window.innerWidth <= 768 &&
    sidebar.classList.contains('open') &&
    !sidebar.contains(e.target) &&
    !btn.contains(e.target)
  ) {
    closeSidebar();
  }
});

// Inicialización general
document.addEventListener('DOMContentLoaded', () => {
  createParticles();

  // Control de navegación en sidebar
  document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();

      // 1) activa el link
      document.querySelectorAll('.sidebar-nav .nav-link')
        .forEach(l => l.classList.remove('active'));
      link.classList.add('active');

      // 2) muestra/oculta secciones según data-section
      const target = link.dataset.section; // "info", "connect", etc.
      document.querySelectorAll('.content-section').forEach(sec => {
        sec.style.display = (sec.id === `section-${target}`) ? 'block' : 'none';
      });

      // 3) cierra sidebar en móvil
      if (window.innerWidth <= 768) {
        closeSidebar();
      }
    });
  });

  // Pricing toggle functionality
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });
});

// Functions for Google Drive connection simulation
function connectDrive() {
  // Simulate connection process
  const btn = document.getElementById('connect-drive-btn');
  const status = document.getElementById('drive-status');
  const lastSync = document.getElementById('last-sync');
  const folderCard = document.getElementById('folder-config-card');
  
  btn.textContent = '🔄 Conectando...';
  btn.disabled = true;
  
  setTimeout(() => {
    status.textContent = 'Conectado';
    status.className = 'status-badge status-active';
    lastSync.textContent = 'Hace unos segundos';
    btn.textContent = '✅ Conectado';
    btn.className = 'btn btn-secondary';
    folderCard.style.display = 'block';
  }, 2000);
}

function createMainFolder() {
  const input = document.getElementById('main-folder-input');
  input.value = 'Juntify-Reuniones-' + Date.now();
  
  // Simulate folder creation
  setTimeout(() => {
    alert('Carpeta principal creada exitosamente: ' + input.value);
  }, 1000);
}

function setMainFolder() {
  const input = document.getElementById('main-folder-input');
  const subfolderCard = document.getElementById('subfolder-card');
  const mainFolderName = document.getElementById('main-folder-name');
  
  if (input.value.trim()) {
    mainFolderName.textContent = input.value;
    subfolderCard.style.display = 'block';
    alert('Carpeta principal establecida: ' + input.value);
  } else {
    alert('Por favor ingresa el ID de la carpeta o crea una nueva');
  }
}

function createSubfolder() {
  const input = document.getElementById('subfolder-input');
  const subfoldersList = document.getElementById('subfolders-list');
  
  if (input.value.trim()) {
    const subfolderDiv = document.createElement('div');
    subfolderDiv.style.cssText = 'margin: 0.5rem 0; padding: 0.75rem; background: rgba(59,130,246,0.1); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;';
    subfolderDiv.innerHTML = `
      <span style="color: #e2e8f0;">${input.value}</span>
      <button onclick="this.parentElement.remove()" style="background: rgba(239,68,68,0.2); color: #ef4444; border: none; border-radius: 4px; padding: 0.25rem 0.5rem; cursor: pointer;">🗑️</button>
    `;
    subfoldersList.appendChild(subfolderDiv);
    input.value = '';
  } else {
    alert('Por favor ingresa el nombre de la subcarpeta');
  }
}

window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.toggleMobileNavbar = toggleMobileNavbar;
window.connectDrive = connectDrive;
window.createMainFolder = createMainFolder;
window.setMainFolder = setMainFolder;
window.createSubfolder = createSubfolder;
