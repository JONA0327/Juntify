/**
 * Mobile Navbar Module
 * Maneja la funcionalidad de la barra de navegación móvil y su menú dropdown
 */

class MobileNavbar {
    constructor() {
        this.isDropdownOpen = false;
        this.dropdown = null;
        this.overlay = null;
        this.dropdownToggle = null;
        
        this.init();
    }

    /**
     * Inicializa el módulo de navbar móvil
     */
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    /**
     * Configura todos los elementos y event listeners
     */
    setup() {
        this.dropdown = document.getElementById('mobileDropdown');
        this.overlay = document.getElementById('mobileDropdownOverlay');
        this.dropdownToggle = document.querySelector('.dropdown-toggle');

        if (!this.dropdown || !this.overlay) {
            console.warn('Mobile navbar: Elementos del dropdown no encontrados');
            return;
        }

        this.registerEventListeners();
        console.log('Mobile navbar inicializado');
    }

    /**
     * Registra todos los event listeners
     */
    registerEventListeners() {
        document.addEventListener('click', (e) => this.handleOutsideClick(e));
        document.addEventListener('keydown', (e) => this.handleEscapeKey(e));
        window.addEventListener('resize', () => this.handleResize());
        window.addEventListener('scroll', () => this.handleScroll(), { passive: true });
    }

    /**
     * Alterna la visibilidad del dropdown
     */
    toggle() {
        if (this.isDropdownOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    /**
     * Abre el dropdown
     */
    open() {
        if (!this.dropdown || !this.overlay) return;

        this.isDropdownOpen = true;
        this.dropdown.classList.add('show');
        this.overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Cierra el dropdown
     */
    close() {
        if (!this.dropdown || !this.overlay) return;

        this.isDropdownOpen = false;
        this.dropdown.classList.remove('show');
        this.overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    /**
     * Maneja clicks fuera del dropdown
     */
    handleOutsideClick(event) {
        if (!this.isDropdownOpen) return;

        const isClickInside = this.dropdown.contains(event.target) || 
                             this.dropdownToggle?.contains(event.target);

        if (!isClickInside) {
            this.close();
        }
    }

    /**
     * Maneja la tecla ESC
     */
    handleEscapeKey(event) {
        if (event.key === 'Escape' && this.isDropdownOpen) {
            this.close();
        }
    }

    /**
     * Maneja el resize de ventana
     */
    handleResize() {
        if (window.innerWidth > 768 && this.isDropdownOpen) {
            this.close();
        }
    }

    /**
     * Maneja el scroll de página
     */
    handleScroll() {
        if (this.isDropdownOpen) {
            this.close();
        }
    }
}

// Instancia global
let mobileNavbarInstance = null;

// Funciones globales que siempre están disponibles
window.toggleMobileDropdown = function() {
    console.log('toggleMobileDropdown llamado');
    if (!mobileNavbarInstance) {
        console.log('Creando instancia de MobileNavbar');
        mobileNavbarInstance = new MobileNavbar();
    }
    mobileNavbarInstance.toggle();
};

window.closeMobileDropdown = function() {
    console.log('closeMobileDropdown llamado');
    if (mobileNavbarInstance) {
        mobileNavbarInstance.close();
    }
};

// Inicializar la instancia cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM cargado, inicializando MobileNavbar');
        mobileNavbarInstance = new MobileNavbar();
    });
} else {
    console.log('DOM ya está listo, inicializando MobileNavbar');
    mobileNavbarInstance = new MobileNavbar();
}

// Exportar para uso como módulo si es necesario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MobileNavbar;
}
