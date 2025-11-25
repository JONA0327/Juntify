import Shepherd from 'shepherd.js';

class JuntifyTutorial {
    constructor() {
        this.tour = null;
        this.currentPage = this.getCurrentPage();
        this.init();
    }

    getCurrentPage() {
        const path = window.location.pathname;
        if (path === '/') return 'dashboard';
        if (path.includes('/reuniones')) return 'meetings';
        if (path.includes('/containers')) return 'containers';
        if (path.includes('/contacts')) return 'contacts';
        if (path.includes('/ai-assistant')) return 'ai-assistant';
        if (path.includes('/tasks')) return 'tasks';
        if (path.includes('/profile')) return 'profile';
        return 'general';
    }

    // Verificar estado del tutorial desde el servidor
    async checkTutorialStatus() {
        try {
            const response = await fetch('/api/tutorial/status', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                const data = await response.json();
                const tutorialData = data.data;

                if (tutorialData.completed) {
                    // Marcar como completado globalmente
                    window.juntifyTutorialCompleted = true;
                    localStorage.setItem('juntify_tutorial_seen', 'true');
                    console.log('📋 Tutorial ya completado según el servidor');
                } else {
                    window.juntifyTutorialCompleted = false;
                }
            }
        } catch (error) {
            console.log('⚠️ No se pudo verificar el estado del tutorial:', error);
            // Usar localStorage como fallback
            window.juntifyTutorialCompleted = localStorage.getItem('juntify_tutorial_seen') === 'true';
        }
    }

    async init() {
        // Verificar estado del tutorial desde el servidor
        await this.checkTutorialStatus();

        this.createTour();
        this.addTutorialButton();
        this.checkFirstVisit();
    }

