(function() {
    if (typeof window.closeUpgradeModal === 'undefined') {
        window.closeUpgradeModal = function() {
            const modal = document.getElementById('postpone-locked-modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };
    }

    if (typeof window.goToPlans === 'undefined') {
        window.goToPlans = function() {
            window.closeUpgradeModal();
            sessionStorage.setItem('navigateToPlans', 'true');
            window.location.href = '/profile';
        };
    }
})();
