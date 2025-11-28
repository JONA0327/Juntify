// Variables globales
window.mobileDropdownOpen = false;

function getDropdownElements() {
    return {
        dropdown: document.getElementById('dropdownMenu'),
        overlay: document.getElementById('dropdownOverlay'),
    };
}

// Función para toggle del dropdown
window.toggleMore = function toggleMore() {
    const { dropdown, overlay } = getDropdownElements();

    if (!dropdown || !overlay) return;

    if (window.mobileDropdownOpen) {
        dropdown.classList.remove('show');
        overlay.classList.remove('show');
        window.mobileDropdownOpen = false;
    } else {
        dropdown.classList.add('show');
        overlay.classList.add('show');
        window.mobileDropdownOpen = true;
    }
};

// Función para cerrar dropdown
window.closeDropdown = function closeDropdown() {
    const { dropdown, overlay } = getDropdownElements();

    if (dropdown && overlay) {
        dropdown.classList.remove('show');
        overlay.classList.remove('show');
        window.mobileDropdownOpen = false;
    }
};

function handleResize() {
    const navbar = document.getElementById('mobileNavbar');
    if (!navbar) return;

    if (window.innerWidth <= 768) {
        navbar.style.display = 'grid';
        document.body.style.paddingBottom = '85px';
    } else {
        navbar.style.display = 'none';
        document.body.style.paddingBottom = '';
    }
}

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    handleResize();
    window.addEventListener('resize', handleResize);

    const { overlay } = getDropdownElements();
    if (overlay) {
        overlay.addEventListener('click', window.closeDropdown);
    }
});
