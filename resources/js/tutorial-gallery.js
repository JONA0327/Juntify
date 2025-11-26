const tutorialVisualData = {
    reuniones: {
        label: 'Reuniones',
        description: 'Visualiza cómo navegar, filtrar y abrir reuniones en tu panel principal.',
        steps: [
            { title: 'Resumen de reuniones', caption: 'Encuentra un vistazo rápido a tus eventos más recientes.', media: '/tutorial-media/reuniones/step-1.svg' },
            { title: 'Filtros y estados', caption: 'Aplica filtros para enfocarte en las reuniones que necesitas revisar.', media: '/tutorial-media/reuniones/step-2.svg' },
            { title: 'Detalle colaborativo', caption: 'Abre un evento para ver acuerdos, participantes y archivos.', media: '/tutorial-media/reuniones/step-3.svg' },
        ],
    },
    'nueva-reunion': {
        label: 'Nueva Reunión',
        description: 'Aprende a crear una reunión y definir la información clave.',
        steps: [
            { title: 'Configura el contexto', caption: 'Selecciona el equipo, la fecha y el objetivo del encuentro.', media: '/tutorial-media/nueva-reunion/step-1.svg' },
            { title: 'Invita asistentes', caption: 'Agrega participantes y define su rol dentro de la reunión.', media: '/tutorial-media/nueva-reunion/step-2.svg' },
            { title: 'Confirma el resumen', caption: 'Revisa los datos y confirma para enviar invitaciones.', media: '/tutorial-media/nueva-reunion/step-3.svg' },
        ],
    },
    tareas: {
        label: 'Tareas',
        description: 'Gestiona pendientes derivados de tus reuniones de forma visual.',
        steps: [
            { title: 'Vista general', caption: 'Explora el tablero de tareas y cambia entre vistas.', media: '/tutorial-media/tareas/step-1.svg' },
            { title: 'Asignaciones', caption: 'Asigna responsables y fechas límite con un clic.', media: '/tutorial-media/tareas/step-2.svg' },
            { title: 'Seguimiento', caption: 'Marca avances y comparte comentarios con el equipo.', media: '/tutorial-media/tareas/step-3.svg' },
        ],
    },
    contactos: {
        label: 'Contactos',
        description: 'Centraliza participantes frecuentes y sus datos clave.',
        steps: [
            { title: 'Directorio', caption: 'Busca y filtra contactos por empresa o rol.', media: '/tutorial-media/contactos/step-1.svg' },
            { title: 'Detalles', caption: 'Abre una ficha para revisar historial y notas.', media: '/tutorial-media/contactos/step-2.svg' },
            { title: 'Acciones rápidas', caption: 'Lanza invitaciones o comparte enlaces de reunión.', media: '/tutorial-media/contactos/step-3.svg' },
        ],
    },
    organizacion: {
        label: 'Organización',
        description: 'Configura tu organización, roles y espacios compartidos.',
        steps: [
            { title: 'Estructura', caption: 'Visualiza los equipos y define responsables.', media: '/tutorial-media/organizacion/step-1.svg' },
            { title: 'Automatizaciones', caption: 'Activa integraciones y permisos centralizados.', media: '/tutorial-media/organizacion/step-2.svg' },
            { title: 'Drive y carpetas', caption: 'Controla el almacenamiento conectado para cada equipo.', media: '/tutorial-media/organizacion/step-3.svg' },
        ],
    },
    'asistente-ia': {
        label: 'Asistente IA',
        description: 'Descubre cómo la IA resume, analiza y genera acciones.',
        steps: [
            { title: 'Chat guiado', caption: 'Realiza preguntas sobre notas y acuerdos en lenguaje natural.', media: '/tutorial-media/asistente-ia/step-1.svg' },
            { title: 'Ideas clave', caption: 'Obtén highlights automáticos y próximos pasos sugeridos.', media: '/tutorial-media/asistente-ia/step-2.svg' },
            { title: 'Exportables', caption: 'Descarga resúmenes o envíalos al equipo desde el mismo hilo.', media: '/tutorial-media/asistente-ia/step-3.svg' },
        ],
    },
    perfil: {
        label: 'Perfil',
        description: 'Ajusta tus datos personales, plan y preferencias.',
        steps: [
            { title: 'Información general', caption: 'Actualiza foto, nombre y datos de contacto.', media: '/tutorial-media/perfil/step-1.svg' },
            { title: 'Plan y facturación', caption: 'Consulta tu plan actual y descarga comprobantes.', media: '/tutorial-media/perfil/step-2.svg' },
            { title: 'Preferencias', caption: 'Activa notificaciones y sincronizaciones conectadas.', media: '/tutorial-media/perfil/step-3.svg' },
        ],
    },
};

