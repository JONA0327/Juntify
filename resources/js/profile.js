// resources/js/profile.js

import { showError } from './utils/alerts.js';
import { initMercadoPagoStatusPolling } from './payments/status-modal';

// Configura Axios para incluir el token CSRF en cada petición
axios.defaults.headers.common['X-CSRF-TOKEN'] = document
  .querySelector('meta[name="csrf-token"]')
  .getAttribute('content');

const profileTranslations = window.profileTranslations || {};
const t = (key, fallback) => profileTranslations[key] ?? fallback;
const formatMessage = (key, params = {}, fallback = '') => {
  let message = t(key, fallback);
  Object.entries(params).forEach(([paramKey, value]) => {
    message = message.replaceAll(`{${paramKey}}`, value);
  });
  return message;
};

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

function showDriveLockedModal() {
  const modal = document.getElementById('drive-locked-modal');
  const retentionEl = document.getElementById('drive-locked-retention');
  const days = Number(window.tempRetentionDays || 7);

  if (retentionEl) {
    retentionEl.textContent = `${days} ${days === 1 ? t('day_singular', 'día') : t('day_plural', 'días')}`;
  }

  if (!modal) {
    alert(t('drive_locked_message', 'La conexión con Drive está disponible solo para planes Business y Enterprise.'));
    return;
  }

  modal.style.display = 'flex';
  modal.style.alignItems = 'center';
  modal.style.justifyContent = 'center';
  modal.style.visibility = 'visible';
  modal.style.opacity = '1';
  document.body.style.overflow = 'hidden';
}

/**
 * Llama al endpoint para autorizar Google Drive
 */