    createTour() {
        this.tour = new Shepherd.Tour({
            useModalOverlay: true,
            modalContainer: document.body,
            defaultStepOptions: {
                classes: 'shepherd-theme-custom shepherd-dialog-style',
                scrollTo: { behavior: 'smooth', block: 'center' },
                cancelIcon: {
                    enabled: true,
                },
                modalOverlayOpeningPadding: 10,
                modalOverlayOpeningRadius: 16,
                highlightClass: 'shepherd-highlight-element',
                popperOptions: {
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 20]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: 'viewport',
                                padding: 16
                            }
                        }
                    ]
                }
            }
        });

        // Agregar eventos para mejorar el highlighting
        this.tour.on('show', (event) => {
            this.enhanceHighlight(event.step);
        });

        this.tour.on('hide', () => {
            this.removeHighlight();
        });

        // Definir los pasos según la página actual
        this.addStepsForPage();
    }

    addStepsForPage() {
        const steps = this.getStepsForPage(this.currentPage);
        steps.forEach(step => this.tour.addStep(step));
    }

    getStepsForPage(page) {
        const commonSteps = [
            {
                title: '¡Bienvenido a Juntify! 🎉',
                text: 'Te guiaremos através de las principales funciones de la plataforma. Puedes salir del tutorial en cualquier momento.',
                buttons: [
                    {
                        text: 'Comenzar Tutorial',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    },
                    {
                        text: 'Saltar Tutorial',
                        action: this.tour.complete,
                        classes: 'btn btn-secondary'
                    }
                ],
                id: 'welcome'
            }
        ];

        switch (page) {
            case 'dashboard':
                return [...commonSteps, ...this.getDashboardSteps()];
            case 'meetings':
                return [...commonSteps, ...this.getMeetingSteps()];
            case 'containers':
                return [...commonSteps, ...this.getContainerSteps()];
            case 'contacts':
                return [...commonSteps, ...this.getContactSteps()];
            case 'ai-assistant':
                return [...commonSteps, ...this.getAiAssistantSteps()];
            case 'tasks':
                return [...commonSteps, ...this.getTaskSteps()];
            case 'profile':
                return [...commonSteps, ...this.getProfileSteps()];
            default:
                return [...commonSteps, ...this.getGeneralSteps()];
        }
    }

    getDashboardSteps() {
        return [
            {
                title: 'Panel de Navegación',
                text: 'Desde aquí puedes acceder a todas las secciones principales: reuniones, contenedores, contactos y más.',
                attachTo: {
                    element: '.sidebar, .navbar, [data-tutorial="navigation"]',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Resumen de Actividad',
                text: 'Aquí puedes ver un resumen de tus reuniones recientes, tareas pendientes y estadísticas generales.',
                attachTo: {
                    element: '[data-tutorial="dashboard-summary"], .dashboard-stats, main',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getMeetingSteps() {
        return [
            {
                title: 'Gestión de Reuniones',
                text: 'Esta es tu centro de control para todas las reuniones. Puedes crear, editar y revisar reuniones.',
                attachTo: {
                    element: '[data-tutorial="meetings-header"], .meetings-container, main',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Nueva Reunión',
                text: 'Haz clic aquí para crear una nueva reunión. Podrás configurar participantes, agenda y más.',
                attachTo: {
                    element: '[data-tutorial="new-meeting"], .btn-new-meeting, .create-meeting-btn',
                    on: 'bottom'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Lista de Reuniones',
                text: 'Aquí se muestran todas tus reuniones. Puedes filtrar, buscar y acceder a los detalles de cada una.',
                attachTo: {
                    element: '[data-tutorial="meetings-list"], .meetings-table, .meeting-item',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getContainerSteps() {
        return [
            {
                title: 'Contenedores de Organización',
                text: 'Los contenedores te ayudan a organizar tus reuniones por proyectos, equipos o cualquier clasificación que necesites.',
                attachTo: {
                    element: '[data-tutorial="containers"], .container-list, main',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getContactSteps() {
        return [
            {
                title: 'Gestión de Contactos',
                text: 'Administra todos tus contactos y participantes de reuniones desde aquí.',
                attachTo: {
                    element: '[data-tutorial="contacts"], .contacts-list, main',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Finalizar',
                        action: this.tour.complete,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getAiAssistantSteps() {
        return [
            {
                title: 'Asistente IA',
                text: 'Tu asistente inteligente puede ayudarte a analizar reuniones, buscar información y generar resúmenes.',
                attachTo: {
                    element: '[data-tutorial="ai-chat"], .ai-chat-container, .chat-input',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Consultas Inteligentes',
                text: 'Puedes hacer preguntas específicas sobre reuniones, participantes o contenido. Por ejemplo: "¿Qué dijo Juan en la última reunión?"',
                attachTo: {
                    element: '[data-tutorial="ai-input"], .message-input, textarea',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Finalizar',
                        action: this.tour.complete,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getTaskSteps() {
        return [
            {
                title: 'Gestión de Tareas',
                text: 'Organiza y da seguimiento a todas las tareas derivadas de tus reuniones.',
                attachTo: {
                    element: '[data-tutorial="tasks"], .tasks-container, main',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Finalizar',
                        action: this.tour.complete,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getProfileSteps() {
        return [
            {
                title: 'Panel de Usuario',
                text: 'Bienvenido a tu panel de usuario. Aquí tienes acceso a todas las funciones principales de Juntify.',
                attachTo: {
                    element: '.sidebar, [data-tutorial="sidebar"]',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Sección Información',
                text: 'Aquí puedes ver y editar tu información personal: nombre de usuario, correo electrónico, organización y ver tu plan actual.',
                attachTo: {
                    element: '[data-tutorial="info-link"], .nav-link[data-section="info"], .sidebar a[data-section="info"]',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Sección Conectar',
                text: 'Conecta tu cuenta con Google Drive y Google Calendar para sincronizar tus archivos y eventos automáticamente.',
                attachTo: {
                    element: '[data-tutorial="connect-link"], .nav-link[data-section="connect"], .sidebar a[data-section="connect"]',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Configuración de Carpetas',
                text: 'Gestiona la organización automática de tus archivos. Juntify crea carpetas específicas para audios, transcripciones y documentos.',
                attachTo: {
                    element: '.grid > div, .card, .bg-slate-800',
                    on: 'top'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Siguiente',
                        action: this.tour.next,
                        classes: 'btn btn-primary'
                    }
                ]
            },
            {
                title: 'Botones de Acción',
                text: 'Utiliza estos botones para conectar servicios, establecer carpetas o realizar otras acciones importantes en tu cuenta.',
                attachTo: {
                    element: 'button, .btn, input[type="submit"]',
                    on: 'bottom'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Finalizar',
                        action: this.tour.complete,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    getGeneralSteps() {
        return [
            {
                title: 'Navegación General',
                text: 'Usa la barra de navegación para moverte entre las diferentes secciones de Juntify.',
                attachTo: {
                    element: '.navbar, .sidebar',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: this.tour.back,
                        classes: 'btn btn-secondary'
                    },
                    {
                        text: 'Finalizar',
                        action: this.tour.complete,
                        classes: 'btn btn-primary'
                    }
                ]
            }
        ];
    }

    addTutorialButton() {
        // Crear botón flotante para iniciar tutorial
        const tutorialBtn = document.createElement('button');
        tutorialBtn.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        `;
        tutorialBtn.className = 'fixed bottom-6 right-6 bg-sky-600 hover:bg-sky-700 text-white p-3 rounded-full shadow-lg z-50 transition-colors duration-200';
        tutorialBtn.setAttribute('data-tutorial', 'help-button');
        tutorialBtn.setAttribute('title', 'Iniciar Tutorial');
        tutorialBtn.addEventListener('click', () => {
            if (window.juntifyTutorialCompleted) {
                this.showCompletionMessage();
            } else {
                this.startTour();
            }
        });

        document.body.appendChild(tutorialBtn);

        // Función para ocultar enlaces de tutorial en el sidebar
        this.hideTutorialSidebarLinks();
    }

    checkFirstVisit() {
        const hasSeenTutorial = localStorage.getItem('juntify_tutorial_seen');
        const isCompleted = window.juntifyTutorialCompleted;

        if (!hasSeenTutorial && !isCompleted) {
            // Mostrar tutorial automáticamente en la primera visita
            setTimeout(() => this.startTour(), 1000);
        }
    }

    startTour() {
        const hasSeenTutorial = localStorage.getItem('juntify_tutorial_seen');
        const isCompleted = window.juntifyTutorialCompleted;

        if (hasSeenTutorial || isCompleted) {
            console.log('🚫 Tutorial ya completado, no se inicia');
            return;
        }

        if (this.tour) {
            this.tour.start();
        }
    }

    onTourComplete() {
        // Guardar en localStorage
        localStorage.setItem('juntify_tutorial_seen', 'true');
        localStorage.setItem('juntify_tutorial_completion_date', new Date().toISOString());

        // Limpiar highlighting inmediatamente
        this.removeHighlight();

        // Completar y limpiar el tour
        if (this.tour && this.tour.isActive()) {
            this.tour.hide();
            this.tour.cancel();
        }

        // Actualizar el servidor
        this.updateTutorialProgress(100, true);

        // Ocultar botón de ayuda si existe
        const helpButton = document.querySelector('.tutorial-help-button');
        if (helpButton) {
            helpButton.style.display = 'none';
        }

        console.log('✅ Tutorial completado y actualizado en el servidor');
    }

    onTourCancel() {
        // Guardar en localStorage
        localStorage.setItem('juntify_tutorial_seen', 'true');

        // Limpiar highlighting inmediatamente
        this.removeHighlight();

        // Asegurar que el tour se cancela completamente
        if (this.tour && this.tour.isActive()) {
            this.tour.hide();
        }

        // Actualizar el servidor como cancelado
        this.updateTutorialProgress(0, false);

        console.log('❌ Tutorial cancelado y actualizado en el servidor');
    }

    // Función para actualizar progreso en el servidor
    async updateTutorialProgress(progress, completed) {
        try {
            const response = await fetch('/api/tutorial/progress', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    progress: progress,
                    completed: completed,
                    completion_date: completed ? new Date().toISOString() : null
                })
            });

            if (response.ok) {
                const data = await response.json();
                console.log('📊 Progreso actualizado:', data);

                // Si se completó, asegurar que no se reactive
                if (completed) {
                    // Marcar globalmente como completado
                    window.juntifyTutorialCompleted = true;

                    // Opcional: mostrar mensaje de éxito
                    this.showCompletionMessage();
                }
            } else {
                console.error('❌ Error al actualizar progreso:', response.status);
            }
        } catch (error) {
            console.error('❌ Error de red al actualizar progreso:', error);
        }
    }

    // Mostrar mensaje de tutorial completado
    showCompletionMessage() {
        // Crear notificación temporal
        const notification = document.createElement('div');
        notification.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #10B981, #059669);
                color: white;
                padding: 16px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
                z-index: 99999;
                font-weight: 500;
                animation: slideInRight 0.4s ease-out;
            ">
                ✅ ¡Tutorial completado con éxito!
            </div>
        `;

        document.body.appendChild(notification);

        // Remover después de 3 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }

    // Función para reiniciar el tutorial
    async resetTutorial() {
        try {
            // Llamar al servidor para resetear el tutorial
            const response = await fetch('/api/tutorial/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                console.log('✅ Tutorial reseteado en el servidor');
            }
        } catch (error) {
            console.log('⚠️ Error al resetear en el servidor:', error);
        }

        // Limpiar el localStorage
        localStorage.removeItem('juntify_tutorial_seen');
        localStorage.removeItem('juntify_tutorial_completion_date');

        // Limpiar flag global
        window.juntifyTutorialCompleted = false;

        // Si hay un tour activo, terminarlo primero
        if (this.tour && this.tour.isActive()) {
            this.tour.complete();
        }

        // Mostrar notificación de reset
        const notification = document.createElement('div');
        notification.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #F59E0B, #D97706);
                color: white;
                padding: 16px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
                z-index: 99999;
                font-weight: 500;
            ">
                🔄 Tutorial reiniciado. Recarga la página para verlo de nuevo.
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 4000);

        return true;
    }

    // Función para forzar el inicio del tutorial
    forceStart() {
        if (this.tour) {
            this.tour.start();
            return true;
        }
        return false;
    }

    // Función para ocultar enlaces de tutorial en el sidebar
    hideTutorialSidebarLinks() {
        // Ocultar enlaces que contengan 'tutorial' en su href o texto
        const sidebarLinks = document.querySelectorAll('.sidebar a, .profile-sidebar a');
        sidebarLinks.forEach(link => {
            const href = link.getAttribute('href') || '';
            const text = link.textContent.toLowerCase();

            if (href.includes('tutorial') || text.includes('tutorial')) {
                link.style.display = 'none';

                // También ocultar el elemento padre si es un li
                const parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.style.display = 'none';
                }
            }
        });
    }

    // Función para navegar a la vista de conectar
    navigateToConnect() {
        const connectLink = document.querySelector('a[href*="connect"]');
        if (connectLink) {
            connectLink.click();
        } else {
            // Fallback: navegar directamente
            window.location.href = '/profile/connect';
        }
    }

    // Función para mejorar el highlighting del elemento activo
    enhanceHighlight(step) {
        console.log('🎯 Iniciando highlight para:', step);

        // Remover highlighting previo
        this.removeHighlight();

        // Obtener el elemento target del step
        let target = null;

        // Si el step tiene attachTo, usar ese elemento
        if (step.options && step.options.attachTo && step.options.attachTo.element) {
            const elementSelector = step.options.attachTo.element;
            console.log('🔍 Buscando elemento con selector:', elementSelector);

            if (typeof elementSelector === 'string') {
                // Intentar múltiples selectores separados por coma
                const selectors = elementSelector.split(',').map(s => s.trim());
                for (const selector of selectors) {
                    try {
                        // Esperar un poco para que el DOM se actualice
                        target = document.querySelector(selector);
                        if (target && this.isElementVisible(target)) {
                            console.log('✅ Elemento encontrado con selector:', selector);
                            break;
                        } else {
                            console.log('❌ No se encontró elemento visible con selector:', selector);
                            target = null;
                        }
                    } catch (e) {
                        console.log('❌ Error con selector:', selector, e);
                        target = null;
                    }
                }

                // Si no se encontró con los selectores específicos, intentar selectores más generales
                if (!target) {
                    console.log('🔄 Intentando selectores más generales...');
                    const fallbackSelectors = [
                        '.sidebar a',
                        '.sidebar li',
                        '.sidebar',
                        'main',
                        '.grid > div',
                        '.card',
                        'button',
                        '.btn'
                    ];

                    for (const fallback of fallbackSelectors) {
                        const elements = document.querySelectorAll(fallback);
                        if (elements.length > 0) {
                            target = elements[0];
                            console.log('✅ Elemento fallback encontrado:', fallback);
                            break;
                        }
                    }
                }
            } else {
                target = elementSelector;
            }
        }

        // Crear overlay de fondo siempre
        this.createBackgroundOverlay();

        if (target && this.isElementVisible(target)) {
            console.log('🎯 Elemento target encontrado:', target);

            // Hacer scroll al elemento
            setTimeout(() => {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'center'
                });
            }, 100);

            // Aplicar efectos de resaltado
            setTimeout(() => {
                this.applyHighlightEffects(target);
                this.createSpotlightEffect(target);
            }, 300);

        } else {
            console.log('⚠️ No se encontró elemento target válido, usando overlay general');
            // Solo mostrar overlay general si no hay elemento
            this.createGeneralOverlay();
        }
    }

    // Función para verificar si un elemento es visible
    isElementVisible(element) {
        if (!element) return false;

        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);

        return (
            element.offsetParent !== null &&
            style.display !== 'none' &&
            style.visibility !== 'hidden' &&
            style.opacity !== '0' &&
            rect.width > 0 &&
            rect.height > 0
        );
    }    // Sin overlay de fondo
    createBackgroundOverlay() {
        // No crear overlay - mantener interfaz completamente clara
        console.log('🎭 Sin overlay de fondo - interfaz clara');
    }

    // Aplicar efectos de resaltado al elemento
    applyHighlightEffects(target) {
        // Guardar estilos originales
        target.setAttribute('data-original-style', target.style.cssText || '');

        // Agregar clases de highlight sutiles
        target.classList.add('shepherd-highlight-active', 'tutorial-highlight');

        // Solo aplicar estilos que NO cambien colores originales
        const highlightStyles = `
            position: relative !important;
            z-index: 10002 !important;
            outline: none !important;
        `;

        // Aplicar solo estilos seguros que no cambien colores
        const currentStyle = target.getAttribute('data-original-style') || '';
        target.style.cssText = currentStyle + highlightStyles;

        console.log('✨ Efectos de resaltado sutiles aplicados respetando colores originales');
    }

    // Crear efecto spotlight sutil
    createSpotlightEffect(target) {
        // Sin spotlight - solo highlighting directo del elemento
        console.log('🔦 Sin spotlight - solo highlighting directo');
    }    // Crear overlay general cuando no hay elemento específico
    createGeneralOverlay() {
        const generalOverlay = document.createElement('div');
        generalOverlay.className = 'tutorial-general-overlay';

        document.body.appendChild(generalOverlay);

        console.log('🎭 Overlay general mejorado creado');
    }    // Función para remover el highlighting
    removeHighlight() {
        console.log('🧹 Limpiando highlighting anterior');

        // Remover clases de highlight y restaurar estilos originales
        document.querySelectorAll('.shepherd-highlight-active, .tutorial-highlight').forEach(el => {
            el.classList.remove('shepherd-highlight-active', 'tutorial-highlight');

            // Remover elementos glow hijos
            const glowElements = el.querySelectorAll('.tutorial-highlight-glow');
            glowElements.forEach(glow => glow.remove());

            // Restaurar estilos originales
            const originalStyle = el.getAttribute('data-original-style');
            if (originalStyle !== null) {
                el.style.cssText = originalStyle;
                el.removeAttribute('data-original-style');
            } else {
                // Limpiar estilos específicos si no hay originales guardados
                el.style.transform = '';
                el.style.boxShadow = '';
                el.style.filter = '';
                el.style.borderRadius = '';
                el.style.zIndex = '';
                el.style.position = '';
                el.style.outline = '';
                el.style.background = '';
                el.style.border = '';
            }
        });

        // Solo remover elementos highlight sin overlays
        const overlays = document.querySelectorAll('.shepherd-highlight-overlay, .shepherd-main-overlay, .shepherd-spotlight-overlay, .shepherd-general-overlay');

        overlays.forEach(overlay => {
            if (overlay && overlay.parentNode) {
                overlay.remove();
            }
        });

        console.log('🧹 Highlighting limpiado - sin overlays');
    }
}

// Inicializar el tutorial cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos en una página que soporta tutorial
    if (document.querySelector('main') || document.querySelector('.navbar')) {
        const tutorial = new JuntifyTutorial();

        // Eventos del tour
        if (tutorial.tour) {
            tutorial.tour.on('complete', tutorial.onTourComplete);
            tutorial.tour.on('cancel', tutorial.onTourCancel);
        }

        // Hacer el tutorial accesible globalmente
        window.juntifyTutorial = tutorial;
    }
});

// Funciones globales para usar el tutorial
window.resetJuntifyTutorial = function() {
    if (window.juntifyTutorial) {
        return window.juntifyTutorial.resetTutorial();
    }
    console.warn('Tutorial no está inicializado');
    return false;
};

window.startJuntifyTutorial = function() {
    if (window.juntifyTutorial) {
        window.juntifyTutorial.startTour();
        return true;
    }
    console.warn('Tutorial no está inicializado');
    return false;
};

export { JuntifyTutorial };
