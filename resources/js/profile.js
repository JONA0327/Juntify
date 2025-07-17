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
    sidebar.classList.remove('open');
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
        document.getElementById('sidebar').classList.remove('open');
      }
    });
  });
});
window.toggleSidebar = toggleSidebar;