function connectDrive(event) {
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
  }

  if (!window.userCanUseDrive) {
    showDriveLockedModal();
    return;
  }

  const modal = document.getElementById('drive-loading-modal');
  if (modal) modal.classList.add('show');

  axios.get('/drive/status')
    .then(res => {
      const { connected, needs_reauth: needsReauth } = res.data || {};

      if (!connected || needsReauth) {
        window.location.href = '/auth/google/redirect';
      } else {
        window.location.reload();
      }
    })
    .catch(() => {
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
    return alert(t('main_folder_required', 'Por favor ingresa el ID de la carpeta o créala primero.'));
  }

  if (currentId && currentId !== mainFolderId) {
    const confirmMsg =
      t(
        'main_folder_change_confirm',
        'Al cambiar la carpeta principal, deberás mover manualmente los archivos de audio existentes a la nueva carpeta. ¿Deseas continuar?'
      );
    if (!confirm(confirmMsg)) {
      return; // Abort if user cancels
    }
  }

  btn.disabled = true;
  axios.post('/drive/set-main-folder', { id: mainFolderId })
    .then((res) => {
      const msg = res?.data?.message || t('main_folder_set', 'Carpeta principal establecida.');
      // Actualiza nombre/ID visibles
      if (mainFolderName) {
        const nameEl = document.querySelector('#main-folder-name div:first-child');
        const idEl = document.querySelector('#main-folder-name div:last-child');
        if (nameEl && idEl) {
          nameEl.textContent = nameEl.textContent || t('main_folder_custom_name', 'Carpeta personalizada');
          idEl.textContent = `${t('main_folder_id_label', 'ID')}: ${mainFolderId}`;
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
      showErrorMessage(serverMessage ?? t('main_folder_error', 'No se pudo establecer la carpeta principal.'));
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

function initVoiceEnrollment() {
  const card = document.getElementById('voice-enrollment-card');
  if (!card) return;

  const startButton = document.getElementById('voice-record-start');
  const stopButton = document.getElementById('voice-record-stop');
  const indicator = document.getElementById('voice-recording-indicator');
  const timer = document.getElementById('voice-recording-timer');
  const status = document.getElementById('voice-enrollment-status');

  let mediaRecorder = null;
  let audioChunks = [];
  let stream = null;
  let startTime = null;
  let timerInterval = null;

  const setStatus = (message) => {
    if (status) status.textContent = message;
  };

  const setRecordingState = (isRecording) => {
    if (startButton) startButton.disabled = isRecording;
    if (stopButton) stopButton.disabled = !isRecording;
    if (indicator) indicator.classList.toggle('is-active', isRecording);
  };

  const setUploadingState = (isUploading) => {
    if (startButton) startButton.disabled = isUploading;
    if (stopButton) stopButton.disabled = isUploading;
    if (indicator) indicator.classList.toggle('is-active', false);
  };

  const resetTimer = () => {
    if (timer) timer.textContent = '00:00';
  };

  const updateTimer = () => {
    if (!timer || !startTime) return;
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const seconds = String(elapsed % 60).padStart(2, '0');
    timer.textContent = `${minutes}:${seconds}`;
  };

  const stopStream = () => {
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
      stream = null;
    }
  };

  const uploadRecording = async (blob, durationSeconds) => {
  const enrollUrl = card.dataset.enrollUrl;
  if (!enrollUrl) {
      showErrorMessage(t('voice.enroll_route_missing', 'No se encontró la ruta de enrolamiento.'));
      return;
  }

  if (durationSeconds < 10) {
      setStatus(t('voice.status_too_short', 'Estado: la grabación es demasiado corta.'));
      showErrorMessage(t('voice.too_short', 'Graba al menos 10 segundos para registrar tu huella de voz.'));
      return;
  }

    const formData = new FormData();
    formData.append('audio', blob, 'voice-enrollment.webm');

    setStatus(t('voice.status_processing', 'Estado: procesando tu huella de voz...'));
    setUploadingState(true);

    try {
      const response = await axios.post(enrollUrl, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setStatus(t('voice.status_registered', 'Estado: huella registrada correctamente.'));
      showSuccessMessage(response?.data?.message || t('voice.registered', 'Huella de voz registrada.'));
    } catch (error) {
      const message = error?.response?.data?.message || t('voice.register_error', 'No se pudo registrar tu huella de voz.');
      setStatus(formatMessage('voice.status_message', { message }, 'Estado: {message}'));
      showErrorMessage(message);
    } finally {
      setUploadingState(false);
    }
  };

  if (startButton) {
    startButton.addEventListener('click', async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        showErrorMessage(t('voice.unsupported_browser', 'Tu navegador no soporta la grabación de audio.'));
        return;
      }

      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch (error) {
        setStatus(t('voice.status_microphone_denied', 'Estado: no se pudo acceder al micrófono.'));
        showErrorMessage(t('voice.microphone_denied', 'No se pudo acceder al micrófono. Verifica permisos.'));
        return;
      }

      mediaRecorder = new MediaRecorder(stream);
      audioChunks = [];
      startTime = Date.now();
      resetTimer();
      updateTimer();
      timerInterval = setInterval(updateTimer, 1000);

      mediaRecorder.addEventListener('dataavailable', event => {
        if (event.data && event.data.size > 0) {
          audioChunks.push(event.data);
        }
      });

      mediaRecorder.addEventListener('stop', () => {
        clearInterval(timerInterval);
        timerInterval = null;

        const durationSeconds = Math.floor((Date.now() - startTime) / 1000);
        const blob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
        stopStream();
        setRecordingState(false);
        uploadRecording(blob, durationSeconds);
      });

      mediaRecorder.start();
      setStatus(t('voice.status_recording', 'Estado: grabando...'));
      setRecordingState(true);
    });
  }

  if (stopButton) {
    stopButton.addEventListener('click', () => {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        setStatus(t('voice.status_preparing', 'Estado: preparando el audio...'));
        mediaRecorder.stop();
    }
    });
  }
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


    if (confirm(formatMessage('subfolder.delete_confirm', { name: folderName }, '¿Estás seguro de que quieres eliminar la subcarpeta "{name}"?'))) {
            axios.delete('/drive/subfolder/' + id)
        .then(() => {
          folderDiv.remove();
          showSuccessMessage(formatMessage('subfolder.deleted', { name: folderName }, 'Subcarpeta "{name}" eliminada'));
        })
        .catch(() => {
          showErrorMessage(t('subfolder.delete_error', 'No se pudo eliminar la subcarpeta'));
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
  initNotifications();
  initPlanSelection();
  initGoogleConnectionMonitor();
  initVoiceEnrollment();

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

      // Mostrar/ocultar la tarjeta de bienvenida solo en la sección de información
      const welcomeCard = document.getElementById('welcome-card');
      if (welcomeCard) {
        welcomeCard.style.display = (section === 'info') ? 'flex' : 'none';
      }

      if (window.innerWidth <= 768) {
        closeSidebar();
      }
    });
  });

  // Configurar estado inicial de la tarjeta de bienvenida
  const activeLink = document.querySelector('.sidebar-nav .nav-link.active');
  const welcomeCard = document.getElementById('welcome-card');
  if (welcomeCard && activeLink) {
    const activeSection = activeLink.dataset.section;
    welcomeCard.style.display = (activeSection === 'info') ? 'flex' : 'none';
  }

  initMercadoPagoStatusPolling();

  // Verificar si se debe navegar a la sección de planes
  checkNavigateToPlans();

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

  const languageSelect = document.querySelector('[data-language-select]');
  if (languageSelect) {
    languageSelect.addEventListener('change', () => {
      if (window.localStorage) {
        window.localStorage.setItem('juntify.locale', languageSelect.value);
      }
      const languageForm = document.getElementById('language-form');
      if (languageForm) {
        languageForm.submit();
      }
    });
  }

  const notificationsList = document.getElementById('notifications-list');
  if (notificationsList) {
      axios.get('/api/notifications')
        .then(res => {
          const items = res.data;
          notificationsList.innerHTML = '';
          if (items.length === 0) {
            notificationsList.innerHTML = `<p style="color: #cbd5e1; text-align: center; padding: 2rem;">${t('notifications.empty', 'No tienes notificaciones nuevas.')}</p>`;
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
            showError(t('session.expired', 'Tu sesión ha expirado. Inicia sesión nuevamente.'));
          }
        });

    notificationsList.addEventListener('click', e => {
      if (e.target.classList.contains('delete')) {
        const id = e.target.dataset.id;
          axios.delete(`/api/notifications/${id}`)
            .then(() => {
              e.target.parentElement.remove();
              if (!notificationsList.children.length) {
                notificationsList.innerHTML = `<p style="color: #cbd5e1; text-align: center; padding: 2rem;">${t('notifications.empty', 'No tienes notificaciones nuevas.')}</p>`;
              }
            })
            .catch(err => {
              if (err.response && err.response.status === 401) {
                showError(t('session.expired', 'Tu sesión ha expirado. Inicia sesión nuevamente.'));
              }
            });
        }
    });
  }
});

