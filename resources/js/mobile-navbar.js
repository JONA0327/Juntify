// ===== MODERN MOBILE NAVBAR JAVASCRIPT =====

let mobileDropdownOpen = false;

/**
 * Toggle mobile dropdown menu
 */
function toggleMobileDropdown() {
    const dropdown = document.getElementById('mobileDropdown');
    const overlay = document.getElementById('mobileDropdownOverlay');

    if (!dropdown || !overlay) return;

    mobileDropdownOpen = !mobileDropdownOpen;

    if (mobileDropdownOpen) {
        showMobileDropdown();
    } else {
        hideMobileDropdown();
    }
}

/**
 * Show mobile dropdown
 */
function showMobileDropdown() {
    const dropdown = document.getElementById('mobileDropdown');
    const overlay = document.getElementById('mobileDropdownOverlay');

    if (!dropdown || !overlay) return;

    // Add show classes
    dropdown.classList.add('show');
    overlay.classList.add('show');

    // Prevent body scroll
    document.body.classList.add('dropdown-open');

    // Add escape key listener
    document.addEventListener('keydown', handleEscapeKey);
}

/**
 * Hide mobile dropdown
 */
function hideMobileDropdown() {
    const dropdown = document.getElementById('mobileDropdown');
    const overlay = document.getElementById('mobileDropdownOverlay');

    if (!dropdown || !overlay) return;

    // Remove show classes
    dropdown.classList.remove('show');
    overlay.classList.remove('show');

    // Restore body scroll
    document.body.classList.remove('dropdown-open');

    // Remove escape key listener
    document.removeEventListener('keydown', handleEscapeKey);

    mobileDropdownOpen = false;
}

/**
 * Close mobile dropdown
 */
function closeMobileDropdown() {
    hideMobileDropdown();
}

/**
 * Handle escape key to close dropdown
 */
function handleEscapeKey(event) {
    if (event.key === 'Escape' && mobileDropdownOpen) {
        hideMobileDropdown();
    }
}

/**
 * Handle click outside to close dropdown
 */
function handleOutsideClick(event) {
    const dropdown = document.getElementById('mobileDropdown');
    const dropdownToggle = document.querySelector('.dropdown-toggle');

    if (!dropdown || !dropdownToggle) return;

    // If clicked outside dropdown and toggle button, close dropdown
    if (mobileDropdownOpen &&
        !dropdown.contains(event.target) &&
        !dropdownToggle.contains(event.target)) {
        hideMobileDropdown();
    }
}

/**
 * Add smooth navigation transitions
 */
function addNavigationTransitions() {
    const navItems = document.querySelectorAll('.nav-item:not(.dropdown-toggle)');

    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Add loading state
            this.style.opacity = '0.6';
            this.style.transform = 'scale(0.95)';

            // Reset after navigation
            setTimeout(() => {
                this.style.opacity = '';
                this.style.transform = '';
            }, 150);
        });
    });
}

/**
 * Handle center button special animation
 */
function handleCenterButtonAnimation() {
    const centerButton = document.querySelector('.nav-center');

    if (!centerButton) return;

    centerButton.addEventListener('touchstart', function(e) {
        this.style.transform = 'scale(0.95)';
    });

    centerButton.addEventListener('touchend', function(e) {
        this.style.transform = '';
    });
}

/**
 * Initialize mobile navbar
 */
function initMobileNavbar() {
    // Add event listeners
    document.addEventListener('click', handleOutsideClick);

    // Add navigation transitions
    addNavigationTransitions();

    // Handle center button animation
    handleCenterButtonAnimation();

    // Handle orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            if (mobileDropdownOpen) {
                hideMobileDropdown();
            }
        }, 100);
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && mobileDropdownOpen) {
            hideMobileDropdown();
        }
    });

    console.log('Mobile navbar initialized successfully');
}

/**
 * Cleanup function
 */
function cleanupMobileNavbar() {
    document.removeEventListener('click', handleOutsideClick);
    document.removeEventListener('keydown', handleEscapeKey);
    document.body.classList.remove('dropdown-open');
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileNavbar);
} else {
    initMobileNavbar();
}

// Cleanup on page unload
window.addEventListener('beforeunload', cleanupMobileNavbar);

// Export functions for global access
window.toggleMobileDropdown = toggleMobileDropdown;
window.closeMobileDropdown = closeMobileDropdown;
