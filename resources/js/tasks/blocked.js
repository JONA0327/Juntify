document.addEventListener('DOMContentLoaded', () => {
    const closeModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    window.closeModal = closeModal;

    if (typeof window.showTasksLockedModal === 'function') {
        window.showTasksLockedModal();
    }
});
