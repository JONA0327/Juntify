import { initApiIntegrationSection, showSuccessMessage, showErrorMessage } from './integrations/api-key';

axios.defaults.headers.common['X-CSRF-TOKEN'] = document
  .querySelector('meta[name="csrf-token"]')
  ?.getAttribute('content') ?? '';

function createParticles() {
  const container = document.getElementById('particles');
  if (!container) {
    return;
  }

  const count = 50;
  for (let i = 0; i < count; i += 1) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = `${Math.random() * 100}%`;
    particle.style.top = `${Math.random() * 100}%`;
    particle.style.animationDelay = `${Math.random() * 6}s`;
    particle.style.animationDuration = `${Math.random() * 6 + 4}s`;
    container.appendChild(particle);
  }
}

function toggleSidebar() {
  const layout = document.querySelector('.doc-layout');
  layout?.classList.toggle('sidebar-open');
}

function closeSidebar() {
  const layout = document.querySelector('.doc-layout');
  layout?.classList.remove('sidebar-open');
}

function enableSidebarNavigation() {
  const links = document.querySelectorAll('.doc-sidebar a');
  const sections = Array.from(links)
    .map(link => document.querySelector(link.getAttribute('href')))
    .filter(Boolean);

  links.forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      const target = document.querySelector(link.getAttribute('href'));
      if (!target) {
        return;
      }

      closeSidebar();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  if (!sections.length) {
    return;
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) {
        return;
      }
      const id = entry.target.getAttribute('id');
      links.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
      });
    });
  }, { threshold: 0.4 });

  sections.forEach(section => observer.observe(section));
}

function enableSnippetCopy() {
  const copyButton = document.getElementById('copy-doc-snippet');
  const snippet = document.getElementById('doc-snippet-content');
  if (!copyButton || !snippet) {
    return;
  }

  copyButton.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(snippet.textContent.trim());
      showSuccessMessage('Fragmento copiado. Pégalo en tu aplicación.');
    } catch (error) {
      showErrorMessage('No pudimos copiar el fragmento automáticamente.');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  createParticles();
  initApiIntegrationSection('doc-api-section');
  enableSidebarNavigation();
  enableSnippetCopy();

  document.querySelectorAll('[data-doc-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      toggleSidebar();
    });
  });

  document.querySelectorAll('.doc-overlay').forEach(overlay => {
    overlay.addEventListener('click', () => closeSidebar());
  });
});