/**
 * Verifica si se debe navegar automáticamente a la sección de planes
 */
function checkNavigateToPlans() {
  // Verificar sessionStorage (desde new-meeting)
  const shouldNavigateSession = sessionStorage.getItem('navigateToPlans');

  // Verificar Laravel session flash (desde redirección)
  const shouldNavigateFlash = document.querySelector('meta[name="navigate-to-plans"]')?.getAttribute('content') === 'true';

  // Verificar si hay un modal de éxito de pago
  const paymentSuccessModal = document.getElementById('payment-success-modal');

  if (shouldNavigateSession === 'true' || shouldNavigateFlash || paymentSuccessModal) {// Limpiar el flag de sessionStorage
    if (shouldNavigateSession === 'true') {
      sessionStorage.removeItem('navigateToPlans');
    }

    // Navegar a la sección de planes
    setTimeout(() => {
      const plansLink = document.querySelector('.sidebar-nav .nav-link[data-section="plans"]');
      if (plansLink) {
        // Simular click en el enlace de planes
        plansLink.click();// Si hay modal de éxito de pago, mostrarlo después de navegar
        if (paymentSuccessModal) {
          setTimeout(() => {
            paymentSuccessModal.classList.add('active');}, 500);
        }
      } else {
        console.warn('⚠️ No se encontró el enlace de la sección de planes');
      }
    }, 100);
  }
}

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

