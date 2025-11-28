(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const sessionsSidebar = document.getElementById('sessions-sidebar');
        const mobileOverlay = document.getElementById('mobile-sidebar-overlay');
        const mobileCloseBtn = document.getElementById('mobile-close-btn');

        function openMobileSidebar() {
            if (sessionsSidebar) {
                sessionsSidebar.classList.add('sidebar-open');
                document.body.classList.add('sidebar-mobile-open');
            }
        }

        function closeMobileSidebar() {
            if (sessionsSidebar) {
                sessionsSidebar.classList.remove('sidebar-open');
                document.body.classList.remove('sidebar-mobile-open');
            }
        }

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', openMobileSidebar);
        }

        if (mobileCloseBtn) {
            mobileCloseBtn.addEventListener('click', closeMobileSidebar);
        }

        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', closeMobileSidebar);
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeMobileSidebar();
            }
        });

        function checkMobile() {
            const isMobile = window.innerWidth <= 768;
            if (mobileMenuToggle) {
                mobileMenuToggle.style.display = isMobile ? 'flex' : 'none';
            }
        }

        checkMobile();
        window.addEventListener('resize', checkMobile);
    });
})();