function createNavButton(key, data, isActive) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `tutorial-nav-btn ${isActive ? 'is-active' : ''}`;
    button.dataset.key = key;
    button.innerHTML = `
        <span class="tutorial-nav-title">${data.label}</span>
        <span class="tutorial-nav-desc">${data.description}</span>
    `;
    return button;
}

function createDot(isActive, index) {
    const dot = document.createElement('button');
    dot.type = 'button';
    dot.className = `tutorial-dot ${isActive ? 'is-active' : ''}`;
    dot.dataset.index = index;
    dot.setAttribute('aria-label', `Ir al paso ${index + 1}`);
    return dot;
}

function renderSlide(container, step) {
    container.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'tutorial-slide';

    const image = document.createElement('img');
    image.src = step.media;
    image.alt = `${step.title} - ${step.caption}`;
    image.loading = 'lazy';

    const overlay = document.createElement('div');
    overlay.className = 'tutorial-slide-overlay';

    const title = document.createElement('h4');
    title.textContent = step.title;

    const caption = document.createElement('p');
    caption.textContent = step.caption;

    overlay.appendChild(title);
    overlay.appendChild(caption);

    wrapper.appendChild(image);
    wrapper.appendChild(overlay);

    container.appendChild(wrapper);
}

function updateControls(state, totalSteps) {
    const { prevBtn, nextBtn, indicator } = state;

    prevBtn.disabled = state.currentIndex === 0;
    nextBtn.disabled = state.currentIndex === totalSteps - 1;
    indicator.textContent = `Paso ${state.currentIndex + 1} de ${totalSteps}`;
}

function setupTutorialGallery() {
    const gallery = document.querySelector('[data-tutorial-gallery]');
    if (!gallery) return;

    const nav = gallery.querySelector('[data-gallery-nav]');
    const slideContainer = gallery.querySelector('[data-gallery-slide]');
    const dotsContainer = gallery.querySelector('[data-gallery-dots]');
    const prevBtn = gallery.querySelector('[data-gallery-prev]');
    const nextBtn = gallery.querySelector('[data-gallery-next]');
    const indicator = gallery.querySelector('[data-gallery-indicator]');

    let currentKey = Object.keys(tutorialVisualData)[0];
    let currentIndex = 0;

    const state = { prevBtn, nextBtn, indicator, currentIndex };

    function loadCategory(key) {
        currentKey = key;
        currentIndex = 0;
        state.currentIndex = 0;

        Array.from(nav.children).forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.key === key);
        });

        refreshSlides();
    }

    function refreshSlides() {
        const category = tutorialVisualData[currentKey];
        const step = category.steps[currentIndex];
        renderSlide(slideContainer, step);

        dotsContainer.innerHTML = '';
        category.steps.forEach((_, index) => {
            const dot = createDot(index === currentIndex, index);
            dot.addEventListener('click', () => {
                currentIndex = index;
                state.currentIndex = index;
                refreshSlides();
            });
            dotsContainer.appendChild(dot);
        });

        updateControls({ ...state, currentIndex }, category.steps.length);
    }

    // Build nav buttons
    Object.entries(tutorialVisualData).forEach(([key, data], idx) => {
        const button = createNavButton(key, data, idx === 0);
        button.addEventListener('click', () => loadCategory(key));
        nav.appendChild(button);
    });

    prevBtn.addEventListener('click', () => {
        if (currentIndex === 0) return;
        currentIndex -= 1;
        state.currentIndex = currentIndex;
        refreshSlides();
    });

    nextBtn.addEventListener('click', () => {
        const total = tutorialVisualData[currentKey].steps.length;
        if (currentIndex >= total - 1) return;
        currentIndex += 1;
        state.currentIndex = currentIndex;
        refreshSlides();
    });

    // Initial render
    refreshSlides();
}

document.addEventListener('DOMContentLoaded', setupTutorialGallery);
