import './bootstrap';
import './notifications';
import './organization';
import './utils/auth-interceptors';
import './mobile-navbar';
import '../css/mobile-navbar.css';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Funcionalidad del menú móvil
document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileHeader = document.getElementById('mobile-header');

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', () => {
            // Alterna la visibilidad del menú
            mobileMenu.classList.toggle('hidden');

            // Opcional: Evita que el fondo de la página se desplace cuando el menú está abierto
            if (!mobileMenu.classList.contains('hidden')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        });

        // Cerrar menú al hacer clic en un enlace
        const menuLinks = mobileMenu.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
        });

        // Cerrar menú al hacer clic fuera de él
        mobileMenu.addEventListener('click', (e) => {
            if (e.target === mobileMenu) {
                mobileMenu.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Opcional: Ocultar el header móvil al hacer scroll hacia abajo
    if (mobileHeader) {
        let lastScrollTop = 0;
        window.addEventListener("scroll", function() {
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                return; // No hacer nada si el menú está abierto
            }
            let st = window.pageYOffset || document.documentElement.scrollTop;
            if (st > lastScrollTop && st > 80) { // Si hace scroll hacia abajo y ha pasado el header
                mobileHeader.style.transform = 'translateY(-100%)';
                mobileHeader.style.transition = 'transform 0.3s ease-in-out';
            } else {
                mobileHeader.style.transform = 'translateY(0)';
                mobileHeader.style.transition = 'transform 0.3s ease-in-out';
            }
            lastScrollTop = st <= 0 ? 0 : st;
        }, false);
    }
});