/**
 * Función para mostrar una sección específica del perfil
 */
function showSection(sectionName) {
  // Actualizar navegación
  document.querySelectorAll('.sidebar-nav .nav-link')
    .forEach(l => l.classList.remove('active'));

  const targetLink = document.querySelector(`.sidebar-nav .nav-link[data-section="${sectionName}"]`);
  if (targetLink) {
    targetLink.classList.add('active');
  }

  // Mostrar sección correspondiente
  document.querySelectorAll('.content-section').forEach(sec => {
    sec.style.display = (sec.id === `section-${sectionName}`) ? 'block' : 'none';
  });

  // Mostrar/ocultar la tarjeta de bienvenida
  const welcomeCard = document.getElementById('welcome-card');
  if (welcomeCard) {
    welcomeCard.style.display = (sectionName === 'info') ? 'flex' : 'none';
  }

  // Cerrar sidebar en móvil
  if (window.innerWidth <= 768) {
    closeSidebar();
  }
}

/**
 * Manejo de notificaciones del perfil
 */
function closeNotification(notificationId) {
  const notification = document.getElementById(notificationId);
  if (notification) {
    notification.classList.add('closing');
    setTimeout(() => {
      notification.remove();
    }, 300);
  }
}

function initNotifications() {
  const notifications = document.querySelectorAll('.notification');
  notifications.forEach((notification) => {
    setTimeout(() => {
      if (notification && notification.parentNode) {
        closeNotification(notification.id);
      }
    }, 5000);
  });
}

/**
 * Monitoriza la conexión con Google para Drive
 */
function initGoogleConnectionMonitor() {
  if (typeof GoogleConnectionMonitor === 'undefined') return;
  const monitor = new GoogleConnectionMonitor();
  monitor.init();
}

/**
 * Lógica de selección de planes
 */
const PLAN_CURRENCY_SYMBOLS = {
  MXN: '$',
  USD: '$',
  EUR: '€',
};

const PLAN_CURRENCY_LOCALES = {
  MXN: 'es-MX',
  USD: 'en-US',
  EUR: 'es-ES',
};

const planState = {
  modal: null,
  selectedPlanNameEl: null,
  selectedPlanPriceEl: null,
  selectedPlanCurrencyEl: null,
  selectedPlanPeriodEl: null,
  selectedPlanPriceContainer: null,
  billingToggleButtons: [],
  planCards: [],
  selectedPlanId: null,
  selectedPlanPeriodLabel: t('plan.period.month', 'mes'),
  selectedBillingPeriod: 'monthly',
  currentBillingPeriod: 'monthly',
};

function formatPlanPrice(value, currency) {
  const locale = PLAN_CURRENCY_LOCALES[currency] || (window.appLocale === 'en' ? 'en-US' : 'es-ES');
  const amount = Number(value) || 0;
  const hasDecimals = Math.abs(amount % 1) > 0;
  return new Intl.NumberFormat(locale, {
    minimumFractionDigits: hasDecimals ? 2 : 0,
    maximumFractionDigits: hasDecimals ? 2 : 0,
  }).format(amount);
}

function cachePlanElements() {
  planState.modal = document.getElementById('plan-selection-modal');
  planState.selectedPlanNameEl = document.getElementById('selected-plan-name');
  planState.selectedPlanPriceEl = document.getElementById('selected-plan-price');
  planState.selectedPlanCurrencyEl = document.getElementById('selected-plan-price-currency');
  planState.selectedPlanPeriodEl = document.getElementById('selected-plan-price-period');
  planState.selectedPlanPriceContainer = document.querySelector('.selected-plan-price');
  planState.billingToggleButtons = Array.from(document.querySelectorAll('#billing-toggle .toggle-option'));
  planState.planCards = Array.from(document.querySelectorAll('.plan-card'));
}

function selectPlan(planId, planName, planPrice, planCurrency, billingPeriod = 'monthly') {
  planState.selectedPlanId = planId;
  planState.selectedBillingPeriod = billingPeriod;
  planState.selectedPlanPeriodLabel = billingPeriod === 'yearly'
    ? t('plan.period.year', 'año')
    : t('plan.period.month', 'mes');

  const numericPrice = Number(planPrice) || 0;
  const isFree = numericPrice === 0;
  const currencySymbol = PLAN_CURRENCY_SYMBOLS[planCurrency] || planCurrency;

  if (planState.selectedPlanNameEl) {
    planState.selectedPlanNameEl.textContent = planName;
  }

  if (planState.selectedPlanPriceEl) {
    planState.selectedPlanPriceEl.textContent = isFree
      ? t('plan.free', 'Gratis')
      : formatPlanPrice(numericPrice, planCurrency);
  }

  if (planState.selectedPlanCurrencyEl) {
    planState.selectedPlanCurrencyEl.textContent = isFree ? '' : currencySymbol;
  }

  if (planState.selectedPlanPeriodEl) {
    planState.selectedPlanPeriodEl.textContent = isFree ? '' : `/ ${planState.selectedPlanPeriodLabel}`;
  }

  if (planState.selectedPlanPriceContainer) {
    planState.selectedPlanPriceContainer.classList.toggle('is-free', isFree);
  }

  if (planState.modal) {
    planState.modal.style.display = 'flex';
  }
}

function closePlanModal() {
  if (planState.modal) {
    planState.modal.style.display = 'none';
  }
  planState.selectedPlanId = null;
    planState.selectedPlanPeriodLabel = t('plan.period.month', 'mes');
  planState.selectedBillingPeriod = 'monthly';
}

function confirmPlanSelection() {
  if (!planState.selectedPlanId) return;

  axios.post('/subscription/create-preference', {
    plan_id: planState.selectedPlanId,
    billing_period: planState.selectedBillingPeriod,
  })
    .then((response) => {
      const data = response.data || {};
      if (data.success) {
        window.location.href = data.checkout_url;
      } else {
        alert(
          formatMessage(
            'plan.preference_error',
            { error: data.error || t('plan.unknown_error', 'Error desconocido') },
            'Error al crear la preferencia de pago: {error}'
          )
        );
      }
    })
    .catch((error) => {
      console.error('Error:', error);
      alert(formatMessage('plan.request_error', { error: error.message }, 'Error al procesar la solicitud: {error}'));
    })
    .finally(() => {
      closePlanModal();
    });
}

function updatePlanCardsByPeriod() {
  planState.planCards.forEach((card) => {
    const monthlyPrice = Number(card.dataset.monthlyPrice || 0);
    const yearlyPrice = Number(card.dataset.yearlyPrice || monthlyPrice * 12);
    const yearlyBase = Number(card.dataset.yearlyBase || yearlyPrice);
    const discount = Number(card.dataset.discount || 0);
    const freeMonths = Number(card.dataset.freeMonths || 0);
    const currency = card.dataset.currency;

    const isYearly = planState.currentBillingPeriod === 'yearly';
    const price = isYearly ? yearlyPrice : monthlyPrice;
    const priceCurrencyEl = card.querySelector('[data-price-currency]');
    const priceNumberEl = card.querySelector('[data-price-number]');
    const pricePeriodEl = card.querySelector('[data-price-period]');
    const offerTextEl = card.querySelector('[data-offer-text]');

    const isFree = Number(price) === 0;
    const currencySymbol = PLAN_CURRENCY_SYMBOLS[currency] || currency;

    if (priceCurrencyEl) {
      priceCurrencyEl.textContent = isFree ? '' : currencySymbol;
    }

    if (priceNumberEl) {
      priceNumberEl.textContent = isFree ? t('plan.free', 'Gratis') : formatPlanPrice(price, currency);
    }

    if (pricePeriodEl) {
      pricePeriodEl.textContent = isFree ? '' : isYearly
        ? `/${t('plan.period.year', 'año')}`
        : `/${t('plan.period.month', 'mes')}`;
    }

    if (offerTextEl) {
      if (isYearly && (discount > 0 || freeMonths > 0)) {
        const details = [];
        if (discount > 0) {
          details.push(formatMessage('plan.discount', { percent: discount }, 'Descuento {percent}%'));
        }
        if (freeMonths > 0) {
          details.push(formatMessage('plan.free_months', { months: freeMonths }, '{months} mes(es) gratis'));
        }
        if (yearlyBase && yearlyBase > price) {
          details.push(formatMessage('plan.previous_price', { price: formatPlanPrice(yearlyBase, currency) }, 'Antes {price}'));
        }
        offerTextEl.textContent = details.join(' · ');
        offerTextEl.classList.remove('hidden');
      } else {
        offerTextEl.classList.add('hidden');
      }
    }

    const button = card.querySelector('[data-select-plan]');
    if (button) {
      button.dataset.billingPeriod = planState.currentBillingPeriod;
      button.dataset.planPrice = price;
    }
  });
}

function initPlanSelection() {
  cachePlanElements();

  if (!planState.modal || planState.planCards.length === 0) return;

  planState.modal.addEventListener('click', (event) => {
    if (event.target === planState.modal) {
      closePlanModal();
    }
  });

  planState.billingToggleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const selectedPeriod = button.dataset.billingPeriod;
      planState.currentBillingPeriod = selectedPeriod;
      planState.selectedBillingPeriod = selectedPeriod;

      planState.billingToggleButtons.forEach((btn) => btn.classList.toggle('active', btn === button));
      updatePlanCardsByPeriod();
    });
  });

  document.querySelectorAll('[data-select-plan]').forEach((button) => {
    button.addEventListener('click', () => {
      const card = button.closest('.plan-card');
      const planId = button.dataset.planId || card?.dataset.planId;
      const planName = button.dataset.planName || card?.dataset.planName;
      const planCurrency = button.dataset.planCurrency || card?.dataset.currency;
      const billingPeriod = button.dataset.billingPeriod || planState.currentBillingPeriod;
      const planPrice = Number(button.dataset.planPrice || card?.dataset.monthlyPrice || 0);

      selectPlan(planId, planName, planPrice, planCurrency, billingPeriod);
    });
  });

  updatePlanCardsByPeriod();
}

/**
 * Recibo de compra
 */
function downloadReceipt(paymentId) {
  const url = `/profile/payment/${paymentId}/receipt`;
  const link = document.createElement('a');
  link.href = url;
  link.download = `recibo-pago-${paymentId}.pdf`;
  link.target = '_blank';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

/**
 * Modal de éxito de pago
 */
function closePaymentSuccessModal() {
  const modal = document.getElementById('payment-success-modal');
  if (modal) {
    modal.classList.remove('active');
    setTimeout(() => {
      modal.remove();
    }, 300);
  }
}

/**
 * Confirmación de borrado de cuenta
 */
function confirmDeleteAccount(event) {
  const form = event.target;
  const expected = form.dataset.expectedUsername;
  const input = form.querySelector('input[name="confirmation"]')?.value?.trim().toUpperCase();
  if (!expected || input !== expected) {
    alert(t('account.delete_mismatch', 'El texto no coincide. Debes escribir exactamente tu nombre de usuario.'));
    event.preventDefault();
    return false;
  }
  return true;
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
window.showSection         = showSection;
window.closeNotification   = closeNotification;
window.closePlanModal      = closePlanModal;
window.confirmPlanSelection = confirmPlanSelection;
window.downloadReceipt     = downloadReceipt;
window.closePaymentSuccessModal = closePaymentSuccessModal;
window.confirmDeleteAccount = confirmDeleteAccount;
